<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

final class VerifyEmailController
{
    public function __invoke(Request $request, int $id, string $hash): JsonResponse
    {
        abort_unless(URL::hasValidSignature($request), 403);

        $user = User::findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'email_verified' => true,
                'verified_at' => $user->fresh()->email_verified_at?->toIso8601String(),
            ],
        ]);
    }
}
