<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AuditLogController;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Authority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasPermission('users.view') || $user->hasPermission('roles.view') || $user->hasPermission('audit.view');

        $data = ['isAdmin' => $isAdmin, 'modules' => $this->moduleCards($user)];

        if ($isAdmin) {
            $visible = Authority::visibleUsers($user, User::query());
            $data['stats'] = [
                'users' => (clone $visible)->count(),
                'users_new' => (clone $visible)->where('created_at', '>=', now()->startOfMonth())->count(),
                'roles' => Role::count(),
                'roles_active' => Role::active()->count(),
                'permissions' => Permission::count(),
                'audit' => AuditLog::count(),
                'audit_today' => AuditLog::where('created_at', '>=', now()->startOfDay())->count(),
            ];
            $data['moduleAccess'] = $this->moduleAccess();
        }

        $recent = AuditLog::query()->latest('id')->limit(6);
        if ($user->hasPermission('audit.view')) {
            AuditLogController::restrictToAuthority($user, $recent);
        } else {
            $recent->where('actor_id', $user->id);
        }
        $data['recent'] = $recent->get();

        return view('dashboard', $data);
    }

    /** Active users who hold at least one permission in each business module. */
    protected function moduleAccess(): array
    {
        $out = [];
        foreach (config('access.modules') as $module => $label) {
            $out[$module] = [
                'label' => $label,
                'users' => (int) DB::table('users')
                    ->join('role_user', 'users.id', '=', 'role_user.user_id')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->join('permission_role', 'roles.id', '=', 'permission_role.role_id')
                    ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                    ->where('users.status', 'active')
                    ->where('roles.status', 'active')
                    ->where('permissions.module', $module)
                    ->distinct()
                    ->count('users.id'),
            ];
        }

        return $out;
    }

    protected function moduleCards(User $user): array
    {
        return collect(config('access.modules'))
            ->filter(fn ($label, $module) => $user->hasPermission("{$module}.view"))
            ->all();
    }
}
