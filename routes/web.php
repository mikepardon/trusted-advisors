<?php

use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

// OAuth handshake with the MPGames auth service (server-side, session-backed)
Route::get('/auth/redirect', [OAuthController::class, 'redirect']);
Route::get('/auth/callback', [OAuthController::class, 'callback']);
Route::get('/auth/manage', [OAuthController::class, 'manage']);

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

Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');
