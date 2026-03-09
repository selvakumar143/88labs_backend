<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveClientTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $clientId = $user->tenantClientId();
        $tenantOwnerUserId = $user->tenantOwnerUserId();

        if (!$clientId) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tenant is associated with this user.',
            ], 403);
        }

        $request->attributes->set('current_client_id', $clientId);
        $request->attributes->set('current_client_owner_user_id', $tenantOwnerUserId);

        app()->instance('currentClientId', $clientId);
        app()->instance('currentClientOwnerUserId', $tenantOwnerUserId);

        return $next($request);
    }
}
