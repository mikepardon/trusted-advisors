<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\OneSignalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Handle the redirect back from the auth service: validate state, exchange the
 * code, provision/login the local user, then land the SPA on the right screen.
 *
 * Invokable single-action controller (not a FormRequest): the inputs come from
 * the auth provider, and validation failures must redirect to the login screen
 * rather than returning a 422, so the checks are done inline.
 */
final class HandleAuthCallback extends Controller
{
    public function __invoke(Request $request): RedirectResponse
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

        $tokenResponse = Http::asForm()->post("{$authConfig['url']}/oauth/token", [
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
            ->get("{$authConfig['url']}/api/user");

        if ($userResponse->failed()) {
            return redirect('/?auth_error=userinfo');
        }

        $authUser = $userResponse->json();
        $referralCode = $request->session()->pull('oauth_referral');

        $user = DB::transaction(fn (): User => $this->provisionUser($authUser, $tokens, is_string($referralCode) ? $referralCode : null));

        // Register email with OneSignal for new users (external, post-commit)
        if ($user->wasRecentlyCreated) {
            app(OneSignalService::class)->registerEmail($user);
        }

        if ($user->banned_at) {
            return redirect('/?auth_error=banned');
        }

        Auth::login($user);
        $request->session()->regenerate();

        DB::transaction(fn () => $this->recordLogin($user, $request));

        if (! $user->username_chosen) {
            return redirect('/choose-username');
        }

        if ($user->needsAdvisors()) {
            return redirect('/choose-advisors');
        }

        return redirect('/');
    }

    /**
     * Find, link or create the local user for the authenticated identity.
     *
     * @param  array<string, mixed>  $authUser
     * @param  array<string, mixed>  $tokens
     */
    private function provisionUser(array $authUser, array $tokens, ?string $referralCode): User
    {
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

        // Only overwrite name from OAuth if the user hasn't chosen their own
        if (! $user->username_chosen) {
            $user->name = strtolower($authUser['username']);
        }
        $user->avatar_url = $authUser['avatar_url'] ?? null;
        $user->refresh_token = $tokens['refresh_token'] ?? null;
        $user->save();

        if ($user->wasRecentlyCreated && $referralCode !== null && $referralCode !== '') {
            $referrer = User::where('referral_code', strtoupper($referralCode))
                ->where('id', '!=', $user->id)
                ->first();
            if ($referrer) {
                $user->referred_by = $referrer->id;
                $user->save();
            }
        }

        return $user;
    }

    /**
     * Track the daily login streak (for the header flame) and record the login.
     * Login streaks no longer award XP.
     */
    private function recordLogin(User $user, Request $request): void
    {
        $now = CarbonImmutable::now();
        $lastLogin = $user->last_login_at;

        if (! $lastLogin || $lastLogin->lt($now->subDay()->startOfDay())) {
            $user->login_streak = 1;
        } elseif ($lastLogin->lt($now->startOfDay())) {
            $user->login_streak++;
        }

        if ($user->login_streak > $user->max_login_streak) {
            $user->max_login_streak = $user->login_streak;
        }
        $user->last_login_at = $now;
        $user->save();

        LoginLog::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => $now,
        ]);
    }
}
