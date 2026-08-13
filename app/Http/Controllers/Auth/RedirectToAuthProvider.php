<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Kick off the OAuth handshake with the MPGames auth service. The PKCE verifier
 * and state live in the server session, so the frontend never needs the client
 * id, redirect URI or auth URL.
 *
 * Invokable single-action controller (not a FormRequest): the inputs are
 * optional query hints from our own UI, and the endpoint always issues a
 * redirect, so there is no user-submitted payload to validate/reject.
 */
final class RedirectToAuthProvider extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(128);

        $request->session()->put('oauth_state', $state);
        $request->session()->put('oauth_code_verifier', $codeVerifier);

        $referralCode = $request->string('ref')->trim()->toString();
        if ($referralCode !== '') {
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

        $provider = $request->string('provider')->trim()->toString();
        if ($provider !== '') {
            $params['provider'] = $provider;
        }

        return redirect("{$authConfig['url']}/oauth/authorize?" . http_build_query($params));
    }
}
