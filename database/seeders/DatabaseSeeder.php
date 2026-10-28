<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(AccessControlSeeder::class);

        // No default administrator is ever shipped. Outside local development,
        // create the first Super Admin with: php artisan dya:create-super-admin
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
            $this->call(BudgetDemoSeeder::class);
            $this->call(ActivityDemoSeeder::class);
            $this->call(JawanMarkazDemoSeeder::class);
            $this->call(HostelDemoSeeder::class);
        }
    }
}
