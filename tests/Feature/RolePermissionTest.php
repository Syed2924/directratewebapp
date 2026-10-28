<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;

class RolePermissionTest extends AccessTestCase
{
    public function test_permission_changes_take_effect_immediately_and_are_audited()
    {
        $admin = $this->superAdmin();
        $viewer = $this->makeUser('report_viewer', ['district' => [$this->swat->id]]);
        $role = $this->role('report_viewer');

        $this->actingAs($viewer)->get(route('modules.show', 'hostel'))->assertForbidden();

        $keys = $role->permissions()->pluck('key')->push('hostel.view')->all();
        $this->actingAs($admin)->put(route('admin.roles.permissions.update', $role), ['permissions' => $keys])->assertRedirect();

        $this->actingAs($viewer->fresh())->get(route('modules.show', 'hostel'))->assertOk();

        $log = AuditLog::where('event', 'role.permissions_changed')->firstOrFail();
        $this->assertSame(['added' => ['hostel.view']], $log->after);
        $this->assertSame($admin->id, $log->actor_id);
    }

    public function test_cannot_grant_permissions_you_do_not_hold()
    {
        // A role manager without finance permissions tries to give a role budget.record_payment.
        $manager = Role::create(['slug' => 'role_manager', 'name' => 'Role Manager', 'status' => 'active']);
        $manager->permissions()->sync(\App\Models\Permission::whereIn('key', ['roles.view', 'roles.manage', 'reports.view', 'reports.export'])->pluck('id'));
        $actor = $this->makeUser('role_manager');

        $target = $this->role('report_viewer');
        $this->actingAs($actor)->put(route('admin.roles.permissions.update', $target), [
            'permissions' => ['reports.view', 'reports.export', 'budget.record_payment'],
        ])->assertSessionHasErrors('permissions');

        $this->assertFalse($target->permissions()->where('key', 'budget.record_payment')->exists());
    }

    public function test_cannot_edit_a_role_more_powerful_than_yourself()
    {
        $admin = $this->makeUser('directorate_admin');
        $this->role('directorate_admin')->permissions()->attach(\App\Models\Permission::where('key', 'roles.manage')->value('id'));

        $this->actingAs($admin->fresh())->get(route('admin.roles.edit', $this->role('super_admin')))->assertForbidden();
        $this->actingAs($admin->fresh())->get(route('admin.roles.edit', $this->role('finance_staff')))->assertForbidden();
    }

    public function test_inactive_role_grants_nothing()
    {
        $user = $this->makeUser('hostel_staff', ['hostel' => [$this->swatHostel->id]]);
        $this->actingAs($user)->get(route('modules.show', 'hostel'))->assertOk();

        $this->role('hostel_staff')->update(['status' => 'inactive']);
        $this->actingAs($user->fresh())->get(route('modules.show', 'hostel'))->assertForbidden();
    }

    public function test_system_role_cannot_be_deleted_and_roles_in_use_cannot_be_deleted()
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $this->role('super_admin')))->assertForbidden();

        $this->makeUser('report_viewer');
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $this->role('report_viewer')))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'Temporary Role', 'status' => 'active'])->assertRedirect();
        $temp = Role::where('name', 'Temporary Role')->firstOrFail();
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $temp))->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $temp->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'role.deleted', 'entity_id' => $temp->id]);
    }
}
