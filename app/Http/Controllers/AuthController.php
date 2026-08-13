<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(null, 204);
        }

        $data = $user->toArray();

        if (!$user->username_chosen) {
            $data['needs_username'] = true;
        }
        if ($user->needsAdvisors()) {
            $data['needs_advisors'] = true;
        }

        // Streak toast queued by the OAuth callback (read-once)
        $streakNotification = $request->session()->pull('pending_streak_notification');
        if ($streakNotification) {
            $data['streak_notification'] = $streakNotification;
        }

        $impersonatorId = $request->session()->get('impersonator_id');
        if ($impersonatorId) {
            $impersonator = User::find($impersonatorId);
            $data['is_impersonating'] = true;
            $data['impersonator_name'] = $impersonator?->name ?? 'Admin';
        }

        $paymentsToggle = \App\Models\GameRule::getValue('payments_enabled', true);
        $data['payments_enabled'] = $paymentsToggle && (
            !empty(config('services.stripe.key'))
            || !empty(config('services.apple.shared_secret'))
            || !empty(config('services.google_play.package_name'))
        );

        $tournamentsRule = \App\Models\GameRule::where('key', 'tournaments_enabled')->first();
        $data['tournaments_enabled'] = $tournamentsRule ? (bool) $tournamentsRule->value : false;

        return response()->json($data);
    }

    public function registerPushId(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => 'required|string',
        ]);

        $request->user()->update(['onesignal_player_id' => $validated['player_id']]);

        return response()->json(['message' => 'Push subscription registered']);
    }

    public function setUsername(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[a-zA-Z0-9]+$/',
            ],
            'referral_code' => 'nullable|string|max:10',
        ]);

        $username = strtolower($validated['username']);

        // Case-insensitive uniqueness check
        $taken = User::whereRaw('LOWER(name) = ?', [$username])
            ->where('id', '!=', $request->user()->id)
            ->exists();

        if ($taken) {
            return response()->json([
                'message' => 'The username is already taken.',
                'errors' => ['username' => ['The username is already taken.']],
            ], 422);
        }

        $user = $request->user();
        $user->name = $username;
        $user->username_chosen = true;

        // Process referral code if provided and user wasn't already referred
        if (!empty($validated['referral_code']) && !$user->referred_by) {
            $referrer = User::where('referral_code', strtoupper($validated['referral_code']))
                ->where('id', '!=', $user->id)
                ->first();
            if ($referrer) {
                $user->referred_by = $referrer->id;
            }
        }

        $user->save();

        $data = $user->toArray();
        if ($user->needsAdvisors()) {
            $data['needs_advisors'] = true;
        }

        return response()->json($data);
    }

    public function checkUsername(Request $request, string $username): JsonResponse
    {
        $username = strtolower($username);
        $available = !User::whereRaw('LOWER(name) = ?', [$username])
            ->where('id', '!=', $request->user()->id)
            ->exists();

        return response()->json(['available' => $available]);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $participantGameIds = \App\Models\GamePlayer::where('user_id', $userId)->pluck('game_id');

        $games = \App\Models\Game::where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->where('status', 'completed')
            ->get();

        $user = $request->user();

        $stats = [
            'total_games' => $games->count(),
            'total_wins' => $games->where('win', true)->count(),
            'total_losses' => $games->where('win', false)->count(),
            'online_games' => $games->where('game_mode', 'online')->count(),
            'online_wins' => $games->where('game_mode', 'online')->where('win', true)->count(),
            'local_games' => $games->whereIn('game_mode', ['single', 'pass_and_play'])->count(),
            'local_wins' => $games->whereIn('game_mode', ['single', 'pass_and_play'])->where('win', true)->count(),
            'single_wins' => $games->where('game_mode', 'single')->where('win', true)->count(),
            'pnp_wins' => $games->where('game_mode', 'pass_and_play')->where('win', true)->count(),
            'xp' => $user->xp,
            'level' => $user->level,
            'xp_for_next_level' => \App\Models\User::xpForLevel($user->level + 1),
            'elo_rating' => $user->elo_rating,
            'login_streak' => $user->login_streak,
            'max_login_streak' => $user->max_login_streak,
        ];

        return response()->json($stats);
    }
}
