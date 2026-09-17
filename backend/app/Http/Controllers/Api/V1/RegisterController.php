<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class RegisterController
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'surname' => $data['surname'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'date_of_birth' => $data['date_of_birth'],
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);

            $user->address()->create($data['address']);

            $acceptedAt = now();
            $user->policyAcceptances()->createMany([
                [
                    'policy_type' => 'terms',
                    'policy_version' => config('registration.terms_version'),
                    'accepted_at' => $acceptedAt,
                ],
                [
                    'policy_type' => 'privacy',
                    'policy_version' => config('registration.privacy_version'),
                    'accepted_at' => $acceptedAt,
                ],
            ]);

            $user->wallet()->create([
                'diamonds' => 0,
                'soft_currency' => 100,
            ]);
            $user->towerCards()->create([
                'code' => 'ember-archer',
                'level' => 1,
                'quantity' => 1,
                'is_equipped' => true,
            ]);
            $user->inventoryItems()->create([
                'item_type' => 'starter',
                'item_key' => 'welcome-pack',
                'quantity' => 1,
            ]);
            $user->boosts()->create([
                'code' => 'first-watch',
                'quantity' => 1,
            ]);
            $user->loadout()->create([
                'slots' => ['ember-archer'],
            ]);

            return $user;
        });

        $token = $user->createToken('frontend')->plainTextToken;

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\WelcomeRegistrationMail($user));
        } catch (\Throwable) {
            // Logged or swallowed to prevent blocking registration if mail server has transient issues
        }

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
                'message' => 'Registration completed successfully.',
            ],
        ], 201);
    }
}
