<?php

namespace App\Http\Middleware;

use App\Helper\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class StoreScopeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = JWTAuth::user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $role = $user->role?->name;

        // SUPER_ADMIN: boleh lintas toko (tidak dipaksa store_id)
        if ($role === 'SUPER_ADMIN') {
            $request->attributes->set('scoped_store_id', null);
            return $next($request);
        }

        // ADMIN & KASIR: wajib punya store_id
        if (! $user->store_id) {
            return ApiResponse::error('User has no store scope', 403);
        }

        // Tempel store_id scope ke request agar controller pakai ini
        $request->attributes->set('scoped_store_id', (int) $user->store_id);

        return $next($request);
    }
}
