<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;

class SecuritySettingsController extends Controller
{
    public function edit()
    {
        return view('admin.security.edit', ['s' => Setting::security()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'password_min_length' => ['required', 'integer', 'between:8,64'],
            'password_expiry_days' => ['required', 'integer', 'between:0,365'],
            'max_failed_attempts' => ['required', 'integer', 'between:3,20'],
            'lockout_minutes' => ['required', 'integer', 'between:1,1440'],
            'session_timeout_minutes' => ['required', 'integer', 'between:5,' . config('session.lifetime')],
            'remember_me_days' => ['required', 'integer', 'between:0,30'],
        ], [
            'session_timeout_minutes.between' => 'Session timeout must be between 5 and ' . config('session.lifetime') . ' minutes (the server session lifetime).',
        ]);

        foreach (['password_require_uppercase', 'password_require_lowercase', 'password_require_number',
            'password_require_special', 'captcha_enabled', 'password_reset_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $before = Setting::security();
        Setting::put($data);
        [$old, $new] = Audit::diff($before, Setting::security());

        if ($new) {
            Audit::log('security.settings_changed', null, [
                'entity_type' => 'Settings',
                'entity_label' => 'Security settings',
                'description' => 'Changed: ' . implode(', ', array_keys($new)),
                'before' => $old,
                'after' => $new,
            ]);
        }

        return back()->with('status', $new ? 'Security settings saved.' : 'No changes to save.');
    }
}
