<?php

use Illuminate\Support\Facades\Route;

// Deep linking well-known files (must be above catch-all)
Route::get('/.well-known/assetlinks.json', function () {
    return response()->file(public_path('.well-known/assetlinks.json'), [
        'Content-Type' => 'application/json',
    ]);
});

Route::get('/.well-known/apple-app-site-association', function () {
    return response()->file(public_path('.well-known/apple-app-site-association'), [
        'Content-Type' => 'application/json',
    ]);
});

// OAuth callback - redirect mobile browsers back to the native app
Route::get('/auth/callback', function (\Illuminate\Http\Request $request) {
    if ($request->has('code')) {
        $ua = $request->userAgent();
        $isMobile = preg_match('/iPhone|iPad|Android/i', $ua);
        $isWebView = str_contains($ua, 'wtn') || str_contains($ua, 'WebToNative');

        if ($isMobile && !$isWebView) {
            $query = http_build_query($request->only(['code', 'state']));
            $appUrl = 'ta://open/auth/callback?' . $query;

            return response(
                '<html><head><meta name="viewport" content="width=device-width"></head>'
                . '<body style="background:#0d0a06;color:#f0e0c8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0">'
                . '<div style="text-align:center">'
                . '<p>Opening app...</p>'
                . '<p style="margin-top:16px"><a href="' . e($appUrl) . '" style="color:#f0c050">Tap here if the app didn\'t open</a></p>'
                . '</div></body></html>'
                . '<script>window.location.href=' . json_encode($appUrl) . ';</script>'
            );
        }
    }

    return view('welcome');
});

Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');
