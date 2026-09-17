<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class LoginController
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            abort(401, 'Invalid email or password.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $token = $user->createToken('frontend')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'surname' => $user->surname,
                    'email' => $user->email,
                    'email_verified' => true,
                ],
            ],
        ]);
    }
}
