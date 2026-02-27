<?php

use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{gameId}', function ($user, $gameId) {
    $game = Game::find($gameId);
    if (!$game) return false;

    // Host can access
    if ($game->user_id === $user->id) return true;

    // Players with a user_id match can access
    return GamePlayer::where('game_id', $gameId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
