<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Validation\Rules\Password;

/** Builds the password validation rule from the Security Settings screen. */
class PasswordPolicy
{
    /** Validation rules for a new password (append 'required', 'confirmed' etc. as needed). */
    public static function rules(): array
    {
        $s = Setting::security();

        $rule = Password::min(max(8, $s['password_min_length']));
        if ($s['password_require_number']) {
            $rule->numbers();
        }
        if ($s['password_require_special']) {
            $rule->symbols();
        }

        $rules = [$rule, 'max:128'];
        $checks = [
            'password_require_uppercase' => ['/[A-Z]/', 'The :attribute must contain at least one uppercase letter.'],
            'password_require_lowercase' => ['/[a-z]/', 'The :attribute must contain at least one lowercase letter.'],
        ];
        foreach ($checks as $setting => [$pattern, $message]) {
            if ($s[$setting]) {
                $rules[] = function ($attribute, $value, $fail) use ($pattern, $message) {
                    if (! preg_match($pattern, (string) $value)) {
                        $fail(str_replace(':attribute', str_replace('_', ' ', $attribute), $message));
                    }
                };
            }
        }

        return $rules;
    }

    /** Human-readable description shown next to password fields. */
    public static function hint(): string
    {
        $s = Setting::security();
        $parts = ['at least ' . max(8, $s['password_min_length']) . ' characters'];
        if ($s['password_require_uppercase']) {
            $parts[] = 'an uppercase letter';
        }
        if ($s['password_require_lowercase']) {
            $parts[] = 'a lowercase letter';
        }
        if ($s['password_require_number']) {
            $parts[] = 'a number';
        }
        if ($s['password_require_special']) {
            $parts[] = 'a special character';
        }

        return 'Use ' . implode(', ', $parts) . '.';
    }

    public static function isExpired(\App\Models\User $user): bool
    {
        $days = Setting::get('password_expiry_days');

        return $days > 0 && $user->password_changed_at && $user->password_changed_at->lt(now()->subDays($days));
    }
}
