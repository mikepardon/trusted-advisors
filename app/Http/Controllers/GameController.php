<?php

namespace App\Http\Controllers;

use App\Events\GameStarted;
use App\Events\NextRoundStarted;
use App\Events\PlayerAssignedCards;
use App\Events\RoundResolved;
use App\Models\Card;
use App\Models\Character;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameCardDeck;
use App\Models\GameItemDeck;
use App\Models\GamePlayer;
use App\Models\GamePlayerHand;
use App\Models\GamePlayerItem;
use App\Models\GameRoundResult;
use App\Models\GameRule;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function characters(): JsonResponse
    {
        return response()->json(Character::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_mode' => 'required|string|in:single,pass_and_play,online',
            'num_players' => 'required|integer|min:1|max:6',
            'total_rounds' => 'sometimes|integer|in:12,24,36,48,60',
        ]);

        // Single player forces 1 player, pass_and_play/online require 2+
        if ($validated['game_mode'] === 'single' && $validated['num_players'] !== 1) {
            return response()->json(['error' => 'Single player mode requires exactly 1 player'], 422);
        }
        if (in_array($validated['game_mode'], ['pass_and_play', 'online']) && $validated['num_players'] < 2) {
            return response()->json(['error' => 'Multiplayer modes require at least 2 players'], 422);
        }

        $game = Game::create([
            'status' => 'setup',
            'game_mode' => $validated['game_mode'],
            'num_players' => $validated['num_players'],
            'total_rounds' => $validated['total_rounds'] ?? 24,
            'user_id' => $request->user()?->id,
        ]);

        // Online mode: auto-add host as player 1
        if ($validated['game_mode'] === 'online' && $request->user()) {
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $request->user()->id,
                'player_number' => 1,
            ]);
        }

        return response()->json($game, 201);
    }

    public function show(Game $game): JsonResponse
    {
        $game->load(['players.character', 'players.items.item']);

        $data = [
            'game' => $game,
            'game_mode' => $game->game_mode,
            'round_phase' => $game->round_phase,
            'current_round' => $game->current_round,
            'total_rounds' => $game->total_rounds,
        ];

        // For online games in setup, include lobby data
        if ($game->isOnline() && $game->status === 'setup') {
            $data['invites'] = $game->invites()->with(['sender', 'receiver'])->get();
            $data['lobby_players'] = $game->players()->with(['character', 'user'])->get();
        }

        // Get current event
        $event = $this->getCurrentEvent($game);
        $data['current_event'] = $event;

        // During selecting phase, include hand info (which players have assigned roles)
        if ($game->round_phase === 'selecting') {
            $hands = $game->playerHands()
                ->where('round_number', $game->current_round)
                ->get();

            $cardsPerPlayer = $this->getCardsPerPlayer($event);

            $playerStatus = [];
            foreach ($game->players as $player) {
                $playerHands = $hands->where('game_player_id', $player->id);
                $assignedCount = $playerHands->whereNotNull('role')->count();
                $playerStatus[] = [
                    'player_number' => $player->player_number,
                    'character_name' => $player->character->name,
                    'has_assigned' => $assignedCount >= $cardsPerPlayer,
                ];
            }
            $data['player_status'] = $playerStatus;
            $data['all_assigned'] = $this->allPlayersAssigned($game, $event);
            $data['cards_per_player'] = $cardsPerPlayer;
        }

        // During resolving phase, include round results
        if ($game->round_phase === 'resolving') {
            $results = $game->roundResults()
                ->where('round_number', $game->current_round)
                ->with(['card', 'player.character'])
                ->get();

            $data['round_results'] = $results;
        }

        return response()->json($data);
    }

    public function start(Game $game, Request $request): JsonResponse
    {
        if ($game->status !== 'setup') {
            return response()->json(['error' => 'Game already started'], 422);
        }

        $validated = $request->validate([
            'characters' => 'required|array|min:1|max:6',
            'characters.*' => 'required|integer|exists:characters,id',
        ]);

        if (count($validated['characters']) !== $game->num_players) {
            return response()->json(['error' => 'Must select exactly ' . $game->num_players . ' characters'], 422);
        }

        // Use worst-case cards per round (3 per player for altered_deal events)
        $maxCardsPerRound = $game->num_players * 3;
        $totalCardsNeeded = $maxCardsPerRound * $game->total_rounds;

        // For online mode, players already exist from lobby — just update character assignments
        if ($game->isOnline()) {
            $existingPlayers = $game->players()->orderBy('player_number')->get();
            foreach ($existingPlayers as $i => $player) {
                if (isset($validated['characters'][$i])) {
                    $player->update(['character_id' => $validated['characters'][$i]]);
                }
            }
        } else {
            // Create players for single/pass_and_play
            foreach ($validated['characters'] as $index => $characterId) {
                GamePlayer::create([
                    'game_id' => $game->id,
                    'character_id' => $characterId,
                    'player_number' => $index + 1,
                ]);
            }
        }

        // Create shuffled deck (recycle cards if more needed than available)
        $allCards = Card::inRandomOrder()->get();
        $cards = collect();
        while ($cards->count() < $totalCardsNeeded) {
            $cards = $cards->concat($allCards->shuffle());
        }
        $cards = $cards->take($totalCardsNeeded);
        foreach ($cards as $i => $card) {
            GameCardDeck::create([
                'game_id' => $game->id,
                'card_id' => $card->id,
                'position' => $i,
                'is_drawn' => false,
            ]);
        }

        // Create shuffled item deck (recycle items if needed)
        $allItems = Item::inRandomOrder()->get();
        $itemsNeeded = $game->total_rounds * 2; // generous estimate
        $itemPool = collect();
        while ($itemPool->count() < $itemsNeeded) {
            $itemPool = $itemPool->concat($allItems->shuffle());
        }
        $itemPool = $itemPool->take($itemsNeeded);
        foreach ($itemPool as $i => $item) {
            GameItemDeck::create([
                'game_id' => $game->id,
                'item_id' => $item->id,
                'position' => $i,
                'is_drawn' => false,
            ]);
        }

        $game->update([
            'status' => 'active',
            'current_round' => 1,
            'round_phase' => 'selecting',
        ]);

        // Deal first round
        $this->dealCardsForRound($game);

        if ($game->isOnline()) {
            broadcast(new GameStarted($game->id));
        }

        return $this->show($game->fresh());
    }

    /**
     * Get a specific player's hand for the current round.
     */
    public function hand(Game $game, int $playerNumber, Request $request): JsonResponse
    {
        if ($game->status !== 'active') {
            return response()->json(['error' => 'Game is not active'], 422);
        }

        $player = $game->players()->where('player_number', $playerNumber)->first();
        if (!$player) {
            return response()->json(['error' => 'Invalid player number'], 422);
        }

        // Online mode: verify authenticated user owns this player slot
        if ($game->isOnline()) {
            if (!$request->user() || $request->user()->id !== $player->user_id) {
                return response()->json(['error' => 'You cannot view another player\'s hand'], 403);
            }
        }

        $hands = GamePlayerHand::where('game_id', $game->id)
            ->where('game_player_id', $player->id)
            ->where('round_number', $game->current_round)
            ->with('card')
            ->get();

        $event = $this->getCurrentEvent($game);
        $cardsPerPlayer = $this->getCardsPerPlayer($event);

        return response()->json([
            'player_number' => $playerNumber,
            'round' => $game->current_round,
            'cards' => $hands->map(fn ($h) => [
                'hand_id' => $h->id,
                'card' => $h->card,
                'role' => $h->role,
            ]),
            'has_assigned' => $hands->whereNotNull('role')->count() >= $cardsPerPlayer,
            'cards_per_player' => $cardsPerPlayer,
        ]);
    }

    /**
     * Player assigns roles (positive/negative) to their cards.
     */
    public function assignRoles(Game $game, Request $request): JsonResponse
    {
        if ($game->status !== 'active' || $game->round_phase !== 'selecting') {
            return response()->json(['error' => 'Not in selecting phase'], 422);
        }

        $validated = $request->validate([
            'positive_hand_id' => 'required|integer|exists:game_player_hands,id',
            'negative_hand_ids' => 'required|array|min:1',
            'negative_hand_ids.*' => 'required|integer|exists:game_player_hands,id',
        ]);

        $positiveHand = GamePlayerHand::findOrFail($validated['positive_hand_id']);

        // Verify positive hand belongs to this game and current round
        if ($positiveHand->game_id !== $game->id || $positiveHand->round_number !== $game->current_round) {
            return response()->json(['error' => 'Invalid hand selection'], 422);
        }

        // Online mode: verify authenticated user owns these hand cards
        if ($game->isOnline()) {
            $player = GamePlayer::find($positiveHand->game_player_id);
            if (!$player || $player->user_id !== $request->user()->id) {
                return response()->json(['error' => 'You can only assign your own cards'], 403);
            }
        }

        $negativeHands = GamePlayerHand::whereIn('id', $validated['negative_hand_ids'])->get();

        // Verify all negative hands belong to same game, round, and player
        foreach ($negativeHands as $negHand) {
            if ($negHand->game_id !== $game->id || $negHand->round_number !== $game->current_round) {
                return response()->json(['error' => 'Invalid hand selection'], 422);
            }
            if ($negHand->game_player_id !== $positiveHand->game_player_id) {
                return response()->json(['error' => 'Cards must belong to the same player'], 422);
            }
            if ($negHand->id === $positiveHand->id) {
                return response()->json(['error' => 'Must assign different cards to each role'], 422);
            }
        }

        // Check if this player already assigned
        $event = $this->getCurrentEvent($game);
        $cardsPerPlayer = $this->getCardsPerPlayer($event);

        $playerHands = GamePlayerHand::where('game_id', $game->id)
            ->where('game_player_id', $positiveHand->game_player_id)
            ->where('round_number', $game->current_round)
            ->get();

        if ($playerHands->whereNotNull('role')->count() >= $cardsPerPlayer) {
            return response()->json(['error' => 'Player has already assigned roles'], 422);
        }

        // Use transaction for online mode to prevent race condition on last assign
        if ($game->isOnline()) {
            return DB::transaction(function () use ($game, $positiveHand, $negativeHands, $event) {
                // Lock the game row to prevent concurrent auto-resolve
                $game = Game::lockForUpdate()->find($game->id);

                // Assign roles
                $positiveHand->update(['role' => 'positive']);
                foreach ($negativeHands as $negHand) {
                    $negHand->update(['role' => 'negative']);
                }

                $allAssigned = $this->allPlayersAssigned($game, $event);
                $assigningPlayer = GamePlayer::find($positiveHand->game_player_id);

                broadcast(new PlayerAssignedCards(
                    $game->id,
                    $assigningPlayer->player_number,
                    $allAssigned,
                ));

                // Auto-resolve when all players have assigned
                if ($allAssigned) {
                    $resolveResponse = $this->resolveRound($game);
                    $resolveData = json_decode($resolveResponse->getContent(), true);
                    broadcast(new RoundResolved($game->id, $resolveData));

                    return response()->json([
                        'assigned' => true,
                        'all_assigned' => true,
                        'auto_resolved' => true,
                        'resolve_data' => $resolveData,
                    ]);
                }

                return response()->json([
                    'assigned' => true,
                    'all_assigned' => $allAssigned,
                ]);
            });
        }

        // Non-online: no transaction needed
        $positiveHand->update(['role' => 'positive']);
        foreach ($negativeHands as $negHand) {
            $negHand->update(['role' => 'negative']);
        }

        $game = $game->fresh();
        $allAssigned = $this->allPlayersAssigned($game, $event);

        return response()->json([
            'assigned' => true,
            'all_assigned' => $allAssigned,
        ]);
    }

    /**
     * Resolve the current round using cooperative dice system.
     */
    public function resolveRound(Game $game): JsonResponse
    {
        if ($game->status !== 'active' || $game->round_phase !== 'selecting') {
            return response()->json(['error' => 'Not ready to resolve'], 422);
        }

        $event = $this->getCurrentEvent($game);

        if (!$this->allPlayersAssigned($game, $event)) {
            return response()->json(['error' => 'Not all players have assigned roles'], 422);
        }

        $players = $game->players()->with(['character', 'items.item'])->get();

        // Get positive and negative role hands
        $positiveHands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->where('role', 'positive')
            ->with(['card', 'player.character'])
            ->get();

        $negativeHands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->where('role', 'negative')
            ->with(['card', 'player.character'])
            ->get();

        // === POSITIVE PHASE ===
        // Sum all positive cards' difficulty
        $totalDifficulty = $positiveHands->sum(fn ($h) => $h->card->difficulty);

        // Apply item difficulty modifiers
        foreach ($players as $player) {
            foreach ($player->items as $playerItem) {
                $effect = $playerItem->item->effect;
                $bonusType = $effect['bonus_type'] ?? '';
                if ($bonusType === 'difficulty_reduction') {
                    $totalDifficulty -= abs((int) ($effect['bonus_value'] ?? 0));
                } elseif ($bonusType === 'difficulty_increase') {
                    $totalDifficulty += abs((int) ($effect['bonus_value'] ?? 0));
                }
            }
        }
        $totalDifficulty = max(1, $totalDifficulty);

        // Check event reduce_dice for temporary dice reduction
        $tempDiceReduction = 0;
        if ($event && $event->mechanic === 'reduce_dice') {
            $tempDiceReduction = $event->mechanic_data['amount'] ?? 0;
        }

        // All players roll dice (respecting lost_dice and event reduce_dice)
        $diceResults = [];
        $totalRoll = 0;
        $wildTriggers = [];

        // Look up dice-per-player-count rule
        $diceRules = GameRule::getValue('dice_per_player_count', []);
        $playerCount = $players->count();
        $baseDice = $diceRules[(string) $playerCount] ?? 3;

        foreach ($players as $player) {
            $dice = $player->character->dice;
            $activeDice = $baseDice - $player->lost_dice - $tempDiceReduction;
            $activeDice = max(1, $activeDice);
            $playerRolls = [];

            foreach (array_slice($dice, 0, $activeDice) as $dieIndex => $die) {
                $faceIndex = random_int(0, 5);
                $face = $die[$faceIndex];
                $playerRolls[] = [
                    'die' => $dieIndex + 1,
                    'face' => $face,
                    'face_index' => $faceIndex,
                    'value' => $face === 'WILD' ? $player->character->wild_value : (int) $face,
                ];

                if ($face === 'WILD') {
                    $wildValue = $player->character->wild_value;
                    $totalRoll += $wildValue;
                    $wildTriggers[] = [
                        'player_number' => $player->player_number,
                        'character_name' => $player->character->name,
                        'wild_value' => $wildValue,
                        'ability' => $player->character->wild_ability,
                        'ability_description' => $player->character->wild_ability_description,
                    ];
                } else {
                    $totalRoll += (int) $face;
                }
            }

            // Apply item roll bonuses and penalties
            foreach ($player->items as $playerItem) {
                $effect = $playerItem->item->effect;
                $bonusType = $effect['bonus_type'] ?? '';
                if ($bonusType === 'roll_bonus' || $bonusType === 'roll_penalty') {
                    $totalRoll += (int) ($effect['bonus_value'] ?? 0);
                }
            }

            $diceResults[] = [
                'player_number' => $player->player_number,
                'character_name' => $player->character->name,
                'rolls' => $playerRolls,
                'active_dice' => $activeDice,
                'lost_dice' => $player->lost_dice,
            ];
        }

        // Process wild abilities
        $abilityEffects = $this->processWildAbilities($wildTriggers, $totalRoll, $diceResults, $players);
        $totalRoll = $abilityEffects['adjusted_total'];

        // Determine positive phase success
        $positiveSuccess = $totalRoll >= $totalDifficulty;

        // Collect positive stat effects
        $positiveEffects = [];
        $itemGrants = [];
        $specialEffects = [];

        if ($positiveSuccess) {
            foreach ($positiveHands as $hand) {
                $effects = $hand->card->positive_effects ?? [];
                foreach ($effects as $stat => $change) {
                    if ($stat === 'grant_item_id') {
                        $itemGrants[] = ['item_id' => $change, 'player_id' => $hand->game_player_id];
                        continue;
                    }
                    if ($stat === 'draw_item') {
                        // Draw the next item from the deck
                        $drawnItem = $this->drawItemFromDeck($game);
                        if ($drawnItem) {
                            GamePlayerItem::create([
                                'game_player_id' => $hand->game_player_id,
                                'item_id' => $drawnItem->id,
                                'acquired_round' => $game->current_round,
                                'is_cursed' => false,
                            ]);
                            $playerChar = $hand->player->character->name;
                            $specialEffects[] = [
                                'type' => 'draw_item',
                                'phase' => 'positive',
                                'player' => $playerChar,
                                'item' => $drawnItem->name,
                                'is_negative' => $drawnItem->is_negative,
                                'description' => "{$playerChar} found {$drawnItem->name}!",
                            ];
                        }
                        continue;
                    }
                    if ($stat === 'remove_curse') {
                        // Remove a cursed item from a random player
                        $playersWithCurses = $players->filter(function ($p) {
                            return $p->items->contains(fn ($pi) => $pi->is_cursed);
                        });
                        if ($playersWithCurses->isNotEmpty()) {
                            $target = $playersWithCurses->random();
                            $cursedItem = $target->items->filter(fn ($pi) => $pi->is_cursed)->random();
                            $itemName = $cursedItem->item->name;
                            $charName = $target->character->name;
                            $cursedItem->delete();
                            $specialEffects[] = [
                                'type' => 'remove_curse',
                                'phase' => 'positive',
                                'player' => $charName,
                                'item' => $itemName,
                                'description' => "{$charName} was freed from {$itemName}!",
                            ];
                        } else {
                            $specialEffects[] = [
                                'type' => 'remove_curse',
                                'phase' => 'positive',
                                'player' => null,
                                'description' => 'No cursed items to remove.',
                            ];
                        }
                        continue;
                    }
                    if ($stat === 'recover_die') {
                        // Find a player with lost_dice > 0 and recover one
                        $recoverable = $players->filter(fn ($p) => $p->lost_dice > 0)->first();
                        if ($recoverable) {
                            $recoverable->decrement('lost_dice');
                            $charName = $recoverable->character->name;
                            $specialEffects[] = [
                                'type' => 'recover_die',
                                'phase' => 'positive',
                                'player' => $charName,
                                'description' => "{$charName} recovered a lost die!",
                            ];
                        } else {
                            $specialEffects[] = [
                                'type' => 'recover_die',
                                'phase' => 'positive',
                                'player' => null,
                                'description' => 'No advisors had lost dice to recover.',
                            ];
                        }
                        continue;
                    }
                    $positiveEffects[$stat] = ($positiveEffects[$stat] ?? 0) + $change;
                }
            }
        }

        // === NEGATIVE PHASE ===
        $negativeEffects = [];
        foreach ($negativeHands as $hand) {
            $effects = $hand->card->negative_effects ?? [];
            foreach ($effects as $stat => $change) {
                if ($stat === 'lose_die') {
                    // Pick a random player and increment their lost_dice (cap at 2)
                    $target = $players->random();
                    if ($target->lost_dice < 2) {
                        $target->increment('lost_dice');
                        $charName = $target->character->name;
                        $specialEffects[] = [
                            'type' => 'lose_die',
                            'phase' => 'negative',
                            'player' => $charName,
                            'description' => "{$charName} lost a die from exhaustion!",
                        ];
                    }
                    continue;
                }
                if ($stat === 'draw_item') {
                    // Negative phase: draw item from deck, marked as cursed
                    $drawnItem = $this->drawItemFromDeck($game);
                    if ($drawnItem) {
                        // Pick a random player to receive the cursed item
                        $target = $players->random();
                        GamePlayerItem::create([
                            'game_player_id' => $target->id,
                            'item_id' => $drawnItem->id,
                            'acquired_round' => $game->current_round,
                            'is_cursed' => true,
                        ]);
                        $charName = $target->character->name;
                        $specialEffects[] = [
                            'type' => 'draw_item',
                            'phase' => 'negative',
                            'player' => $charName,
                            'item' => $drawnItem->name,
                            'is_cursed' => true,
                            'description' => "{$charName} received a cursed {$drawnItem->name}!",
                        ];
                    }
                    continue;
                }
                if ($stat === 'discard_item') {
                    // Find a random player who has non-cursed items and discard one
                    $playersWithItems = $players->filter(function ($p) {
                        return $p->items->contains(fn ($pi) => !$pi->is_cursed);
                    });
                    if ($playersWithItems->isNotEmpty()) {
                        $target = $playersWithItems->random();
                        $item = $target->items->filter(fn ($pi) => !$pi->is_cursed)->random();
                        $itemName = $item->item->name;
                        $charName = $target->character->name;
                        $item->delete();
                        $specialEffects[] = [
                            'type' => 'discard_item',
                            'phase' => 'negative',
                            'player' => $charName,
                            'item' => $itemName,
                            'description' => "{$charName} lost {$itemName}!",
                        ];
                    }
                    continue;
                }
                $negativeEffects[$stat] = ($negativeEffects[$stat] ?? 0) + $change;
            }
        }

        // Compute game_after_positive (only positive stat effects applied)
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $gameAfterPositive = [];
        foreach ($stats as $stat) {
            $val = $game->{$stat} + ($positiveEffects[$stat] ?? 0);
            $gameAfterPositive[$stat] = max(0, min(20, $val));
        }

        // Combine all stat effects for final
        $combinedEffects = [];
        foreach ($stats as $stat) {
            $total = ($positiveEffects[$stat] ?? 0) + ($negativeEffects[$stat] ?? 0);
            if ($total !== 0) {
                $combinedEffects[$stat] = $total;
            }
        }

        // Apply combined effects to game (cap 0-30)
        foreach ($stats as $stat) {
            if (isset($combinedEffects[$stat])) {
                $game->{$stat} = max(0, min(20, $game->{$stat} + $combinedEffects[$stat]));
            }
        }

        // Process item grants from positive effects (grant_item_id style)
        foreach ($itemGrants as $grant) {
            $item = Item::find($grant['item_id']);
            if ($item) {
                GamePlayerItem::create([
                    'game_player_id' => $grant['player_id'],
                    'item_id' => $item->id,
                    'acquired_round' => $game->current_round,
                ]);
            }
        }

        // Apply current event stat modifier
        $eventEffects = [];
        if ($event && $event->mechanic === 'stat_modifier' && $event->stat_modifiers) {
            if (!$positiveSuccess) {
                foreach ($event->stat_modifiers as $stat => $change) {
                    if ($change < 0) {
                        $eventEffects[$stat] = $change;
                        $game->{$stat} = max(0, min(20, $game->{$stat} + $change));
                    }
                }
            }
            if ($positiveSuccess) {
                foreach ($event->stat_modifiers as $stat => $change) {
                    if ($change > 0) {
                        $eventEffects[$stat] = $change;
                        $game->{$stat} = max(0, min(20, $game->{$stat} + $change));
                    }
                }
            }
        }

        $game->round_phase = 'resolving';
        $game->save();

        // Compute game_after_negative (final stats)
        $gameAfterNegative = $game->fresh()->only($stats);

        // Store combined round result
        GameRoundResult::create([
            'game_id' => $game->id,
            'round_number' => $game->current_round,
            'success' => $positiveSuccess,
            'result_type' => 'combined',
            'dice_results' => $diceResults,
            'stat_totals' => ['total_roll' => $totalRoll, 'total_difficulty' => $totalDifficulty],
            'effects_applied' => $combinedEffects,
            'cards_included' => [
                'positive' => $positiveHands->pluck('card_id')->toArray(),
                'negative' => $negativeHands->pluck('card_id')->toArray(),
            ],
            'wild_triggers' => $wildTriggers,
        ]);

        // Check game over
        $gameOverReason = $game->checkStatBounds();

        return response()->json([
            'positive_phase' => [
                'cards' => $positiveHands->map(fn ($h) => [
                    'card' => $h->card,
                    'player_number' => $h->player->player_number,
                    'character_name' => $h->player->character->name,
                ]),
                'total_difficulty' => $totalDifficulty,
                'total_roll' => $totalRoll,
                'dice_results' => $diceResults,
                'wild_triggers' => $wildTriggers,
                'ability_effects' => $abilityEffects['descriptions'],
                'success' => $positiveSuccess,
                'effects' => $positiveSuccess ? $positiveEffects : [],
            ],
            'negative_phase' => [
                'cards' => $negativeHands->map(fn ($h) => [
                    'card' => $h->card,
                    'player_number' => $h->player->player_number,
                    'character_name' => $h->player->character->name,
                ]),
                'effects' => $negativeEffects,
            ],
            'combined_effects' => $combinedEffects,
            'event_effects' => $eventEffects,
            'item_grants' => $itemGrants,
            'special_effects' => $specialEffects,
            'game_after_positive' => $gameAfterPositive,
            'game_after_negative' => $gameAfterNegative,
            'game_over' => $gameOverReason !== null,
            'game_over_reason' => $gameOverReason,
            'game' => $game->fresh(),
        ]);
    }

    /**
     * Advance to the next round or end the game.
     */
    public function nextRound(Game $game, Request $request): JsonResponse
    {
        if ($game->status !== 'active' || $game->round_phase !== 'resolving') {
            return response()->json(['error' => 'Not in resolving phase'], 422);
        }

        // Online mode: only host can advance
        if ($game->isOnline() && $request->user()->id !== $game->user_id) {
            return response()->json(['error' => 'Only the host can advance the round'], 403);
        }

        // Check game over conditions
        $gameOverReason = $game->checkStatBounds();
        if ($gameOverReason) {
            $game->update([
                'status' => 'completed',
                'round_phase' => 'complete',
                'win' => false,
            ]);

            return response()->json([
                'game_over' => true,
                'win' => false,
                'reason' => $gameOverReason,
                'game' => $game->fresh(),
            ]);
        }

        // Check if we've completed all rounds
        if ($game->current_round >= $game->total_rounds) {
            $game->update([
                'status' => 'completed',
                'round_phase' => 'complete',
                'win' => true,
            ]);

            return response()->json([
                'game_over' => true,
                'win' => true,
                'reason' => 'You survived all ' . $game->total_rounds . ' rounds!',
                'game' => $game->fresh(),
            ]);
        }

        // Advance to next round
        $game->current_round++;
        $game->round_phase = 'selecting';
        $game->save();

        // Check if the new round's event grants items
        $event = $this->getCurrentEvent($game);
        if ($event && $event->mechanic === 'grant_items') {
            $players = $game->players()->get();
            foreach ($players as $player) {
                $drawnItem = $this->drawItemFromDeck($game);
                if ($drawnItem) {
                    GamePlayerItem::create([
                        'game_player_id' => $player->id,
                        'item_id' => $drawnItem->id,
                        'acquired_round' => $game->current_round,
                        'is_cursed' => false,
                    ]);
                }
            }
        }

        // Deal cards for next round
        $this->dealCardsForRound($game);

        $showResponse = $this->show($game->fresh());

        if ($game->isOnline()) {
            $showData = json_decode($showResponse->getContent(), true);
            broadcast(new NextRoundStarted($game->id, $showData));
        }

        return $showResponse;
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Include games where user is host OR a participant
        $participantGameIds = GamePlayer::where('user_id', $userId)->pluck('game_id');

        $activeGames = Game::where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->whereIn('status', ['setup', 'active'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($game) => [
                'id' => $game->id,
                'status' => $game->status,
                'game_mode' => $game->game_mode,
                'current_round' => $game->current_round,
                'total_rounds' => $game->total_rounds,
                'num_players' => $game->num_players,
            ]);

        $completedGames = Game::where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->where('status', 'completed')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($game) => [
                'id' => $game->id,
                'win' => $game->win,
                'game_mode' => $game->game_mode,
                'score' => $game->wealth + $game->influence + $game->security + $game->religion + $game->food + $game->happiness,
                'num_players' => $game->num_players,
                'rounds_survived' => $game->current_round,
                'total_rounds' => $game->total_rounds,
                'played_at' => $game->updated_at->toDateTimeString(),
            ]);

        return response()->json([
            'active_games' => $activeGames,
            'completed_games' => $completedGames,
        ]);
    }

    // --- Private helpers ---

    private function getCurrentEvent(Game $game): ?Event
    {
        $eventIndex = (int) floor(($game->current_round - 1) / 7);
        return Event::skip($eventIndex)->first();
    }

    private function getCardsPerPlayer(?Event $event): int
    {
        if ($event && $event->mechanic === 'altered_deal') {
            $positive = $event->mechanic_data['positive_cards'] ?? 1;
            $negative = $event->mechanic_data['negative_cards'] ?? 1;
            return $positive + $negative;
        }
        return 2;
    }

    private function allPlayersAssigned(Game $game, ?Event $event): bool
    {
        $cardsPerPlayer = $this->getCardsPerPlayer($event);
        $expectedCount = $game->num_players * $cardsPerPlayer;
        $assignedCount = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->whereNotNull('role')
            ->count();

        return $assignedCount >= $expectedCount;
    }

    private function dealCardsForRound(Game $game): void
    {
        $players = $game->players()->orderBy('player_number')->get();
        $event = $this->getCurrentEvent($game);
        $cardsPerPlayer = $this->getCardsPerPlayer($event);
        $cardsPerRound = $game->num_players * $cardsPerPlayer;

        // Pull next N undrawn cards from the deck
        $deckCards = $game->cardDeck()
            ->where('is_drawn', false)
            ->orderBy('position')
            ->limit($cardsPerRound)
            ->get();

        // Mark them as drawn
        foreach ($deckCards as $dc) {
            $dc->update(['is_drawn' => true]);
        }

        // Deal cards per player
        $cardIndex = 0;
        foreach ($players as $player) {
            for ($i = 0; $i < $cardsPerPlayer; $i++) {
                if (isset($deckCards[$cardIndex])) {
                    GamePlayerHand::create([
                        'game_id' => $game->id,
                        'game_player_id' => $player->id,
                        'card_id' => $deckCards[$cardIndex]->card_id,
                        'round_number' => $game->current_round,
                    ]);
                    $cardIndex++;
                }
            }
        }
    }

    /**
     * Draw the next undrawn item from the game's item deck.
     */
    private function drawItemFromDeck(Game $game): ?Item
    {
        $deckEntry = $game->itemDeck()
            ->where('is_drawn', false)
            ->orderBy('position')
            ->first();

        if (!$deckEntry) {
            return null;
        }

        $deckEntry->update(['is_drawn' => true]);

        return Item::find($deckEntry->item_id);
    }

    /**
     * Process wild ability triggers and return adjusted roll total.
     */
    private function processWildAbilities(array $wildTriggers, int $totalRoll, array &$diceResults, $players): array
    {
        $adjustedTotal = $totalRoll;
        $descriptions = [];

        foreach ($wildTriggers as $trigger) {
            $ability = $trigger['ability'] ?? '';
            $playerName = $trigger['character_name'];

            switch ($ability) {
                case 'inspire':
                    // +1 per player
                    $bonus = $players->count();
                    $adjustedTotal += $bonus;
                    $descriptions[] = "{$playerName}'s Inspire: +{$bonus} (1 per player)";
                    break;

                case 'rally':
                    // Reroll the lowest die (simulated: add +2)
                    $adjustedTotal += 2;
                    $descriptions[] = "{$playerName}'s Rally: rerolled lowest die (+2)";
                    break;

                case 'divine':
                    // WILD counts double (add wild_value again)
                    $bonus = $trigger['wild_value'];
                    $adjustedTotal += $bonus;
                    $descriptions[] = "{$playerName}'s Divine: WILD counts double (+{$bonus})";
                    break;

                case 'gamble':
                    // Reroll all dice (simulated: random -3 to +5)
                    $bonus = random_int(-3, 5);
                    $adjustedTotal += $bonus;
                    $sign = $bonus >= 0 ? '+' : '';
                    $descriptions[] = "{$playerName}'s Gamble: rerolled all dice ({$sign}{$bonus})";
                    break;

                case 'shadow':
                    // Flavor ability - peek at a card
                    $descriptions[] = "{$playerName}'s Shadow: glimpsed the future (no roll effect)";
                    break;

                case 'wisdom':
                    // +2 to total
                    $adjustedTotal += 2;
                    $descriptions[] = "{$playerName}'s Wisdom: +2 to total";
                    break;
            }
        }

        return [
            'adjusted_total' => $adjustedTotal,
            'descriptions' => $descriptions,
        ];
    }
}
