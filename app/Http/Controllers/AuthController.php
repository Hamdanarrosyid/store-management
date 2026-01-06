<?php

namespace App\Http\Controllers;

use App\Helper\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc,dns'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (! $token = JWTAuth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $user = JWTAuth::user();

        if (! $user->is_active) {
            JWTAuth::invalidate($token);
            throw ValidationException::withMessages([
                'email' => ['Account is inactive.'],
            ]);
        }
        
        $user->forceFill(['last_login_at' => now()])->save();

        return ApiResponse::success($this->respondWithToken($token));
    }

    public function updateProfile(Request $request)
    {
        $user = JWTAuth::user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email:rfc,dns', 'max:150', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', 'min:6', 'confirmed'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        return ApiResponse::success($this->userPayload($user));
    }


    public function me()
    {
        return ApiResponse::success($this->userPayload(JWTAuth::user()));
    }

   
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Successfully logged out']);
    }

  
    public function refresh()
    {
        return ApiResponse::success($this->respondWithToken(JWTAuth::refresh()));
    }

    
    private function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60
        ];
    }

    private function respondWithTokenAndUser($token, $user)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'data' => $this->userPayload($user),
        ]);
    }

    private function userPayload($user): array
    {
        // Pastikan relasi role disiapkan (role()) di model User
        $user->loadMissing('role', 'store');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'role' => $user->role?->name,
            'store' => $user->store ? [
                'id' => $user->store->id,
                'name' => $user->store->name,
                'level' => $user->store->level,
            ] : null,
            'last_login_at' => $user->last_login_at,
        ];
    }
}
