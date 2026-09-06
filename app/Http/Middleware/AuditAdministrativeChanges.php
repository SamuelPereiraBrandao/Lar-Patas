<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdministrativeChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $request->isMethodSafe() && $response->isSuccessful() && $request->user()?->hasRole('admin')) {
            AuditLog::create(['user_id' => $request->user()->id, 'action' => $request->method(), 'resource' => $request->path(), 'changes' => $request->only(['status', 'roles', 'is_active', 'owner_id', 'owner_ids', 'shelter_id', 'pickup_at', 'pickup_timezone', 'active'])]);
        }

        return $response;
    }
}
