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

Route::get('/{any?}', function () {
    return view('welcome');
})->where('any', '.*');
