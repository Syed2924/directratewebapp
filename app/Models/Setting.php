<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'security_settings';

    /** All security settings merged over the defaults in config/access.php, cast to the default's type. */
    public static function security(): array
    {
        $defaults = config('access.security_defaults');

        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::pluck('value', 'key')->all());

        $out = [];
        foreach ($defaults as $key => $default) {
            $value = $stored[$key] ?? $default;
            settype($value, gettype($default));
            $out[$key] = $value;
        }

        return $out;
    }

    public static function get(string $key)
    {
        return static::security()[$key] ?? null;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? (int) $value : $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
