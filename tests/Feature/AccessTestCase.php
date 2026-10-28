<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\District;
use App\Models\Hostel;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\ScopeAssignment;
use App\Support\ScopeCoverage;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

abstract class AccessTestCase extends TestCase
{
    use RefreshDatabase;

    protected District $peshawar;
    protected District $swat;
    protected Center $peshawarCenter;
    protected Hostel $swatHostel;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(AccessControlSeeder::class);

        $this->peshawar = District::create(['name' => 'Peshawar']);
        $this->swat = District::create(['name' => 'Swat']);
        Office::create(['district_id' => $this->peshawar->id, 'name' => 'Peshawar Office']);
        $this->peshawarCenter = Center::create(['district_id' => $this->peshawar->id, 'name' => 'JM Peshawar']);
        $this->swatHostel = Hostel::create(['district_id' => $this->swat->id, 'name' => 'Swat Hostel']);
    }

    /** @param array $scope e.g. ['global' => true] or ['district' => [1]] */
    protected function makeUser(string $roleSlug, array $scope = ['global' => true], array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->roles()->attach(Role::where('slug', $roleSlug)->value('id'));
        ScopeAssignment::save($user, new ScopeCoverage(! empty($scope['global']), $scope));

        return $user->fresh();
    }

    protected function superAdmin(): User
    {
        return $this->makeUser('super_admin');
    }

    protected function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }
}
