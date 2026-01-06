<?php

namespace App\Http\Middleware;

use App\Helper\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleMiddleware
{

    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = JWTAuth::user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $role = $user->role?->name;

        if (! $role || ! in_array($role, $roles, true)) {
            return ApiResponse::error('Forbidden', 403);
        }

        return $next($request);
    }
}
