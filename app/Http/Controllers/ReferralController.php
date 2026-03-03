<?php

namespace App\Http\Controllers;

use App\Models\ReferralReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function getCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = $user->generateReferralCode();

        return response()->json(['code' => $code]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalReferred = $user->referrals()->count();
        $verifiedCount = $user->referrals()->where('level', '>=', 2)->count();
        $totalCoins = ReferralReward::where('referrer_id', $user->id)->sum('coins_awarded');

        return response()->json([
            'total_referred' => $totalReferred,
            'verified_count' => $verifiedCount,
            'total_coins_earned' => (int) $totalCoins,
        ]);
    }
}
