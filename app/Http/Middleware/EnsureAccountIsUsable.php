<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Runs on every authenticated request:
 *  - an account that is no longer active loses access immediately (spec §8.1.5);
 *  - idle sessions expire after the configured timeout.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            return $this->endSession($request, 'Your account is not active. Please contact the administrator.');
        }

        if ($user) {
            $timeout = Setting::get('session_timeout_minutes');
            $last = $request->session()->get('last_activity_at');

            if ($timeout > 0 && $last && (time() - $last) > $timeout * 60) {
                Audit::log('session.expired', $user, ['actor' => $user]);

                return $this->endSession($request, 'Your session expired due to inactivity. Please sign in again.');
            }

            $request->session()->put('last_activity_at', time());
        }

        return $next($request);
    }

    protected function endSession(Request $request, string $message)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        return redirect()->route('login')->with('status_error', $message);
    }
}
