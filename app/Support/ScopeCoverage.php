<?php

namespace App\Support;

use App\Models\Center;
use App\Models\Hostel;
use App\Models\Office;
use App\Models\User;

/**
 * The organizational units a user may access.
 *
 * Explicit assignments are expanded downwards: a district grants every
 * office, center and hostel in that district. A "global" assignment grants
 * everything. A user with no scope rows can access no scoped records.
 */
class ScopeCoverage
{
    public const UNIT_TYPES = ['district', 'office', 'center', 'hostel'];

    /** @var array<string, int[]> explicit assignments by type */
    public array $explicit;

    /** @var array<string, int[]> expanded ids by type */
    protected array $expanded;

    public function __construct(public bool $global, array $explicit)
    {
        $this->explicit = [];
        foreach (self::UNIT_TYPES as $type) {
            $this->explicit[$type] = array_values(array_unique(array_map('intval', $explicit[$type] ?? [])));
        }

        $this->expanded = $this->expand();
    }

    public static function forUser(User $user): self
    {
        $rows = $user->relationLoaded('scopes') ? $user->scopes : $user->scopes()->get();

        $explicit = [];
        foreach ($rows as $row) {
            if ($row->scope_type !== 'global') {
                $explicit[$row->scope_type][] = $row->scope_id;
            }
        }

        return new self($rows->contains('scope_type', 'global'), $explicit);
    }

    protected function expand(): array
    {
        if ($this->global) {
            return [];
        }

        $districts = $this->explicit['district'];
        $grow = function (string $class, string $type) use ($districts) {
            $inDistricts = $districts ? $class::whereIn('district_id', $districts)->pluck('id')->all() : [];

            return array_values(array_unique(array_merge($this->explicit[$type], $inDistricts)));
        };

        return [
            'district' => $districts,
            'office' => $grow(Office::class, 'office'),
            'center' => $grow(Center::class, 'center'),
            'hostel' => $grow(Hostel::class, 'hostel'),
        ];
    }

    /** Ids of the given unit type this coverage includes, or null when unrestricted (global). */
    public function ids(string $type): ?array
    {
        return $this->global ? null : ($this->expanded[$type] ?? []);
    }

    public function covers(string $type, $id): bool
    {
        return $this->global || in_array((int) $id, $this->expanded[$type] ?? [], true);
    }

    public function isEmpty(): bool
    {
        return ! $this->global && collect($this->explicit)->flatten()->isEmpty();
    }

    /** True when every unit in $other is also covered by this coverage. */
    public function contains(self $other): bool
    {
        if ($this->global) {
            return true;
        }
        if ($other->global) {
            return false;
        }

        foreach (self::UNIT_TYPES as $type) {
            if (array_diff($other->explicit[$type], $this->expanded[$type]) !== []) {
                return false;
            }
        }

        return true;
    }

    /** Readable summary for screens, e.g. "All units" or "2 districts, 1 hostel". */
    public function summary(): string
    {
        if ($this->global) {
            return 'All districts & units';
        }

        $parts = [];
        foreach (self::UNIT_TYPES as $type) {
            $n = count($this->explicit[$type]);
            if ($n) {
                $parts[] = $n . ' ' . \Illuminate\Support\Str::plural($type, $n);
            }
        }

        return $parts ? implode(', ', $parts) : 'No scope assigned';
    }
}
