<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('id')
            ->paginate(25)
            ->through(function ($user) {
                $lastLogin = LoginLog::where('user_id', $user->id)->latest('logged_in_at')->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'level' => $user->level,
                    'xp' => $user->xp,
                    'elo_rating' => $user->elo_rating,
                    'coins' => $user->coins,
                    'timeout_count' => $user->timeout_count ?? 0,
                    'is_admin' => $user->is_admin,
                    'banned_at' => $user->banned_at?->toDateTimeString(),
                    'last_login_at' => $lastLogin?->logged_in_at?->toDateTimeString(),
                    'created_at' => $user->created_at->toDateTimeString(),
                ];
            });

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $gamesPlayed = Game::whereHas('players', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('status', 'completed')->count();

        $gamesWon = DB::table('games')
            ->join('game_players', 'games.id', '=', 'game_players.game_id')
            ->where('games.status', 'completed')
            ->where('game_players.user_id', $user->id)
            ->where(function ($q) {
                $q->where('games.win', true)
                   ->orWhereColumn('game_players.player_number', 'games.winner_player_number');
            })
            ->count();

        $achievementCount = UserAchievement::where('user_id', $user->id)->count();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'level' => $user->level,
            'xp' => $user->xp,
            'elo_rating' => $user->elo_rating,
            'coins' => $user->coins,
            'is_admin' => $user->is_admin,
            'banned_at' => $user->banned_at?->toDateTimeString(),
            'timeout_count' => $user->timeout_count ?? 0,
            'login_streak' => $user->login_streak,
            'max_login_streak' => $user->max_login_streak,
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'is_premium' => $user->is_premium,
            'premium_expires_at' => $user->premium_expires_at?->toDateTimeString(),
            'games_played' => $gamesPlayed,
            'games_won' => $gamesWon,
            'achievement_count' => $achievementCount,
        ]);
    }

    public function ban(User $user): JsonResponse
    {
        if ($user->is_admin) {
            return response()->json(['error' => 'Cannot ban an admin user'], 422);
        }

        if ($user->banned_at) {
            $user->update(['banned_at' => null]);

            return response()->json(['message' => 'User unbanned', 'banned_at' => null]);
        }

        $user->update(['banned_at' => now()]);

        return response()->json(['message' => 'User banned', 'banned_at' => $user->fresh()->banned_at->toDateTimeString()]);
    }

    public function loginLogs(User $user): JsonResponse
    {
        $logs = LoginLog::where('user_id', $user->id)
            ->orderByDesc('logged_in_at')
            ->limit(50)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'logged_in_at' => $log->logged_in_at->toDateTimeString(),
                ];
            });

        return response()->json($logs);
    }

    public function impersonate(Request $request, User $user): JsonResponse
    {
        if ($user->is_admin) {
            return response()->json(['error' => 'Cannot impersonate an admin user'], 422);
        }

        if ($user->banned_at) {
            return response()->json(['error' => 'Cannot impersonate a banned user'], 422);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);

        return response()->json(['message' => 'Now impersonating ' . $user->name]);
    }

    public function stopImpersonating(Request $request): JsonResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if (!$impersonatorId) {
            return response()->json(['error' => 'Not currently impersonating'], 422);
        }

        $admin = User::find($impersonatorId);
        if (!$admin) {
            return response()->json(['error' => 'Original admin not found'], 422);
        }

        $request->session()->forget('impersonator_id');
        Auth::login($admin);

        return response()->json(['message' => 'Stopped impersonating, welcome back ' . $admin->name]);
    }
}
