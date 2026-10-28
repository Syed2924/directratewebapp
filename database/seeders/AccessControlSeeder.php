<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Installs the permission catalogue and proposed roles from config/access.php.
 *
 * Safe to re-run: new permissions are added, and new roles receive their
 * draft defaults. Existing roles keep whatever administrators configured,
 * except that Super Admin always holds every permission.
 * Set ACCESS_RESET_MATRIX=true to re-apply the draft matrix to every role.
 */
class AccessControlSeeder extends Seeder
{
    public function run()
    {
        $sensitiveActions = config('access.sensitive_actions');
        $sensitiveKeys = config('access.sensitive_permissions');

        foreach (config('access.module_actions') as $module => $actions) {
            foreach ($actions as $action) {
                $key = "{$module}.{$action}";
                Permission::updateOrCreate(['key' => $key], [
                    'module' => $module,
                    'action' => $action,
                    'description' => config("access.actions.{$action}") . ' — ' . config("access.modules.{$module}"),
                    'is_sensitive' => in_array($action, $sensitiveActions, true),
                ]);
            }
        }

        foreach (config('access.admin_permissions') as $key => $description) {
            [$module, $action] = explode('.', $key, 2);
            Permission::updateOrCreate(['key' => $key], [
                'module' => $module,
                'action' => $action,
                'description' => $description,
                'is_sensitive' => in_array($key, $sensitiveKeys, true),
            ]);
        }

        $all = Permission::pluck('id', 'key');
        $reset = filter_var(env('ACCESS_RESET_MATRIX', false), FILTER_VALIDATE_BOOLEAN);

        foreach (config('access.roles') as $slug => $def) {
            $role = Role::firstOrNew(['slug' => $slug]);
            $isNew = ! $role->exists;
            if ($isNew) {
                $role->fill([
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'status' => 'active',
                    'is_system' => $def['system'] ?? false,
                ])->save();
            }

            if ($isNew || $reset || $slug === config('access.super_admin_role')) {
                $role->permissions()->sync($this->expand($def['permissions'], $all));
            }
        }
    }

    protected function expand(array $patterns, $all): array
    {
        $ids = [];
        foreach ($patterns as $pattern) {
            foreach ($all as $key => $id) {
                if ($pattern === '*' || $pattern === $key || (str_ends_with($pattern, '.*') && str_starts_with($key, substr($pattern, 0, -1)))) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
