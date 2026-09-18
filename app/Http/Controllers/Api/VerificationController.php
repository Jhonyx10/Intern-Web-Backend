<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VerificationController extends Controller
{
    public function sendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 400);
        }

        $code = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        Cache::put("email_verify_{$user->id}", $code, now()->addMinutes(15));
        
        $user->notify(new EmailVerificationNotification($code));

        return response()->json(['message' => 'Verification code sent to your email.']);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ]);

        $user = $request->user();
        
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 400);
        }

        $cachedCode = Cache::get("email_verify_{$user->id}");

        if (!$cachedCode) {
            return response()->json(['message' => 'Verification code has expired or was not requested.'], 422);
        }

        if ((string)$request->code !== (string)$cachedCode) {
            return response()->json(['message' => 'The provided verification code is incorrect.'], 422);
        }

        $user->markEmailAsVerified();
        Cache::forget("email_verify_{$user->id}");

        return response()->json(['message' => 'Email verified successfully.']);
    }
}
