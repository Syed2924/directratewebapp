<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    protected $fillable = ['label', 'starts_on', 'ends_on'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function budget()
    {
        return $this->hasOne(Budget::class);
    }

    /** The year containing today, otherwise the most recent one. */
    public static function current(): ?self
    {
        $today = now()->toDateString();

        return static::where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->first()
            ?? static::orderByDesc('starts_on')->first();
    }

    /** The year selected by ?fy= on a request, falling back to the current one. */
    public static function fromRequest($request): ?self
    {
        return ($request->filled('fy') ? static::find((int) $request->input('fy')) : null) ?? static::current();
    }

    public function contains($date): bool
    {
        return $date && $this->starts_on->lte($date) && $this->ends_on->gte($date);
    }
}
