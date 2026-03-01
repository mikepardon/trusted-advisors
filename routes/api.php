<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameLobbyController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\Admin\AchievementController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\Admin\CharacterController;
use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\DailyChallengeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\GameRuleController;
use App\Http\Controllers\Admin\BotGameController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\SoundAssetController;
use App\Http\Controllers\Admin\UnlockableController;
use App\Http\Controllers\Admin\AddonController;
use App\Http\Controllers\Admin\GameManagementController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Public
Route::get('/characters', [GameController::class, 'characters']);

// Serve S3/Minio files through the app (works behind ngrok)
Route::get('/storage/{path}', function (string $path) {
    if (!Storage::disk('s3')->exists($path)) {
        abort(404);
    }
    return response(Storage::disk('s3')->get($path))
        ->header('Content-Type', Storage::disk('s3')->mimeType($path))
        ->header('Cache-Control', 'public, max-age=86400');
})->where('path', '.*');
Route::get('/sound-assets', [SoundAssetController::class, 'publicIndex']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);

// Auth required
Route::middleware('auth:web')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::post('/auth/push-subscribe', [AuthController::class, 'registerPushId']);
    Route::get('/auth/stats', [AuthController::class, 'stats']);

    Route::get('/games/history', [GameController::class, 'history']);
    Route::post('/games', [GameController::class, 'store']);
    Route::get('/games/{game}', [GameController::class, 'show']);
    Route::post('/games/{game}/start', [GameController::class, 'start']);
    Route::get('/games/{game}/hand/{playerNumber}', [GameController::class, 'hand']);
    Route::post('/games/{game}/assign-roles', [GameController::class, 'assignRoles']);
    Route::post('/games/{game}/resolve-round', [GameController::class, 'resolveRound']);
    Route::post('/games/{game}/next-round', [GameController::class, 'nextRound']);

    // Duel mode routes
    Route::post('/games/{game}/duel-offer', [GameController::class, 'duelOffer']);
    Route::post('/games/{game}/duel-choose', [GameController::class, 'duelChoose']);
    Route::post('/games/{game}/duel-roll', [GameController::class, 'duelRoll']);
    Route::get('/games/{game}/duel-hand/{playerNumber}', [GameController::class, 'duelHand']);

    // Cancel game
    Route::post('/games/{game}/cancel', [GameController::class, 'cancelGame']);

    // Game replay
    Route::get('/games/{game}/replay', [ReplayController::class, 'show']);

    // Leaderboards
    Route::get('/leaderboards/global', [LeaderboardController::class, 'global']);
    Route::get('/leaderboards/friends', [LeaderboardController::class, 'friends']);

    // Achievements & daily challenge for current user
    Route::get('/achievements', [GameController::class, 'achievements']);
    Route::post('/achievements/{achievement}/claim', [GameController::class, 'claimAchievement']);
    Route::get('/daily-challenge', [GameController::class, 'dailyChallenge']);
    Route::get('/seasons', [GameController::class, 'seasons']);

    Route::get('/users/{user}/profile', [UserProfileController::class, 'show']);

    Route::get('/friends', [FriendshipController::class, 'index']);
    Route::post('/friends', [FriendshipController::class, 'store']);
    Route::post('/friends/{friendship}/accept', [FriendshipController::class, 'accept']);
    Route::delete('/friends/{friendship}', [FriendshipController::class, 'destroy']);

    // Online lobby routes
    Route::post('/games/{game}/invite', [GameLobbyController::class, 'invite']);
    Route::post('/game-invites/{invite}/accept', [GameLobbyController::class, 'acceptInvite']);
    Route::post('/game-invites/{invite}/decline', [GameLobbyController::class, 'declineInvite']);
    Route::post('/games/{game}/select-character', [GameLobbyController::class, 'selectCharacter']);
    Route::get('/games/{game}/lobby', [GameLobbyController::class, 'lobbyStatus']);
    Route::get('/game-invites/pending', [GameLobbyController::class, 'myPendingInvites']);
});

// Admin CRUD routes
Route::prefix('admin')->middleware(['auth:web', 'admin'])->group(function () {
    Route::get('dashboard-stats', [DashboardController::class, 'stats']);
    Route::apiResource('characters', CharacterController::class);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('events', EventController::class);
    Route::apiResource('items', ItemController::class);
    Route::apiResource('seasons', SeasonController::class);
    Route::apiResource('achievements', AchievementController::class);
    Route::apiResource('unlockables', UnlockableController::class);
    Route::apiResource('daily-challenges', DailyChallengeController::class);
    Route::apiResource('addons', AddonController::class);
    Route::get('rules', [GameRuleController::class, 'index']);
    Route::put('rules/{key}', [GameRuleController::class, 'update']);
    Route::post('characters/{character}/image', [CharacterController::class, 'uploadImage']);
    Route::get('sound-assets', [SoundAssetController::class, 'index']);
    Route::post('sound-assets/{key}/upload', [SoundAssetController::class, 'upload']);
    Route::post('bot-simulate', [BotGameController::class, 'simulate']);
    Route::get('games', [GameManagementController::class, 'index']);
    Route::post('games/{game}/cancel', [GameManagementController::class, 'cancel']);
    Route::get('levels', [DashboardController::class, 'levels']);
});
