<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Sequential references such as AL-2026-0007. The next number is read under
 * a row lock inside a transaction; the column's unique index is the final
 * guard, and a clash from a concurrent save is simply retried.
 */
class ReferenceNumber
{
    public static function preview(string $class, string $column, string $prefix): string
    {
        return self::format($prefix, now()->year, self::last($class, $column, $prefix, now()->year) + 1);
    }

    /** Assign the next reference to $model and save it. */
    public static function saveWith(Model $model, string $column, string $prefix): void
    {
        $year = now()->year;
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::transaction(function () use ($model, $column, $prefix, $year) {
                    $model->{$column} = self::format($prefix, $year, self::last(get_class($model), $column, $prefix, $year, true) + 1);
                    $model->save();
                });

                return;
            } catch (QueryException $e) {
                if ($attempt >= 5 || ($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }
    }

    protected static function last(string $class, string $column, string $prefix, int $year, bool $lock = false): int
    {
        $start = "{$prefix}-{$year}-";
        $query = $class::query()->where($column, 'like', $start . '%');
        if ($lock) {
            $query->lockForUpdate();
        }
        $last = $query->orderByDesc($column)->value($column);

        return $last ? (int) substr($last, strlen($start)) : 0;
    }

    protected static function format(string $prefix, int $year, int $n): string
    {
        return sprintf('%s-%d-%04d', $prefix, $year, $n);
    }
}
