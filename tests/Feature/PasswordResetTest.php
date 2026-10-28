<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PasswordLinkNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

class PasswordResetTest extends AccessTestCase
{
    public function test_self_service_reset_is_unavailable_unless_enabled()
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/login')->assertDontSee('Forgot password?');
    }

    public function test_reset_request_does_not_reveal_whether_an_account_exists()
    {
        Notification::fake();
        Setting::put(['password_reset_enabled' => true]);
        $user = $this->makeUser('report_viewer');

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', PasswordResetController::GENERIC_SENT);
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ghost@example.com'])
            ->assertSessionHas('status', PasswordResetController::GENERIC_SENT);

        Notification::assertSentTo($user, PasswordLinkNotification::class);
        Notification::assertSentTimes(PasswordLinkNotification::class, 1);
    }

    public function test_reset_token_is_single_use_and_revokes_sessions()
    {
        $user = $this->makeUser('report_viewer');
        $token = Password::broker()->createToken($user);
        \Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);

        $payload = ['token' => $token, 'email' => $user->email, 'password' => 'Brand#New789', 'password_confirmation' => 'Brand#New789'];
        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->assertTrue(Hash::check('Brand#New789', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);

        $payload['password'] = $payload['password_confirmation'] = 'Another#New789';
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Brand#New789', $user->fresh()->password));
    }

    public function test_reset_token_expires()
    {
        $user = $this->makeUser('report_viewer');
        $token = Password::broker()->createToken($user);

        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'Brand#New789', 'password_confirmation' => 'Brand#New789',
        ])->assertSessionHasErrors('email');
    }

    public function test_setup_link_activates_a_pending_account()
    {
        $user = User::factory()->pending()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'Brand#New789', 'password_confirmation' => 'Brand#New789',
        ])->assertRedirect('/login');

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertFalse($user->must_change_password);
    }

    public function test_deactivated_account_cannot_use_a_reset_link()
    {
        $user = User::factory()->inactive()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => 'Brand#New789', 'password_confirmation' => 'Brand#New789',
        ])->assertSessionHasErrors('email');
        $this->assertSame('inactive', $user->fresh()->status);
    }
}
