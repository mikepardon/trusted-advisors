<?php

namespace App\Http\Controllers;

use App\Models\ReferralReward;
use App\Models\User;
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

    public function apply(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:10']);
        $user = $request->user();

        if ($user->referred_by) {
            return response()->json(['message' => 'You have already used a referral code.'], 422);
        }

        $code = strtoupper(trim($request->input('code')));
        $referrer = User::where('referral_code', $code)
            ->where('id', '!=', $user->id)
            ->first();

        if (!$referrer) {
            return response()->json(['message' => 'Invalid referral code.'], 422);
        }

        $user->referred_by = $referrer->id;
        $user->save();

        return response()->json(['message' => 'Referral code applied!']);
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
