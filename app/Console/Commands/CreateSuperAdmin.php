<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\PasswordPolicy;
use App\Support\ScopeAssignment;
use App\Support\ScopeCoverage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Controlled initial setup (spec §3 "Initial Setup", §16.9): creates the first
 * Super Admin from the server console. Refuses to run once an active Super
 * Admin exists, so it cannot be used as a back door later.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'dya:create-super-admin';

    protected $description = 'Create the first Super Admin account (only when none exists)';

    public function handle()
    {
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\AccessControlSeeder', '--force' => true]);

        if (User::activeSuperAdminCount() > 0) {
            $this->error('An active Super Admin already exists. Create further administrators from the User Management screen.');

            return self::FAILURE;
        }

        $name = $this->ask('Full name');
        $email = Str::lower((string) $this->ask('Official email (login identifier)'));
        $password = $this->secret('Password (' . PasswordPolicy::hint() . ')');
        $confirm = $this->secret('Confirm password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => array_merge(['required', 'confirmed'], PasswordPolicy::rules()),
            ]
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);
            $user->roles()->attach(Role::where('slug', config('access.super_admin_role'))->value('id'), ['assigned_at' => now()]);
            ScopeAssignment::save($user, new ScopeCoverage(true, []));

            return $user;
        });

        Audit::log('user.created', $user, [
            'actor' => null,
            'actor_name' => 'System (console setup)',
            'description' => 'Initial Super Admin created via dya:create-super-admin',
        ]);

        $this->info("Super Admin {$user->email} created. Sign in at " . url('/login'));

        return self::SUCCESS;
    }
}
