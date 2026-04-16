<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        // Single-tenant for now: always resolve the primary tenant
        $tenant = Tenant::where('is_active', true)->first();

        if (! $tenant) {
            abort(503, 'Platform not configured.');
        }

        app()->instance('currentTenant', $tenant);

        // Share with all views
        view()->share('currentTenant', $tenant);

        return $next($request);
    }
}
