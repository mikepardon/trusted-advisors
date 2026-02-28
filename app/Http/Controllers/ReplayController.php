<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\JsonResponse;

class ReplayController extends Controller
{
    public function show(Game $game): JsonResponse
    {
        if ($game->status !== 'completed') {
            return response()->json(['error' => 'Game is not completed'], 422);
        }

        $game->load([
            'players.character',
            'players.items.item',
            'roundResults.card',
            'roundResults.player.character',
            'playerKingdoms.player.character',
        ]);

        $roundResults = $game->roundResults->groupBy('round_number')->map(function ($results) {
            return $results->map(function ($result) {
                return [
                    'id' => $result->id,
                    'round_number' => $result->round_number,
                    'card' => $result->card,
                    'player' => $result->player,
                    'success' => $result->success,
                    'dice_results' => $result->dice_results,
                    'stat_totals' => $result->stat_totals,
                    'required' => $result->required,
                    'effects_applied' => $result->effects_applied,
                    'result_type' => $result->result_type,
                ];
            });
        });

        return response()->json([
            'game' => $game->makeHidden('roundResults'),
            'rounds' => $roundResults,
            'total_rounds_played' => $game->current_round,
        ]);
    }
}
