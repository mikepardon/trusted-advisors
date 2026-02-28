<?php

namespace App\Http\Controllers;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[a-zA-Z0-9]+$/', 'unique:users,name'],
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:4|confirmed',
        ], [
            'name.min' => 'Username must be at least 4 characters.',
            'name.max' => 'Username must be 20 characters or fewer.',
            'name.regex' => 'Username can only contain letters and numbers.',
        ]);

        $user = User::create([
            'name' => strtolower($validated['name']),
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
        ]);

        // Register email with OneSignal
        app(OneSignalService::class)->registerEmail($user);

        // Generate and send verification code
        $this->sendVerificationCode($user);

        return response()->json([
            'requires_verification' => true,
            'user_id' => $user->id,
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'code' => 'required|string|size:6',
        ]);

        $verification = EmailVerificationCode::where('user_id', $validated['user_id'])
            ->where('code', $validated['code'])
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $user = User::findOrFail($validated['user_id']);
        $user->update(['email_verified_at' => now()]);

        // Clean up used codes
        EmailVerificationCode::where('user_id', $user->id)->delete();

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json($user);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified.'], 422);
        }

        // Rate limit: check if a code was sent in the last 60 seconds
        $recent = EmailVerificationCode::where('user_id', $user->id)
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();

        if ($recent) {
            return response()->json(['message' => 'Please wait before requesting another code.'], 429);
        }

        $this->sendVerificationCode($user);

        return response()->json(['message' => 'Verification code sent.']);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
        ]);

        $input = $validated['name'];
        $loginField = str_contains($input, '@') ? 'email' : 'name';
        $loginValue = strtolower($input);

        if (!Auth::attempt([$loginField => $loginValue, 'password' => $validated['password']])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();

        // Check email verification
        if (!$user->email_verified_at) {
            $userId = $user->id;
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'requires_verification' => true,
                'user_id' => $userId,
            ], 403);
        }

        $request->session()->regenerate();

        // Handle login streak
        $streakXp = 0;
        $now = Carbon::now();
        $lastLogin = $user->last_login_at;

        if (!$lastLogin || $lastLogin->lt($now->copy()->subDay()->startOfDay())) {
            // No previous login or more than 1 day ago - reset streak
            $user->login_streak = 1;
        } elseif ($lastLogin->lt($now->copy()->startOfDay())) {
            // Last login was yesterday - increment streak
            $user->login_streak++;
            $streakXp = $user->login_streak * 10;
            $user->xp += $streakXp;
            $user->level = User::calculateLevel($user->xp);
        }
        // else: already logged in today, no change

        if ($user->login_streak > $user->max_login_streak) {
            $user->max_login_streak = $user->login_streak;
        }
        $user->last_login_at = $now;
        $user->save();

        $response = $user->toArray();
        if ($streakXp > 0) {
            $response['streak_xp_awarded'] = $streakXp;
            $response['login_streak'] = $user->login_streak;
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

    private function sendVerificationCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        $html = '<div style="font-family: Georgia, serif; max-width: 480px; margin: 0 auto; padding: 30px; background: #1a1209; color: #e8d5b0; border: 1px solid #6b5b3a; border-radius: 8px;">'
            . '<h2 style="color: #c9a84c; font-size: 1.4rem; text-align: center;">Trusted Advisors</h2>'
            . '<p style="text-align: center;">Your verification code is:</p>'
            . '<p style="text-align: center; font-size: 2rem; font-weight: bold; letter-spacing: 6px; color: #c9a84c;">' . $code . '</p>'
            . '<p style="text-align: center; font-size: 0.85rem; color: #a09080;">This code expires in 15 minutes.</p>'
            . '</div>';

        app(OneSignalService::class)->sendEmailToUser($user, 'Trusted Advisors - Verify Your Email', $html);
    }
}
