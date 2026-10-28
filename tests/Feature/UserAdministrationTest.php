<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class UserAdministrationTest extends AccessTestCase
{
    protected function validUser(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'roles' => [$this->role('activity_officer')->id],
            'scope' => ['district' => [$this->peshawar->id]],
            'setup_method' => 'password',
            'password' => 'Temp#Pass123',
            'password_confirmation' => 'Temp#Pass123',
        ], $overrides);
    }

    public function test_super_admin_creates_user_with_role_and_scope_and_it_is_audited()
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('admin.users.store'), $this->validUser())->assertRedirect();

        $user = User::where('email', 'new.person@example.com')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole('activity_officer'));
        $this->assertTrue($user->scopeCoverage()->covers('district', $this->peshawar->id));
        $this->assertFalse($user->scopeCoverage()->covers('district', $this->swat->id));
        $this->assertSame($admin->id, $user->created_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'entity_id' => $user->id, 'actor_id' => $admin->id]);
    }

    public function test_setup_link_onboarding_keeps_account_pending_and_sends_link()
    {
        Notification::fake();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('admin.users.store'), $this->validUser([
            'setup_method' => 'link', 'password' => null, 'password_confirmation' => null,
        ]))->assertRedirect();

        $user = User::where('email', 'new.person@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        Notification::assertSentTo($user, \App\Notifications\PasswordLinkNotification::class);
    }

    public function test_duplicate_login_identifier_is_rejected()
    {
        $admin = $this->superAdmin();
        $this->makeUser('report_viewer', ['global' => true], ['email' => 'taken@example.com']);

        $this->actingAs($admin)->post(route('admin.users.store'), $this->validUser(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');
        $this->actingAs($admin)->post(route('admin.users.store'), $this->validUser(['email' => 'TAKEN@example.com']))
            ->assertSessionHasErrors('email');
    }

    public function test_scoped_admin_cannot_assign_roles_beyond_their_authority()
    {
        $scopedAdmin = $this->makeUser('directorate_admin', ['district' => [$this->peshawar->id]]);

        $this->actingAs($scopedAdmin)->post(route('admin.users.store'), $this->validUser([
            'roles' => [$this->role('super_admin')->id],
        ]))->assertSessionHasErrors('roles');

        // Activity Officer holds activity.create, which a Directorate Admin does not hold.
        $this->actingAs($scopedAdmin)->post(route('admin.users.store'), $this->validUser())->assertSessionHasErrors('roles');

        $this->actingAs($scopedAdmin)->post(route('admin.users.store'), $this->validUser([
            'roles' => [$this->role('report_viewer')->id],
        ]))->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('audit_logs', ['event' => 'access.denied', 'actor_id' => $scopedAdmin->id]);
    }

    public function test_scoped_admin_cannot_assign_scope_outside_their_own()
    {
        $scopedAdmin = $this->makeUser('directorate_admin', ['district' => [$this->peshawar->id]]);
        $viewer = ['roles' => [$this->role('report_viewer')->id]];

        $this->actingAs($scopedAdmin)->post(route('admin.users.store'), $this->validUser($viewer + ['scope' => ['district' => [$this->swat->id]]]))
            ->assertSessionHasErrors('scope');
        $this->actingAs($scopedAdmin)->post(route('admin.users.store'), $this->validUser($viewer + ['scope' => ['hostel' => [$this->swatHostel->id]]]))
            ->assertSessionHasErrors('scope');
        $this->actingAs($scopedAdmin)->post(route('admin.users.store'), $this->validUser($viewer + ['scope' => ['global' => 1]]))
            ->assertSessionHasErrors('scope');
        // A unit inside their district is fine.
        $this->actingAs($scopedAdmin)->post(route('admin.users.store'), $this->validUser($viewer + ['scope' => ['center' => [$this->peshawarCenter->id]]]))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_scoped_admin_cannot_view_or_edit_users_in_another_district_by_changing_the_id()
    {
        $scopedAdmin = $this->makeUser('directorate_admin', ['district' => [$this->peshawar->id]]);
        $swatUser = $this->makeUser('report_viewer', ['district' => [$this->swat->id]]);
        $peshawarUser = $this->makeUser('report_viewer', ['district' => [$this->peshawar->id]]);

        $this->actingAs($scopedAdmin)->get(route('admin.users.index'))->assertOk()
            ->assertSee($peshawarUser->email)->assertDontSee($swatUser->email);

        $this->actingAs($scopedAdmin)->get(route('admin.users.show', $swatUser))->assertForbidden();
        $this->actingAs($scopedAdmin)->get(route('admin.users.edit', $swatUser))->assertForbidden();
        $this->actingAs($scopedAdmin)->post(route('admin.users.deactivate', $swatUser), ['reason' => 'Trying it'])->assertForbidden();

        $this->actingAs($scopedAdmin)->get(route('admin.users.edit', $peshawarUser))->assertOk();
    }

    public function test_admin_cannot_manage_more_privileged_accounts_or_themselves()
    {
        $admin = $this->makeUser('directorate_admin');
        $superAdmin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.users.edit', $superAdmin))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.users.edit', $admin))->assertForbidden();
    }

    public function test_deactivation_requires_reason_revokes_sessions_and_is_audited()
    {
        $admin = $this->superAdmin();
        $target = $this->makeUser('report_viewer');
        DB::table('sessions')->insert(['id' => 'abc123', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()]);

        $this->actingAs($admin)->post(route('admin.users.deactivate', $target), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.users.deactivate', $target), ['reason' => 'Left the Directorate'])->assertRedirect();

        $target->refresh();
        $this->assertSame('inactive', $target->status);
        $this->assertSame('Left the Directorate', $target->deactivation_reason);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $target->id]); // deactivated, not deleted
        $log = AuditLog::where('event', 'user.deactivated')->firstOrFail();
        $this->assertSame('Left the Directorate', $log->reason);
        $this->assertSame(['status' => 'active'], $log->before);
    }

    /** An account holding every permission without being a Super Admin — the only one with authority over the last Super Admin. */
    protected function allPowerfulNonSuperAdmin(): User
    {
        $role = \App\Models\Role::create(['slug' => 'everything', 'name' => 'Everything', 'status' => 'active']);
        $role->permissions()->sync(\App\Models\Permission::pluck('id'));

        return $this->makeUser('everything');
    }

    public function test_super_admin_can_be_deactivated_while_another_remains_active()
    {
        $a = $this->superAdmin();
        $b = $this->superAdmin();

        $this->actingAs($b)->post(route('admin.users.deactivate', $a), ['reason' => 'Retired'])->assertRedirect();
        $this->assertSame('inactive', $a->fresh()->status);
        $this->assertTrue($b->fresh()->isLastActiveSuperAdmin());
    }

    public function test_last_active_super_admin_cannot_be_deactivated_or_lose_the_role()
    {
        $last = $this->superAdmin();
        $actor = $this->allPowerfulNonSuperAdmin();

        $this->actingAs($actor)->post(route('admin.users.deactivate', $last), ['reason' => 'Testing rule'])->assertForbidden();
        $this->assertSame('active', $last->fresh()->status);

        $this->actingAs($actor)->put(route('admin.users.update', $last), [
            'name' => $last->name, 'email' => $last->email,
            'roles' => [$this->role('report_viewer')->id],
            'scope' => ['global' => 1],
        ])->assertSessionHasErrors('roles');
        $this->assertTrue($last->fresh()->isSuperAdmin());
    }

    public function test_super_admin_role_keeps_its_essential_permissions()
    {
        $admin = $this->superAdmin();
        $role = $this->role('super_admin');
        $keys = $role->permissions()->pluck('key')->reject(fn ($k) => $k === 'users.manage')->values()->all();

        $this->actingAs($admin)->put(route('admin.roles.permissions.update', $role), ['permissions' => $keys])
            ->assertSessionHasErrors('permissions');
        $this->assertTrue($role->permissions()->where('key', 'users.manage')->exists());
    }

    public function test_user_without_permission_cannot_reach_user_management_by_url()
    {
        $user = $this->makeUser('hostel_staff', ['hostel' => [$this->swatHostel->id]]);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.store'), $this->validUser())->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'access.denied', 'actor_id' => $user->id]);
    }
}
