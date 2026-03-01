<?php

namespace App\Http\Controllers;

use App\Events\DuelChoiceMade;
use App\Events\DuelOfferMade;
use App\Events\DuelRollComplete;
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
use App\Models\GamePlayerKingdom;
use App\Models\GameRoundResult;
use App\Models\GameRule;
use App\Models\Achievement;
use App\Models\DailyChallenge;
use App\Models\DailyChallengeEntry;
use App\Models\Item;
use App\Models\Season;
use App\Models\Unlockable;
use App\Models\UserAchievement;
use App\Models\User;
use App\Models\UserUnlockable;
use App\Services\BotService;
use App\Services\GameCompletionService;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function characters(Request $request): JsonResponse
    {
        $characters = Character::all();
        $userId = $request->user()?->id;

        // Attach lock info: a character is locked if an unlockable exists for it
        $charUnlockables = Unlockable::where('type', 'character')->get()->keyBy('entity_id');
        $userUnlockableIds = $userId
            ? UserUnlockable::where('user_id', $userId)->pluck('unlockable_id')->toArray()
            : [];

        $characters = $characters->map(function ($c) use ($charUnlockables, $userUnlockableIds) {
            $data = $c->toArray();
            $unlockable = $charUnlockables[$c->id] ?? null;
            $data['is_locked_for_user'] = false;
            $data['unlock_requirement'] = null;

            if ($unlockable) {
                $isUnlocked = in_array($unlockable->id, $userUnlockableIds);
                $data['is_locked_for_user'] = !$isUnlocked;
                if (!$isUnlocked) {
                    $data['unlock_requirement'] = $unlockable->unlock_method === 'level'
                        ? "Reach level {$unlockable->unlock_value}"
                        : "Earn required achievement";
                }
            }

            return $data;
        });

        return response()->json($characters);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_mode' => 'required|string|in:single,pass_and_play,online',
            'num_players' => 'required|integer|min:1|max:6',
            'total_rounds' => 'sometimes|integer|in:12,24,36,48,60',
            'game_type' => 'sometimes|string|in:cooperative,duel',
            'bot_difficulty' => 'sometimes|string|in:easy,medium,hard',
        ]);

        $gameType = $validated['game_type'] ?? 'cooperative';

        // Single player forces 1 player (unless duel, which gets a bot), pass_and_play/online require 2+
        if ($validated['game_mode'] === 'single' && $validated['num_players'] !== 1 && $gameType !== 'duel') {
            return response()->json(['error' => 'Single player mode requires exactly 1 player'], 422);
        }
        if (in_array($validated['game_mode'], ['pass_and_play', 'online']) && $validated['num_players'] < 2) {
            return response()->json(['error' => 'Multiplayer modes require at least 2 players'], 422);
        }

        // Duel mode requires exactly 2 players (single player will get a bot as player 2)
        if ($gameType === 'duel') {
            if ($validated['game_mode'] === 'single') {
                $validated['num_players'] = 2; // Bot will be player 2
            }
            if ($validated['num_players'] !== 2) {
                return response()->json(['error' => 'Duel mode requires exactly 2 players'], 422);
            }
        }

        // Assign active season if applicable
        $seasonId = null;
        $activeSeason = Season::active()->first();
        if ($activeSeason && now()->between($activeSeason->starts_at, $activeSeason->ends_at)) {
            $seasonId = $activeSeason->id;
        }

        $game = Game::create([
            'status' => 'setup',
            'game_mode' => $validated['game_mode'],
            'game_type' => $gameType,
            'num_players' => $validated['num_players'],
            'total_rounds' => $validated['total_rounds'] ?? 24,
            'user_id' => $request->user()?->id,
            'season_id' => $seasonId,
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
            'game_type' => $game->game_type ?? 'cooperative',
            'round_phase' => $game->round_phase,
            'current_round' => $game->current_round,
            'total_rounds' => $game->total_rounds,
        ];

        // Duel-specific data
        if ($game->isDuel()) {
            $data['offerer_player_number'] = $game->offerer_player_number;
            $data['duel_phase'] = $game->duel_phase;
            $data['player_kingdoms'] = $game->playerKingdoms()->with('player.character')->get();
        }

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
            'bot_difficulty' => 'sometimes|string|in:easy,medium,hard',
        ]);

        // Single-player duel: only 1 character needed (bot gets assigned in player creation below)
        $expectedChars = ($game->game_mode === 'single' && $game->isDuel()) ? 1 : $game->num_players;
        if (count($validated['characters']) !== $expectedChars) {
            return response()->json(['error' => 'Must select exactly ' . $expectedChars . ' character(s)'], 422);
        }

        // Enforce unlock restrictions for the logged-in user
        $userId = $request->user()->id;
        $lockedUnlockables = Unlockable::where('type', 'character')
            ->whereIn('entity_id', $validated['characters'])
            ->get();

        if ($lockedUnlockables->isNotEmpty()) {
            $userUnlockableIds = UserUnlockable::where('user_id', $userId)
                ->pluck('unlockable_id')
                ->toArray();

            foreach ($lockedUnlockables as $unlockable) {
                if (!in_array($unlockable->id, $userUnlockableIds)) {
                    $character = Character::find($unlockable->entity_id);
                    $name = $character?->name ?? "Character #{$unlockable->entity_id}";
                    return response()->json(['error' => "{$name} is locked. Reach the required level or achievement to unlock it."], 422);
                }
            }
        }

        // Duel mode: size deck as 2 cards per round (no events)
        if ($game->isDuel()) {
            $totalCardsNeeded = 2 * $game->total_rounds;
        } else {
            // Use worst-case cards per round (3 per player for altered_deal events)
            $maxCardsPerRound = $game->num_players * 3;
            $totalCardsNeeded = $maxCardsPerRound * $game->total_rounds;
        }

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
                $playerData = [
                    'game_id' => $game->id,
                    'character_id' => $characterId,
                    'player_number' => $index + 1,
                ];
                // Assign the authenticated user to player 1 so they receive XP/rewards
                if ($index === 0 && $request->user()) {
                    $playerData['user_id'] = $request->user()->id;
                }
                GamePlayer::create($playerData);
            }

            // Single-player duel: create bot as player 2
            if ($game->game_mode === 'single' && $game->isDuel()) {
                $botDifficulty = $request->input('bot_difficulty', 'medium');
                // Pick a random character not chosen by the player
                $usedCharacterIds = $validated['characters'];
                $botCharacter = Character::whereNotIn('id', $usedCharacterIds)->inRandomOrder()->first()
                    ?? Character::inRandomOrder()->first();
                GamePlayer::create([
                    'game_id' => $game->id,
                    'character_id' => $botCharacter->id,
                    'player_number' => 2,
                    'is_bot' => true,
                    'bot_difficulty' => $botDifficulty,
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

        // Duel mode: skip item deck
        if (!$game->isDuel()) {
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
        }

        // Shuffle event order per game so every game is unique
        $eventOrder = Event::pluck('id')->shuffle()->values()->toArray();

        if ($game->isDuel()) {
            $game->update([
                'status' => 'active',
                'current_round' => 1,
                'round_phase' => 'selecting',
                'offerer_player_number' => 1,
                'duel_phase' => 'offering',
                'event_order' => $eventOrder,
            ]);

            // Refresh players relationship (just created/updated above)
            $game->load('players');

            // Create per-player kingdoms
            foreach ($game->players as $player) {
                GamePlayerKingdom::create([
                    'game_id' => $game->id,
                    'game_player_id' => $player->id,
                ]);
            }

            // Deal duel cards for first round
            $this->dealDuelCardsForRound($game);
        } else {
            $game->update([
                'status' => 'active',
                'current_round' => 1,
                'round_phase' => 'selecting',
                'event_order' => $eventOrder,
            ]);

            // Deal first round
            $this->dealCardsForRound($game);
        }

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

        $items = GamePlayerItem::where('game_player_id', $player->id)
            ->with('item')
            ->get();

        // Calculate active dice count
        $tempDiceReduction = 0;
        if ($event && $event->mechanic === 'reduce_dice') {
            $tempDiceReduction = $event->mechanic_data['amount'] ?? 0;
        }
        $diceRules = GameRule::getValue('dice_per_player_count', []);
        $playerCount = $game->players()->count();
        $baseDice = $diceRules[(string) $playerCount] ?? 3;
        $diceCount = max(1, $baseDice - $player->lost_dice - $tempDiceReduction);

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
            'items' => $items,
            'dice_count' => $diceCount,
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

        // Apply item difficulty modifiers (only active/non-used items)
        $itemModifiers = [];
        foreach ($players as $player) {
            foreach ($player->items->where('is_used', false) as $playerItem) {
                $effect = $playerItem->item->effect;
                $bonusType = $effect['bonus_type'] ?? '';
                if ($bonusType === 'difficulty_reduction') {
                    $value = abs((int) ($effect['bonus_value'] ?? 0));
                    $totalDifficulty -= $value;
                    $itemModifiers[] = [
                        'item_name' => $playerItem->item->name,
                        'type' => 'difficulty_reduction',
                        'value' => $value,
                        'player' => $player->character->name,
                    ];
                } elseif ($bonusType === 'difficulty_increase') {
                    $value = abs((int) ($effect['bonus_value'] ?? 0));
                    $totalDifficulty += $value;
                    $itemModifiers[] = [
                        'item_name' => $playerItem->item->name,
                        'type' => 'difficulty_increase',
                        'value' => $value,
                        'player' => $player->character->name,
                    ];
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

            // Apply item roll bonuses and penalties (only active/non-used items)
            foreach ($player->items->where('is_used', false) as $playerItem) {
                $effect = $playerItem->item->effect;
                $bonusType = $effect['bonus_type'] ?? '';
                if ($bonusType === 'roll_bonus' || $bonusType === 'roll_penalty') {
                    $value = (int) ($effect['bonus_value'] ?? 0);
                    $totalRoll += $value;
                    $itemModifiers[] = [
                        'item_name' => $playerItem->item->name,
                        'type' => $bonusType,
                        'value' => $value,
                        'player' => $player->character->name,
                    ];
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

        // Apply manually activated abilities
        foreach ($players as $p) {
            if ($p->ability_active_this_round) {
                $p->load('character');
                $manualAbility = $this->applyManualAbility($p, $totalRoll);
                $totalRoll = $manualAbility['adjusted_total'];
                $abilityEffects['descriptions'] = array_merge($abilityEffects['descriptions'], $manualAbility['descriptions']);
                $p->update(['ability_active_this_round' => false]);
            }
        }

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
                            $isConsumed = $drawnItem->is_consumable;
                            $immediateDesc = null;
                            $playerObj = $players->firstWhere('id', $hand->game_player_id);
                            if ($isConsumed && $playerObj) {
                                $immediateDesc = $this->applyImmediateItemEffect($game, $playerObj, $drawnItem);
                            }
                            GamePlayerItem::create([
                                'game_player_id' => $hand->game_player_id,
                                'item_id' => $drawnItem->id,
                                'acquired_round' => $game->current_round,
                                'is_cursed' => false,
                                'is_used' => $isConsumed,
                            ]);
                            $playerChar = $hand->player->character->name;
                            $effect = [
                                'type' => 'draw_item',
                                'phase' => 'positive',
                                'player' => $playerChar,
                                'item' => $drawnItem->name,
                                'is_negative' => $drawnItem->is_negative,
                                'description' => "{$playerChar} found {$drawnItem->name}!",
                                'is_consumable' => $isConsumed,
                            ];
                            if ($isConsumed && $immediateDesc) {
                                $effect['type'] = 'immediate_item';
                                $effect['immediate_description'] = $immediateDesc;
                                $effect['description'] .= " ({$immediateDesc})";
                            }
                            $specialEffects[] = $effect;
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
        // On failed roll, ALL cards' negative effects apply (positive cards included)
        $handsForNegativePhase = !$positiveSuccess ? $negativeHands->merge($positiveHands) : $negativeHands;

        $negativeEffects = [];
        foreach ($handsForNegativePhase as $hand) {
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
                        $isConsumed = $drawnItem->is_consumable;
                        $immediateDesc = null;
                        if ($isConsumed) {
                            $immediateDesc = $this->applyImmediateItemEffect($game, $target, $drawnItem);
                        }
                        GamePlayerItem::create([
                            'game_player_id' => $target->id,
                            'item_id' => $drawnItem->id,
                            'acquired_round' => $game->current_round,
                            'is_cursed' => true,
                            'is_used' => $isConsumed,
                        ]);
                        $charName = $target->character->name;
                        $effect = [
                            'type' => 'draw_item',
                            'phase' => 'negative',
                            'player' => $charName,
                            'item' => $drawnItem->name,
                            'is_cursed' => true,
                            'description' => "{$charName} received a cursed {$drawnItem->name}!",
                            'is_consumable' => $isConsumed,
                        ];
                        if ($isConsumed && $immediateDesc) {
                            $effect['type'] = 'immediate_item';
                            $effect['immediate_description'] = $immediateDesc;
                            $effect['description'] .= " ({$immediateDesc})";
                        }
                        $specialEffects[] = $effect;
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
                $grantPlayer = $players->firstWhere('id', $grant['player_id']);
                $isConsumed = $item->is_consumable;
                $immediateDesc = null;
                if ($isConsumed && $grantPlayer) {
                    $immediateDesc = $this->applyImmediateItemEffect($game, $grantPlayer, $item);
                }
                GamePlayerItem::create([
                    'game_player_id' => $grant['player_id'],
                    'item_id' => $item->id,
                    'acquired_round' => $game->current_round,
                    'is_used' => $isConsumed,
                ]);
                if ($grantPlayer) {
                    $playerChar = $grantPlayer->character->name;
                    $effect = [
                        'type' => 'draw_item',
                        'phase' => 'positive',
                        'player' => $playerChar,
                        'item' => $item->name,
                        'is_negative' => $item->is_negative ?? false,
                        'description' => "{$playerChar} received {$item->name}!",
                        'is_consumable' => $isConsumed,
                    ];
                    if ($isConsumed && $immediateDesc) {
                        $effect['type'] = 'immediate_item';
                        $effect['immediate_description'] = $immediateDesc;
                        $effect['description'] .= " ({$immediateDesc})";
                    }
                    $specialEffects[] = $effect;
                }
            }
        }

        // Apply current event stat modifier (always applies each round)
        $eventEffects = [];
        if ($event && $event->stat_modifiers) {
            foreach ($event->stat_modifiers as $stat => $change) {
                if (in_array($stat, $stats) && is_numeric($change)) {
                    $eventEffects[$stat] = $change;
                    $game->{$stat} = max(0, min(20, $game->{$stat} + $change));
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
                'negative' => $handsForNegativePhase->pluck('card_id')->toArray(),
            ],
            'wild_triggers' => $wildTriggers,
            'special_effects' => $specialEffects,
            'kingdom_snapshot' => $game->fresh()->only($stats),
            'event_data' => $event ? ['id' => $event->id, 'name' => $event->name, 'description' => $event->description] : null,
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
                'item_modifiers' => $itemModifiers,
                'success' => $positiveSuccess,
                'effects' => $positiveSuccess ? $positiveEffects : [],
            ],
            'negative_phase' => [
                'cards' => $handsForNegativePhase->map(fn ($h) => [
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
            'player_items' => $game->players()->with('items.item')->get()->mapWithKeys(fn ($p) => [
                $p->player_number => $p->items,
            ]),
            'items_over_limit' => $this->getPlayersOverItemLimit($game),
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

        // === DUEL MODE ===
        if ($game->isDuel()) {
            return $this->nextRoundDuel($game);
        }

        // === COOPERATIVE MODE ===
        // Check game over conditions
        $gameOverReason = $game->checkStatBounds();
        if ($gameOverReason) {
            $game->update([
                'status' => 'completed',
                'round_phase' => 'complete',
                'win' => false,
            ]);

            $completionSummary = app(GameCompletionService::class)->processCompletion($game);

            return response()->json([
                'game_over' => true,
                'win' => false,
                'reason' => $gameOverReason,
                'game' => $game->fresh(),
                'completion' => $completionSummary,
            ]);
        }

        // Check if we've completed all rounds
        if ($game->current_round >= $game->total_rounds) {
            $game->update([
                'status' => 'completed',
                'round_phase' => 'complete',
                'win' => true,
            ]);

            $completionSummary = app(GameCompletionService::class)->processCompletion($game);

            return response()->json([
                'game_over' => true,
                'win' => true,
                'reason' => 'You survived all ' . $game->total_rounds . ' rounds!',
                'game' => $game->fresh(),
                'completion' => $completionSummary,
            ]);
        }

        // Advance to next round
        $game->current_round++;
        $game->round_phase = 'selecting';
        $game->save();

        // Reset ability flags for new round
        $game->players()->update(['ability_active_this_round' => false]);

        // Check if the new round's event grants items
        $event = $this->getCurrentEvent($game);
        if ($event && $event->mechanic === 'grant_items') {
            $players = $game->players()->get();
            foreach ($players as $player) {
                $drawnItem = $this->drawItemFromDeck($game);
                if ($drawnItem) {
                    $isConsumed = $drawnItem->is_consumable;
                    if ($isConsumed) {
                        $this->applyImmediateItemEffect($game, $player, $drawnItem);
                    }
                    GamePlayerItem::create([
                        'game_player_id' => $player->id,
                        'item_id' => $drawnItem->id,
                        'acquired_round' => $game->current_round,
                        'is_cursed' => false,
                        'is_used' => $isConsumed,
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
            $this->notifyTurn($game, 'Round ' . $game->current_round . ' has started — pick your cards!');
        }

        return $showResponse;
    }

    private function nextRoundDuel(Game $game): JsonResponse
    {
        $kingdoms = $game->playerKingdoms()->with('player')->get();

        // Check both kingdoms for win/loss
        foreach ($kingdoms as $kingdom) {
            $result = $game->checkDuelStatBounds($kingdom);
            if ($result === 'win') {
                $game->update([
                    'status' => 'completed',
                    'round_phase' => 'complete',
                    'winner_player_number' => $kingdom->player->player_number,
                ]);
                $completionSummary = app(GameCompletionService::class)->processCompletion($game);
                return response()->json([
                    'game_over' => true,
                    'winner_player_number' => $kingdom->player->player_number,
                    'reason' => 'Player ' . $kingdom->player->player_number . ' achieved 3 stats at maximum!',
                    'game' => $game->fresh(),
                    'player_kingdoms' => $kingdoms,
                    'completion' => $completionSummary,
                ]);
            }
            if ($result === 'loss') {
                $winnerNumber = $kingdom->player->player_number === 1 ? 2 : 1;
                $game->update([
                    'status' => 'completed',
                    'round_phase' => 'complete',
                    'winner_player_number' => $winnerNumber,
                ]);
                $completionSummary = app(GameCompletionService::class)->processCompletion($game);
                return response()->json([
                    'game_over' => true,
                    'winner_player_number' => $winnerNumber,
                    'reason' => 'Player ' . $kingdom->player->player_number . '\'s kingdom collapsed!',
                    'game' => $game->fresh(),
                    'player_kingdoms' => $kingdoms,
                    'completion' => $completionSummary,
                ]);
            }
        }

        // Check if all rounds completed — tiebreaker by total score
        if ($game->current_round >= $game->total_rounds) {
            $k1 = $kingdoms->firstWhere('game_player_id', $game->players->firstWhere('player_number', 1)->id);
            $k2 = $kingdoms->firstWhere('game_player_id', $game->players->firstWhere('player_number', 2)->id);
            $score1 = $k1->totalScore();
            $score2 = $k2->totalScore();
            $winnerNumber = $score1 >= $score2 ? 1 : 2;

            $game->update([
                'status' => 'completed',
                'round_phase' => 'complete',
                'winner_player_number' => $winnerNumber,
            ]);

            $completionSummary = app(GameCompletionService::class)->processCompletion($game);

            return response()->json([
                'game_over' => true,
                'winner_player_number' => $winnerNumber,
                'reason' => "Campaign complete! Player {$winnerNumber} wins with the stronger kingdom.",
                'game' => $game->fresh(),
                'player_kingdoms' => $kingdoms,
                'completion' => $completionSummary,
            ]);
        }

        // Advance to next round, swap offerer
        $game->current_round++;
        $game->round_phase = 'selecting';
        $game->duel_phase = 'offering';
        $game->offerer_player_number = $game->offerer_player_number === 1 ? 2 : 1;
        $game->save();

        // Reset ability flags for new round
        $game->players()->update(['ability_active_this_round' => false]);

        // Deal duel cards
        $this->dealDuelCardsForRound($game);

        $showResponse = $this->show($game->fresh());

        if ($game->isOnline()) {
            $showData = json_decode($showResponse->getContent(), true);
            broadcast(new NextRoundStarted($game->id, $showData));
            $this->notifyPlayer($game, $game->offerer_player_number, 'Round ' . $game->current_round . ' — you\'re offering cards!');
        }

        return $showResponse;
    }

    // =============================
    // DUEL MODE ENDPOINTS
    // =============================

    /**
     * Get player-appropriate card view for duel mode.
     */
    public function duelHand(Game $game, int $playerNumber, Request $request): JsonResponse
    {
        if (!$game->isDuel() || $game->status !== 'active') {
            return response()->json(['error' => 'Not an active duel game'], 422);
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

        $hands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->with('card')
            ->get();

        $offererNumber = $game->offerer_player_number;
        $chooserNumber = $offererNumber === 1 ? 2 : 1;
        $isOfferer = $playerNumber === $offererNumber;

        $cards = [];

        if ($game->duel_phase === 'offering' && $isOfferer) {
            // Offerer sees both cards, full details
            foreach ($hands as $h) {
                $cards[] = [
                    'hand_id' => $h->id,
                    'card' => $h->card,
                    'revealed' => $h->revealed,
                ];
            }
        } elseif ($game->duel_phase === 'choosing' && $playerNumber === $chooserNumber) {
            // Chooser sees 1 revealed card full, 1 hidden (hand_id only)
            foreach ($hands as $h) {
                if ($h->revealed) {
                    $cards[] = [
                        'hand_id' => $h->id,
                        'card' => $h->card,
                        'revealed' => true,
                    ];
                } else {
                    $cards[] = [
                        'hand_id' => $h->id,
                        'card' => null,
                        'revealed' => false,
                    ];
                }
            }
        } elseif (in_array($game->duel_phase, ['rolling_offerer', 'rolling_chooser', 'resolving'])) {
            // Each player sees their own card
            $myHand = $hands->firstWhere('game_player_id', $player->id);
            if ($myHand) {
                $cards[] = [
                    'hand_id' => $myHand->id,
                    'card' => $myHand->card,
                    'revealed' => true,
                ];
            }
        }

        $items = GamePlayerItem::where('game_player_id', $player->id)
            ->with('item')
            ->get();

        // Calculate active dice count for duel (base 3, no event reduction)
        $diceCount = max(1, 3 - $player->lost_dice);

        return response()->json([
            'player_number' => $playerNumber,
            'round' => $game->current_round,
            'duel_phase' => $game->duel_phase,
            'is_offerer' => $isOfferer,
            'cards' => $cards,
            'items' => $items,
            'dice_count' => $diceCount,
        ]);
    }

    /**
     * Offerer reveals one card and offers the pair.
     */
    public function duelOffer(Game $game, Request $request): JsonResponse
    {
        if (!$game->isDuel() || $game->duel_phase !== 'offering') {
            return response()->json(['error' => 'Not in offering phase'], 422);
        }

        $validated = $request->validate([
            'revealed_hand_id' => 'required|integer|exists:game_player_hands,id',
        ]);

        $offerer = $game->getOfferer();
        $chooser = $game->getChooser();

        // Online mode: verify authenticated user is the offerer
        if ($game->isOnline()) {
            if (!$request->user() || $request->user()->id !== $offerer->user_id) {
                return response()->json(['error' => 'Only the offerer can make an offer'], 403);
            }
        }

        $hands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->where('game_player_id', $offerer->id)
            ->get();

        $revealedHand = $hands->firstWhere('id', $validated['revealed_hand_id']);
        if (!$revealedHand) {
            return response()->json(['error' => 'Invalid hand selection'], 422);
        }

        // Mark the revealed card and set offered_to_player_id on both
        foreach ($hands as $hand) {
            $hand->update([
                'revealed' => $hand->id === $revealedHand->id,
                'offered_to_player_id' => $chooser->id,
            ]);
        }

        $game->update(['duel_phase' => 'choosing']);

        if ($game->isOnline()) {
            broadcast(new DuelOfferMade(
                $game->id,
                $game->offerer_player_number,
                $revealedHand->id,
                'choosing',
            ));
            $chooserNumber = $game->offerer_player_number === 1 ? 2 : 1;
            $this->notifyPlayer($game, $chooserNumber, 'Your rival has revealed a card — choose wisely!');
        }

        return response()->json([
            'success' => true,
            'duel_phase' => 'choosing',
        ]);
    }

    /**
     * Chooser picks one of the two cards.
     */
    public function duelChoose(Game $game, Request $request): JsonResponse
    {
        if (!$game->isDuel() || $game->duel_phase !== 'choosing') {
            return response()->json(['error' => 'Not in choosing phase'], 422);
        }

        $validated = $request->validate([
            'chosen_hand_id' => 'required|integer|exists:game_player_hands,id',
        ]);

        $offerer = $game->getOfferer();
        $chooser = $game->getChooser();

        // Online mode: verify authenticated user is the chooser
        if ($game->isOnline()) {
            if (!$request->user() || $request->user()->id !== $chooser->user_id) {
                return response()->json(['error' => 'Only the chooser can choose'], 403);
            }
        }

        $hands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->get();

        $chosenHand = $hands->firstWhere('id', $validated['chosen_hand_id']);
        if (!$chosenHand) {
            return response()->json(['error' => 'Invalid hand selection'], 422);
        }

        // Reassign: chooser gets the chosen card, offerer gets the other
        foreach ($hands as $hand) {
            if ($hand->id === $chosenHand->id) {
                $hand->update(['game_player_id' => $chooser->id]);
            } else {
                $hand->update(['game_player_id' => $offerer->id]);
            }
        }

        // Online (real players): both roll simultaneously; bot games + local: sequential
        $hasBotPlayer = $game->players()->where('is_bot', true)->exists();
        $rollingPhase = ($game->isOnline() && !$hasBotPlayer) ? 'rolling' : 'rolling_offerer';
        $game->update(['duel_phase' => $rollingPhase]);

        // Reload hands with updated assignments
        $updatedHands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->with('card')
            ->get();

        $chooserCard = $updatedHands->firstWhere('game_player_id', $chooser->id);
        $offererCard = $updatedHands->firstWhere('game_player_id', $offerer->id);

        if ($game->isOnline()) {
            broadcast(new DuelChoiceMade(
                $game->id,
                $chooser->player_number,
                $chooserCard ? ['hand_id' => $chooserCard->id, 'card' => $chooserCard->card] : null,
                $offererCard ? ['hand_id' => $offererCard->id, 'card' => $offererCard->card] : null,
                $rollingPhase,
            ));
            $this->notifyPlayer($game, $game->offerer_player_number, 'Cards chosen — time to roll your dice!');
            $this->notifyPlayer($game, $chooser->player_number, 'Cards chosen — time to roll your dice!');
        }

        return response()->json([
            'success' => true,
            'duel_phase' => $rollingPhase,
            'chooser_card' => $chooserCard ? ['hand_id' => $chooserCard->id, 'card' => $chooserCard->card] : null,
            'offerer_card' => $offererCard ? ['hand_id' => $offererCard->id, 'card' => $offererCard->card] : null,
        ]);
    }

    /**
     * A duel player rolls their character's dice against their card's difficulty.
     */
    public function duelRoll(Game $game, Request $request): JsonResponse
    {
        if (!$game->isDuel()) {
            return response()->json(['error' => 'Not a duel game'], 422);
        }

        if (!in_array($game->duel_phase, ['rolling_offerer', 'rolling_chooser', 'rolling'])) {
            return response()->json(['error' => 'Not in a rolling phase'], 422);
        }

        // Determine who's rolling
        $offerer = $game->getOfferer();
        $chooser = $game->getChooser();

        if ($game->duel_phase === 'rolling') {
            // Simultaneous rolling (online) — determine player from auth
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Authentication required'], 403);
            }

            if ($user->id === $offerer->user_id) {
                $rollingPlayer = $offerer;
            } elseif ($user->id === $chooser->user_id) {
                $rollingPlayer = $chooser;
            } else {
                return response()->json(['error' => 'You are not in this game'], 403);
            }

            // Check if this player already rolled this round
            $alreadyRolled = GameRoundResult::where('game_id', $game->id)
                ->where('round_number', $game->current_round)
                ->where('game_player_id', $rollingPlayer->id)
                ->exists();

            if ($alreadyRolled) {
                return response()->json(['error' => 'You have already rolled this round'], 422);
            }
        } else {
            $rollingPlayer = $game->duel_phase === 'rolling_offerer' ? $offerer : $chooser;

            // Online mode: verify authenticated user matches the rolling player
            if ($game->isOnline()) {
                if (!$request->user() || $request->user()->id !== $rollingPlayer->user_id) {
                    return response()->json(['error' => 'It is not your turn to roll'], 403);
                }
            }
        }

        $rollingPlayer->load(['character', 'items.item']);

        // Get the player's card for this round
        $hand = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->where('game_player_id', $rollingPlayer->id)
            ->with('card')
            ->first();

        if (!$hand) {
            return response()->json(['error' => 'No card assigned to rolling player'], 422);
        }

        $card = $hand->card;
        $difficulty = $card->difficulty;

        // Apply item difficulty modifiers (only active/non-used items)
        foreach ($rollingPlayer->items->where('is_used', false) as $playerItem) {
            $effect = $playerItem->item->effect;
            $bonusType = $effect['bonus_type'] ?? '';
            if ($bonusType === 'difficulty_reduction') {
                $difficulty -= abs((int) ($effect['bonus_value'] ?? 0));
            } elseif ($bonusType === 'difficulty_increase') {
                $difficulty += abs((int) ($effect['bonus_value'] ?? 0));
            }
        }
        $difficulty = max(1, $difficulty);

        // Roll dice
        $character = $rollingPlayer->character;
        $dice = $character->dice;
        $activeDice = 3 - $rollingPlayer->lost_dice;
        $activeDice = max(1, $activeDice);

        $totalRoll = 0;
        $playerRolls = [];
        $wildTriggers = [];

        foreach (array_slice($dice, 0, $activeDice) as $dieIndex => $die) {
            $faceIndex = random_int(0, 5);
            $face = $die[$faceIndex];
            $playerRolls[] = [
                'die' => $dieIndex + 1,
                'face' => $face,
                'face_index' => $faceIndex,
                'value' => $face === 'WILD' ? $character->wild_value : (int) $face,
            ];

            if ($face === 'WILD') {
                $totalRoll += $character->wild_value;
                $wildTriggers[] = [
                    'player_number' => $rollingPlayer->player_number,
                    'character_name' => $character->name,
                    'wild_value' => $character->wild_value,
                    'ability' => $character->wild_ability,
                    'ability_description' => $character->wild_ability_description,
                ];
            } else {
                $totalRoll += (int) $face;
            }
        }

        // Apply item roll bonuses and penalties (only active/non-used items)
        foreach ($rollingPlayer->items->where('is_used', false) as $playerItem) {
            $effect = $playerItem->item->effect;
            $bonusType = $effect['bonus_type'] ?? '';
            if ($bonusType === 'roll_bonus' || $bonusType === 'roll_penalty') {
                $totalRoll += (int) ($effect['bonus_value'] ?? 0);
            }
        }

        // Process wild abilities (duel version: inspire gives +1 instead of +player_count)
        $abilityEffects = $this->processDuelWildAbilities($wildTriggers, $totalRoll);
        $totalRoll = $abilityEffects['adjusted_total'];

        // Apply manually activated ability (if player used their ability this round)
        if ($rollingPlayer->ability_active_this_round) {
            $manualAbility = $this->applyManualAbility($rollingPlayer, $totalRoll);
            $totalRoll = $manualAbility['adjusted_total'];
            $abilityEffects['descriptions'] = array_merge($abilityEffects['descriptions'], $manualAbility['descriptions']);
            // Reset the flag
            $rollingPlayer->update(['ability_active_this_round' => false]);
        }

        $success = $totalRoll >= $difficulty;

        // Determine effects
        $statEffects = [];
        // Negative effects ALWAYS apply
        $negativeEffects = $card->negative_effects ?? [];
        foreach ($negativeEffects as $stat => $change) {
            if (in_array($stat, ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'])) {
                $statEffects[$stat] = ($statEffects[$stat] ?? 0) + $change;
            }
        }

        // Positive effects apply on success
        if ($success) {
            $positiveEffects = $card->positive_effects ?? [];
            foreach ($positiveEffects as $stat => $change) {
                if (in_array($stat, ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'])) {
                    $statEffects[$stat] = ($statEffects[$stat] ?? 0) + $change;
                }
            }
        }

        // Apply effects to the player's kingdom
        $kingdom = GamePlayerKingdom::where('game_id', $game->id)
            ->where('game_player_id', $rollingPlayer->id)
            ->first();

        if ($kingdom && !empty($statEffects)) {
            $kingdom->applyEffects($statEffects);
        }

        // Save round result
        $duelEvent = $this->getCurrentEvent($game);
        GameRoundResult::create([
            'game_id' => $game->id,
            'round_number' => $game->current_round,
            'card_id' => $card->id,
            'game_player_id' => $rollingPlayer->id,
            'success' => $success,
            'result_type' => 'duel',
            'dice_results' => [[
                'player_number' => $rollingPlayer->player_number,
                'character_name' => $character->name,
                'rolls' => $playerRolls,
                'active_dice' => $activeDice,
                'lost_dice' => $rollingPlayer->lost_dice,
            ]],
            'stat_totals' => ['total_roll' => $totalRoll, 'total_difficulty' => $difficulty],
            'effects_applied' => $statEffects,
            'wild_triggers' => $wildTriggers,
            'special_effects' => [],
            'kingdom_snapshot' => $kingdom ? $kingdom->fresh()->only(['wealth', 'influence', 'security', 'religion', 'food', 'happiness']) : null,
            'event_data' => $duelEvent ? ['id' => $duelEvent->id, 'name' => $duelEvent->name, 'description' => $duelEvent->description] : null,
        ]);

        // Check instant win/loss after roll
        $kingdom->refresh();
        $duelResult = $game->checkDuelStatBounds($kingdom);

        // Advance duel_phase
        if ($game->duel_phase === 'rolling') {
            // Simultaneous: check if both players have now rolled
            $rollCount = GameRoundResult::where('game_id', $game->id)
                ->where('round_number', $game->current_round)
                ->count();

            if ($rollCount >= 2) {
                $game->update([
                    'duel_phase' => 'resolving',
                    'round_phase' => 'resolving',
                ]);
            }
            // else: stay in 'rolling' phase, waiting for other player
        } elseif ($game->duel_phase === 'rolling_offerer') {
            $game->update(['duel_phase' => 'rolling_chooser']);
        } else {
            $game->update([
                'duel_phase' => 'resolving',
                'round_phase' => 'resolving',
            ]);
        }

        $rollData = [
            'player_number' => $rollingPlayer->player_number,
            'character_name' => $character->name,
            'card' => $card,
            'difficulty' => $difficulty,
            'rolls' => $playerRolls,
            'total_roll' => $totalRoll,
            'success' => $success,
            'effects' => $statEffects,
            'ability_effects' => $abilityEffects['descriptions'],
            'kingdom' => $kingdom->fresh(),
            'duel_result' => $duelResult,
        ];

        if ($game->isOnline()) {
            $freshGame = $game->fresh();
            broadcast(new DuelRollComplete(
                $game->id,
                $rollingPlayer->player_number,
                $rollData,
                $freshGame->duel_phase,
            ));
            // Sequential: notify chooser it's their turn
            if ($freshGame->duel_phase === 'rolling_chooser') {
                $chooserNumber = $freshGame->offerer_player_number === 1 ? 2 : 1;
                $this->notifyPlayer($game, $chooserNumber, 'Your rival has rolled — now it\'s your turn!');
            }
        }

        return response()->json($rollData);
    }

    /**
     * Activate character ability for the current rolling phase.
     */
    public function useAbility(Game $game, Request $request): JsonResponse
    {
        if ($game->status !== 'active') {
            return response()->json(['error' => 'Game is not active'], 422);
        }

        $validated = $request->validate([
            'player_number' => 'required|integer',
        ]);

        $player = $game->players()->where('player_number', $validated['player_number'])->with('character')->first();
        if (!$player) {
            return response()->json(['error' => 'Invalid player'], 422);
        }

        if ($player->ability_uses <= 0) {
            return response()->json(['error' => 'No ability uses remaining'], 422);
        }

        if ($player->ability_active_this_round) {
            return response()->json(['error' => 'Ability already activated this round'], 422);
        }

        // Duel mode: must be in the player's rolling phase
        if ($game->isDuel()) {
            $offerer = $game->getOfferer();
            $isOffererRolling = $game->duel_phase === 'rolling_offerer' && $player->id === $offerer->id;
            $chooser = $game->getChooser();
            $isChooserRolling = $game->duel_phase === 'rolling_chooser' && $player->id === $chooser->id;
            if (!$isOffererRolling && !$isChooserRolling) {
                return response()->json(['error' => 'Not your rolling phase'], 422);
            }
        } else {
            // Cooperative: must be in selecting phase (before resolve)
            if ($game->round_phase !== 'selecting') {
                return response()->json(['error' => 'Cannot use ability in this phase'], 422);
            }
        }

        $player->update([
            'ability_active_this_round' => true,
            'ability_uses' => $player->ability_uses - 1,
        ]);

        $ability = $player->character->wild_ability;
        $response = [
            'activated' => true,
            'ability' => $ability,
            'ability_name' => $player->character->wild_ability,
            'ability_description' => $player->character->wild_ability_description,
            'remaining_uses' => $player->ability_uses,
        ];

        // Shadow ability: peek at next round's cards
        if ($ability === 'shadow') {
            $nextRound = $game->current_round + 1;
            $nextCards = $game->playerHands()
                ->where('round_number', $nextRound)
                ->with('card:id,title,difficulty')
                ->get()
                ->pluck('card')
                ->filter();

            if ($nextCards->isEmpty()) {
                // Try to peek at next deck cards
                $nextDeckCards = GameCardDeck::where('game_id', $game->id)
                    ->where('is_drawn', false)
                    ->orderBy('position')
                    ->limit(2)
                    ->with('card:id,title,difficulty')
                    ->get()
                    ->pluck('card');
                $response['peeked_cards'] = $nextDeckCards->values();
            } else {
                $response['peeked_cards'] = $nextCards->values();
            }
        }

        return response()->json($response);
    }

    /**
     * Process the opponent's turn in a duel (bot player).
     */
    public function opponentTurn(Game $game, Request $request): JsonResponse
    {
        if (!$game->isDuel()) {
            return response()->json(['error' => 'Not a duel game'], 422);
        }

        // Find the bot player
        $bot = $game->players()->where('is_bot', true)->first();
        if (!$bot) {
            return response()->json(['error' => 'No bot player in this game'], 422);
        }

        $botUser = User::find($bot->user_id);
        $botService = app(BotService::class);
        $phase = $game->duel_phase;

        // Bot is the offerer and it's offering phase
        if ($phase === 'offering' && $game->offerer_player_number === $bot->player_number) {
            $handId = $botService->decideDuelOffer($game, $bot);

            $fakeRequest = Request::create('', 'POST', ['revealed_hand_id' => $handId]);
            $fakeRequest->setUserResolver(fn () => $botUser);
            return $this->duelOffer($game->fresh(), $fakeRequest);
        }

        // Bot is the chooser and it's choosing phase
        if ($phase === 'choosing') {
            $chooser = $game->getChooser();
            if ($chooser && $chooser->is_bot) {
                $handId = $botService->decideDuelChoice($game, $bot);

                $fakeRequest = Request::create('', 'POST', ['chosen_hand_id' => $handId]);
                $fakeRequest->setUserResolver(fn () => $botUser);
                return $this->duelChoose($game->fresh(), $fakeRequest);
            }
        }

        // Bot is rolling
        if ($phase === 'rolling_offerer' && $game->offerer_player_number === $bot->player_number) {
            $fakeRequest = Request::create('', 'POST', ['player_number' => $bot->player_number]);
            $fakeRequest->setUserResolver(fn () => $botUser);
            return $this->duelRoll($game->fresh(), $fakeRequest);
        }

        if ($phase === 'rolling_chooser') {
            $chooser = $game->getChooser();
            if ($chooser && $chooser->is_bot) {
                $fakeRequest = Request::create('', 'POST', ['player_number' => $bot->player_number]);
                $fakeRequest->setUserResolver(fn () => $botUser);
                return $this->duelRoll($game->fresh(), $fakeRequest);
            }
        }

        return response()->json(['error' => 'Not the opponent\'s turn'], 422);
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Include games where user is host OR a participant
        $participantGameIds = GamePlayer::where('user_id', $userId)->pluck('game_id');

        $activeGames = Game::with(['players.character', 'players.user'])
            ->where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->whereIn('status', ['setup', 'active'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($game) => [
                'id' => $game->id,
                'status' => $game->status,
                'game_mode' => $game->game_mode,
                'game_type' => $game->game_type ?? 'cooperative',
                'current_round' => $game->current_round,
                'total_rounds' => $game->total_rounds,
                'num_players' => $game->num_players,
                'players' => $game->players->map(fn ($p) => [
                    'character_name' => $p->character?->name,
                    'username' => $p->user?->name,
                ])->values(),
            ]);

        $completedGames = Game::with(['players.character', 'players.user'])
            ->where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->where('status', 'completed')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($game) => [
                'id' => $game->id,
                'win' => $game->win,
                'game_mode' => $game->game_mode,
                'game_type' => $game->game_type ?? 'cooperative',
                'score' => $game->wealth + $game->influence + $game->security + $game->religion + $game->food + $game->happiness,
                'winner_player_number' => $game->winner_player_number,
                'num_players' => $game->num_players,
                'rounds_survived' => $game->current_round,
                'total_rounds' => $game->total_rounds,
                'played_at' => $game->updated_at->toDateTimeString(),
                'players' => $game->players->map(fn ($p) => [
                    'character_name' => $p->character?->name,
                    'username' => $p->user?->name,
                ])->values(),
            ]);

        return response()->json([
            'active_games' => $activeGames,
            'completed_games' => $completedGames,
        ]);
    }

    // --- Private helpers ---

    private function getCurrentEvent(Game $game): ?Event
    {
        $eventIndex = (int) floor(($game->current_round - 1) / 3);
        $eventOrder = $game->event_order;

        // Use shuffled event order if available, otherwise fall back to ID order
        if (!empty($eventOrder) && isset($eventOrder[$eventIndex])) {
            return Event::find($eventOrder[$eventIndex]);
        }

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
     * Apply immediate effects for consumable items. Returns a description string or null.
     */
    private function applyImmediateItemEffect(Game $game, GamePlayer $player, Item $item): ?string
    {
        $effect = $item->effect;
        $bonusType = $effect['bonus_type'] ?? '';
        $bonusValue = (int) ($effect['bonus_value'] ?? 0);

        if ($bonusType === 'stat_boost') {
            $stat = $effect['stat'] ?? null;
            $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
            if ($stat && in_array($stat, $stats)) {
                if ($game->isDuel()) {
                    $kingdom = GamePlayerKingdom::where('game_id', $game->id)
                        ->where('game_player_id', $player->id)
                        ->first();
                    if ($kingdom) {
                        $kingdom->{$stat} = max(0, min(20, $kingdom->{$stat} + $bonusValue));
                        $kingdom->save();
                    }
                } else {
                    $game->{$stat} = max(0, min(20, $game->{$stat} + $bonusValue));
                    $game->save();
                }
                $sign = $bonusValue > 0 ? '+' : '';
                return "{$sign}{$bonusValue} {$stat}";
            }
        }

        if ($bonusType === 'heal_die') {
            if ($player->lost_dice > 0) {
                $player->decrement('lost_dice');
                return "Recovered a lost die!";
            }
            return "No lost dice to recover.";
        }

        return null;
    }

    /**
     * Deal 2 cards to the offerer for a duel round.
     */
    private function dealDuelCardsForRound(Game $game): void
    {
        $offerer = $game->getOfferer();

        $deckCards = $game->cardDeck()
            ->where('is_drawn', false)
            ->orderBy('position')
            ->limit(2)
            ->get();

        foreach ($deckCards as $dc) {
            $dc->update(['is_drawn' => true]);

            GamePlayerHand::create([
                'game_id' => $game->id,
                'game_player_id' => $offerer->id,
                'card_id' => $dc->card_id,
                'round_number' => $game->current_round,
                'revealed' => false,
            ]);
        }
    }

    /**
     * Process wild abilities for duel mode (single-player context).
     * Inspire gives +1 instead of +player_count.
     */
    private function processDuelWildAbilities(array $wildTriggers, int $totalRoll): array
    {
        $adjustedTotal = $totalRoll;
        $descriptions = [];

        foreach ($wildTriggers as $trigger) {
            $ability = $trigger['ability'] ?? '';
            $playerName = $trigger['character_name'];

            switch ($ability) {
                case 'inspire':
                    $adjustedTotal += 1;
                    $descriptions[] = "{$playerName}'s Inspire: +1";
                    break;
                case 'rally':
                    $adjustedTotal += 2;
                    $descriptions[] = "{$playerName}'s Rally: rerolled lowest die (+2)";
                    break;
                case 'divine':
                    $bonus = $trigger['wild_value'];
                    $adjustedTotal += $bonus;
                    $descriptions[] = "{$playerName}'s Divine: WILD counts double (+{$bonus})";
                    break;
                case 'gamble':
                    // WILD triggers notification only — player can manually activate Gamble
                    $descriptions[] = "{$playerName}'s Gamble: WILD rolled! Use ability to gamble.";
                    break;
                case 'shadow':
                    $descriptions[] = "{$playerName}'s Shadow: glimpsed the future (no roll effect)";
                    break;
                case 'wisdom':
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

    /**
     * Apply manually activated character ability to a roll.
     */
    private function applyManualAbility(GamePlayer $player, int $totalRoll): array
    {
        $character = $player->character;
        $ability = $character->wild_ability;
        $name = $character->name;
        $adjustedTotal = $totalRoll;
        $descriptions = [];

        switch ($ability) {
            case 'inspire':
                $adjustedTotal += 2;
                $descriptions[] = "{$name} activates Inspire: +2 to roll!";
                break;
            case 'rally':
                $adjustedTotal += 3;
                $descriptions[] = "{$name} activates Rally: rerolled lowest die (+3)!";
                break;
            case 'divine':
                $bonus = $character->wild_value;
                $adjustedTotal += $bonus;
                $descriptions[] = "{$name} activates Divine: +{$bonus} divine guidance!";
                break;
            case 'gamble':
                $bonus = random_int(-2, 6);
                $adjustedTotal += $bonus;
                $sign = $bonus >= 0 ? '+' : '';
                $descriptions[] = "{$name} activates Gamble: risky reroll ({$sign}{$bonus})!";
                break;
            case 'shadow':
                $descriptions[] = "{$name} activates Shadow: peered into the future!";
                break;
            case 'wisdom':
                $adjustedTotal += 3;
                $descriptions[] = "{$name} activates Wisdom: +3 insight!";
                break;
        }

        return [
            'adjusted_total' => $adjustedTotal,
            'descriptions' => $descriptions,
        ];
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
                    // WILD triggers notification only — player can manually activate Gamble
                    $descriptions[] = "{$playerName}'s Gamble: WILD rolled! Use ability to gamble.";
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

    /**
     * Send push notification to online game players that it's their turn.
     */
    private function notifyTurn(Game $game, string $message = "It's your turn!"): void
    {
        if (!$game->isOnline()) {
            return;
        }

        try {
            $onesignal = app(OneSignalService::class);
            $players = $game->players()->with('user')->get();

            foreach ($players as $player) {
                if ($player->user) {
                    $onesignal->sendToUser(
                        $player->user,
                        'Trusted Advisors',
                        $message,
                        ['type' => 'your_turn', 'game_id' => $game->id]
                    );
                }
            }
        } catch (\Throwable $e) {
            // Notification failure should never break game flow
        }
    }

    /**
     * Send push notification to a specific player in an online game.
     */
    private function notifyPlayer(Game $game, int $playerNumber, string $message): void
    {
        if (!$game->isOnline()) {
            return;
        }

        // Broadcast in-game alert via WebSocket
        try {
            event(new \App\Events\GameAlertSent($game->id, $message, $playerNumber));
        } catch (\Throwable $e) {
            // Alert failure should never break game flow
        }

        // Push notification via OneSignal
        try {
            $player = $game->players()->where('player_number', $playerNumber)->with('user')->first();
            if ($player?->user) {
                app(OneSignalService::class)->sendToUser(
                    $player->user,
                    'Trusted Advisors',
                    $message,
                    ['type' => 'your_turn', 'game_id' => $game->id]
                );
            }
        } catch (\Throwable $e) {
            // Notification failure should never break game flow
        }
    }

    // =============================
    // PLAYER-FACING ENDPOINTS
    // =============================

    public function achievements(Request $request): JsonResponse
    {
        $user = $request->user();
        $allAchievements = Achievement::orderBy('tier_group')->orderBy('tier')->orderBy('name')->get();
        $userAchievements = UserAchievement::where('user_id', $user->id)->get()->keyBy('achievement_id');
        $completionService = app(GameCompletionService::class);

        // Group tiered achievements to determine visibility
        $tierGroups = $allAchievements->whereNotNull('tier_group')->groupBy('tier_group');

        $visible = collect();

        // Add standalone achievements (no tier_group) — always visible
        foreach ($allAchievements->whereNull('tier_group') as $a) {
            $visible->push($a);
        }

        // For tiered groups: show claimed tiers + the lowest unclaimed tier. Higher tiers hidden.
        foreach ($tierGroups as $group => $achievements) {
            $sorted = $achievements->sortBy('tier');
            $foundUnclaimed = false;

            foreach ($sorted as $a) {
                $ua = $userAchievements->get($a->id);
                $isClaimed = $ua && $ua->claimed_at !== null;
                if ($isClaimed) {
                    $visible->push($a);
                } elseif (!$foundUnclaimed) {
                    $visible->push($a);
                    $foundUnclaimed = true;
                }
            }
        }

        $result = $visible->sortBy('category')->values()->map(function ($a) use ($userAchievements, $user, $completionService) {
            $ua = $userAchievements->get($a->id);
            $isEarned = $ua !== null;
            $data = [
                'id' => $a->id,
                'key' => $a->key,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'category' => $a->category,
                'earned' => $isEarned,
                'claimed' => $ua && $ua->claimed_at !== null,
                'reward_xp' => $a->reward_xp,
                'reward_coins' => $a->reward_coins ?? 0,
                'tier' => $a->tier,
                'tier_group' => $a->tier_group,
                'progress' => null,
            ];

            if (!$isEarned) {
                $progress = $completionService->getProgress($user, $a->criteria);
                $data['progress'] = $progress;

                // Auto-earn if criteria already met (e.g. wins before achievement was seeded)
                if ($progress && isset($progress['current'], $progress['target']) && $progress['current'] >= $progress['target']) {
                    UserAchievement::create([
                        'user_id' => $user->id,
                        'achievement_id' => $a->id,
                        'unlocked_at' => now(),
                    ]);
                    $data['earned'] = true;
                    $data['claimed'] = false;
                }
            }

            return $data;
        });

        return response()->json($result);
    }

    public function claimAchievement(Request $request, Achievement $achievement): JsonResponse
    {
        $user = $request->user();

        $ua = UserAchievement::where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->first();

        if (!$ua) {
            return response()->json(['error' => 'Achievement not earned.'], 403);
        }

        if ($ua->claimed_at !== null) {
            return response()->json(['error' => 'Already claimed.'], 409);
        }

        $ua->claimed_at = now();
        $ua->save();

        // Award XP
        $oldLevel = $user->level;
        $user->xp += $achievement->reward_xp;
        $user->level = \App\Models\User::calculateLevel($user->xp);

        // Award coins
        $coinsAwarded = $achievement->reward_coins ?? 0;
        $user->coins += $coinsAwarded;

        $user->save();

        if ($coinsAwarded > 0) {
            $user->recordCoinTransaction($coinsAwarded, 'earn', 'achievement', $achievement->id, "Claimed achievement: {$achievement->name}");
        }

        $leveledUp = $user->level > $oldLevel;

        // Find next tier in same group
        $nextTier = null;
        if ($achievement->tier_group) {
            $next = Achievement::where('tier_group', $achievement->tier_group)
                ->where('tier', $achievement->tier + 1)
                ->first();

            if ($next) {
                $nextUa = UserAchievement::where('user_id', $user->id)
                    ->where('achievement_id', $next->id)
                    ->first();

                $nextTier = [
                    'id' => $next->id,
                    'key' => $next->key,
                    'name' => $next->name,
                    'description' => $next->description,
                    'icon' => $next->icon,
                    'reward_xp' => $next->reward_xp,
                    'reward_coins' => $next->reward_coins ?? 0,
                    'tier' => $next->tier,
                    'earned' => $nextUa !== null,
                    'claimed' => $nextUa && $nextUa->claimed_at !== null,
                ];
            }
        }

        return response()->json([
            'xp_awarded' => $achievement->reward_xp,
            'coins_awarded' => $coinsAwarded,
            'new_xp' => $user->xp,
            'new_level' => $user->level,
            'new_coins' => $user->coins,
            'leveled_up' => $leveledUp,
            'next_tier' => $nextTier,
        ]);
    }

    public function dailyChallenge(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();
        $challenge = DailyChallenge::where('date', $today)->first();

        if (!$challenge) {
            return response()->json(null);
        }

        $entry = DailyChallengeEntry::where('user_id', $user->id)
            ->where('daily_challenge_id', $challenge->id)
            ->first();

        return response()->json([
            'id' => $challenge->id,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'reward_xp' => $challenge->reward_xp,
            'completed' => $entry && $entry->completed_at !== null,
        ]);
    }

    public function seasons(): JsonResponse
    {
        return response()->json(Season::orderByDesc('starts_at')->get());
    }

    public function cancelGame(Request $request, Game $game): JsonResponse
    {
        $userId = $request->user()->id;

        // Verify user is host or participant
        $isHost = $game->user_id === $userId;
        $isParticipant = $game->players()->where('user_id', $userId)->exists();

        if (!$isHost && !$isParticipant) {
            return response()->json(['error' => 'You are not part of this game.'], 403);
        }

        if ($game->status === 'completed') {
            return response()->json(['error' => 'Game is already completed.'], 422);
        }

        $game->update([
            'status' => 'completed',
            'win' => false,
        ]);

        return response()->json(['message' => 'Game cancelled.']);
    }

    /**
     * Check which players have more than 2 active (non-used) items.
     */
    private function getPlayersOverItemLimit(Game $game): array
    {
        $players = $game->players()->with(['items' => fn ($q) => $q->where('is_used', false), 'items.item', 'character'])->get();
        $overLimit = [];

        foreach ($players as $player) {
            $activeItems = $player->items->where('is_used', false);
            if ($activeItems->count() > 2) {
                $overLimit[] = [
                    'player_number' => $player->player_number,
                    'character_name' => $player->character->name ?? 'Player ' . $player->player_number,
                    'items' => $activeItems->map(fn ($pi) => [
                        'id' => $pi->id,
                        'item_name' => $pi->item->name,
                        'description' => $pi->item->description,
                        'effect' => $pi->item->effect,
                        'is_cursed' => $pi->is_cursed,
                    ])->values(),
                ];
            }
        }

        return $overLimit;
    }

    /**
     * Discard an item from a player's inventory.
     */
    public function discardItem(Game $game, Request $request): JsonResponse
    {
        if ($game->status !== 'active') {
            return response()->json(['error' => 'Game is not active'], 422);
        }

        $validated = $request->validate([
            'game_player_item_id' => 'required|integer',
        ]);

        $playerItem = GamePlayerItem::where('id', $validated['game_player_item_id'])
            ->whereHas('gamePlayer', fn ($q) => $q->where('game_id', $game->id))
            ->with('item')
            ->first();

        if (!$playerItem) {
            return response()->json(['error' => 'Item not found in this game'], 404);
        }

        if ($playerItem->is_cursed) {
            return response()->json(['error' => 'Cursed items cannot be discarded'], 422);
        }

        if ($playerItem->is_used) {
            return response()->json(['error' => 'Item is already used'], 422);
        }

        $playerItem->delete();

        return response()->json([
            'discarded' => true,
            'items_over_limit' => $this->getPlayersOverItemLimit($game),
        ]);
    }
}
