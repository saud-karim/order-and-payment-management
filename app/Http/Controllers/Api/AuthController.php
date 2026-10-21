<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));

        $token = Auth::guard('api')->login($user);

        return ApiResponse::success(
            $this->tokenPayload($user, $token),
            'User registered successfully.',
            201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! $token = Auth::guard('api')->attempt($request->validated())) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        return ApiResponse::success(
            $this->tokenPayload($user, $token),
            'Login successful.'
        );
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return ApiResponse::success(null, 'Successfully logged out.');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return ApiResponse::success([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(User $user, string $token): array
    {
        return [
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ];
    }
}
