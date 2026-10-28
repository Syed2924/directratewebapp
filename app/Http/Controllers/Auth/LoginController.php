<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** One generic message for every credential failure, so account existence is never disclosed. */
    public const GENERIC_ERROR = 'The email address or password is incorrect.';

    /** Per email+IP request cap, independent of account lockout. */
    protected const MAX_ATTEMPTS_PER_MINUTE = 10;

    /** Hash of a random discarded value; checked when the account does not exist. */
    protected const DUMMY_HASH = '$2y$10$sqVzwGqyonDhknLLGpoGfuShxwZo8lR8mYxUycorW/t4qhldGbrlm';

    public function show(Request $request)
    {
        $settings = Setting::security();

        return view('auth.login', [
            'captcha' => $settings['captcha_enabled'] ? $this->newCaptcha($request) : null,
            'rememberEnabled' => $settings['remember_me_days'] > 0,
            'resetEnabled' => $settings['password_reset_enabled'],
        ]);
    }

    public function login(Request $request)
    {
        $settings = Setting::security();
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            Audit::failure('login.blocked', null, ['description' => 'Rate limit reached for ' . $request->input('email')]);
            throw ValidationException::withMessages([
                'email' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
            ]);
        }
        RateLimiter::hit($throttleKey, 60);

        if ($settings['captcha_enabled'] && ! $this->captchaPasses($request)) {
            throw ValidationException::withMessages(['captcha' => 'The answer to the security question is incorrect.']);
        }

        $user = User::where('email', $request->input('email'))->first();

        // Always run a hash check so response timing does not reveal whether the account exists.
        $passwordOk = Hash::check($request->input('password'), $user->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordOk) {
            $this->recordFailure($user, $request, $settings);
            throw ValidationException::withMessages(['email' => self::GENERIC_ERROR]);
        }

        // Temporary lock has run its course.
        if ($user->status === 'locked' && $user->locked_until && $user->locked_until->isPast()) {
            $user->forceFill(['status' => 'active', 'locked_until' => null, 'failed_login_attempts' => 0])->save();
            Audit::log('account.unlocked', $user, ['actor' => $user, 'description' => 'Automatic unlock after lockout period']);
        }

        // The correct password was supplied, so explaining the status discloses nothing new.
        if (! $user->isActive()) {
            Audit::failure('login.blocked', $user, ['actor' => $user, 'description' => "Account status: {$user->status}"]);
            $message = $user->status === 'locked'
                ? 'This account is temporarily locked after repeated failed sign-ins. Try again later or contact the administrator.'
                : 'This account is not active. Please contact the administrator.';
            throw ValidationException::withMessages(['email' => $message]);
        }

        $remember = $request->boolean('remember') && $settings['remember_me_days'] > 0;
        if ($remember) {
            Auth::guard('web')->setRememberDuration($settings['remember_me_days'] * 1440);
        }

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('last_activity_at', time());
        RateLimiter::clear($throttleKey);

        $user->forceFill([
            'failed_login_attempts' => 0,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Audit::log('login.success', $user, ['actor' => $user, 'description' => 'User logged in']);

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            Audit::log('logout', $user, ['description' => 'User logged out']);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    protected function recordFailure(?User $user, Request $request, array $settings): void
    {
        if (! $user) {
            Audit::failure('login.failed', null, ['description' => 'Unknown login identifier']);

            return;
        }

        $attempts = $user->failed_login_attempts + 1;
        $user->forceFill(['failed_login_attempts' => $attempts])->save();
        Audit::failure('login.failed', $user, ['actor_name' => $user->name, 'description' => "Invalid password (attempt {$attempts})"]);

        if ($user->status === 'active' && $settings['max_failed_attempts'] > 0 && $attempts >= $settings['max_failed_attempts']) {
            $user->forceFill([
                'status' => 'locked',
                'locked_until' => now()->addMinutes(max(1, $settings['lockout_minutes'])),
            ])->save();
            $user->revokeSessions();
            Audit::log('account.locked', $user, [
                'actor' => null,
                'actor_name' => 'System',
                'description' => "Locked for {$settings['lockout_minutes']} minutes after {$attempts} failed attempts",
            ]);
        }
    }

    protected function newCaptcha(Request $request): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $request->session()->put('login_captcha', $a + $b);

        return "{$a} + {$b}";
    }

    protected function captchaPasses(Request $request): bool
    {
        $expected = $request->session()->pull('login_captcha');

        return $expected !== null && (int) $request->input('captcha') === (int) $expected;
    }
}
