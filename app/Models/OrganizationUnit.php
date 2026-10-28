<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for offices, Jawan Markaz centers and hostels (all belong to a district).
 */
abstract class OrganizationUnit extends Model
{
    protected $fillable = ['district_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'district_id' => 'integer'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Map a scope type to its model class. */
    public static function classFor(string $type): string
    {
        return [
            'district' => District::class,
            'office' => Office::class,
            'center' => Center::class,
            'hostel' => Hostel::class,
        ][$type];
    }
}
