<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CoinShopController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameLobbyController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MatchmakingController;
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
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AiGeneratorController;
use App\Http\Controllers\Admin\GameManagementController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Webhooks (signature-verified, no CSRF)
Route::post('/webhooks/auth', [WebhookController::class, 'handleAuthWebhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->middleware(\App\Http\Middleware\VerifyWebhookSignature::class);

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
Route::post('/auth/callback', [AuthController::class, 'handleOAuthCallback']);

// Public replay
Route::get('/replays/{token}', [ReplayController::class, 'showPublic']);

// Auth required
Route::middleware('auth:web')->group(function () {
    Route::post('/impersonate/stop', [AdminUserController::class, 'stopImpersonating']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
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
    Route::post('/games/{game}/duel-select', [GameController::class, 'duelSelect']);
    Route::post('/games/{game}/duel-roll', [GameController::class, 'duelRoll']);
    Route::post('/games/{game}/duel-reroll', [GameController::class, 'duelReroll']);
    Route::get('/games/{game}/duel-hand/{playerNumber}', [GameController::class, 'duelHand']);

    // Opponent turn (duel)
    Route::post('/games/{game}/opponent-turn', [GameController::class, 'opponentTurn']);

    // Character ability
    Route::post('/games/{game}/use-ability', [GameController::class, 'useAbility']);

    // Item management
    Route::post('/games/{game}/discard-item', [GameController::class, 'discardItem']);

    // Cancel game
    Route::post('/games/{game}/cancel', [GameController::class, 'cancelGame']);

    // Game replay
    Route::get('/games/{game}/replay', [ReplayController::class, 'show']);
    Route::post('/games/{game}/share', [ReplayController::class, 'generateShareToken']);

    // Matchmaking
    Route::post('/matchmaking/join', [MatchmakingController::class, 'join']);
    Route::post('/matchmaking/leave', [MatchmakingController::class, 'leave']);
    Route::get('/matchmaking/status', [MatchmakingController::class, 'status']);

    // Leaderboards
    Route::get('/leaderboards/global', [LeaderboardController::class, 'global']);
    Route::get('/leaderboards/friends', [LeaderboardController::class, 'friends']);

    // Achievements & daily challenge for current user
    Route::get('/achievements', [GameController::class, 'achievements']);
    Route::post('/achievements/claim-all', [GameController::class, 'claimAllAchievements']);
    Route::post('/achievements/{achievement}/claim', [GameController::class, 'claimAchievement']);
    Route::get('/daily-challenge', [GameController::class, 'dailyChallenge']);
    Route::get('/seasons', [GameController::class, 'seasons']);
    Route::get('/seasons/{season}', [GameController::class, 'seasonDetail']);

    Route::get('/my-characters', [GameController::class, 'myCharacters']);
    Route::get('/users/{user}/profile', [UserProfileController::class, 'show']);

    // Coin shop
    Route::get('/shop', [CoinShopController::class, 'index']);
    Route::post('/shop/{unlockable}/purchase', [CoinShopController::class, 'purchase']);
    Route::get('/coin-transactions', [CoinShopController::class, 'transactions']);

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
    Route::get('seasons/{season}/rewards', [SeasonController::class, 'rewards']);
    Route::post('seasons/{season}/rewards', [SeasonController::class, 'storeReward']);
    Route::put('seasons/{season}/rewards/{reward}', [SeasonController::class, 'updateReward']);
    Route::delete('seasons/{season}/rewards/{reward}', [SeasonController::class, 'destroyReward']);
    Route::apiResource('achievements', AchievementController::class);
    Route::apiResource('unlockables', UnlockableController::class);
    Route::post('daily-challenges/generate', [DailyChallengeController::class, 'generateRange']);
    Route::apiResource('daily-challenges', DailyChallengeController::class);
    Route::apiResource('addons', AddonController::class);
    Route::get('rules', [GameRuleController::class, 'index']);
    Route::put('rules/{key}', [GameRuleController::class, 'update']);
    Route::post('characters/{character}/image', [CharacterController::class, 'uploadImage']);
    Route::get('sound-assets', [SoundAssetController::class, 'index']);
    Route::post('sound-assets/{key}/upload', [SoundAssetController::class, 'upload']);
    Route::post('bot-simulate', [BotGameController::class, 'simulate']);
    Route::post('bot-simulate-duel', [BotGameController::class, 'simulateDuel']);
    Route::get('games', [GameManagementController::class, 'index']);
    Route::post('games/{game}/cancel', [GameManagementController::class, 'cancel']);
    Route::get('levels', [DashboardController::class, 'levels']);
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::post('users/{user}/ban', [AdminUserController::class, 'ban']);
    Route::get('users/{user}/login-logs', [AdminUserController::class, 'loginLogs']);
    Route::post('users/{user}/impersonate', [AdminUserController::class, 'impersonate']);

    // AI content generation
    Route::post('ai/generate-character', [AiGeneratorController::class, 'generateCharacter']);
    Route::post('ai/generate-card', [AiGeneratorController::class, 'generateCard']);
    Route::post('ai/generate-event', [AiGeneratorController::class, 'generateEvent']);
});
