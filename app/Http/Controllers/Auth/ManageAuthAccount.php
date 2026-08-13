<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Send the user to the auth service's account dashboard. Kept server-side so the
 * frontend never needs the auth service URL.
 */
final class ManageAuthAccount extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $authConfig = config('services.mpgames_auth');

        return redirect("{$authConfig['url']}/dashboard?from=" . urlencode($request->getSchemeAndHttpHost()));
    }
}
