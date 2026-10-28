<?php

namespace App\Support;

use App\Models\Center;
use App\Models\District;
use App\Models\Hostel;
use App\Models\Office;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Support\Facades\DB;

/** Reads a scope selection from a form and stores it on a user. */
class ScopeAssignment
{
    public static function rules(): array
    {
        return [
            'scope' => ['nullable', 'array'],
            'scope.global' => ['nullable', 'boolean'],
            'scope.district' => ['nullable', 'array'],
            'scope.district.*' => ['integer', 'exists:districts,id'],
            'scope.office' => ['nullable', 'array'],
            'scope.office.*' => ['integer', 'exists:offices,id'],
            'scope.center' => ['nullable', 'array'],
            'scope.center.*' => ['integer', 'exists:centers,id'],
            'scope.hostel' => ['nullable', 'array'],
            'scope.hostel.*' => ['integer', 'exists:hostels,id'],
        ];
    }

    /** Build a normalized coverage: units already covered by a selected district are dropped. */
    public static function fromInput(?array $input): ScopeCoverage
    {
        $input = $input ?? [];
        if (! empty($input['global'])) {
            return new ScopeCoverage(true, []);
        }

        $districts = array_map('intval', $input['district'] ?? []);
        $explicit = ['district' => $districts];
        foreach (['office' => Office::class, 'center' => Center::class, 'hostel' => Hostel::class] as $type => $class) {
            $ids = array_map('intval', $input[$type] ?? []);
            $explicit[$type] = $ids ? $class::whereIn('id', $ids)->whereNotIn('district_id', $districts)->pluck('id')->all() : [];
        }

        return new ScopeCoverage(false, $explicit);
    }

    public static function save(User $user, ScopeCoverage $coverage): void
    {
        DB::transaction(function () use ($user, $coverage) {
            $user->scopes()->delete();

            if ($coverage->global) {
                $user->scopes()->create(['scope_type' => 'global', 'scope_id' => null]);

                return;
            }
            foreach ($coverage->explicit as $type => $ids) {
                foreach ($ids as $id) {
                    $user->scopes()->create(['scope_type' => $type, 'scope_id' => $id]);
                }
            }
        });
        $user->flushAccessCache();
    }

    /** Named list for audit records and detail screens, e.g. ['District: Peshawar', 'Hostel: Swat Youth Hostel']. */
    public static function describe(ScopeCoverage $coverage): array
    {
        if ($coverage->global) {
            return ['All districts & units'];
        }

        $out = [];
        $classes = ['district' => District::class, 'office' => Office::class, 'center' => Center::class, 'hostel' => Hostel::class];
        $labels = ['district' => 'District', 'office' => 'Office', 'center' => 'Jawan Markaz', 'hostel' => 'Hostel'];
        foreach ($classes as $type => $class) {
            if ($ids = $coverage->explicit[$type]) {
                foreach ($class::whereIn('id', $ids)->orderBy('name')->pluck('name') as $name) {
                    $out[] = "{$labels[$type]}: {$name}";
                }
            }
        }

        return $out;
    }

    /** Organizational tree for the scope picker, limited to what the actor may delegate. */
    public static function treeFor(User $actor): array
    {
        $coverage = $actor->scopeCoverage();
        $districts = District::active()->orderBy('name')
            ->with([
                'offices' => fn ($q) => $q->active()->orderBy('name'),
                'centers' => fn ($q) => $q->active()->orderBy('name'),
                'hostels' => fn ($q) => $q->active()->orderBy('name'),
            ])->get();

        $tree = [];
        foreach ($districts as $d) {
            $node = [
                'id' => $d->id,
                'name' => $d->name,
                'selectable' => $coverage->covers('district', $d->id),
                'children' => [],
            ];
            foreach (['office' => 'offices', 'center' => 'centers', 'hostel' => 'hostels'] as $type => $rel) {
                $node['children'][$type] = $d->$rel
                    ->filter(fn ($u) => $coverage->covers($type, $u->id))
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
            }
            if ($node['selectable'] || collect($node['children'])->flatten(1)->isNotEmpty()) {
                $tree[] = $node;
            }
        }

        return ['global' => $coverage->global, 'districts' => $tree];
    }
}
