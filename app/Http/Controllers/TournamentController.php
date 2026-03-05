<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TournamentController extends Controller
{
    public function index(): JsonResponse
    {
        $tournaments = Tournament::where('status', 'setup')
            ->withCount('participants')
            ->with('creator:id,name')
            ->get()
            ->filter(fn ($t) => $t->participants_count < $t->max_players)
            ->values();

        return response()->json($tournaments);
    }

    public function mine(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $tournaments = Tournament::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->orWhere('creator_id', $userId)
            ->withCount('participants')
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($tournaments);
    }

    public function show(Tournament $tournament): JsonResponse
    {
        $tournament->load([
            'creator:id,name',
            'participants.user:id,name',
            'matches' => fn ($q) => $q->orderBy('bracket_round')->orderBy('match_number'),
            'matches.player1:id,name',
            'matches.player2:id,name',
            'matches.winner:id,name',
            'matches.game:id,status,winner_player_number',
        ]);

        return response()->json($tournament);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isPremium()) {
            return response()->json(['error' => 'Premium subscription required to create tournaments.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'max_players' => 'required|integer|in:4,8,16',
            'game_type' => 'sometimes|string|in:cooperative,duel',
            'total_rounds' => 'sometimes|integer|in:12,24,36,48,60',
            'custom_rules' => 'sometimes|array',
            'is_private' => 'sometimes|boolean',
            'password' => 'required_if:is_private,true|nullable|string|min:1',
        ]);

        $tournament = Tournament::create([
            'name' => $validated['name'],
            'creator_id' => $user->id,
            'status' => 'setup',
            'game_type' => $validated['game_type'] ?? 'duel',
            'max_players' => $validated['max_players'],
            'total_rounds' => $validated['total_rounds'] ?? 24,
            'custom_rules' => $validated['custom_rules'] ?? null,
            'is_private' => !empty($validated['is_private']),
            'lobby_password' => !empty($validated['is_private']) && isset($validated['password'])
                ? bcrypt($validated['password'])
                : null,
        ]);

        // Creator auto-joins
        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $user->id,
        ]);

        return response()->json($tournament->load('participants.user:id,name'), 201);
    }

    public function join(Tournament $tournament, Request $request): JsonResponse
    {
        if ($tournament->status !== 'setup') {
            return response()->json(['error' => 'Tournament is not accepting players.'], 422);
        }

        $userId = $request->user()->id;

        if ($tournament->participants()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'Already joined this tournament.'], 422);
        }

        if ($tournament->participants()->count() >= $tournament->max_players) {
            return response()->json(['error' => 'Tournament is full.'], 422);
        }

        // Password check for private tournaments
        if ($tournament->is_private && $tournament->lobby_password) {
            $request->validate(['password' => 'required|string']);
            if (!Hash::check($request->password, $tournament->lobby_password)) {
                return response()->json(['error' => 'Incorrect password.'], 403);
            }
        }

        TournamentParticipant::create([
            'tournament_id' => $tournament->id,
            'user_id' => $userId,
        ]);

        return response()->json([
            'joined' => true,
            'participants_count' => $tournament->participants()->count(),
        ]);
    }

    public function start(Tournament $tournament, Request $request): JsonResponse
    {
        if ($tournament->creator_id !== $request->user()->id) {
            return response()->json(['error' => 'Only the creator can start the tournament.'], 403);
        }

        if ($tournament->status !== 'setup') {
            return response()->json(['error' => 'Tournament already started.'], 422);
        }

        $participants = $tournament->participants()->with('user')->get();

        if ($participants->count() < 2) {
            return response()->json(['error' => 'Need at least 2 participants.'], 422);
        }

        // Seed players (by ELO if available, otherwise random)
        $sorted = $participants->sortByDesc(function ($p) {
            return $p->user->elo_rating ?? 1000;
        })->values();

        foreach ($sorted as $i => $p) {
            $p->update(['seed' => $i + 1]);
        }

        // Handle byes for non-power-of-2
        $playerCount = $sorted->count();
        $bracketSize = 1;
        while ($bracketSize < $playerCount) $bracketSize *= 2;
        $byeCount = $bracketSize - $playerCount;

        // Build first round matchups
        $seeded = $sorted->pluck('user_id')->toArray();
        // Pad with null for byes
        while (count($seeded) < $bracketSize) {
            $seeded[] = null;
        }

        // Standard bracket pairing: 1v(n), 2v(n-1), etc
        $matchups = [];
        $half = $bracketSize / 2;
        for ($i = 0; $i < $half; $i++) {
            $matchups[] = [
                'player1_id' => $seeded[$i],
                'player2_id' => $seeded[$bracketSize - 1 - $i],
            ];
        }

        // Create matches and games for round 1
        foreach ($matchups as $i => $matchup) {
            $matchRecord = TournamentMatch::create([
                'tournament_id' => $tournament->id,
                'bracket_round' => 1,
                'match_number' => $i + 1,
                'player1_id' => $matchup['player1_id'],
                'player2_id' => $matchup['player2_id'],
                'status' => 'pending',
            ]);

            // If one player is a bye (null), auto-advance
            if ($matchup['player1_id'] === null) {
                $matchRecord->update([
                    'winner_id' => $matchup['player2_id'],
                    'status' => 'completed',
                ]);
            } elseif ($matchup['player2_id'] === null) {
                $matchRecord->update([
                    'winner_id' => $matchup['player1_id'],
                    'status' => 'completed',
                ]);
            } else {
                // Create game for this match
                $game = Game::create([
                    'status' => 'setup',
                    'game_mode' => 'online',
                    'game_type' => $tournament->game_type,
                    'num_players' => 2,
                    'total_rounds' => $tournament->total_rounds,
                    'user_id' => $matchup['player1_id'],
                    'is_custom' => !empty($tournament->custom_rules),
                    'custom_rules' => $tournament->custom_rules,
                    'tournament_match_id' => $matchRecord->id,
                ]);

                // Add players to game
                GamePlayer::create([
                    'game_id' => $game->id,
                    'user_id' => $matchup['player1_id'],
                    'player_number' => 1,
                ]);
                GamePlayer::create([
                    'game_id' => $game->id,
                    'user_id' => $matchup['player2_id'],
                    'player_number' => 2,
                ]);

                $matchRecord->update([
                    'game_id' => $game->id,
                    'status' => 'in_progress',
                ]);
            }
        }

        $tournament->update([
            'status' => 'in_progress',
            'current_bracket_round' => 1,
            'started_at' => now(),
        ]);

        return response()->json($tournament->load([
            'matches' => fn ($q) => $q->orderBy('bracket_round')->orderBy('match_number'),
            'matches.player1:id,name',
            'matches.player2:id,name',
            'matches.game:id,status',
        ]));
    }

    public function advanceBracket(Tournament $tournament): void
    {
        $currentRound = $tournament->current_bracket_round;
        $matches = $tournament->matches()
            ->where('bracket_round', $currentRound)
            ->get();

        // Check if all matches in current round are completed
        if ($matches->contains(fn ($m) => $m->status !== 'completed')) {
            return;
        }

        $winners = $matches->pluck('winner_id')->filter()->values()->toArray();

        // If only one winner, tournament is complete
        if (count($winners) <= 1) {
            $tournament->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Set final placements
            if (count($winners) === 1) {
                TournamentParticipant::where('tournament_id', $tournament->id)
                    ->where('user_id', $winners[0])
                    ->update(['final_placement' => 1]);
            }

            // Losers from final round get 2nd place
            $losers = $matches->map(function ($m) {
                if ($m->winner_id === $m->player1_id) return $m->player2_id;
                return $m->player1_id;
            })->filter()->values();

            foreach ($losers as $loserId) {
                TournamentParticipant::where('tournament_id', $tournament->id)
                    ->where('user_id', $loserId)
                    ->whereNull('final_placement')
                    ->update(['final_placement' => 2, 'eliminated_at' => now()]);
            }

            return;
        }

        // Mark losers as eliminated
        foreach ($matches as $match) {
            $loserId = $match->winner_id === $match->player1_id ? $match->player2_id : $match->player1_id;
            if ($loserId) {
                $placement = count($winners) + 1; // Rough placement
                TournamentParticipant::where('tournament_id', $tournament->id)
                    ->where('user_id', $loserId)
                    ->whereNull('eliminated_at')
                    ->update(['eliminated_at' => now(), 'final_placement' => $placement]);
            }
        }

        // Generate next round matches
        $nextRound = $currentRound + 1;
        $halfCount = count($winners) / 2;

        for ($i = 0; $i < $halfCount; $i++) {
            $p1 = $winners[$i * 2] ?? null;
            $p2 = $winners[$i * 2 + 1] ?? null;

            $matchRecord = TournamentMatch::create([
                'tournament_id' => $tournament->id,
                'bracket_round' => $nextRound,
                'match_number' => $i + 1,
                'player1_id' => $p1,
                'player2_id' => $p2,
                'status' => 'pending',
            ]);

            // Auto-advance byes
            if (!$p1 && $p2) {
                $matchRecord->update(['winner_id' => $p2, 'status' => 'completed']);
            } elseif ($p1 && !$p2) {
                $matchRecord->update(['winner_id' => $p1, 'status' => 'completed']);
            } elseif ($p1 && $p2) {
                $game = Game::create([
                    'status' => 'setup',
                    'game_mode' => 'online',
                    'game_type' => $tournament->game_type,
                    'num_players' => 2,
                    'total_rounds' => $tournament->total_rounds,
                    'user_id' => $p1,
                    'is_custom' => !empty($tournament->custom_rules),
                    'custom_rules' => $tournament->custom_rules,
                    'tournament_match_id' => $matchRecord->id,
                ]);

                GamePlayer::create([
                    'game_id' => $game->id,
                    'user_id' => $p1,
                    'player_number' => 1,
                ]);
                GamePlayer::create([
                    'game_id' => $game->id,
                    'user_id' => $p2,
                    'player_number' => 2,
                ]);

                $matchRecord->update([
                    'game_id' => $game->id,
                    'status' => 'in_progress',
                ]);
            }
        }

        $tournament->update(['current_bracket_round' => $nextRound]);

        // Recurse in case all next-round matches were byes
        $this->advanceBracket($tournament->fresh());
    }
}
