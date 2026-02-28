<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:users,name',
            'password' => 'required|string|min:4|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['name']) . '@trusted-advisors.local',
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return response()->json($user);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt(['name' => $validated['name'], 'password' => $validated['password']])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $request->session()->regenerate();

        return response()->json(Auth::user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(): JsonResponse
    {
        $user = Auth::user();

        return $user
            ? response()->json($user)
            : response()->json(null, 204);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:4|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json(['message' => 'Password updated']);
    }

    public function registerPushId(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => 'required|string',
        ]);

        $request->user()->update(['onesignal_player_id' => $validated['player_id']]);

        return response()->json(['message' => 'Push subscription registered']);
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
        ];

        return response()->json($stats);
    }
}
