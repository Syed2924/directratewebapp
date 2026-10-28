<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends AccessTestCase
{
    public function test_active_user_can_log_in_and_sees_only_authorized_navigation()
    {
        $user = $this->makeUser('report_viewer', ['district' => [$this->swat->id]]);

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertOk()
            ->assertSee('Report Management')
            ->assertDontSee('Activity Management')
            ->assertDontSee('Security Settings')
            ->assertDontSee(route('admin.users.index'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'login.success', 'actor_id' => $user->id]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invalid_credentials_give_the_same_generic_error_for_known_and_unknown_accounts()
    {
        $user = $this->makeUser('report_viewer');

        $known = $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        $known->assertSessionHasErrors(['email' => LoginController::GENERIC_ERROR]);

        $unknown = $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);
        $unknown->assertSessionHasErrors(['email' => LoginController::GENERIC_ERROR]);

        $this->assertGuest();
        $this->assertSame(2, AuditLog::where('event', 'login.failed')->count());
    }

    public function test_inactive_and_pending_users_cannot_log_in()
    {
        foreach (['inactive', 'pending'] as $state) {
            $user = User::factory()->{$state}()->create(['password' => Hash::make(UserFactory::PASSWORD)]);
            $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_account_locks_after_configured_failed_attempts_and_unlocks_after_lockout_period()
    {
        Setting::put(['max_failed_attempts' => 3, 'lockout_minutes' => 30]);
        $user = $this->makeUser('report_viewer');

        foreach (range(1, 3) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'bad']);
        }
        $this->assertSame('locked', $user->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'account.locked', 'entity_id' => $user->id]);

        // Even the right password is refused while locked.
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(31)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])->assertRedirect('/dashboard');
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_logout_ends_the_session_and_protected_routes_redirect_to_login()
    {
        $user = $this->makeUser('report_viewer');
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_deactivated_user_loses_access_on_the_next_request()
    {
        $user = $this->makeUser('report_viewer');
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['status' => 'inactive'])->save();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_idle_session_expires_after_configured_timeout()
    {
        Setting::put(['session_timeout_minutes' => 15]);
        $user = $this->makeUser('report_viewer');

        $this->actingAs($user)->withSession(['last_activity_at' => time() - 16 * 60])
            ->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_with_temporary_password_must_change_it_first()
    {
        $user = $this->makeUser('report_viewer', ['global' => true], ['must_change_password' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('account.password'));
        $this->actingAs($user)->get(route('account.password'))->assertOk();
    }

    public function test_password_change_requires_the_current_password_and_enforces_policy()
    {
        $user = $this->makeUser('report_viewer');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'not-my-password',
            'password' => 'NewSecret#456', 'password_confirmation' => 'NewSecret#456',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'NewSecret#456', 'password_confirmation' => 'NewSecret#456',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('NewSecret#456', $user->fresh()->password));
    }

    public function test_audit_log_never_contains_passwords()
    {
        $user = $this->makeUser('report_viewer');
        $this->post('/login', ['email' => $user->email, 'password' => 'Wrong#Guess999']);
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'NewSecret#456', 'password_confirmation' => 'NewSecret#456',
        ]);

        $dump = AuditLog::all()->toJson();
        foreach (['Wrong#Guess999', UserFactory::PASSWORD, 'NewSecret#456', $user->fresh()->password] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }

    public function test_captcha_is_required_when_enabled()
    {
        Setting::put(['captcha_enabled' => true]);
        $user = $this->makeUser('report_viewer');

        $this->get('/login')->assertSee('Security check');
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD, 'captcha' => '999'])
            ->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }
}
