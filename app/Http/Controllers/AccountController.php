<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Audit;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('roles', 'scopes');

        return view('account.show', [
            'user' => $user,
            'recent' => AuditLog::where('actor_id', $user->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
        ]);

        [$before, $after] = Audit::diff($user->only('phone'), $data);
        $user->update($data + ['updated_by' => $user->id]);
        if ($after) {
            Audit::log('user.updated', $user, ['description' => 'Updated own contact details', 'before' => $before, 'after' => $after]);
        }

        return back()->with('status', 'Your details have been updated.');
    }

    public function editPassword()
    {
        return view('account.password', ['hint' => PasswordPolicy::hint()]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => array_merge(['required', 'confirmed', 'different:current_password'], PasswordPolicy::rules()),
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.different' => 'The new password must be different from the current password.',
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        // Sign out every other session for this account.
        $user->revokeSessions($request->session()->getId());
        Audit::log('password.changed', $user, ['description' => 'Password updated successfully']);

        return redirect()->route('dashboard')->with('status', 'Your password has been changed. Other sessions have been signed out.');
    }
}
