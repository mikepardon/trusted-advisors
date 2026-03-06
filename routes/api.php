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
use App\Http\Controllers\Admin\AdminDiceController;
use App\Http\Controllers\Admin\AdminKingdomStyleController;
use App\Http\Controllers\Admin\AiGeneratorController;
use App\Http\Controllers\Admin\CsvController;
use App\Http\Controllers\Admin\GameManagementController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\AdminGiftController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\RotatingEventController as AdminRotatingEventController;
use App\Http\Controllers\Admin\WeeklyChallengeController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\DddiceController;
use App\Http\Controllers\DiceController;
use App\Http\Controllers\KingdomStyleController;
use App\Http\Controllers\RotatingEventController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Webhooks (signature-verified, no CSRF)
Route::post('/webhooks/auth', [WebhookController::class, 'handleAuthWebhook'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->middleware(\App\Http\Middleware\VerifyWebhookSignature::class);

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

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
Route::get('/site-settings', [GameRuleController::class, 'siteSettings']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/callback', [AuthController::class, 'handleOAuthCallback']);

// Public replay
Route::get('/replays/{token}', [ReplayController::class, 'showPublic']);

// Auth required
Route::middleware('auth:web')->group(function () {
    Route::post('/impersonate/stop', [AdminUserController::class, 'stopImpersonating']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/set-username', [AuthController::class, 'setUsername']);
    Route::get('/auth/check-username/{username}', [AuthController::class, 'checkUsername']);
    Route::post('/auth/push-subscribe', [AuthController::class, 'registerPushId']);
    Route::get('/auth/stats', [AuthController::class, 'stats']);

    Route::get('/games/history', [GameController::class, 'history']);
    Route::get('/games/timeline', [GameController::class, 'timeline']);
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
    Route::post('/games/{game}/use-item', [GameController::class, 'useItem']);
    Route::post('/games/{game}/skip-item', [GameController::class, 'skipItem']);
    Route::post('/games/{game}/discard-item', [GameController::class, 'discardItem']);

    // Timeout check (nudges server to forfeit if timer expired)
    Route::post('/games/{game}/check-timeout', [GameController::class, 'checkTimeout']);
    Route::post('/games/{game}/report-timeout', [GameController::class, 'checkTimeout']); // legacy alias

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
    Route::get('/weekly-challenge', [GameController::class, 'weeklyChallenge']);
    Route::get('/seasons', [GameController::class, 'seasons']);
    Route::get('/seasons/{season}', [GameController::class, 'seasonDetail']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/{notification}/claim', [NotificationController::class, 'claim']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('/my-characters', [GameController::class, 'myCharacters']);
    Route::get('/users/{user}/profile', [UserProfileController::class, 'show']);

    // Coin shop
    Route::get('/shop', [CoinShopController::class, 'index']);
    Route::post('/shop/{unlockable}/purchase', [CoinShopController::class, 'purchase']);
    Route::post('/shop/{unlockable}/gift', [CoinShopController::class, 'gift']);
    Route::get('/coin-transactions', [CoinShopController::class, 'transactions']);

    // Announcements
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss']);

    // Referral
    Route::get('/referral/code', [ReferralController::class, 'getCode']);
    Route::get('/referral/stats', [ReferralController::class, 'stats']);
    Route::post('/referral/apply', [ReferralController::class, 'apply']);

    // Rotating events
    Route::get('/rotating-events', [RotatingEventController::class, 'index']);
    Route::get('/rotating-events/{rotatingEvent}', [RotatingEventController::class, 'show']);
    Route::get('/rotating-events/{rotatingEvent}/leaderboard', [RotatingEventController::class, 'leaderboard']);

    // dddice 3D dice
    Route::get('/dddice/guest-token', [DddiceController::class, 'guestToken']);

    // User dice themes
    Route::get('/my-dice', [DiceController::class, 'myDice']);
    Route::post('/my-dice/{diceTheme}/activate', [DiceController::class, 'activate']);

    // User kingdom styles
    Route::get('/my-kingdom-styles', [KingdomStyleController::class, 'myStyles']);
    Route::post('/my-kingdom-styles/{kingdomStyle}/activate', [KingdomStyleController::class, 'activate']);
    Route::post('/my-kingdom-styles/set-title', [KingdomStyleController::class, 'setTitle']);

    // Stats dashboard (premium-gated)
    Route::middleware('premium')->group(function () {
        Route::get('/stats/overview', [StatsController::class, 'overview']);
        Route::get('/stats/history', [StatsController::class, 'history']);
        Route::get('/stats/characters', [StatsController::class, 'characters']);
        Route::get('/games/custom-options', [GameController::class, 'customGameOptions']);
    });

    // Payments & Premium
    Route::get('/premium/price', [PaymentController::class, 'premiumPrice']);
    Route::get('/premium/status', [PaymentController::class, 'premiumStatus']);
    Route::post('/purchase/stripe/checkout', [PaymentController::class, 'stripeCheckout']);
    Route::post('/purchase/iap/verify', [PaymentController::class, 'iapVerify']);
    Route::post('/purchase/restore', [PaymentController::class, 'restore']);
    Route::get('/premium/manage', [PaymentController::class, 'managePremium']);
    Route::get('/premium/details', [PaymentController::class, 'subscriptionDetails']);
    Route::post('/premium/cancel', [PaymentController::class, 'cancelSubscription']);
    Route::get('/app-review/should-prompt', [PaymentController::class, 'shouldPromptReview']);
    Route::post('/app-review/prompted', [PaymentController::class, 'markReviewPrompted']);

    Route::get('/friends', [FriendshipController::class, 'index']);
    Route::post('/friends', [FriendshipController::class, 'store']);
    Route::post('/friends/{friendship}/accept', [FriendshipController::class, 'accept']);
    Route::delete('/friends/{friendship}', [FriendshipController::class, 'destroy']);

    // Online lobby routes
    Route::get('/games/lobbies', [GameLobbyController::class, 'publicLobbies']);
    Route::post('/games/{game}/invite', [GameLobbyController::class, 'invite']);
    Route::post('/games/{game}/join', [GameLobbyController::class, 'joinLobby']);
    Route::post('/game-invites/{invite}/accept', [GameLobbyController::class, 'acceptInvite']);
    Route::post('/game-invites/{invite}/decline', [GameLobbyController::class, 'declineInvite']);
    Route::post('/games/{game}/select-character', [GameLobbyController::class, 'selectCharacter']);
    Route::get('/games/{game}/lobby', [GameLobbyController::class, 'lobbyStatus']);
    Route::get('/game-invites/pending', [GameLobbyController::class, 'myPendingInvites']);

    // Tournaments
    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::get('/tournaments/mine', [TournamentController::class, 'mine']);
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
    Route::post('/tournaments/{tournament}/join', [TournamentController::class, 'join']);
    Route::post('/tournaments/{tournament}/start', [TournamentController::class, 'start']);
});

// Admin CRUD routes
Route::prefix('admin')->middleware(['auth:web', 'admin'])->group(function () {
    Route::get('dashboard-stats', [DashboardController::class, 'stats']);

    // CSV export/import (must be before apiResource to avoid {id} catch)
    Route::get('{type}/export-csv', [CsvController::class, 'export'])->where('type', 'characters|cards|events|items');
    Route::post('{type}/import-csv', [CsvController::class, 'import'])->where('type', 'characters|cards|events|items');

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
    Route::delete('homepage-background', [GameRuleController::class, 'removeHomepageBackground']);
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

    // Season end
    Route::post('seasons/{season}/end', [SeasonController::class, 'endSeason']);

    // Admin gifts
    Route::get('gifts', [AdminGiftController::class, 'index']);
    Route::post('gifts', [AdminGiftController::class, 'store']);

    // Announcements
    Route::apiResource('announcements', AdminAnnouncementController::class);

    // Rotating events
    Route::apiResource('rotating-events', AdminRotatingEventController::class);

    // Weekly challenges
    Route::post('weekly-challenges/generate', [WeeklyChallengeController::class, 'generateRange']);
    Route::apiResource('weekly-challenges', WeeklyChallengeController::class);

    // Dice themes
    Route::get('dice-themes', [AdminDiceController::class, 'index']);
    Route::put('dice-themes/{diceTheme}', [AdminDiceController::class, 'update']);
    Route::post('dice-themes/sync', [AdminDiceController::class, 'sync']);

    // Media library
    Route::apiResource('media-library', MediaLibraryController::class);
    Route::get('media-library-tags', [MediaLibraryController::class, 'tags']);

    // Kingdom styles
    Route::get('kingdom-styles', [AdminKingdomStyleController::class, 'index']);
    Route::put('kingdom-styles/{kingdomStyle}', [AdminKingdomStyleController::class, 'update']);
    Route::post('kingdom-styles/{kingdomStyle}/image', [AdminKingdomStyleController::class, 'uploadImage']);
    Route::delete('kingdom-styles/{kingdomStyle}/image', [AdminKingdomStyleController::class, 'removeImage']);

    // AI content generation
    Route::post('ai/generate-character', [AiGeneratorController::class, 'generateCharacter']);
    Route::post('ai/generate-card', [AiGeneratorController::class, 'generateCard']);
    Route::post('ai/generate-event', [AiGeneratorController::class, 'generateEvent']);

    // Payment management
    Route::get('subscribers', [AdminPaymentController::class, 'subscribers']);
    Route::get('purchases', [AdminPaymentController::class, 'purchases']);
    Route::get('payment-settings', [AdminPaymentController::class, 'settings']);
    Route::put('payment-settings', [AdminPaymentController::class, 'updateSettings']);
    Route::post('users/{user}/grant-premium', [AdminPaymentController::class, 'grantPremium']);
    Route::post('users/{user}/revoke-premium', [AdminPaymentController::class, 'revokePremium']);
});
