<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\OrganizationUnit;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Districts, offices, Jawan Markaz centers and hostels used for access scope. */
class OrganizationController extends Controller
{
    public const TYPES = ['district' => 'Districts', 'office' => 'Offices', 'center' => 'Jawan Markaz Centers', 'hostel' => 'Hostels'];

    public function index(Request $request)
    {
        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'district';
        $class = OrganizationUnit::classFor($type);

        $units = $class::query()
            ->when($type !== 'district', fn ($q) => $q->with('district'))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->input('q') . '%'))
            ->orderBy($type === 'district' ? 'name' : 'district_id')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.organization.index', [
            'type' => $type,
            'types' => self::TYPES,
            'units' => $units,
            'districts' => District::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->validate(['type' => ['required', Rule::in(array_keys(self::TYPES))]])['type'];
        $class = OrganizationUnit::classFor($type);
        $table = (new $class)->getTable();

        $rules = ['name' => ['required', 'string', 'max:150']];
        if ($type === 'district') {
            $rules['name'][] = Rule::unique('districts', 'name');
        } else {
            $rules['district_id'] = ['required', 'exists:districts,id'];
            $rules['name'][] = Rule::unique($table, 'name')->where('district_id', $request->input('district_id'));
        }
        $data = $request->validate($rules);

        $unit = $class::create($data + ['is_active' => true]);
        Audit::log('organization.changed', $unit, ['description' => 'Added ' . rtrim(self::TYPES[$type], 's') . ": {$unit->name}", 'after' => $data]);

        return back()->with('status', "{$unit->name} added.");
    }

    public function toggle(Request $request, string $type, int $id)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        $unit = OrganizationUnit::classFor($type)::findOrFail($id);
        $unit->update(['is_active' => ! $unit->is_active]);

        Audit::log('organization.changed', $unit, [
            'description' => ($unit->is_active ? 'Activated ' : 'Deactivated ') . $unit->name,
            'before' => ['is_active' => ! $unit->is_active], 'after' => ['is_active' => $unit->is_active],
        ]);

        return back()->with('status', "{$unit->name} " . ($unit->is_active ? 'activated' : 'deactivated') . '.');
    }
}
