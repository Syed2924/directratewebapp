<?php

namespace App\Http\Controllers\JawanMarkaz;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\FinancialYear;
use App\Models\JawanMarkazExpense;
use App\Models\User;
use App\Support\Audit;
use App\Support\JawanMarkazSummary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Center records. Centers live in the shared organization master list; their
 * profile (location, contact, in-charge) is maintained by administrators
 * with organization.manage.
 */
class CenterController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('jawan_markaz.view');
        $fy = FinancialYear::fromRequest($request);

        return view('jawan-markaz.centers.index', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'summary' => JawanMarkazSummary::for($fy, $request->user()),
        ]);
    }

    public function show(Request $request, Center $center)
    {
        $this->authorize('jawan_markaz.view');
        abort_unless($center->isWithinScopeOf($request->user()), 403);
        $center->load('district', 'incharge', 'jmBudgets.financialYear');

        return view('jawan-markaz.centers.show', [
            'center' => $center,
            'staff' => $center->assignedStaff(),
            'budgets' => $center->jmBudgets->sortByDesc(fn ($b) => $b->financialYear->starts_on),
            'expenses' => JawanMarkazExpense::where('center_id', $center->id)->latest('id')->limit(8)->get(),
            'counts' => JawanMarkazExpense::where('center_id', $center->id)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function edit(Request $request, Center $center)
    {
        $this->authorize('organization.manage');
        abort_unless($center->isWithinScopeOf($request->user()), 403);

        return view('jawan-markaz.centers.edit', ['center' => $center, 'staff' => $this->inchargeOptions($center)]);
    }

    public function update(Request $request, Center $center)
    {
        $this->authorize('organization.manage');
        abort_unless($center->isWithinScopeOf($request->user()), 403);
        $data = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'incharge_id' => ['nullable', Rule::in($this->inchargeOptions($center)->pluck('id')->all())],
            'is_active' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = array_map('strval', $center->only(array_keys($data)));
        $center->update($data);
        [$old, $new] = Audit::diff($before, array_map('strval', $center->only(array_keys($data))));
        if ($new) {
            Audit::log('organization.changed', $center, ['description' => "Jawan Markaz profile updated: {$center->name}", 'before' => $old, 'after' => $new]);
        }

        return redirect()->route('jawan-markaz.centers.show', $center)->with('status', 'Center information saved.');
    }

    /** Active users who could be the center in-charge (Jawan Markaz roles covering this center). */
    protected function inchargeOptions(Center $center)
    {
        return User::where('status', 'active')
            ->whereHas('roles', fn ($r) => $r->where('status', 'active')->whereHas('permissions', fn ($p) => $p->where('key', 'jawan_markaz.create')))
            ->with('scopes', 'roles')->orderBy('name')->get()
            ->filter(fn ($u) => $u->scopeCoverage()->covers('center', $center->id))->values();
    }
}
