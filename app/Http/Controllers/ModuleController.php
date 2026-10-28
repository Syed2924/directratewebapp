<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Entry point for the five business modules. Access is enforced here on the
 * server (route + permission), not just by hiding menu items. Each module
 * will replace this placeholder with its own controllers in later phases.
 */
class ModuleController extends Controller
{
    public function show(Request $request, string $module)
    {
        $modules = config('access.modules');
        abort_unless(isset($modules[$module]), 404);
        $this->authorize("{$module}.view");

        // Modules that have been built redirect to their own screens.
        if ($module === 'activity') {
            return redirect()->route('activity.proposals.index');
        }
        if ($module === 'budget') {
            return redirect()->route('budget.overview');
        }
        if ($module === 'reports') {
            return redirect()->route('reports.dashboard');
        }
        if ($module === 'hostel') {
            return redirect()->route('hostel.dashboard');
        }
        if ($module === 'jawan_markaz') {
            return redirect()->route('jawan-markaz.dashboard');
        }

        $user = $request->user();
        $actions = collect(config("access.module_actions.{$module}"))
            ->mapWithKeys(fn ($a) => [$a => $user->hasPermission("{$module}.{$a}")]);

        return view('modules.show', [
            'module' => $module,
            'label' => $modules[$module],
            'actions' => $actions,
            'coverage' => $user->scopeCoverage(),
        ]);
    }
}
