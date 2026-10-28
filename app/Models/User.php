<?php

namespace App\Models;

use App\Support\ScopeCoverage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const STATUSES = ['pending', 'active', 'inactive', 'locked'];

    protected $fillable = [
        'name', 'email', 'phone', 'employee_no', 'designation', 'password', 'status',
        'must_change_password', 'password_changed_at', 'deactivation_reason', 'deactivated_at',
        'created_by', 'updated_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'failed_login_attempts' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /** Per-request caches. */
    protected ?Collection $permissionCache = null;
    protected ?ScopeCoverage $coverageCache = null;

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withPivot('assigned_by', 'assigned_at');
    }

    public function scopes()
    {
        return $this->hasMany(UserScope::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('access.super_admin_role'));
    }

    /** Permission keys granted through the user's *active* roles. */
    public function permissionKeys(): Collection
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = Permission::query()
                ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->join('role_user', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $this->id)
                ->where('roles.status', 'active')
                ->distinct()
                ->pluck('permissions.key');
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $key): bool
    {
        return $this->permissionKeys()->contains($key);
    }

    public function hasAnyModuleAccess(string $module): bool
    {
        return $this->permissionKeys()->contains(fn ($k) => str_starts_with($k, $module . '.'));
    }

    public function scopeCoverage(): ScopeCoverage
    {
        return $this->coverageCache ??= ScopeCoverage::forUser($this);
    }

    public function flushAccessCache(): void
    {
        $this->permissionCache = null;
        $this->coverageCache = null;
        $this->unsetRelation('roles');
        $this->unsetRelation('scopes');
    }

    /** End every server-side session belonging to this user (database session driver). */
    public function revokeSessions(?string $exceptSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $this->id)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();

        // Invalidate "remember me" cookies as well.
        $this->forceFill(['remember_token' => null])->saveQuietly();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    }

    public function primaryRoleName(): string
    {
        return $this->roles->first()->name ?? 'No role';
    }

    public static function activeSuperAdminCount(): int
    {
        return static::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('slug', config('access.super_admin_role'))->where('status', 'active'))
            ->count();
    }

    public function isLastActiveSuperAdmin(): bool
    {
        return $this->isActive() && $this->isSuperAdmin() && static::activeSuperAdminCount() <= 1;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\PasswordLinkNotification($token, $this->password_changed_at === null));
    }
}
