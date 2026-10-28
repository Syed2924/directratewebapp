<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Writes security and administrative events to the audit log.
 * Secrets are always stripped from before/after values (spec §12).
 */
class Audit
{
    /** Keys that must never be written to the log. */
    protected const SECRET_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password', 'password_hash',
        'token', '_token', 'remember_token', 'session', 'session_id', 'captcha', 'api_key',
    ];

    public static function log(string $event, ?Model $entity = null, array $options = []): AuditLog
    {
        $request = request();
        /** @var User|null $actor */
        $actor = $options['actor'] ?? auth()->user();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? ($options['actor_name'] ?? null),
            'event' => $event,
            'entity_type' => $entity ? class_basename($entity) : ($options['entity_type'] ?? null),
            'entity_id' => $entity?->getKey() ?? ($options['entity_id'] ?? null),
            'entity_label' => $options['entity_label'] ?? self::labelFor($entity),
            'outcome' => $options['outcome'] ?? 'success',
            'description' => isset($options['description']) ? Str::limit($options['description'], 495) : null,
            'reason' => isset($options['reason']) ? Str::limit($options['reason'], 495) : null,
            'before' => self::clean($options['before'] ?? null),
            'after' => self::clean($options['after'] ?? null),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
        ]);
    }

    public static function failure(string $event, ?Model $entity = null, array $options = []): AuditLog
    {
        return self::log($event, $entity, ['outcome' => 'failure'] + $options);
    }

    protected static function labelFor(?Model $entity): ?string
    {
        if (! $entity) {
            return null;
        }

        return $entity->name ?? $entity->key ?? (class_basename($entity) . ' #' . $entity->getKey());
    }

    public static function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $out = [];
        foreach ($values as $key => $value) {
            $k = strtolower((string) $key);
            if (in_array($k, self::SECRET_KEYS, true) || Str::endsWith($k, ['_token', '_secret']) || Str::contains($k, 'secret')) {
                continue;
            }
            $out[$key] = is_array($value) ? self::clean($value) : $value;
        }

        return $out;
    }

    /** Only the keys whose values differ, for compact before/after records. */
    public static function diff(array $before, array $after): array
    {
        $changed = array_keys(array_filter($after, fn ($v, $k) => ($before[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));

        return [Arr::only($before, $changed), Arr::only($after, $changed)];
    }
}
