<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function handleOAuthCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'code_verifier' => 'required|string',
            'referral_code' => 'nullable|string|max:10',
        ]);

        $authConfig = config('services.mpgames_auth');

        // Exchange authorization code for tokens
        $tokenResponse = Http::asForm()->post($authConfig['url'] . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $authConfig['client_id'],
            'redirect_uri' => $authConfig['redirect_uri'],
            'code' => $validated['code'],
            'code_verifier' => $validated['code_verifier'],
        ]);

        if ($tokenResponse->failed()) {
            return response()->json(['message' => 'Authentication failed.'], 401);
        }

        $tokens = $tokenResponse->json();

        // Fetch user info from auth service
        $userResponse = Http::withToken($tokens['access_token'])
            ->get($authConfig['url'] . '/api/user');

        if ($userResponse->failed()) {
            return response()->json(['message' => 'Failed to retrieve user info.'], 401);
        }

        $authUser = $userResponse->json();

        // Find existing user by auth_id, or match by email/name for migration
        $user = User::where('auth_id', $authUser['id'])->first();

        if (!$user) {
            $user = User::where('email', strtolower($authUser['email']))->first()
                ?? User::where('name', strtolower($authUser['username']))->first();

            if ($user) {
                // Link existing account to auth service
                $user->auth_id = $authUser['id'];
                $user->email = strtolower($authUser['email']);
            } else {
                // Create new local user
                $user = new User();
                $user->auth_id = $authUser['id'];
                $user->email = strtolower($authUser['email']);
                $user->email_verified_at = now();
                $user->username_chosen = false;
            }
        }

        // Only overwrite name from OAuth if user hasn't chosen their own
        if (!$user->username_chosen) {
            $user->name = strtolower($authUser['username']);
        }
        $user->avatar_url = $authUser['avatar_url'] ?? null;
        $user->refresh_token = $tokens['refresh_token'] ?? null;
        $user->save();

        // Register email with OneSignal for new users
        if ($user->wasRecentlyCreated) {
            app(OneSignalService::class)->registerEmail($user);

            // Process referral code
            $referralCode = $validated['referral_code'] ?? $request->input('referral_code');
            if ($referralCode) {
                $referrer = User::where('referral_code', strtoupper($referralCode))
                    ->where('id', '!=', $user->id)
                    ->first();
                if ($referrer) {
                    $user->referred_by = $referrer->id;
                    $user->save();
                }
            }
        }

        // Check if user is banned
        if ($user->banned_at) {
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        // Login and regenerate session
        Auth::login($user);
        $request->session()->regenerate();

        // Handle login streak
        $streakXp = 0;
        $now = Carbon::now();
        $lastLogin = $user->last_login_at;

        if (!$lastLogin || $lastLogin->lt($now->copy()->subDay()->startOfDay())) {
            $user->login_streak = 1;
        } elseif ($lastLogin->lt($now->copy()->startOfDay())) {
            $user->login_streak++;
            $streakXp = $user->login_streak * 10;
            $user->xp += $streakXp;
            $user->level = User::calculateLevel($user->xp);
        }

        if ($user->login_streak > $user->max_login_streak) {
            $user->max_login_streak = $user->login_streak;
        }
        $user->last_login_at = $now;
        $user->save();

        // Record login log
        LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => $now,
        ]);

        $response = $user->toArray();
        if ($streakXp > 0) {
            $response['streak_xp_awarded'] = $streakXp;
            $response['login_streak'] = $user->login_streak;
        }
        if (!$user->username_chosen) {
            $response['needs_username'] = true;
        }
        if ($user->needsAdvisors()) {
            $response['needs_advisors'] = true;
        }

        return response()->json($response);
    }

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

        $impersonatorId = $request->session()->get('impersonator_id');
        if ($impersonatorId) {
            $impersonator = User::find($impersonatorId);
            $data['is_impersonating'] = true;
            $data['impersonator_name'] = $impersonator?->name ?? 'Admin';
        }

        $data['payments_enabled'] = !empty(config('services.stripe.key'))
            || !empty(config('services.apple.shared_secret'))
            || !empty(config('services.google_play.package_name'));

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

        return response()->json($user->toArray());
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
