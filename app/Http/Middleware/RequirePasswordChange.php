<?php

namespace App\Http\Middleware;

use App\Support\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;

/** Forces a new password after an administrator-set password or when the password has expired. */
class RequirePasswordChange
{
    protected array $except = ['account.password', 'account.password.update', 'logout'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $request->routeIs(...$this->except)
            && ($user->must_change_password || PasswordPolicy::isExpired($user))) {
            return redirect()->route('account.password')
                ->with('status_error', $user->must_change_password
                    ? 'Please set a new password before continuing.'
                    : 'Your password has expired. Please choose a new one.');
        }

        return $next($request);
    }
}
