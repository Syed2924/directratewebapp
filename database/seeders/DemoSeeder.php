<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\District;
use App\Models\Hostel;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\ScopeAssignment;
use App\Support\ScopeCoverage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL DEMO DATA ONLY — sample organizational units and accounts so every
 * screen can be explored. Never run in production (DatabaseSeeder guards this).
 * All demo accounts use the password in DEMO_PASSWORD.
 */
class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Demo@12345';

    public function run()
    {
        $units = [
            'Peshawar' => ['Peshawar District Office', 'Jawan Markaz Peshawar', 'Peshawar Youth Hostel'],
            'Charsadda' => ['Charsadda Office', 'Jawan Markaz Charsadda', null],
            'Swat' => ['Swat Office', 'Jawan Markaz Mingora', 'Swat Youth Hostel'],
            'Mardan' => ['Mardan Office', 'Jawan Markaz Mardan', null],
            'Kohat' => ['Kohat Office', null, 'Kohat Youth Hostel'],
            'Abbottabad' => ['Abbottabad Office', 'Jawan Markaz Abbottabad', 'Abbottabad Youth Hostel'],
        ];

        foreach ($units as $districtName => [$office, $center, $hostel]) {
            $d = District::firstOrCreate(['name' => $districtName]);
            if ($office) {
                Office::firstOrCreate(['district_id' => $d->id, 'name' => $office]);
            }
            if ($center) {
                Center::firstOrCreate(['district_id' => $d->id, 'name' => $center]);
            }
            if ($hostel) {
                Hostel::firstOrCreate(['district_id' => $d->id, 'name' => $hostel]);
            }
        }

        $district = fn ($n) => District::where('name', $n)->value('id');
        $people = [
            ['Syed Awais', 'alsyedk123@gmail.com', 'super_admin', ['global' => true], 'active'],
            ['Sana Ullah', 'sana.ullah@dya.test', 'directorate_admin', ['district' => [$district('Peshawar'), $district('Charsadda')]], 'active'],
            ['Ahmed Khan', 'ahmed.khan@dya.test', 'activity_officer', ['district' => [$district('Peshawar')]], 'active'],
            ['Ayesha Tariq', 'ayesha.tariq@dya.test', 'finance_staff', ['global' => true], 'active'],
            ['Imran Ali', 'imran.ali@dya.test', 'jawan_markaz_staff', ['center' => [Center::where('name', 'Jawan Markaz Peshawar')->value('id')]], 'inactive'],
            ['Faisal Rehman', 'faisal.rehman@dya.test', 'hostel_staff', ['hostel' => [Hostel::where('name', 'Swat Youth Hostel')->value('id')]], 'active'],
            ['Zara Khan', 'zara.khan@dya.test', 'report_viewer', ['district' => [$district('Swat')]], 'active'],
            ['Asma Riaz', 'asma.riaz@dya.test', 'officer_approver', ['global' => true], 'active'],
        ];

        foreach ($people as [$name, $email, $roleSlug, $scope, $status]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'status' => $status,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'deactivation_reason' => $status === 'inactive' ? 'Transferred to another department (demo data)' : null,
                'deactivated_at' => $status === 'inactive' ? now() : null,
            ]);
            $user->roles()->sync([Role::where('slug', $roleSlug)->value('id')]);
            ScopeAssignment::save($user, new ScopeCoverage(! empty($scope['global']), $scope));
        }
    }
}
