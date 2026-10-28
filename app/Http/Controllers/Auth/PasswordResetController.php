<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Self-service "forgot password" (only when enabled in Security Settings) and the
 * shared reset/setup form used by both self-service and administrator-issued links.
 * Tokens are hashed, time-limited and single-use (Laravel password broker).
 */
class PasswordResetController extends Controller
{
    public const GENERIC_SENT = 'If an active account matches that email address, a password reset link has been sent.';

    public function request()
    {
        abort_unless(Setting::get('password_reset_enabled'), 404);

        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        abort_unless(Setting::get('password_reset_enabled'), 404);
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user && in_array($user->status, ['active', 'pending'], true)) {
            Password::broker()->sendResetLink(['email' => $user->email]);
            Audit::log('password.reset_requested', $user, ['actor' => null, 'actor_name' => $user->name]);
        }

        // Same response whether or not the account exists (spec §7.7).
        return back()->with('status', self::GENERIC_SENT);
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
            'hint' => PasswordPolicy::hint(),
        ]);
    }

    public function update(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => array_merge(['required', 'confirmed'], PasswordPolicy::rules()),
        ]);

        $candidate = User::where('email', $request->input('email'))->first();
        if ($candidate && ! in_array($candidate->status, ['active', 'pending', 'locked'], true)) {
            throw ValidationException::withMessages(['email' => 'This password link is invalid or has expired.']);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $wasPending = $user->status === 'pending';
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'password_changed_at' => now(),
                    'must_change_password' => false,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'status' => in_array($user->status, ['pending', 'locked'], true) ? 'active' : $user->status,
                ])->save();
                $user->revokeSessions();
                Audit::log('password.reset', $user, [
                    'actor' => null,
                    'actor_name' => $user->name,
                    'description' => $wasPending ? 'Initial password set via setup link; account activated' : 'Password reset via emailed link',
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This password link is invalid or has expired.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been set. You can now sign in.');
    }
}
