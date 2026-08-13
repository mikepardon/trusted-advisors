<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class OAuthController extends Controller
{
    /**
     * Kick off the OAuth handshake with the MPGames auth service. The PKCE
     * verifier and state live in the server session, so the frontend never
     * needs the client id, redirect URI or auth URL.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(128);

        $request->session()->put('oauth_state', $state);
        $request->session()->put('oauth_code_verifier', $codeVerifier);

        $referralCode = $request->query('ref');
        if (is_string($referralCode) && $referralCode !== '') {
            $request->session()->put('oauth_referral', $referralCode);
        }

        $codeChallenge = strtr(mb_rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');

        $authConfig = config('services.mpgames_auth');

        $params = [
            'client_id' => $authConfig['client_id'],
            'redirect_uri' => $authConfig['redirect_uri'],
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'login',
        ];

        $provider = $request->query('provider');
        if (is_string($provider) && $provider !== '') {
            $params['provider'] = $provider;
        }

        return redirect($authConfig['url'] . '/oauth/authorize?' . http_build_query($params));
    }

    /**
     * Send the user to the auth service's account dashboard. Kept server-side
     * so the frontend never needs the auth service URL.
     */
    public function manage(Request $request): RedirectResponse
    {
        $authConfig = config('services.mpgames_auth');

        return redirect($authConfig['url'] . '/dashboard?from=' . urlencode($request->getSchemeAndHttpHost()));
    }

    /**
     * Handle the redirect back from the auth service: validate state, exchange
     * the code, provision/login the local user, then land the SPA on the right
     * screen.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->query('error') !== null) {
            return redirect('/?auth_error=denied');
        }

        if ($request->query('state') !== $request->session()->pull('oauth_state')) {
            return redirect('/?auth_error=state');
        }

        $codeVerifier = $request->session()->pull('oauth_code_verifier');
        $code = $request->query('code');

        if (! is_string($code) || ! is_string($codeVerifier)) {
            return redirect('/?auth_error=invalid');
        }

        $authConfig = config('services.mpgames_auth');

        $tokenResponse = Http::asForm()->post($authConfig['url'] . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $authConfig['client_id'],
            'redirect_uri' => $authConfig['redirect_uri'],
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);

        if ($tokenResponse->failed()) {
            return redirect('/?auth_error=token');
        }

        $tokens = $tokenResponse->json();

        $userResponse = Http::withToken($tokens['access_token'])
            ->get($authConfig['url'] . '/api/user');

        if ($userResponse->failed()) {
            return redirect('/?auth_error=userinfo');
        }

        $authUser = $userResponse->json();

        // Find existing user by auth_id, or match by email/name for migration
        $user = User::where('auth_id', $authUser['id'])->first();

        if (! $user) {
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
        if (! $user->username_chosen) {
            $user->name = strtolower($authUser['username']);
        }
        $user->avatar_url = $authUser['avatar_url'] ?? null;
        $user->refresh_token = $tokens['refresh_token'] ?? null;
        $user->save();

        // Register email with OneSignal for new users
        if ($user->wasRecentlyCreated) {
            app(OneSignalService::class)->registerEmail($user);

            // Process referral code captured before the redirect
            $referralCode = $request->session()->pull('oauth_referral');
            if (is_string($referralCode) && $referralCode !== '') {
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
            return redirect('/?auth_error=banned');
        }

        // Login and regenerate session
        Auth::login($user);
        $request->session()->regenerate();

        // Handle login streak
        $streakXp = 0;
        $now = Carbon::now();
        $lastLogin = $user->last_login_at;

        if (! $lastLogin || $lastLogin->lt($now->copy()->subDay()->startOfDay())) {
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

        // Surface the streak toast on the next /api/auth/me call (read-once)
        if ($streakXp > 0) {
            $request->session()->put('pending_streak_notification', [
                'streak' => $user->login_streak,
                'xp' => $streakXp,
            ]);
        }

        if (! $user->username_chosen) {
            return redirect('/choose-username');
        }

        if ($user->needsAdvisors()) {
            return redirect('/choose-advisors');
        }

        return redirect('/');
    }
}
