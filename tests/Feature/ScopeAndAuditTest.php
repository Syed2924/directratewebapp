<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\ScopedRecordPolicy;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/** Stand-in for a future business-module record (e.g. a Jawan Markaz expense). */
class FakeExpense extends Model
{
    use HasOrganizationalScope;

    protected $table = 'fake_expenses';
    protected $guarded = [];
    protected string $scopeUnit = 'center';
    protected string $scopeColumn = 'center_id';
}

class FakeExpensePolicy extends ScopedRecordPolicy
{
    protected string $module = 'jawan_markaz';
}

class ScopeAndAuditTest extends AccessTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('fake_expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('center_id');
            $t->foreignId('submitted_by')->nullable();
            $t->timestamps();
        });
        Gate::policy(FakeExpense::class, FakeExpensePolicy::class);
    }

    public function test_records_are_filtered_by_organizational_scope()
    {
        $otherCenter = \App\Models\Center::create(['district_id' => $this->swat->id, 'name' => 'JM Mingora']);
        $mine = FakeExpense::create(['center_id' => $this->peshawarCenter->id]);
        $theirs = FakeExpense::create(['center_id' => $otherCenter->id]);

        $centerStaff = $this->makeUser('jawan_markaz_staff', ['center' => [$this->peshawarCenter->id]]);
        $districtUser = $this->makeUser('officer_approver', ['district' => [$this->peshawar->id]]);
        $globalUser = $this->makeUser('finance_staff');

        $this->assertSame([$mine->id], FakeExpense::visibleTo($centerStaff)->pluck('id')->all());
        $this->assertSame([$mine->id], FakeExpense::visibleTo($districtUser)->pluck('id')->all());
        $this->assertCount(2, FakeExpense::visibleTo($globalUser)->get());

        // Changing the record ID does not help: single-record checks also apply scope.
        $this->assertTrue($centerStaff->can('view', $mine));
        $this->assertFalse($centerStaff->can('view', $theirs));
    }

    public function test_users_cannot_approve_or_pay_their_own_submissions()
    {
        $approver = $this->makeUser('officer_approver');
        $finance = $this->makeUser('finance_staff');
        $own = FakeExpense::create(['center_id' => $this->peshawarCenter->id, 'submitted_by' => $approver->id]);
        $other = FakeExpense::create(['center_id' => $this->peshawarCenter->id, 'submitted_by' => $finance->id]);

        $this->assertFalse($approver->can('approve', $own));
        $this->assertTrue($approver->can('approve', $other));
        $this->assertFalse($finance->can('recordPayment', $other));
        $this->assertTrue($finance->can('recordPayment', $own));
        // Submission permission does not imply approval permission.
        $this->assertFalse($this->makeUser('jawan_markaz_staff', ['center' => [$this->peshawarCenter->id]])->can('approve', $other));
    }

    public function test_module_routes_enforce_permissions_server_side()
    {
        $hostelStaff = $this->makeUser('hostel_staff', ['hostel' => [$this->swatHostel->id]]);

        $this->actingAs($hostelStaff)->get(route('modules.show', 'hostel'))->assertOk();
        $this->actingAs($hostelStaff)->get(route('modules.show', 'budget'))->assertForbidden();
        $this->actingAs($hostelStaff)->get(route('admin.audit.index'))->assertForbidden();
        $this->actingAs($hostelStaff)->get(route('admin.security.edit'))->assertForbidden();
    }

    public function test_audit_export_requires_permission_and_contains_no_secrets()
    {
        $admin = $this->superAdmin();
        $auditViewer = $this->makeUser('directorate_admin'); // audit.view but not audit.export

        $this->actingAs($auditViewer)->get(route('admin.audit.index'))->assertOk();
        $this->actingAs($auditViewer)->get(route('admin.audit.export'))->assertForbidden();

        $response = $this->actingAs($admin)->get(route('admin.audit.export'));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Date & Time', $csv);
        $this->assertStringNotContainsString($admin->password, $csv);
        $this->assertDatabaseHas('audit_logs', ['event' => 'audit.exported', 'actor_id' => $admin->id]);
    }

    public function test_scoped_audit_viewer_only_sees_events_within_their_authority()
    {
        $scopedAdmin = $this->makeUser('directorate_admin', ['district' => [$this->peshawar->id]]);
        $inside = $this->makeUser('report_viewer', ['district' => [$this->peshawar->id]], ['name' => 'Inside Person']);
        $outside = $this->makeUser('report_viewer', ['district' => [$this->swat->id]], ['name' => 'Outside Person']);

        \App\Support\Audit::log('login.success', $inside, ['actor' => $inside, 'description' => 'inside-login']);
        \App\Support\Audit::log('login.success', $outside, ['actor' => $outside, 'description' => 'outside-login']);

        $this->actingAs($scopedAdmin)->get(route('admin.audit.index'))
            ->assertSee('inside-login')->assertDontSee('outside-login');
    }

    public function test_audit_entries_are_immutable()
    {
        $log = \App\Support\Audit::log('logout', null, ['description' => 'x']);

        $this->expectException(\LogicException::class);
        $log->update(['description' => 'tampered']);
    }

    public function test_all_admin_screens_render_for_super_admin()
    {
        $admin = $this->superAdmin();
        $other = $this->makeUser('report_viewer', ['district' => [$this->swat->id]]);

        foreach ([
            route('dashboard'), route('account.show'), route('account.password'),
            route('admin.users.index'), route('admin.users.create'), route('admin.users.show', $other), route('admin.users.edit', $other),
            route('admin.roles.index'), route('admin.roles.create'), route('admin.roles.permissions'),
            route('admin.roles.edit', $this->role('report_viewer')),
            route('admin.audit.index'), route('admin.security.edit'),
            route('admin.organization.index'), route('admin.organization.index', ['type' => 'hostel']),
            route('modules.show', 'activity'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
