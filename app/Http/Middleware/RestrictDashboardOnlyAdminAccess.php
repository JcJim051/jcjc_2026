<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RestrictDashboardOnlyAdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$this->isDashboardOnlyUser($user)) {
            return $next($request);
        }

        $allowedRoutes = [
            'admin.home',
            'admin.mesa_reportes.dashboard',
        ];

        $routeName = optional($request->route())->getName();

        if (in_array($routeName, $allowedRoutes, true)) {
            return $next($request);
        }

        return redirect()->route('admin.mesa_reportes.dashboard');
    }

    private function isDashboardOnlyUser($user): bool
    {
        $roleName = trim((string) optional(Role::find((int) ($user->role ?? 0)))->name);
        $normalized = mb_strtolower($roleName);

        return in_array($normalized, [
            'dashboard operativo',
            'dashboard_operativo',
            'dashboard-operativo',
            'solo dashboard',
            'dashboard pmu',
        ], true);
    }
}
