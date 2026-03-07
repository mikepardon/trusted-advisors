<?php

namespace App\Http\Controllers;

use App\Events\DuelChoiceMade;
use App\Events\DuelGameOver;
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
use App\Models\GameCurseDeck;
use App\Models\GameItemDeck;
use App\Models\GamePlayer;
use App\Models\GamePlayerCurse;
use App\Models\GamePlayerHand;
use App\Models\GamePlayerItem;
use App\Models\Curse;
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
use App\Jobs\ForfeitExpiredTurn;
use App\Jobs\ProcessBotTurn;
use App\Services\BotService;
use App\Services\DuelForfeitService;
use App\Services\GameCompletionService;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /**
     * Dispatch timeout enforcement + bot turn jobs for an online duel with a timer.
     */
    private function dispatchTurnTimerJobs(Game $game): void
    {
        if (!$game->isOnline() || !$game->turn_time_limit || !$game->turn_started_at) {
            return;
        }

        $turnStartedAt = $game->turn_started_at->toIso8601String();

        ForfeitExpiredTurn::dispatch($game->id, $turnStartedAt)
            ->delay(now()->addSeconds($game->turn_time_limit));

        // If the game has a bot player, schedule the bot's turn (3-9s delay)
        if ($game->players()->where('is_bot', true)->exists()) {
            $botDelay = rand(3, 9);
            ProcessBotTurn::dispatch($game->id, $turnStartedAt)
                ->delay(now()->addSeconds($botDelay));
        }
    }

    public function characters(Request $request): JsonResponse
    {
        $gameType = $request->query('game_type');
        $query = Character::query();
        if ($gameType === 'duel') {
            $query->where('available_duel', true);
        } elseif ($gameType === 'cooperative') {
            $query->where('available_cooperative', true);
        }
        $characters = $query->get();
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

    public function myCharacters(Request $request): JsonResponse
    {
        $user = $request->user();
        $characters = Character::all();

        $charUnlockables = Unlockable::where('type', 'character')->get()->keyBy('entity_id');
        $userUnlockables = UserUnlockable::where('user_id', $user->id)->get()->keyBy('unlockable_id');

        $result = $characters->filter(function ($c) use ($charUnlockables, $userUnlockables) {
            // Hide unavailable characters unless user owns them
            if (!$c->is_available) {
                $unlockable = $charUnlockables[$c->id] ?? null;
                if (!$unlockable) return true; // default unlocked chars always visible
                return isset($userUnlockables[$unlockable->id]);
            }
            return true;
        })->map(function ($c) use ($charUnlockables, $userUnlockables) {
            $data = [
                'id' => $c->id,
                'name' => $c->name,
                'image_url' => $c->image_url,
                'description' => $c->description,
                'dice' => $c->dice,
                'wild_value' => $c->wild_value,
                'wild_ability' => $c->wild_ability,
                'wild_ability_description' => $c->wild_ability_description,
            ];

            $unlockable = $charUnlockables[$c->id] ?? null;
            if (!$unlockable) {
                $data['is_unlocked'] = true;
                $data['unlock_requirement'] = null;
                $data['unlock_method'] = null;
                $data['unlocked_at'] = null;
            } else {
                $userUnlockable = $userUnlockables[$unlockable->id] ?? null;
                $isUnlocked = $userUnlockable !== null;
                $data['is_unlocked'] = $isUnlocked;
                $data['unlock_method'] = $unlockable->unlock_method;
                $data['unlocked_at'] = $isUnlocked ? $userUnlockable->unlocked_at?->toDateString() : null;
                $data['unlock_requirement'] = !$isUnlocked
                    ? ($unlockable->unlock_method === 'level'
                        ? "Reach level {$unlockable->unlock_value}"
                        : ($unlockable->unlock_method === 'shop'
                            ? "Purchase from shop ({$unlockable->unlock_value} coins)"
                            : "Earn required achievement"))
                    : null;
            }

            return $data;
        })->values();

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_mode' => 'required|string|in:single,pass_and_play,online',
            'num_players' => 'required|integer|min:1|max:6',
            'total_rounds' => 'sometimes|nullable|integer|min:12|max:120',
            'game_type' => 'sometimes|string|in:cooperative,duel',
            'bot_difficulty' => 'sometimes|string|in:easy,medium,hard',
            'rotating_event_id' => 'nullable|integer|exists:rotating_events,id',
            'is_custom' => 'sometimes|boolean',
            'starting_stats' => 'sometimes|integer|min:1|max:20',
            'card_pool' => 'sometimes|array',
            'card_pool.*' => 'integer|exists:cards,id',
            'event_pool' => 'sometimes|array',
            'event_pool.*' => 'integer|exists:events,id',
            'item_pool' => 'sometimes|array',
            'item_pool.*' => 'integer|exists:items,id',
            'house_rules' => 'sometimes|array',
            'is_private' => 'sometimes|boolean',
            'lobby_password' => 'required_if:is_private,true|nullable|string|min:1',
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
        // Duel mode is always 24 rounds
        if ($gameType === 'duel') {
            $validated['total_rounds'] = 24;
            if ($validated['game_mode'] === 'single') {
                $validated['num_players'] = 2; // Bot will be player 2
            }
            if ($validated['num_players'] !== 2) {
                return response()->json(['error' => 'Duel mode requires exactly 2 players'], 422);
            }
        }

        // Handle rotating event
        $rotatingEventId = $validated['rotating_event_id'] ?? null;
        if ($rotatingEventId) {
            $rotatingEvent = \App\Models\RotatingEvent::currentlyActive()->find($rotatingEventId);
            if (!$rotatingEvent) {
                return response()->json(['error' => 'Event is not currently active'], 422);
            }
            // Override total rounds if the event specifies it
            if ($rotatingEvent->total_rounds) {
                $validated['total_rounds'] = $rotatingEvent->total_rounds;
            }
            // Enforce max attempts limit
            if ($rotatingEvent->max_attempts) {
                $attemptCount = \App\Models\RotatingEventEntry::where('rotating_event_id', $rotatingEventId)
                    ->where('user_id', $request->user()->id)
                    ->count();
                if ($attemptCount >= $rotatingEvent->max_attempts) {
                    return response()->json(['error' => "You've reached the maximum of {$rotatingEvent->max_attempts} attempts for this event."], 422);
                }
            }
        }

        // Assign active season if applicable (skip for event games)
        $seasonId = null;
        if (!$rotatingEventId) {
            $activeSeason = Season::active()->first();
            if ($activeSeason && now()->between($activeSeason->starts_at, $activeSeason->ends_at)) {
                $seasonId = $activeSeason->id;
            }
        }

        // Premium-gated custom/private game features
        $isCustom = !empty($validated['is_custom']);
        $isPrivate = !empty($validated['is_private']);
        $customRules = null;

        if ($isCustom || $isPrivate) {
            $user = $request->user();
            if (!$user || !$user->isPremium()) {
                return response()->json(['error' => 'Premium subscription required for custom and private games.'], 403);
            }
        }

        if ($isCustom) {
            $customRules = [];
            if (isset($validated['starting_stats'])) {
                $customRules['starting_stats'] = $validated['starting_stats'];
            }
            if (isset($validated['card_pool'])) {
                $customRules['card_pool'] = $validated['card_pool'];
            }
            if (isset($validated['event_pool'])) {
                $customRules['event_pool'] = $validated['event_pool'];
            }
            if (isset($validated['item_pool'])) {
                $customRules['item_pool'] = $validated['item_pool'];
            }
            if (isset($validated['house_rules'])) {
                $customRules['house_rules'] = $validated['house_rules'];
            }
        }

        // Apply rotating event modifiers (starting stats, house rules) to custom_rules
        if ($rotatingEventId && isset($rotatingEvent) && $rotatingEvent->modifiers) {
            $mods = $rotatingEvent->modifiers;
            if (!empty($mods['starting_stats'])) {
                $customRules = $customRules ?? [];
                $customRules['starting_stats'] = $mods['starting_stats'];
            }
            if (!empty($mods['house_rules'])) {
                $customRules = $customRules ?? [];
                $customRules['house_rules'] = $mods['house_rules'];
            }
        }

        $game = Game::create([
            'status' => 'setup',
            'game_mode' => $validated['game_mode'],
            'game_type' => $gameType,
            'num_players' => $validated['num_players'],
            'total_rounds' => $validated['total_rounds'] ?? (int) GameRule::getValue('default_total_rounds', 60),
            'user_id' => $request->user()?->id,
            'season_id' => $seasonId,
            'rotating_event_id' => $rotatingEventId,
            'is_custom' => $isCustom,
            'custom_rules' => $customRules,
            'is_private' => $isPrivate,
            'lobby_password' => $isPrivate && isset($validated['lobby_password'])
                ? bcrypt($validated['lobby_password'])
                : null,
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
        $game->load(['players.character', 'players.user.activeKingdomStyle', 'players.items.item', 'players.curses.curse']);

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
            $data['player_kingdoms'] = $game->playerKingdoms()->with(['player.character', 'player.user'])->get();

            if ($game->timed_out_player_number !== null) {
                $data['timed_out_player_number'] = $game->timed_out_player_number;
            }

            if ($game->turn_time_limit) {
                $data['turn_time_limit'] = $game->turn_time_limit;
                $data['turn_started_at'] = $game->turn_started_at?->toIso8601String();
                $data['turn_time_remaining'] = $game->turnTimeRemaining();
            }
        }

        // For online games in setup, include lobby data
        if ($game->isOnline() && $game->status === 'setup') {
            $data['invites'] = $game->invites()->with(['sender', 'receiver'])->get();
            $data['lobby_players'] = $game->players()->with(['character', 'user'])->get();
        }

        // Get current event
        $event = $this->getCurrentEvent($game);
        $data['current_event'] = $event;

        // Include pending curses and active curses
        if (!empty($game->pending_curses)) {
            $data['pending_curses'] = $game->pending_curses;
        }
        $data['player_curses'] = $game->players->mapWithKeys(fn ($p) => [
            $p->player_number => $p->curses->map(fn ($c) => [
                'id' => $c->id,
                'curse' => $c->curse,
                'acquired_round' => $c->acquired_round,
            ]),
        ]);

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
                $hasItems = $player->items->where('is_used', false)->isNotEmpty();
                $playerStatus[] = [
                    'player_number' => $player->player_number,
                    'character_name' => $player->character->name,
                    'has_assigned' => $assignedCount >= $cardsPerPlayer,
                    'item_decided' => $player->item_decided || !$hasItems,
                    'has_items' => $hasItems,
                ];
            }
            $data['player_status'] = $playerStatus;
            $data['all_assigned'] = $this->allPlayersAssigned($game, $event);
            $data['all_items_decided'] = $this->allItemsDecided($game);
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

        // Validate character pool restriction from rotating event
        $rotatingEvent = $game->rotating_event_id ? $game->rotatingEvent : null;
        if ($rotatingEvent && $rotatingEvent->character_pool) {
            $invalidChars = array_diff($validated['characters'], $rotatingEvent->character_pool);
            if (!empty($invalidChars)) {
                return response()->json(['error' => 'One or more selected characters are not allowed in this event'], 422);
            }
        }

        // Duel mode: 4 cards per round (2 per player, no events)
        if ($game->isDuel()) {
            $totalCardsNeeded = 4 * $game->total_rounds;
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
                $botDifficulty = $request->input('bot_difficulty')
                    ?? $game->rotatingEvent?->modifiers['bot_difficulty']
                    ?? 'medium';
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

        // Determine availability column for game type filtering
        $availCol = $game->isDuel() ? 'available_duel' : 'available_cooperative';
        $customRules = $game->custom_rules;

        // Create shuffled deck (recycle cards if more needed than available)
        if (!empty($customRules['card_pool'])) {
            $allCards = Card::whereIn('id', $customRules['card_pool'])->inRandomOrder()->get();
        } elseif ($rotatingEvent && $rotatingEvent->card_pool) {
            $allCards = Card::whereIn('id', $rotatingEvent->card_pool)->inRandomOrder()->get();
        } else {
            $allCards = Card::where($availCol, true)->inRandomOrder()->get();
        }
        $cards = collect();
        while ($cards->count() < $totalCardsNeeded) {
            $cards = $cards->concat($allCards->shuffle());
        }
        $cards = $cards->take($totalCardsNeeded);
        $deckRows = [];
        $now = now();
        foreach ($cards as $i => $card) {
            $deckRows[] = [
                'game_id' => $game->id,
                'card_id' => $card->id,
                'position' => $i,
                'is_drawn' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        GameCardDeck::insert($deckRows);

        // Duel mode: skip item deck
        if (!$game->isDuel()) {
            // Create shuffled item deck (recycle items if needed)
            if (!empty($customRules['item_pool'])) {
                $allItems = Item::whereIn('id', $customRules['item_pool'])->inRandomOrder()->get();
            } elseif ($rotatingEvent && $rotatingEvent->item_pool) {
                $allItems = Item::whereIn('id', $rotatingEvent->item_pool)->inRandomOrder()->get();
            } else {
                $allItems = Item::where($availCol, true)->inRandomOrder()->get();
            }
            $itemsNeeded = $game->total_rounds * 2; // generous estimate
            $itemPool = collect();
            while ($itemPool->count() < $itemsNeeded) {
                $itemPool = $itemPool->concat($allItems->shuffle());
            }
            $itemPool = $itemPool->take($itemsNeeded);
            $itemRows = [];
            foreach ($itemPool as $i => $item) {
                $itemRows[] = [
                    'game_id' => $game->id,
                    'item_id' => $item->id,
                    'position' => $i,
                    'is_drawn' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            GameItemDeck::insert($itemRows);
        }

        // Initialize curse deck (both modes)
        $this->initCurseDeck($game);

        // Shuffle event order per game so every game is unique
        if ($rotatingEvent && $rotatingEvent->fixed_event_id) {
            // Fixed event: repeat the same event for every round
            $eventOrder = array_fill(0, $game->total_rounds, $rotatingEvent->fixed_event_id);
        } elseif (!empty($customRules['event_pool'])) {
            $eventOrder = collect($customRules['event_pool'])->shuffle()->values()->toArray();
        } elseif ($rotatingEvent && $rotatingEvent->event_pool) {
            $eventOrder = collect($rotatingEvent->event_pool)->shuffle()->values()->toArray();
        } else {
            $eventOrder = Event::where($availCol, true)->pluck('id')->shuffle()->values()->toArray();
        }

        if ($game->isDuel()) {
            $game->update([
                'status' => 'active',
                'current_round' => 1,
                'round_phase' => 'selecting',
                'offerer_player_number' => 1,
                'duel_phase' => 'choosing',
                'event_order' => $eventOrder,
            ]);

            // Refresh players relationship (just created/updated above)
            $game->load('players');

            // Create per-player kingdoms (with custom starting stats if set)
            $startStats = $customRules['starting_stats'] ?? 8;
            $randomStart = !empty($customRules['house_rules']['random_starting_stats']);
            foreach ($game->players as $player) {
                $kingdomData = [
                    'game_id' => $game->id,
                    'game_player_id' => $player->id,
                ];
                if ($randomStart) {
                    foreach (['wealth', 'influence', 'security', 'religion', 'food', 'happiness'] as $stat) {
                        $kingdomData[$stat] = random_int(1, 15);
                    }
                } elseif ($startStats !== 8) {
                    foreach (['wealth', 'influence', 'security', 'religion', 'food', 'happiness'] as $stat) {
                        $kingdomData[$stat] = $startStats;
                    }
                }
                GamePlayerKingdom::create($kingdomData);
            }

            // Deal duel cards for first round
            $this->dealDuelCardsForRound($game);
        } else {
            $coopUpdate = [
                'status' => 'active',
                'current_round' => 1,
                'round_phase' => 'selecting',
                'event_order' => $eventOrder,
            ];

            // Custom starting stats for cooperative mode
            if (!empty($customRules['house_rules']['random_starting_stats'])) {
                foreach (['wealth', 'influence', 'security', 'religion', 'food', 'happiness'] as $stat) {
                    $coopUpdate[$stat] = random_int(1, 15);
                }
            } elseif (!empty($customRules['starting_stats']) && $customRules['starting_stats'] !== 8) {
                foreach (['wealth', 'influence', 'security', 'religion', 'food', 'happiness'] as $stat) {
                    $coopUpdate[$stat] = $customRules['starting_stats'];
                }
            }

            $game->update($coopUpdate);

            // Deal first round
            $this->dealCardsForRound($game);
        }

        if ($game->isOnline()) {
            // Start turn timer for online duels
            if ($game->isDuel() && $game->turn_time_limit) {
                $game->update(['turn_started_at' => now()]);
                $this->dispatchTurnTimerJobs($game);
            }
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

        $player = $game->players()->with('character')->where('player_number', $playerNumber)->first();
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

        // Difficulty scaling
        $scaling = $this->getDifficultyScaling($game);

        // Gather all players' dice faces for odds calculation
        $allPlayers = $game->players()->with(['character', 'items.item'])->get();
        $allDiceFaces = [];
        foreach ($allPlayers as $p) {
            $pDice = $p->character->dice;
            $pTempReduction = $tempDiceReduction;
            $pActiveDice = max(1, $baseDice - $p->lost_dice - $pTempReduction);
            foreach (array_slice($pDice, 0, $pActiveDice) as $die) {
                $faces = array_map(fn ($f) => $f === 'WILD' ? $p->character->wild_value : (int) $f, $die);
                $allDiceFaces[] = $faces;
            }
        }

        // Sum roll modifiers from items used this round (single-use system)
        $totalRollMod = 0;
        $totalDiffMod = 0;
        foreach ($allPlayers as $p) {
            $usedItem = $p->items->firstWhere('used_round', $game->current_round);
            if ($usedItem) {
                $eff = $usedItem->item->effect ?? [];
                $bt = $eff['bonus_type'] ?? '';
                $bv = (int) ($eff['bonus_value'] ?? 0);
                if ($bt === 'roll_bonus' || $bt === 'roll_penalty') {
                    $totalRollMod += $bv;
                } elseif ($bt === 'difficulty_increase') {
                    $totalDiffMod += abs($bv);
                } elseif ($bt === 'difficulty_reduction') {
                    $totalDiffMod -= abs($bv);
                }
            }
        }

        $isDuel = $game->isDuel();

        return response()->json([
            'player_number' => $playerNumber,
            'round' => $game->current_round,
            'cards' => $hands->map(function ($h) use ($scaling, $allDiceFaces, $totalRollMod, $totalDiffMod, $isDuel) {
                $cardData = $h->card->toArray();
                if ($isDuel) {
                    $cardData['difficulty'] = $h->card->getDuelDifficulty() + $scaling;
                    $cardData['positive_effects'] = $h->card->getDuelPositiveEffects();
                    $cardData['negative_effects'] = $h->card->getDuelNegativeEffects();
                } else {
                    $cardData['difficulty'] = $h->card->difficulty + $scaling;
                }
                $target = $cardData['difficulty'] + $totalDiffMod;
                return [
                    'hand_id' => $h->id,
                    'card' => $cardData,
                    'role' => $h->role,
                    'success_odds' => $this->calculateSuccessOdds($allDiceFaces, $totalRollMod, $target),
                ];
            }),
            'has_assigned' => $hands->whereNotNull('role')->count() >= $cardsPerPlayer,
            'cards_per_player' => $cardsPerPlayer,
            'items' => $items,
            'dice_count' => $diceCount,
            'item_decided' => $player->item_decided,
            'character_dice' => $isDuel ? $player->character->getDuelDice() : $player->character->dice,
            'character_wild_value' => $isDuel ? $player->character->getDuelWildValue() : $player->character->wild_value,
            'character_wild_ability' => $isDuel ? $player->character->getDuelWildAbilityDescription() : $player->character->wild_ability_description,
            'player_curses' => GamePlayerCurse::where('game_player_id', $player->id)->with('curse')->get(),
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

        $players = $game->players()->with(['character', 'items.item', 'curses.curse'])->get();

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
        // Sum all positive cards' difficulty (with scaling)
        $scaling = $this->getDifficultyScaling($game);
        $totalDifficulty = $positiveHands->sum(fn ($h) => $h->card->difficulty + $scaling);

        // Apply single-use item difficulty modifiers (items used this round)
        $itemModifiers = [];
        foreach ($players as $player) {
            $usedItem = $player->items->firstWhere('used_round', $game->current_round);
            if ($usedItem) {
                $effect = $usedItem->item->effect;
                $bonusType = $effect['bonus_type'] ?? '';
                if ($bonusType === 'difficulty_reduction') {
                    $value = abs((int) ($effect['bonus_value'] ?? 0));
                    $totalDifficulty -= $value;
                    $itemModifiers[] = [
                        'item_name' => $usedItem->item->name,
                        'type' => 'difficulty_reduction',
                        'value' => $value,
                        'player' => $player->character->name,
                    ];
                } elseif ($bonusType === 'difficulty_increase') {
                    $value = abs((int) ($effect['bonus_value'] ?? 0));
                    $totalDifficulty += $value;
                    $itemModifiers[] = [
                        'item_name' => $usedItem->item->name,
                        'type' => 'difficulty_increase',
                        'value' => $value,
                        'player' => $player->character->name,
                    ];
                }
            }
        }
        // Apply curse difficulty modifiers
        $curseDifficultyMod = $this->getCurseDifficultyModifier($players, $game);
        $totalDifficulty += $curseDifficultyMod;

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

            // Apply single-use item roll bonuses (items used this round)
            $usedItemForRoll = $player->items->firstWhere('used_round', $game->current_round);
            if ($usedItemForRoll) {
                $effect = $usedItemForRoll->item->effect;
                $bonusType = $effect['bonus_type'] ?? '';
                if ($bonusType === 'roll_bonus' || $bonusType === 'roll_penalty') {
                    $value = (int) ($effect['bonus_value'] ?? 0);
                    $totalRoll += $value;
                    $itemModifiers[] = [
                        'item_name' => $usedItemForRoll->item->name,
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
        $pendingCurses = [];

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
                            $playerObj = $players->firstWhere('id', $hand->game_player_id);
                            $playerChar = $hand->player->character->name;

                            if ($playerObj && !$this->canPlayerReceiveItem($playerObj)) {
                                $specialEffects[] = [
                                    'type' => 'item_blocked',
                                    'player' => $playerChar,
                                    'item' => $drawnItem->name,
                                    'description' => "{$playerChar}'s inventory is full! {$drawnItem->name} was lost.",
                                ];
                            } else {
                                GamePlayerItem::create([
                                    'game_player_id' => $hand->game_player_id,
                                    'item_id' => $drawnItem->id,
                                    'acquired_round' => $game->current_round,
                                    'is_cursed' => false,
                                    'is_used' => false,
                                ]);
                                $specialEffects[] = [
                                    'type' => 'draw_item',
                                    'phase' => 'positive',
                                    'player' => $playerChar,
                                    'item' => $drawnItem->name,
                                    'description' => "{$playerChar} found {$drawnItem->name}!",
                                ];
                            }
                        }
                        continue;
                    }
                    if ($stat === 'remove_curse') {
                        // Priority: remove a curse card first, then fall back to cursed items
                        $playersWithCurseCards = $players->filter(fn ($p) => $p->curses->isNotEmpty());
                        if ($playersWithCurseCards->isNotEmpty()) {
                            $target = $playersWithCurseCards->random();
                            $curseRecord = $target->curses->random();
                            $curseName = $curseRecord->curse->name;
                            $charName = $target->character->name;
                            $curseRecord->delete();
                            $specialEffects[] = [
                                'type' => 'remove_curse',
                                'phase' => 'positive',
                                'player' => $charName,
                                'item' => $curseName,
                                'description' => "{$charName} was freed from the curse of {$curseName}!",
                            ];
                        } else {
                            // Fall back to cursed items
                            $playersWithCursedItems = $players->filter(function ($p) {
                                return $p->items->contains(fn ($pi) => $pi->is_cursed);
                            });
                            if ($playersWithCursedItems->isNotEmpty()) {
                                $target = $playersWithCursedItems->random();
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
                            }
                        }
                        continue;
                    }
                    if ($stat === 'draw_curse') {
                        $curseResult = $this->drawCursesFromDeck($game, $hand->game_player_id, $hand->player->character->name);
                        if ($curseResult) {
                            $pendingCurses[] = $curseResult['pending'];
                            $specialEffects[] = $curseResult['effect'];
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
                    if ($stat === 'bonus_score') {
                        $game->bonus_score = ($game->bonus_score ?? 0) + (int) $change;
                        $specialEffects[] = [
                            'type' => 'bonus_score',
                            'phase' => 'positive',
                            'value' => (int) $change,
                            'description' => "Kingdom renown increased by +{$change}!",
                        ];
                        continue;
                    }
                    if ($stat === 'end_game_modifier') {
                        $game->score_modifier = ($game->score_modifier ?? 0) + (float) $change;
                        $specialEffects[] = [
                            'type' => 'end_game_modifier',
                            'phase' => 'positive',
                            'value' => (float) $change,
                            'description' => "Final score modifier increased by +{$change}%!",
                        ];
                        continue;
                    }
                    $positiveEffects[$stat] = ($positiveEffects[$stat] ?? 0) + $change;
                }
            }
        }

        // Double positive effects if house rule is active
        $houseRules = $game->custom_rules['house_rules'] ?? [];
        if (!empty($houseRules['double_positive_effects'])) {
            foreach ($positiveEffects as $stat => $change) {
                if ($change > 0) {
                    $positiveEffects[$stat] = $change * 2;
                }
            }
        }

        // === NEGATIVE PHASE ===
        // On failed roll, ALL cards' negative effects apply (positive cards included)
        $handsForNegativePhase = !$positiveSuccess ? $negativeHands->merge($positiveHands) : $negativeHands;

        $negativeEffects = [];
        $skipNegatives = !empty($houseRules['no_negative_effects']);
        foreach ($handsForNegativePhase as $hand) {
            if ($skipNegatives) break;
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
                    // Negative phase: draw item from deck
                    $drawnItem = $this->drawItemFromDeck($game);
                    if ($drawnItem) {
                        $target = $players->random();
                        $charName = $target->character->name;

                        if (!$this->canPlayerReceiveItem($target)) {
                            $specialEffects[] = [
                                'type' => 'item_blocked',
                                'player' => $charName,
                                'item' => $drawnItem->name,
                                'description' => "{$charName}'s inventory is full! {$drawnItem->name} was lost.",
                            ];
                        } else {
                            GamePlayerItem::create([
                                'game_player_id' => $target->id,
                                'item_id' => $drawnItem->id,
                                'acquired_round' => $game->current_round,
                                'is_cursed' => false,
                                'is_used' => false,
                            ]);
                            $specialEffects[] = [
                                'type' => 'draw_item',
                                'phase' => 'negative',
                                'player' => $charName,
                                'item' => $drawnItem->name,
                                'description' => "{$charName} found {$drawnItem->name}!",
                            ];
                        }
                    }
                    continue;
                }
                if ($stat === 'draw_curse') {
                    $curseResult = $this->drawCursesFromDeck($game, $hand->game_player_id, $hand->player->character->name);
                    if ($curseResult) {
                        $pendingCurses[] = $curseResult['pending'];
                        $specialEffects[] = $curseResult['effect'];
                    }
                    continue;
                }
                if ($stat === 'discard_item') {
                    // Find a random player who has items and discard one
                    $playersWithItems = $players->filter(function ($p) {
                        return $p->items->isNotEmpty();
                    });
                    if ($playersWithItems->isNotEmpty()) {
                        $target = $playersWithItems->random();
                        $item = $target->items->random();
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
                if ($stat === 'bonus_score') {
                    $game->bonus_score = ($game->bonus_score ?? 0) + (int) $change;
                    $specialEffects[] = [
                        'type' => 'bonus_score',
                        'phase' => 'negative',
                        'value' => (int) $change,
                        'description' => "Kingdom renown decreased by {$change}!",
                    ];
                    continue;
                }
                if ($stat === 'end_game_modifier') {
                    $game->score_modifier = ($game->score_modifier ?? 0) + (float) $change;
                    $specialEffects[] = [
                        'type' => 'end_game_modifier',
                        'phase' => 'negative',
                        'value' => (float) $change,
                        'description' => "Final score modifier decreased by {$change}%!",
                    ];
                    continue;
                }
                $negativeEffects[$stat] = ($negativeEffects[$stat] ?? 0) + $change;
            }
        }

        // Apply double_negative curse: double all negative stat effects
        if ($this->hasDoublNegativeCurse($players, $game)) {
            foreach ($negativeEffects as $stat => $change) {
                if ($change < 0) {
                    $negativeEffects[$stat] = $change * 2;
                }
            }
        }

        // Apply curse per-round stat effects (cooperative)
        $cursePerRoundEffects = $this->applyCursePerRoundEffects($players, $game);
        foreach ($cursePerRoundEffects['stat_changes'] as $stat => $change) {
            $negativeEffects[$stat] = ($negativeEffects[$stat] ?? 0) + $change;
        }
        $specialEffects = array_merge($specialEffects, $cursePerRoundEffects['effects']);

        // House rule: draw_curse_per_round — trigger curse draw for a random player each round
        if (!empty($houseRules['draw_curse_per_round'])) {
            $target = $players->random();
            $charName = $target->character->name;
            $curseResult = $this->drawCursesFromDeck($game, $target->id, $charName);
            if ($curseResult) {
                $pendingCurses[] = $curseResult['pending'];
                $specialEffects[] = $curseResult['effect'];
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

                if ($grantPlayer && !$this->canPlayerReceiveItem($grantPlayer)) {
                    $playerChar = $grantPlayer->character->name;
                    $specialEffects[] = [
                        'type' => 'item_blocked',
                        'player' => $playerChar,
                        'item' => $item->name,
                        'description' => "{$playerChar}'s inventory is full! {$item->name} was lost.",
                    ];
                    continue;
                }

                // Positive items go to inventory
                GamePlayerItem::create([
                    'game_player_id' => $grant['player_id'],
                    'item_id' => $item->id,
                    'acquired_round' => $game->current_round,
                    'is_used' => false,
                ]);
                if ($grantPlayer) {
                    $playerChar = $grantPlayer->character->name;
                    $specialEffects[] = [
                        'type' => 'draw_item',
                        'phase' => 'positive',
                        'player' => $playerChar,
                        'item' => $item->name,
                        'description' => "{$playerChar} received {$item->name}!",
                    ];
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

        // Score event mechanic: add/subtract bonus_score each round (cooperative only)
        if (!$game->isDuel() && $event && $event->mechanic === 'score_event') {
            $scorePerRound = (int) ($event->mechanic_data['score_per_round'] ?? 0);
            if ($scorePerRound !== 0) {
                $game->bonus_score = ($game->bonus_score ?? 0) + $scorePerRound;
                $specialEffects[] = [
                    'type' => 'bonus_score',
                    'phase' => 'event',
                    'value' => $scorePerRound,
                    'description' => $scorePerRound > 0
                        ? "The {$event->title} adds +{$scorePerRound} renown!"
                        : "The {$event->title} costs {$scorePerRound} renown!",
                ];
            }
        }

        // Score modifier items are now single-use (score_bonus applied via useItem endpoint)
        // No passive score_per_round or score_multiplier processing needed

        $game->round_phase = 'resolving';

        // Save pending curses if any
        if (!empty($pendingCurses)) {
            $game->pending_curses = $pendingCurses;
        }

        $game->save();

        // Reset item_decided for all players after resolution
        $game->players()->update(['item_decided' => false]);

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
            'game' => $game->fresh(['players.character', 'players.user.activeKingdomStyle', 'players.items.item']),
            'player_items' => $game->players()->with('items.item')->get()->mapWithKeys(fn ($p) => [
                $p->player_number => $p->items,
            ]),
            'items_over_limit' => $this->getPlayersOverItemLimit($game),
            'pending_curses' => !empty($pendingCurses) ? $pendingCurses : null,
            'player_curses' => $game->players()->with('curses.curse')->get()->mapWithKeys(fn ($p) => [
                $p->player_number => $p->curses->map(fn ($c) => [
                    'id' => $c->id,
                    'curse' => $c->curse,
                    'acquired_round' => $c->acquired_round,
                ]),
            ]),
        ]);
    }

    /**
     * Advance to the next round or end the game.
     */
    public function nextRound(Game $game, Request $request): JsonResponse
    {
        $inResolving = $game->round_phase === 'resolving'
            || ($game->isDuel() && $game->duel_phase === 'resolving');
        if ($game->status !== 'active' || !$inResolving) {
            return response()->json(['error' => 'Not in resolving phase'], 422);
        }

        // Online mode: only host can advance
        if ($game->isOnline() && $request->user()->id !== $game->user_id) {
            return response()->json(['error' => 'Only the host can advance the round'], 403);
        }

        // Block advancement until all pending curses are chosen
        if (!empty($game->pending_curses)) {
            return response()->json(['error' => 'Pending curse choices must be resolved first'], 422);
        }

        // === DUEL MODE ===
        if ($game->isDuel()) {
            return $this->nextRoundDuel($game);
        }

        // === COOPERATIVE MODE ===
        // Check game over conditions
        $gameOverReason = $game->checkStatBounds();
        if ($gameOverReason) {
            $game->computeFinalScore();
            $game->update([
                'status' => 'completed',
                'round_phase' => 'complete',
                'win' => false,
                'final_score' => $game->final_score,
            ]);

            $completionSummary = app(GameCompletionService::class)->processCompletion($game);

            return response()->json([
                'game_over' => true,
                'win' => false,
                'reason' => $gameOverReason,
                'game' => $game->fresh(),
                'completion' => $completionSummary,
                'score_breakdown' => [
                    'base_score' => $game->baseScore(),
                    'year_multiplier' => $game->yearMultiplier(),
                    'balance_bonus' => $game->balanceBonus(),
                    'year_bonus' => $game->yearBonus(),
                    'stacking_bonus' => $game->stackingBonus(),
                    'bonus_score' => $game->bonus_score,
                    'score_modifier' => $game->score_modifier,
                    'final_score' => $game->final_score,
                ],
            ]);
        }

        // Check if we've completed all rounds
        if ($game->current_round >= $game->total_rounds) {
            $game->computeFinalScore();
            $game->update([
                'status' => 'completed',
                'round_phase' => 'complete',
                'win' => true,
                'final_score' => $game->final_score,
            ]);

            $completionSummary = app(GameCompletionService::class)->processCompletion($game);

            return response()->json([
                'game_over' => true,
                'win' => true,
                'reason' => 'You survived all ' . $game->total_rounds . ' rounds!',
                'game' => $game->fresh(),
                'completion' => $completionSummary,
                'score_breakdown' => [
                    'base_score' => $game->baseScore(),
                    'year_multiplier' => $game->yearMultiplier(),
                    'balance_bonus' => $game->balanceBonus(),
                    'year_bonus' => $game->yearBonus(),
                    'stacking_bonus' => $game->stackingBonus(),
                    'bonus_score' => $game->bonus_score,
                    'score_modifier' => $game->score_modifier,
                    'final_score' => $game->final_score,
                ],
            ]);
        }

        // Advance to next round
        $game->current_round++;
        $game->round_phase = 'selecting';
        $game->save();

        // Reset ability and item decision flags for new round
        $game->players()->update(['ability_active_this_round' => false, 'item_decided' => false]);

        // Check if the new round's event grants items
        $event = $this->getCurrentEvent($game);
        if ($event && $event->mechanic === 'grant_items') {
            $players = $game->players()->get();
            foreach ($players as $player) {
                if (!$this->canPlayerReceiveItem($player)) {
                    continue;
                }
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
                $gameOverData = [
                    'game_over' => true,
                    'winner_player_number' => $kingdom->player->player_number,
                    'reason' => 'Player ' . $kingdom->player->player_number . ' achieved 3 stats at maximum!',
                    'game' => $game->fresh(),
                    'player_kingdoms' => $kingdoms,
                    'completion' => $completionSummary,
                ];
                if ($game->isOnline()) {
                    broadcast(new DuelGameOver($game->id, $gameOverData));
                }
                return response()->json($gameOverData);
            }
            if ($result === 'loss') {
                $winnerNumber = $kingdom->player->player_number === 1 ? 2 : 1;
                $game->update([
                    'status' => 'completed',
                    'round_phase' => 'complete',
                    'winner_player_number' => $winnerNumber,
                ]);
                $completionSummary = app(GameCompletionService::class)->processCompletion($game);
                $gameOverData = [
                    'game_over' => true,
                    'winner_player_number' => $winnerNumber,
                    'reason' => 'Player ' . $kingdom->player->player_number . '\'s kingdom collapsed!',
                    'game' => $game->fresh(),
                    'player_kingdoms' => $kingdoms,
                    'completion' => $completionSummary,
                ];
                if ($game->isOnline()) {
                    broadcast(new DuelGameOver($game->id, $gameOverData));
                }
                return response()->json($gameOverData);
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

            $gameOverData = [
                'game_over' => true,
                'winner_player_number' => $winnerNumber,
                'reason' => "Campaign complete! Player {$winnerNumber} wins with the stronger kingdom.",
                'game' => $game->fresh(),
                'player_kingdoms' => $kingdoms,
                'completion' => $completionSummary,
            ];
            if ($game->isOnline()) {
                broadcast(new DuelGameOver($game->id, $gameOverData));
            }
            return response()->json($gameOverData);
        }

        // Advance to next round, swap offerer (still used for sequential rolling order)
        $game->current_round++;
        $game->round_phase = 'selecting';
        $game->duel_phase = 'choosing';
        $game->offerer_player_number = $game->offerer_player_number === 1 ? 2 : 1;
        if ($game->isOnline() && $game->turn_time_limit) {
            $game->turn_started_at = now();
        }
        $game->save();

        if ($game->isOnline() && $game->turn_time_limit) {
            $this->dispatchTurnTimerJobs($game);
        }

        // Reset ability and item decision flags for new round
        $game->players()->update(['ability_active_this_round' => false, 'item_decided' => false]);

        // Deal duel cards (2 to each player)
        $this->dealDuelCardsForRound($game);

        $showResponse = $this->show($game->fresh());

        if ($game->isOnline()) {
            $showData = json_decode($showResponse->getContent(), true);
            broadcast(new NextRoundStarted($game->id, $showData));
            $this->notifyPlayer($game, 1, 'Round ' . $game->current_round . ' — choose your card!');
            $this->notifyPlayer($game, 2, 'Round ' . $game->current_round . ' — choose your card!');
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

        $isOfferer = $playerNumber === $game->offerer_player_number;

        $cards = [];

        if ($game->duel_phase === 'choosing') {
            // Each player sees their own 2 cards (full details)
            $myHands = $hands->where('game_player_id', $player->id);
            foreach ($myHands as $h) {
                $cards[] = [
                    'hand_id' => $h->id,
                    'card' => $h->card,
                    'revealed' => true,
                ];
            }
        } elseif (in_array($game->duel_phase, ['rolling_offerer', 'rolling_chooser', 'rolling', 'resolving'])) {
            // Each player sees their 2 cards (kept + received from opponent)
            $myHands = $hands->where('game_player_id', $player->id);
            foreach ($myHands as $h) {
                $cards[] = [
                    'hand_id' => $h->id,
                    'card' => $h->card,
                    'revealed' => true,
                ];
            }
        }

        $items = GamePlayerItem::where('game_player_id', $player->id)
            ->with('item')
            ->get();

        // Calculate active dice count for duel (base 4, with event reduce_dice using duel override)
        $duelHandEvent = $this->getCurrentEvent($game);
        $duelHandTempReduction = 0;
        if ($duelHandEvent && $duelHandEvent->getDuelMechanic() === 'reduce_dice') {
            $duelHandTempReduction = $duelHandEvent->getDuelMechanicData()['amount'] ?? 0;
        }
        $diceCount = max(1, 4 - $player->lost_dice - $duelHandTempReduction);

        // No difficulty scaling in duel mode (co-op only)
        $scaling = 0;

        // Gather this player's dice faces for odds calculation (using duel stats)
        $player->load(['character', 'items.item']);
        $allDiceFaces = [];
        $activeDice = $diceCount;
        $pDice = $player->character->getDuelDice();
        $pWildValue = $player->character->getDuelWildValue();
        for ($di = 0; $di < $activeDice; $di++) {
            $die = $pDice[$di % count($pDice)];
            $faces = array_map(fn ($f) => $f === 'WILD' ? $pWildValue : (int) $f, $die);
            $allDiceFaces[] = $faces;
        }

        // Sum roll modifiers from items used this round (single-use system, using duel effects)
        $totalRollMod = 0;
        $totalDiffMod = 0;
        $usedItem = $player->items->firstWhere('used_round', $game->current_round);
        if ($usedItem) {
            $eff = $usedItem->item->getDuelEffect() ?? [];
            $bt = $eff['bonus_type'] ?? '';
            $bv = (int) ($eff['bonus_value'] ?? 0);
            if ($bt === 'roll_bonus' || $bt === 'roll_penalty') {
                $totalRollMod += $bv;
            } elseif ($bt === 'difficulty_increase') {
                $totalDiffMod += abs($bv);
            } elseif ($bt === 'difficulty_reduction') {
                $totalDiffMod -= abs($bv);
            }
        }

        // Apply scaling and odds to cards (using duel stats)
        $cardsWithOdds = array_map(function ($c) use ($scaling, $allDiceFaces, $totalRollMod, $totalDiffMod) {
            if (isset($c['card']) && is_object($c['card'])) {
                $card = $c['card'];
                $cardData = $card->toArray();
                $baseDiff = $card->getDuelDifficulty();
                $cardData['difficulty'] = $baseDiff + $scaling;
                $cardData['positive_effects'] = $card->getDuelPositiveEffects();
                $cardData['negative_effects'] = $card->getDuelNegativeEffects();
                $target = $baseDiff + $scaling + $totalDiffMod;
                $c['card'] = $cardData;
                $c['success_odds'] = $this->calculateSuccessOdds($allDiceFaces, $totalRollMod, $target);
            }
            return $c;
        }, $cards);

        return response()->json([
            'player_number' => $playerNumber,
            'round' => $game->current_round,
            'duel_phase' => $game->duel_phase,
            'is_offerer' => $isOfferer,
            'cards' => $cardsWithOdds,
            'items' => $items,
            'dice_count' => $diceCount,
            'character_dice' => $player->character->getDuelDice(),
            'character_wild_value' => $player->character->getDuelWildValue(),
            'character_wild_ability' => $player->character->getDuelWildAbilityDescription(),
        ]);
    }

    /**
     * Player selects which card to keep (the other is sent to opponent).
     * Both players submit independently; when both are done, cards swap and rolling begins.
     */
    public function duelSelect(Game $game, Request $request): JsonResponse
    {
        if (!$game->isDuel() || $game->duel_phase !== 'choosing') {
            return response()->json(['error' => 'Not in choosing phase'], 422);
        }

        $validated = $request->validate([
            'kept_hand_id' => 'required|integer|exists:game_player_hands,id',
        ]);

        // Determine which player is submitting
        $user = $request->user();
        $players = $game->players()->orderBy('player_number')->get();
        $submittingPlayer = null;

        if ($game->isOnline()) {
            $submittingPlayer = $players->firstWhere('user_id', $user?->id);
            if (!$submittingPlayer) {
                return response()->json(['error' => 'You are not in this game'], 403);
            }
        } else {
            // Pass-and-play / single-player: determine from the hand ownership
            $keptHand = GamePlayerHand::find($validated['kept_hand_id']);
            if ($keptHand) {
                $submittingPlayer = $players->firstWhere('id', $keptHand->game_player_id);
            }
        }

        if (!$submittingPlayer) {
            return response()->json(['error' => 'Could not determine player'], 422);
        }

        $opponent = $players->firstWhere('player_number', $submittingPlayer->player_number === 1 ? 2 : 1);

        // Get this player's hands for this round
        $myHands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->where('game_player_id', $submittingPlayer->id)
            ->get();

        $keptHand = $myHands->firstWhere('id', $validated['kept_hand_id']);
        if (!$keptHand) {
            return response()->json(['error' => 'Invalid hand selection — not your card'], 422);
        }

        // Check if this player already submitted
        $alreadySubmitted = $myHands->whereNotNull('offered_to_player_id')->isNotEmpty();
        if ($alreadySubmitted) {
            return response()->json(['error' => 'You have already selected'], 422);
        }

        // Mark the OTHER hand as the one being sent to opponent
        foreach ($myHands as $hand) {
            if ($hand->id !== $keptHand->id) {
                $hand->update(['offered_to_player_id' => $opponent->id]);
            }
        }

        // Check if both players have now submitted
        $allHands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->get();

        $submittedCount = $allHands->whereNotNull('offered_to_player_id')->count();

        if ($submittedCount < 2) {
            // If online game with a bot opponent, trigger the bot to select
            if ($game->isOnline() && $opponent->is_bot) {
                $botDelay = rand(3, 6);
                ProcessBotTurn::dispatch($game->id, 'choosing_round_' . $game->current_round)
                    ->delay(now()->addSeconds($botDelay));
            }

            // Still waiting for opponent
            return response()->json([
                'success' => true,
                'waiting' => true,
                'duel_phase' => 'choosing',
            ]);
        }

        // Both submitted — swap sent cards
        $sentHands = $allHands->whereNotNull('offered_to_player_id');
        foreach ($sentHands as $hand) {
            $hand->update(['game_player_id' => $hand->offered_to_player_id]);
        }

        // Determine rolling phase — simultaneous for all except pass-and-play
        $isPassAndPlay = $game->game_mode === 'pass_and_play';
        $rollingPhase = $isPassAndPlay ? 'rolling_offerer' : 'rolling';
        $updateData = ['duel_phase' => $rollingPhase];
        if ($game->isOnline() && $game->turn_time_limit) {
            $updateData['turn_started_at'] = now();
        }
        $game->update($updateData);

        if ($game->isOnline() && $game->turn_time_limit) {
            $this->dispatchTurnTimerJobs($game);
        }

        // Reload hands with updated assignments
        $updatedHands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->with('card')
            ->get();

        $p1 = $players->firstWhere('player_number', 1);
        $p2 = $players->firstWhere('player_number', 2);

        $p1Cards = $updatedHands->where('game_player_id', $p1->id)->values()->map(fn ($h) => ['hand_id' => $h->id, 'card' => $h->card]);
        $p2Cards = $updatedHands->where('game_player_id', $p2->id)->values()->map(fn ($h) => ['hand_id' => $h->id, 'card' => $h->card]);

        if ($game->isOnline()) {
            broadcast(new DuelChoiceMade(
                $game->id,
                $p1Cards->toArray(),
                $p2Cards->toArray(),
                $rollingPhase,
            ));
            $this->notifyPlayer($game, 1, 'Cards chosen — time to roll!');
            $this->notifyPlayer($game, 2, 'Cards chosen — time to roll!');
        }

        return response()->json([
            'success' => true,
            'waiting' => false,
            'duel_phase' => $rollingPhase,
            'player1_cards' => $p1Cards,
            'player2_cards' => $p2Cards,
        ]);
    }

    /**
     * A duel player rolls their character's dice against BOTH of their cards.
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
            // Simultaneous rolling — determine player from auth or player_number param (for bots)
            $playerNumber = $request->input('player_number');
            $user = $request->user();

            if ($playerNumber) {
                // Bot or explicit player number — find by player_number
                $rollingPlayer = ($offerer->player_number == $playerNumber) ? $offerer : $chooser;
            } elseif ($user) {
                if ($user->id === $offerer->user_id) {
                    $rollingPlayer = $offerer;
                } elseif ($user->id === $chooser->user_id) {
                    $rollingPlayer = $chooser;
                } else {
                    return response()->json(['error' => 'You are not in this game'], 403);
                }
            } else {
                return response()->json(['error' => 'Authentication required'], 403);
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

        $rollingPlayer->load(['character', 'items.item', 'curses.curse']);

        // Get BOTH of the player's cards for this round
        $hands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->where('game_player_id', $rollingPlayer->id)
            ->with('card')
            ->get();

        if ($hands->isEmpty()) {
            return response()->json(['error' => 'No cards assigned to rolling player'], 422);
        }

        // Calculate single-use item modifiers (items used this round by rolling player, using duel effects)
        $difficultyReduction = 0;
        $usedItem = $rollingPlayer->items->firstWhere('used_round', $game->current_round);
        if ($usedItem) {
            $effect = $usedItem->item->getDuelEffect() ?? [];
            $bonusType = $effect['bonus_type'] ?? '';
            if ($bonusType === 'difficulty_reduction') {
                $difficultyReduction += abs((int) ($effect['bonus_value'] ?? 0));
            } elseif ($bonusType === 'difficulty_increase') {
                $difficultyReduction -= abs((int) ($effect['bonus_value'] ?? 0));
            }
        }

        // Check for opponent-targeting items used against the rolling player
        $otherPlayer = $rollingPlayer->player_number === $offerer->player_number ? $chooser : $offerer;
        $otherPlayer->load('items.item');
        $opponentDebuffs = $otherPlayer->items
            ->where('used_round', $game->current_round)
            ->filter(fn ($pi) => ($pi->item->target ?? null) === 'opponent');
        foreach ($opponentDebuffs as $debuffItem) {
            $dEffect = $debuffItem->item->getDuelEffect() ?? [];
            $dBonusType = $dEffect['bonus_type'] ?? '';
            if ($dBonusType === 'increase_difficulty') {
                $difficultyReduction -= abs((int) ($dEffect['bonus_value'] ?? 0));
            }
        }

        // Roll dice ONCE (use duel-specific dice if available)
        $character = $rollingPlayer->character;
        $dice = $character->getDuelDice();
        $wildValue = $character->getDuelWildValue();
        $wildAbility = $character->getDuelWildAbility();
        $wildAbilityDesc = $character->getDuelWildAbilityDescription();

        // Check event reduce_dice for temporary dice reduction (using duel override)
        $duelTempDiceReduction = 0;
        $duelEvent = $this->getCurrentEvent($game);
        $duelEventMechanic = $duelEvent ? $duelEvent->getDuelMechanic() : null;
        $duelEventMechanicData = $duelEvent ? $duelEvent->getDuelMechanicData() : null;
        if ($duelEventMechanic === 'reduce_dice') {
            $duelTempDiceReduction = $duelEventMechanicData['amount'] ?? 0;
        }
        $activeDice = max(1, 4 - $rollingPlayer->lost_dice - $duelTempDiceReduction);

        $totalRoll = 0;
        $playerRolls = [];
        $wildTriggers = [];

        for ($dieIndex = 0; $dieIndex < $activeDice; $dieIndex++) {
            $die = $dice[$dieIndex % count($dice)];
            $faceIndex = random_int(0, 5);
            $face = $die[$faceIndex];
            $playerRolls[] = [
                'die' => $dieIndex + 1,
                'face' => $face,
                'face_index' => $faceIndex,
                'value' => $face === 'WILD' ? $wildValue : (int) $face,
            ];

            if ($face === 'WILD') {
                $totalRoll += $wildValue;
                $wildTriggers[] = [
                    'player_number' => $rollingPlayer->player_number,
                    'character_name' => $character->name,
                    'wild_value' => $wildValue,
                    'ability' => $wildAbility,
                    'ability_description' => $wildAbilityDesc,
                ];
            } else {
                $totalRoll += (int) $face;
            }
        }

        // Apply single-use item roll bonuses (items used this round, using duel effects)
        if ($usedItem) {
            $uEffect = $usedItem->item->getDuelEffect() ?? [];
            $uBonusType = ($uEffect['bonus_type'] ?? '');
            if ($uBonusType === 'roll_bonus' || $uBonusType === 'roll_penalty') {
                $totalRoll += (int) ($uEffect['bonus_value'] ?? 0);
            }
        }

        // Apply opponent debuff_roll items
        foreach ($opponentDebuffs as $debuffItem) {
            $dEffect = $debuffItem->item->getDuelEffect() ?? [];
            $dBonusType = $dEffect['bonus_type'] ?? '';
            if ($dBonusType === 'debuff_roll') {
                $totalRoll += (int) ($dEffect['bonus_value'] ?? 0);
            }
        }

        // Check for shield_negative item
        $hasShield = $usedItem && (($usedItem->item->getDuelEffect() ?? [])['bonus_type'] ?? '') === 'shield_negative';

        // Process wild abilities
        $abilityEffects = $this->processDuelWildAbilities($wildTriggers, $totalRoll);
        $totalRoll = $abilityEffects['adjusted_total'];

        // Apply manually activated ability
        if ($rollingPlayer->ability_active_this_round) {
            $manualAbility = $this->applyManualAbility($rollingPlayer, $totalRoll);
            $totalRoll = $manualAbility['adjusted_total'];
            $abilityEffects['descriptions'] = array_merge($abilityEffects['descriptions'], $manualAbility['descriptions']);
            $rollingPlayer->update(['ability_active_this_round' => false]);
        }

        // Process cards: sum difficulties for a single combined check
        $cardResults = [];
        $combinedEffects = [];
        $statKeys = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

        // No difficulty scaling in duel mode (co-op only)
        $scaling = 0;

        // Calculate curse difficulty modifiers for this player
        $curseDiffMod = 0;
        foreach ($rollingPlayer->curses as $pc) {
            $neg = $pc->curse->getDuelNegativeEffect();
            if (($neg['type'] ?? '') === 'difficulty_modifier') {
                $curseDiffMod += (int) ($neg['value'] ?? 1);
            }
        }
        // Check opponent's curses for opponent_difficulty (positive effect that hurts us)
        $otherPlayer->load('curses.curse');
        foreach ($otherPlayer->curses as $pc) {
            $pos = $pc->curse->getDuelPositiveEffect();
            if (($pos['type'] ?? '') === 'opponent_difficulty') {
                $curseDiffMod += (int) ($pos['value'] ?? 1);
            }
        }

        // Calculate combined difficulty from all cards (using duel stats)
        $totalDifficulty = 0;
        $cardData = [];
        foreach ($hands as $hand) {
            $card = $hand->card;
            $difficulty = max(1, $card->getDuelDifficulty() + $scaling - $difficultyReduction + $curseDiffMod);
            $totalDifficulty += $difficulty;
            $cardData[] = ['card' => $card, 'difficulty' => $difficulty];
        }

        // One combined check: roll vs sum of all card difficulties
        $success = $totalRoll >= $totalDifficulty;

        $duelHouseRules = $game->custom_rules['house_rules'] ?? [];
        $duelPendingCurses = [];
        $duelSpecialEffects = [];
        foreach ($cardData as $cd) {
            $card = $cd['card'];
            $difficulty = $cd['difficulty'];

            $cardEffects = [];
            // Negative effects ALWAYS apply (unless shielded or no_negative_effects house rule)
            if (!$hasShield && empty($duelHouseRules['no_negative_effects'])) {
                foreach (($card->getDuelNegativeEffects() ?? []) as $stat => $change) {
                    if ($stat === 'draw_curse') {
                        $curseResult = $this->drawCursesFromDeck($game, $rollingPlayer->id, $character->name);
                        if ($curseResult) {
                            $duelPendingCurses[] = $curseResult['pending'];
                            $duelSpecialEffects[] = $curseResult['effect'];
                        }
                        continue;
                    }
                    if (in_array($stat, $statKeys)) {
                        $cardEffects[$stat] = ($cardEffects[$stat] ?? 0) + $change;
                    }
                }
            }
            // Positive effects only on success (all-or-nothing)
            if ($success) {
                foreach (($card->getDuelPositiveEffects() ?? []) as $stat => $change) {
                    if ($stat === 'draw_curse') {
                        $curseResult = $this->drawCursesFromDeck($game, $rollingPlayer->id, $character->name);
                        if ($curseResult) {
                            $duelPendingCurses[] = $curseResult['pending'];
                            $duelSpecialEffects[] = $curseResult['effect'];
                        }
                        continue;
                    }
                    if (in_array($stat, $statKeys)) {
                        $val = !empty($duelHouseRules['double_positive_effects']) && $change > 0 ? $change * 2 : $change;
                        $cardEffects[$stat] = ($cardEffects[$stat] ?? 0) + $val;
                    }
                }
            }

            $cardResults[] = [
                'card' => $card,
                'difficulty' => $difficulty,
                'success' => $success,
                'effects' => $cardEffects,
            ];

            // Merge into combined effects
            foreach ($cardEffects as $stat => $change) {
                $combinedEffects[$stat] = ($combinedEffects[$stat] ?? 0) + $change;
            }
        }

        // Apply double_negative curse for duel: double negative stat effects
        $hasDoubleNeg = $rollingPlayer->curses->contains(fn ($pc) =>
            ($pc->curse->getDuelNegativeEffect()['type'] ?? '') === 'double_negative'
        );
        if ($hasDoubleNeg) {
            foreach ($combinedEffects as $stat => $change) {
                if ($change < 0) {
                    $combinedEffects[$stat] = $change * 2;
                }
            }
        }

        // Apply curse per-round stat effects for this player (duel)
        $duelCurseEffects = [];
        foreach ($rollingPlayer->curses as $pc) {
            $isDuel = true;
            $neg = $pc->curse->getDuelNegativeEffect();
            $pos = $pc->curse->getDuelPositiveEffect();
            if (($neg['type'] ?? '') === 'stat_per_round' && isset($neg['stat'], $neg['value'])) {
                $duelCurseEffects[$neg['stat']] = ($duelCurseEffects[$neg['stat']] ?? 0) + (int) $neg['value'];
            }
            if (($pos['type'] ?? '') === 'stat_per_round' && isset($pos['stat'], $pos['value'])) {
                $duelCurseEffects[$pos['stat']] = ($duelCurseEffects[$pos['stat']] ?? 0) + (int) $pos['value'];
            }
        }
        foreach ($duelCurseEffects as $stat => $change) {
            $combinedEffects[$stat] = ($combinedEffects[$stat] ?? 0) + $change;
        }

        // House rule: draw_curse_per_round — trigger curse draw for the rolling player each roll
        $duelHouseRulesForCurse = $game->custom_rules['house_rules'] ?? [];
        if (!empty($duelHouseRulesForCurse['draw_curse_per_round'])) {
            $curseResult = $this->drawCursesFromDeck($game, $rollingPlayer->id, $character->name);
            if ($curseResult) {
                $duelPendingCurses[] = $curseResult['pending'];
                $duelSpecialEffects[] = $curseResult['effect'];
            }
        }

        // Apply duel event stat modifiers (using duel override)
        if ($duelEvent) {
            $duelEventMods = $duelEvent->getDuelStatModifiers();
            if ($duelEventMods) {
                foreach ($duelEventMods as $stat => $change) {
                    if (in_array($stat, $statKeys) && is_numeric($change)) {
                        $combinedEffects[$stat] = ($combinedEffects[$stat] ?? 0) + $change;
                    }
                }
            }
        }

        // Apply combined effects to kingdom
        $kingdom = GamePlayerKingdom::where('game_id', $game->id)
            ->where('game_player_id', $rollingPlayer->id)
            ->first();

        if ($kingdom && !empty($combinedEffects)) {
            $kingdom->applyEffects($combinedEffects);
        }

        // Save round result
        $anySuccess = collect($cardResults)->contains('success', true);
        GameRoundResult::create([
            'game_id' => $game->id,
            'round_number' => $game->current_round,
            'card_id' => $hands->first()->card_id,
            'game_player_id' => $rollingPlayer->id,
            'success' => $anySuccess,
            'result_type' => 'duel',
            'dice_results' => [[
                'player_number' => $rollingPlayer->player_number,
                'character_name' => $character->name,
                'rolls' => $playerRolls,
                'active_dice' => $activeDice,
                'lost_dice' => $rollingPlayer->lost_dice,
            ]],
            'stat_totals' => ['total_roll' => $totalRoll],
            'effects_applied' => $combinedEffects,
            'wild_triggers' => $wildTriggers,
            'special_effects' => $duelSpecialEffects,
            'kingdom_snapshot' => $kingdom ? $kingdom->fresh()->only($statKeys) : null,
            'event_data' => $duelEvent ? ['id' => $duelEvent->id, 'name' => $duelEvent->name, 'description' => $duelEvent->description] : null,
        ]);

        // Save pending curses if any
        if (!empty($duelPendingCurses)) {
            $existing = $game->pending_curses ?? [];
            $game->pending_curses = array_merge($existing, $duelPendingCurses);
            $game->save();
        }

        // Check instant win/loss after roll
        $kingdom->refresh();
        $duelResult = $game->checkDuelStatBounds($kingdom);

        // Advance duel_phase
        if ($game->duel_phase === 'rolling') {
            $rollCount = GameRoundResult::where('game_id', $game->id)
                ->where('round_number', $game->current_round)
                ->count();

            if ($rollCount >= 2) {
                $game->update([
                    'duel_phase' => 'resolving',
                    'round_phase' => 'resolving',
                ]);
            }
        } elseif ($game->duel_phase === 'rolling_offerer') {
            $updateData = ['duel_phase' => 'rolling_chooser'];
            if ($game->isOnline() && $game->turn_time_limit) {
                $updateData['turn_started_at'] = now();
            }
            $game->update($updateData);

            if ($game->isOnline() && $game->turn_time_limit) {
                $this->dispatchTurnTimerJobs($game);
            }
        } else {
            $game->update([
                'duel_phase' => 'resolving',
                'round_phase' => 'resolving',
            ]);
        }

        $rollData = [
            'player_number' => $rollingPlayer->player_number,
            'character_name' => $character->name,
            'cards' => $cardResults,
            'rolls' => $playerRolls,
            'total_roll' => $totalRoll,
            'combined_effects' => $combinedEffects,
            'ability_effects' => $abilityEffects['descriptions'],
            'kingdom' => $kingdom->fresh(),
            'duel_result' => $duelResult,
            'special_effects' => $duelSpecialEffects,
            'pending_curses' => !empty($duelPendingCurses) ? $duelPendingCurses : null,
            'player_curses' => GamePlayerCurse::where('game_player_id', $rollingPlayer->id)->with('curse')->get(),
        ];

        if ($game->isOnline()) {
            $freshGame = $game->fresh();
            broadcast(new DuelRollComplete(
                $game->id,
                $rollingPlayer->player_number,
                $rollData,
                $freshGame->duel_phase,
            ));
            if ($freshGame->duel_phase === 'rolling_chooser') {
                $chooserNumber = $freshGame->offerer_player_number === 1 ? 2 : 1;
                $this->notifyPlayer($game, $chooserNumber, 'Your rival has rolled — now it\'s your turn!');
            }
        }

        return response()->json($rollData);
    }

    /**
     * Reroll dice using a reroll-type ability (rally or gamble) after seeing roll results.
     */
    public function duelReroll(Game $game, Request $request): JsonResponse
    {
        if (!$game->isDuel()) {
            return response()->json(['error' => 'Not a duel game'], 422);
        }

        $validated = $request->validate([
            'player_number' => 'required|integer',
        ]);

        // Determine which player is rerolling
        $offerer = $game->getOfferer();
        $chooser = $game->getChooser();
        $rollingPlayer = $game->players()->where('player_number', $validated['player_number'])->with(['character', 'items.item'])->first();

        if (!$rollingPlayer) {
            return response()->json(['error' => 'Invalid player'], 422);
        }

        $ability = $rollingPlayer->character->wild_ability;
        if (!in_array($ability, ['rally', 'gamble'])) {
            return response()->json(['error' => 'Character does not have a reroll ability'], 422);
        }

        if ($rollingPlayer->ability_uses <= 0) {
            return response()->json(['error' => 'No ability uses remaining'], 422);
        }

        if ($rollingPlayer->ability_active_this_round) {
            return response()->json(['error' => 'Ability already used this round'], 422);
        }

        // Find the existing round result for this player
        $roundResult = GameRoundResult::where('game_id', $game->id)
            ->where('round_number', $game->current_round)
            ->where('game_player_id', $rollingPlayer->id)
            ->first();

        if (!$roundResult) {
            return response()->json(['error' => 'No roll found to reroll'], 422);
        }

        // Reverse the previous effects on the kingdom
        $kingdom = GamePlayerKingdom::where('game_id', $game->id)
            ->where('game_player_id', $rollingPlayer->id)
            ->first();

        $oldEffects = $roundResult->effects_applied ?? [];
        if ($kingdom && !empty($oldEffects)) {
            $reversed = [];
            foreach ($oldEffects as $stat => $val) {
                $reversed[$stat] = -$val;
            }
            $kingdom->applyEffects($reversed);
        }

        // Get this player's cards for the round
        $hands = $game->playerHands()
            ->where('round_number', $game->current_round)
            ->where('game_player_id', $rollingPlayer->id)
            ->with('card')
            ->get();

        // Calculate single-use item modifiers (items used this round)
        $difficultyReduction = 0;
        $rerollUsedItem = $rollingPlayer->items->firstWhere('used_round', $game->current_round);
        if ($rerollUsedItem) {
            $effect = $rerollUsedItem->item->effect;
            $bonusType = $effect['bonus_type'] ?? '';
            if ($bonusType === 'difficulty_reduction') {
                $difficultyReduction += abs((int) ($effect['bonus_value'] ?? 0));
            } elseif ($bonusType === 'difficulty_increase') {
                $difficultyReduction -= abs((int) ($effect['bonus_value'] ?? 0));
            }
        }

        // Check for opponent debuffs targeting this player
        $rerollOtherPlayer = $rollingPlayer->player_number === $game->getOfferer()->player_number
            ? $game->getChooser() : $game->getOfferer();
        $rerollOtherPlayer->load('items.item');
        $rerollOpponentDebuffs = $rerollOtherPlayer->items
            ->where('used_round', $game->current_round)
            ->filter(fn ($pi) => ($pi->item->target ?? null) === 'opponent');
        foreach ($rerollOpponentDebuffs as $debuffItem) {
            $dEffect = $debuffItem->item->effect;
            if (($dEffect['bonus_type'] ?? '') === 'increase_difficulty') {
                $difficultyReduction -= abs((int) ($dEffect['bonus_value'] ?? 0));
            }
        }

        // Get the original dice results
        $character = $rollingPlayer->character;
        $dice = $character->dice;
        $activeDice = max(1, 4 - $rollingPlayer->lost_dice);
        $oldDiceResults = $roundResult->dice_results[0] ?? [];
        $oldRolls = $oldDiceResults['rolls'] ?? [];

        // Reroll based on ability type
        $playerRolls = [];
        $totalRoll = 0;
        $wildTriggers = [];

        if ($ability === 'rally') {
            // Rally: reroll only the lowest die, keep the rest
            $lowestIdx = 0;
            $lowestVal = PHP_INT_MAX;
            foreach ($oldRolls as $i => $roll) {
                $val = $roll['face'] === 'WILD' ? $character->wild_value : (int) $roll['face'];
                if ($val < $lowestVal) {
                    $lowestVal = $val;
                    $lowestIdx = $i;
                }
            }

            foreach ($oldRolls as $i => $roll) {
                if ($i === $lowestIdx) {
                    // Reroll this die
                    $die = $dice[$i] ?? $dice[0];
                    $faceIndex = random_int(0, 5);
                    $face = $die[$faceIndex];
                    $playerRolls[] = [
                        'die' => $i + 1,
                        'face' => $face,
                        'face_index' => $faceIndex,
                        'value' => $face === 'WILD' ? $character->wild_value : (int) $face,
                        'rerolled' => true,
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
                } else {
                    // Keep this die
                    $playerRolls[] = $roll;
                    if ($roll['face'] === 'WILD') {
                        $totalRoll += $character->wild_value;
                        $wildTriggers[] = [
                            'player_number' => $rollingPlayer->player_number,
                            'character_name' => $character->name,
                            'wild_value' => $character->wild_value,
                            'ability' => $character->wild_ability,
                            'ability_description' => $character->wild_ability_description,
                        ];
                    } else {
                        $totalRoll += (int) $roll['face'];
                    }
                }
            }
        } else {
            // Gamble: reroll ALL dice
            for ($dieIndex = 0; $dieIndex < $activeDice; $dieIndex++) {
                $die = $dice[$dieIndex % count($dice)];
                $faceIndex = random_int(0, 5);
                $face = $die[$faceIndex];
                $playerRolls[] = [
                    'die' => $dieIndex + 1,
                    'face' => $face,
                    'face_index' => $faceIndex,
                    'value' => $face === 'WILD' ? $character->wild_value : (int) $face,
                    'rerolled' => true,
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
        }

        // Apply single-use item roll bonuses (items used this round)
        if ($rerollUsedItem) {
            $uBonusType = ($rerollUsedItem->item->effect['bonus_type'] ?? '');
            if ($uBonusType === 'roll_bonus' || $uBonusType === 'roll_penalty') {
                $totalRoll += (int) ($rerollUsedItem->item->effect['bonus_value'] ?? 0);
            }
        }
        foreach ($rerollOpponentDebuffs as $debuffItem) {
            if (($debuffItem->item->effect['bonus_type'] ?? '') === 'debuff_roll') {
                $totalRoll += (int) ($debuffItem->item->effect['bonus_value'] ?? 0);
            }
        }

        // Process wild abilities from new roll
        $abilityEffects = $this->processDuelWildAbilities($wildTriggers, $totalRoll);
        $totalRoll = $abilityEffects['adjusted_total'];

        $abilityLabel = $ability === 'rally' ? 'Rally: rerolled lowest die!' : 'Gamble: rerolled all dice!';
        $abilityEffects['descriptions'][] = "{$character->name}'s {$abilityLabel}";

        // Process cards: sum difficulties for combined check
        $cardResults = [];
        $combinedEffects = [];
        $statKeys = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

        $totalDifficulty = 0;
        $cardData = [];
        foreach ($hands as $hand) {
            $card = $hand->card;
            $difficulty = max(1, $card->difficulty - $difficultyReduction);
            $totalDifficulty += $difficulty;
            $cardData[] = ['card' => $card, 'difficulty' => $difficulty];
        }

        $success = $totalRoll >= $totalDifficulty;

        foreach ($cardData as $cd) {
            $card = $cd['card'];
            $difficulty = $cd['difficulty'];

            $cardEffects = [];
            foreach (($card->negative_effects ?? []) as $stat => $change) {
                if (in_array($stat, $statKeys)) {
                    $cardEffects[$stat] = ($cardEffects[$stat] ?? 0) + $change;
                }
            }
            if ($success) {
                foreach (($card->positive_effects ?? []) as $stat => $change) {
                    if (in_array($stat, $statKeys)) {
                        $cardEffects[$stat] = ($cardEffects[$stat] ?? 0) + $change;
                    }
                }
            }

            $cardResults[] = [
                'card' => $card,
                'difficulty' => $difficulty,
                'success' => $success,
                'effects' => $cardEffects,
            ];

            foreach ($cardEffects as $stat => $change) {
                $combinedEffects[$stat] = ($combinedEffects[$stat] ?? 0) + $change;
            }
        }

        // Apply new effects to kingdom
        if ($kingdom && !empty($combinedEffects)) {
            $kingdom->applyEffects($combinedEffects);
        }

        // Mark ability as used
        $rollingPlayer->update([
            'ability_active_this_round' => true,
            'ability_uses' => $rollingPlayer->ability_uses - 1,
        ]);

        // Update the round result
        $anySuccess = collect($cardResults)->contains('success', true);
        $roundResult->update([
            'success' => $anySuccess,
            'dice_results' => [[
                'player_number' => $rollingPlayer->player_number,
                'character_name' => $character->name,
                'rolls' => $playerRolls,
                'active_dice' => $activeDice,
                'lost_dice' => $rollingPlayer->lost_dice,
            ]],
            'stat_totals' => ['total_roll' => $totalRoll],
            'effects_applied' => $combinedEffects,
            'wild_triggers' => $wildTriggers,
        ]);

        // Check win/loss after reroll
        $kingdom->refresh();
        $duelResult = $game->checkDuelStatBounds($kingdom);

        $rollData = [
            'player_number' => $rollingPlayer->player_number,
            'character_name' => $character->name,
            'cards' => $cardResults,
            'rolls' => $playerRolls,
            'total_roll' => $totalRoll,
            'combined_effects' => $combinedEffects,
            'ability_effects' => $abilityEffects['descriptions'],
            'kingdom' => $kingdom->fresh(),
            'duel_result' => $duelResult,
            'rerolled' => true,
            'remaining_uses' => $rollingPlayer->ability_uses,
        ];

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
            $chooser = $game->getChooser();
            $isSimultaneous = $game->duel_phase === 'rolling';
            $isOffererRolling = $game->duel_phase === 'rolling_offerer' && $player->id === $offerer->id;
            $isChooserRolling = $game->duel_phase === 'rolling_chooser' && $player->id === $chooser->id;
            if (!$isSimultaneous && !$isOffererRolling && !$isChooserRolling) {
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

        // Bot needs to select a card in choosing phase
        if ($phase === 'choosing') {
            // Safety: if bot has no cards (e.g. deck was exhausted), deal them now
            $botHands = GamePlayerHand::where('game_id', $game->id)
                ->where('game_player_id', $bot->id)
                ->where('round_number', $game->current_round)
                ->count();

            if ($botHands === 0) {
                $this->dealDuelCardsForRound($game);
            }

            $handId = $botService->decideDuelSelect($game, $bot);

            $fakeRequest = Request::create('', 'POST', ['kept_hand_id' => $handId]);
            $fakeRequest->setUserResolver(fn () => $botUser);
            return $this->duelSelect($game->fresh(), $fakeRequest);
        }

        // Bot is rolling
        if ($phase === 'rolling' || $phase === 'rolling_offerer' || $phase === 'rolling_chooser') {
            // Determine if bot should roll in this phase
            $shouldRoll = false;

            if ($phase === 'rolling') {
                // Simultaneous mode — bot always needs to roll (check not already rolled)
                $alreadyRolled = GameRoundResult::where('game_id', $game->id)
                    ->where('round_number', $game->current_round)
                    ->where('game_player_id', $bot->id)
                    ->exists();
                $shouldRoll = !$alreadyRolled;
            } elseif ($phase === 'rolling_offerer') {
                $shouldRoll = $game->offerer_player_number === $bot->player_number;
            } else {
                $chooser = $game->getChooser();
                $shouldRoll = $chooser && $chooser->is_bot;
            }

            if ($shouldRoll) {
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

        $completedGames = Game::with(['players.character', 'players.user', 'playerKingdoms'])
            ->where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->where('status', 'completed')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($game) use ($userId) {
                $myPlayer = $game->players->firstWhere('user_id', $userId);
                $myPlayerNumber = $myPlayer?->player_number;

                // Duel: use player kingdom scores; Cooperative: use shared stats
                if ($game->isDuel() && $game->playerKingdoms->isNotEmpty()) {
                    $myKingdom = $game->playerKingdoms->firstWhere('game_player_id', $myPlayer?->id);
                    $score = $myKingdom ? $myKingdom->totalScore() : 0;
                } else {
                    $score = $game->final_score ?? ($game->wealth + $game->influence + $game->security + $game->religion + $game->food + $game->happiness);
                }

                return [
                    'id' => $game->id,
                    'win' => $game->win,
                    'game_mode' => $game->game_mode,
                    'game_type' => $game->game_type ?? 'cooperative',
                    'score' => $score,
                    'winner_player_number' => $game->winner_player_number,
                    'my_player_number' => $myPlayerNumber,
                    'num_players' => $game->num_players,
                    'rounds_survived' => $game->current_round,
                    'total_rounds' => $game->total_rounds,
                    'played_at' => $game->updated_at->toDateTimeString(),
                    'players' => $game->players->map(fn ($p) => [
                        'character_name' => $p->character?->name,
                        'username' => $p->user?->name,
                    ])->values(),
                ];
            });

        return response()->json([
            'active_games' => $activeGames,
            'completed_games' => $completedGames,
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $participantGameIds = GamePlayer::where('user_id', $userId)->pluck('game_id');

        $query = Game::with(['players.character', 'players.user', 'playerKingdoms', 'rotatingEvent'])
            ->where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->whereIn('status', ['completed', 'cancelled'])
            ->orderByDesc('updated_at');

        // Filters
        if ($request->filled('game_type')) {
            $query->where('game_type', $request->input('game_type'));
        }
        if ($request->filled('game_mode')) {
            $query->where('game_mode', $request->input('game_mode'));
        }
        if ($request->filled('date_from')) {
            $query->where('updated_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('updated_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        $paginated = $query->paginate(10);

        $items = collect($paginated->items())->map(function ($game) use ($userId) {
            $myPlayer = $game->players->firstWhere('user_id', $userId);
            $myPlayerNumber = $myPlayer?->player_number;

            if ($game->isDuel() && $game->playerKingdoms->isNotEmpty()) {
                $myKingdom = $game->playerKingdoms->firstWhere('game_player_id', $myPlayer?->id);
                $score = $myKingdom ? $myKingdom->totalScore() : 0;
            } else {
                $score = $game->final_score ?? ($game->wealth + $game->influence + $game->security + $game->religion + $game->food + $game->happiness);
            }

            // Determine outcome
            $outcome = 'loss';
            if ($game->status === 'cancelled') {
                $outcome = 'cancelled';
            } elseif ($game->isDuel()) {
                if (!$game->winner_player_number) {
                    $outcome = 'draw';
                } elseif ($game->winner_player_number === $myPlayerNumber) {
                    $outcome = 'win';
                }
            } else {
                $outcome = $game->win ? 'win' : 'loss';
            }

            $duration = null;
            if ($game->created_at && $game->updated_at) {
                $duration = $game->created_at->diffInMinutes($game->updated_at);
            }

            return [
                'id' => $game->id,
                'outcome' => $outcome,
                'game_mode' => $game->game_mode,
                'game_type' => $game->game_type ?? 'cooperative',
                'score' => $score,
                'winner_player_number' => $game->winner_player_number,
                'my_player_number' => $myPlayerNumber,
                'num_players' => $game->num_players,
                'current_round' => $game->current_round,
                'total_rounds' => $game->total_rounds,
                'duration_minutes' => $duration,
                'played_at' => $game->updated_at->toIso8601String(),
                'share_token' => $game->share_token,
                'rotating_event' => $game->rotatingEvent ? [
                    'id' => $game->rotatingEvent->id,
                    'name' => $game->rotatingEvent->name,
                ] : null,
                'players' => $game->players->map(fn ($p) => [
                    'character_name' => $p->character?->name,
                    'character_image' => $p->character?->image_url,
                    'username' => $p->user?->name,
                    'player_number' => $p->player_number,
                ])->values(),
            ];
        });

        return response()->json([
            'data' => $items,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
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

        // If not enough cards left, reshuffle drawn cards back into the deck
        $remaining = $game->cardDeck()->where('is_drawn', false)->count();
        if ($remaining < $cardsPerRound) {
            $this->reshuffleCardDeck($game);
        }

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
            // If no specific stat, pick a random one
            if (!$stat || !in_array($stat, $stats)) {
                $stat = $stats[array_rand($stats)];
            }
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

        if ($bonusType === 'score_bonus') {
            $game->bonus_score = ($game->bonus_score ?? 0) + $bonusValue;
            $game->save();
            $sign = $bonusValue > 0 ? '+' : '';
            return "{$sign}{$bonusValue} renown";
        }

        if ($bonusType === 'end_game_multiplier') {
            $game->score_modifier = ($game->score_modifier ?? 0) + $bonusValue;
            $game->save();
            $sign = $bonusValue > 0 ? '+' : '';
            return "{$sign}{$bonusValue}% final score modifier";
        }

        return null;
    }

    /**
     * Deal 4 cards for a duel round — 2 to each player.
     */
    private function dealDuelCardsForRound(Game $game): void
    {
        $players = $game->players()->orderBy('player_number')->get();

        $remaining = $game->cardDeck()->where('is_drawn', false)->count();

        // If not enough cards left, reshuffle drawn cards back into the deck
        if ($remaining < 4) {
            $this->reshuffleCardDeck($game);
        }

        $deckCards = $game->cardDeck()
            ->where('is_drawn', false)
            ->orderBy('position')
            ->limit(4)
            ->get();

        foreach ($deckCards as $dc) {
            $dc->update(['is_drawn' => true]);
        }

        // Cards 0,1 → player 1; Cards 2,3 → player 2
        $cardIndex = 0;
        foreach ($players as $player) {
            for ($i = 0; $i < 2; $i++) {
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
     * Reshuffle all drawn cards back into the deck with new random positions.
     */
    private function reshuffleCardDeck(Game $game): void
    {
        $drawnCards = $game->cardDeck()->where('is_drawn', true)->get();

        if ($drawnCards->isEmpty()) {
            return;
        }

        // Get the next position after any remaining undrawn cards
        $maxPosition = $game->cardDeck()->max('position') ?? 0;

        $shuffled = $drawnCards->shuffle()->values();
        foreach ($shuffled as $i => $deckCard) {
            $deckCard->update([
                'is_drawn' => false,
                'position' => $maxPosition + $i + 1,
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
                if ($player->is_bot) continue;
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

        // Skip notifications for bot players
        $player = $game->players()->where('player_number', $playerNumber)->first();
        if ($player?->is_bot) {
            return;
        }

        // Skip push notification for the user who triggered this action (they're on the page)
        $currentUserId = request()->user()?->id;
        if ($currentUserId && $player?->user_id === $currentUserId) {
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
            $player = $player->load('user');
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

    public function claimAllAchievements(Request $request): JsonResponse
    {
        $user = $request->user();

        $unclaimed = UserAchievement::where('user_id', $user->id)
            ->whereNull('claimed_at')
            ->pluck('achievement_id');

        if ($unclaimed->isEmpty()) {
            return response()->json(['error' => 'No achievements to claim.'], 422);
        }

        $achievements = Achievement::whereIn('id', $unclaimed)->get();

        $totalXp = 0;
        $totalCoins = 0;
        $oldLevel = $user->level;

        foreach ($achievements as $achievement) {
            UserAchievement::where('user_id', $user->id)
                ->where('achievement_id', $achievement->id)
                ->update(['claimed_at' => now()]);

            $totalXp += $achievement->reward_xp;
            $totalCoins += $achievement->reward_coins ?? 0;

            if (($achievement->reward_coins ?? 0) > 0) {
                $user->recordCoinTransaction($achievement->reward_coins, 'earn', 'achievement', $achievement->id, "Claimed achievement: {$achievement->name}");
            }
        }

        $user->xp += $totalXp;
        $user->coins += $totalCoins;
        $user->level = User::calculateLevel($user->xp);
        $user->save();

        return response()->json([
            'xp_awarded' => $totalXp,
            'coins_awarded' => $totalCoins,
            'new_xp' => $user->xp,
            'new_level' => $user->level,
            'new_coins' => $user->coins,
            'leveled_up' => $user->level > $oldLevel,
            'count' => $achievements->count(),
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

    public function weeklyChallenge(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();
        $challenge = \App\Models\WeeklyChallenge::where('week_start', '<=', $today)
            ->where('week_end', '>=', $today)
            ->first();

        if (!$challenge) {
            return response()->json(null);
        }

        $entry = \App\Models\WeeklyChallengeEntry::where('user_id', $user->id)
            ->where('weekly_challenge_id', $challenge->id)
            ->first();

        return response()->json([
            'id' => $challenge->id,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'reward_xp' => $challenge->reward_xp,
            'reward_coins' => $challenge->reward_coins,
            'criteria' => $challenge->criteria,
            'progress' => $entry?->progress ?? 0,
            'target' => $challenge->criteria['count'] ?? 1,
            'completed' => $entry && $entry->completed_at !== null,
            'week_end' => $challenge->week_end->toDateString(),
        ]);
    }

    public function seasons(): JsonResponse
    {
        return response()->json(Season::orderByDesc('starts_at')->get());
    }

    public function seasonDetail(Request $request, Season $season): JsonResponse
    {
        $season->load(['rewards' => function ($q) {
            $q->with('rewardCharacter')->orderBy('placement');
        }]);

        // Get top 10 leaderboard for this season
        $leaderboard = DB::table('games')
            ->join('game_players', 'games.id', '=', 'game_players.game_id')
            ->where('games.season_id', $season->id)
            ->where('games.status', 'completed')
            ->whereNotNull('game_players.user_id')
            ->select(
                'game_players.user_id',
                DB::raw("COUNT(CASE WHEN games.win = TRUE OR game_players.player_number = games.winner_player_number THEN 1 END) as wins"),
                DB::raw('COUNT(*) as games_played')
            )
            ->groupBy('game_players.user_id')
            ->orderByDesc('wins')
            ->limit(10)
            ->get();

        $userIds = $leaderboard->pluck('user_id')->toArray();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $leaderboardData = $leaderboard->values()->map(function ($row, $index) use ($users) {
            $user = $users[$row->user_id] ?? null;
            return [
                'rank' => $index + 1,
                'user_id' => $row->user_id,
                'name' => $user?->name ?? 'Unknown',
                'wins' => $row->wins,
                'games_played' => $row->games_played,
                'elo_rating' => $user?->elo_rating ?? 1000,
            ];
        });

        // User's own rank
        $userRank = null;
        $userId = $request->user()?->id;
        if ($userId) {
            $userWins = DB::table('games')
                ->join('game_players', 'games.id', '=', 'game_players.game_id')
                ->where('games.season_id', $season->id)
                ->where('games.status', 'completed')
                ->where('game_players.user_id', $userId)
                ->where(function ($q) {
                    $q->where('games.win', true)
                       ->orWhereColumn('game_players.player_number', 'games.winner_player_number');
                })
                ->count();

            $rank = DB::table('games')
                ->join('game_players', 'games.id', '=', 'game_players.game_id')
                ->where('games.season_id', $season->id)
                ->where('games.status', 'completed')
                ->whereNotNull('game_players.user_id')
                ->select('game_players.user_id', DB::raw("COUNT(CASE WHEN games.win = TRUE OR game_players.player_number = games.winner_player_number THEN 1 END) as wins"))
                ->groupBy('game_players.user_id')
                ->havingRaw("COUNT(CASE WHEN games.win = TRUE OR game_players.player_number = games.winner_player_number THEN 1 END) > ?", [$userWins])
                ->count();

            $userRank = $rank + 1;
        }

        $totalPlayers = DB::table('games')
            ->join('game_players', 'games.id', '=', 'game_players.game_id')
            ->where('games.season_id', $season->id)
            ->where('games.status', 'completed')
            ->whereNotNull('game_players.user_id')
            ->distinct('game_players.user_id')
            ->count('game_players.user_id');

        return response()->json([
            'season' => $season,
            'leaderboard' => $leaderboardData,
            'user_rank' => $userRank,
            'total_players' => $totalPlayers,
        ]);
    }

    /**
     * Called by frontend when the turn timer hits 0.
     * Nudges the server to forfeit the game if the timer has expired.
     */
    public function checkTimeout(Request $request, Game $game): JsonResponse
    {
        $userId = $request->user()->id;

        $isParticipant = $game->players()->where('user_id', $userId)->exists();
        if (!$isParticipant) {
            return response()->json(['error' => 'You are not part of this game.'], 403);
        }

        if ($game->status !== 'active') {
            return response()->json(['processed' => false, 'status' => $game->status]);
        }

        if (!$game->turn_time_limit || !$game->turn_started_at) {
            return response()->json(['processed' => false, 'status' => 'no_timer']);
        }

        $forfeitService = app(DuelForfeitService::class);
        $processed = $forfeitService->handleTimeoutIfExpired($game);

        return response()->json([
            'processed' => $processed,
            'status' => $processed ? 'forfeited' : 'active',
        ]);
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

        if (in_array($game->status, ['completed', 'cancelled'])) {
            return response()->json(['error' => 'Game is already finished.'], 422);
        }

        $game->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json(['message' => 'Game cancelled.']);
    }

    /**
     * Check which players have more than 2 active (non-used) items.
     */
    /**
     * Get difficulty scaling based on round number (increases every 6 rounds).
     */
    private function getDifficultyScaling(Game $game): int
    {
        return (int) floor(($game->current_round - 1) / 6);
    }

    /**
     * Calculate percentage odds of success using DP convolution over dice faces.
     */
    private function calculateSuccessOdds(array $allDiceFaces, int $rollModifier, int $target): int
    {
        // Start with probability distribution: [0 => 1.0]
        $dist = [0 => 1.0];

        foreach ($allDiceFaces as $dieFaces) {
            $newDist = [];
            foreach ($dist as $sum => $prob) {
                foreach ($dieFaces as $faceValue) {
                    $newSum = $sum + $faceValue;
                    $newDist[$newSum] = ($newDist[$newSum] ?? 0) + $prob / count($dieFaces);
                }
            }
            $dist = $newDist;
        }

        // Sum probabilities where roll + modifier >= target
        $effectiveTarget = max(1, $target - $rollModifier);
        $successProb = 0;
        foreach ($dist as $sum => $prob) {
            if ($sum >= $effectiveTarget) {
                $successProb += $prob;
            }
        }

        return (int) round($successProb * 100);
    }

    /**
     * Check if a player can receive a new item (not all slots cursed).
     */
    private function canPlayerReceiveItem(GamePlayer $player): bool
    {
        $activeItems = GamePlayerItem::where('game_player_id', $player->id)
            ->where('is_used', false)
            ->count();

        return $activeItems < 2;
    }

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
     * Use an item from the player's inventory before rolling.
     */
    public function useItem(Game $game, Request $request): JsonResponse
    {
        if ($game->status !== 'active') {
            return response()->json(['error' => 'Game is not active'], 422);
        }

        $validated = $request->validate([
            'game_player_item_id' => 'required|integer',
            'player_number' => 'sometimes|integer',
        ]);

        // Determine the correct phase check
        $isDuel = $game->isDuel();
        if ($isDuel) {
            if (!in_array($game->duel_phase, ['rolling_offerer', 'rolling_chooser', 'rolling'])) {
                return response()->json(['error' => 'Not in a rolling phase'], 422);
            }
        } else {
            if ($game->round_phase !== 'selecting') {
                return response()->json(['error' => 'Not in selecting phase'], 422);
            }
        }

        $playerItem = GamePlayerItem::where('id', $validated['game_player_item_id'])
            ->whereHas('gamePlayer', fn ($q) => $q->where('game_id', $game->id))
            ->with(['item', 'gamePlayer'])
            ->first();

        if (!$playerItem) {
            return response()->json(['error' => 'Item not found in this game'], 404);
        }

        if ($playerItem->is_used) {
            return response()->json(['error' => 'Item is already used'], 422);
        }

        $player = $playerItem->gamePlayer;

        // Verify the requesting user owns this player (online mode)
        if ($game->isOnline() && $request->user()) {
            if ($request->user()->id !== $player->user_id) {
                return response()->json(['error' => 'This item does not belong to you'], 403);
            }
        }

        // Check player hasn't already used an item this round
        $alreadyUsed = GamePlayerItem::where('game_player_id', $player->id)
            ->where('used_round', $game->current_round)
            ->exists();

        if ($alreadyUsed) {
            return response()->json(['error' => 'Already used an item this round'], 422);
        }

        // Mark item as used
        $playerItem->update([
            'is_used' => true,
            'used_round' => $game->current_round,
        ]);

        // Apply immediate effects for stat_boost/heal_die/score_bonus/steal_stat
        $immediateDesc = null;
        $bonusType = $playerItem->item->effect['bonus_type'] ?? '';
        if (in_array($bonusType, ['stat_boost', 'heal_die', 'score_bonus', 'end_game_multiplier'])) {
            $immediateDesc = $this->applyImmediateItemEffect($game, $player, $playerItem->item);
        } elseif ($bonusType === 'steal_stat' && $game->isDuel()) {
            // Steal stat from opponent's kingdom
            $opponent = $game->players()->where('player_number', '!=', $player->player_number)->first();
            if ($opponent) {
                $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
                $stat = $stats[array_rand($stats)];
                $stealValue = (int) ($playerItem->item->effect['bonus_value'] ?? 1);
                $opponentKingdom = GamePlayerKingdom::where('game_id', $game->id)
                    ->where('game_player_id', $opponent->id)->first();
                $playerKingdom = GamePlayerKingdom::where('game_id', $game->id)
                    ->where('game_player_id', $player->id)->first();
                if ($opponentKingdom && $playerKingdom) {
                    $opponentKingdom->{$stat} = max(0, $opponentKingdom->{$stat} - $stealValue);
                    $playerKingdom->{$stat} = min(20, $playerKingdom->{$stat} + $stealValue);
                    $opponentKingdom->save();
                    $playerKingdom->save();
                    $immediateDesc = "Stole {$stealValue} {$stat} from opponent!";
                }
            }
        }

        // Mark player as decided
        $player->update(['item_decided' => true]);

        // Check if all players decided
        $allDecided = $this->allItemsDecided($game);

        // Broadcast for online mode
        if ($game->isOnline()) {
            broadcast(new \App\Events\PlayerItemDecided(
                $game->id,
                $player->player_number,
                true,
                $allDecided,
            ));
        }

        return response()->json([
            'used' => true,
            'immediate_description' => $immediateDesc,
            'item_decided' => true,
            'all_items_decided' => $allDecided,
            'player_items' => GamePlayerItem::where('game_player_id', $player->id)->with('item')->get(),
        ]);
    }

    /**
     * Skip using an item this round.
     */
    public function skipItem(Game $game, Request $request): JsonResponse
    {
        if ($game->status !== 'active') {
            return response()->json(['error' => 'Game is not active'], 422);
        }

        $validated = $request->validate([
            'player_number' => 'required|integer',
        ]);

        $player = $game->players()->where('player_number', $validated['player_number'])->first();
        if (!$player) {
            return response()->json(['error' => 'Invalid player number'], 422);
        }

        // Verify the requesting user owns this player (online mode)
        if ($game->isOnline() && $request->user()) {
            if ($request->user()->id !== $player->user_id) {
                return response()->json(['error' => 'Not your player'], 403);
            }
        }

        $player->update(['item_decided' => true]);

        $allDecided = $this->allItemsDecided($game);

        if ($game->isOnline()) {
            broadcast(new \App\Events\PlayerItemDecided(
                $game->id,
                $player->player_number,
                false,
                $allDecided,
            ));
        }

        return response()->json([
            'skipped' => true,
            'item_decided' => true,
            'all_items_decided' => $allDecided,
        ]);
    }

    /**
     * Check if all players have made their item decision for this round.
     */
    private function allItemsDecided(Game $game): bool
    {
        $players = $game->players()->with(['items' => fn ($q) => $q->where('is_used', false)])->get();

        foreach ($players as $player) {
            // Players with no usable items are auto-decided
            if ($player->items->isEmpty()) {
                continue;
            }
            if (!$player->item_decided) {
                return false;
            }
        }

        return true;
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

    public function customGameOptions(): JsonResponse
    {
        $cards = Card::select('id', 'title', 'category', 'available_cooperative', 'available_duel')->get();
        $events = Event::select('id', 'title', 'available_cooperative', 'available_duel')->get();
        $items = Item::select('id', 'name', 'available_cooperative', 'available_duel')->get();

        return response()->json([
            'cards' => $cards,
            'events' => $events,
            'items' => $items,
        ]);
    }

    /**
     * Choose a curse from the pending curse options.
     */
    public function chooseCurse(Game $game, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'curse_id' => 'required|integer',
            'player_number' => 'required|integer',
        ]);

        $pending = $game->pending_curses;
        if (empty($pending)) {
            return response()->json(['error' => 'No pending curses'], 422);
        }

        // Find the pending entry for this player
        $player = $game->players()->where('player_number', $validated['player_number'])->first();
        if (!$player) {
            return response()->json(['error' => 'Invalid player number'], 422);
        }

        // Online mode: verify user
        if ($game->isOnline() && $request->user()) {
            if ($request->user()->id !== $player->user_id) {
                return response()->json(['error' => 'Not your player'], 403);
            }
        }

        $entryIndex = null;
        foreach ($pending as $i => $entry) {
            if ((int) $entry['player_id'] === $player->id) {
                $entryIndex = $i;
                break;
            }
        }

        if ($entryIndex === null) {
            return response()->json(['error' => 'No pending curse for this player'], 422);
        }

        $entry = $pending[$entryIndex];
        $allowedIds = array_map('intval', $entry['curse_options']);
        if (!in_array((int) $validated['curse_id'], $allowedIds)) {
            return response()->json(['error' => 'Invalid curse choice'], 422);
        }

        $curse = Curse::find($validated['curse_id']);
        if (!$curse) {
            return response()->json(['error' => 'Curse not found'], 404);
        }

        // Create the player curse record
        $playerCurse = GamePlayerCurse::create([
            'game_player_id' => $player->id,
            'curse_id' => $curse->id,
            'acquired_round' => $game->current_round,
        ]);

        // Apply immediate effects (e.g. lose_die)
        $isDuel = $game->isDuel();
        $neg = $isDuel ? $curse->getDuelNegativeEffect() : $curse->negative_effect;
        $immediateDesc = null;
        if (($neg['type'] ?? '') === 'lose_die') {
            $dieLoss = (int) ($neg['value'] ?? 1);
            $player->increment('lost_dice', $dieLoss);
            $immediateDesc = "{$player->character->name} lost {$dieLoss} die from the curse!";
        }

        // Remove this entry from pending
        array_splice($pending, $entryIndex, 1);
        $game->pending_curses = empty($pending) ? null : array_values($pending);
        $game->save();

        return response()->json([
            'chosen_curse' => $curse,
            'immediate_effect' => $immediateDesc,
            'pending_curses' => $game->pending_curses,
            'player_curses' => GamePlayerCurse::where('game_player_id', $player->id)->with('curse')->get(),
        ]);
    }

    /**
     * Initialize the curse deck for a game.
     */
    private function initCurseDeck(Game $game): void
    {
        $rotatingEvent = $game->rotating_event_id ? $game->rotatingEvent : null;

        if ($rotatingEvent && $rotatingEvent->curse_pool) {
            $allCurses = Curse::whereIn('id', $rotatingEvent->curse_pool)->inRandomOrder()->get();
        } else {
            $allCurses = Curse::where('is_available', true)->inRandomOrder()->get();
        }

        if ($allCurses->isEmpty()) {
            return;
        }

        $curseRows = [];
        $now = now();
        foreach ($allCurses as $i => $curse) {
            $curseRows[] = [
                'game_id' => $game->id,
                'curse_id' => $curse->id,
                'position' => $i,
                'is_drawn' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        GameCurseDeck::insert($curseRows);
    }

    /**
     * Draw 2 curses from the deck for a player to choose from.
     */
    private function drawCursesFromDeck(Game $game, int $gamePlayerId, string $charName): ?array
    {
        $available = $game->curseDeck()
            ->where('is_drawn', false)
            ->orderBy('position')
            ->limit(2)
            ->get();

        if ($available->isEmpty()) {
            return null;
        }

        $curseIds = [];
        foreach ($available as $deckEntry) {
            $deckEntry->update(['is_drawn' => true]);
            $curseIds[] = $deckEntry->curse_id;
        }

        $curses = Curse::whereIn('id', $curseIds)->get();

        return [
            'pending' => [
                'player_id' => $gamePlayerId,
                'curse_options' => $curseIds,
                'curse_details' => $curses->toArray(),
            ],
            'effect' => [
                'type' => 'draw_curse',
                'player' => $charName,
                'description' => "{$charName} must choose a curse!",
            ],
        ];
    }

    /**
     * Get total curse difficulty modifier for all players (cooperative).
     */
    private function getCurseDifficultyModifier($players, Game $game): int
    {
        $mod = 0;
        foreach ($players as $player) {
            foreach ($player->curses as $pc) {
                $neg = $game->isDuel() ? $pc->curse->getDuelNegativeEffect() : $pc->curse->negative_effect;
                if (($neg['type'] ?? '') === 'difficulty_modifier') {
                    $mod += (int) ($neg['value'] ?? 1);
                }
            }
        }
        return $mod;
    }

    /**
     * Check if any player has the double_negative curse.
     */
    private function hasDoublNegativeCurse($players, Game $game): bool
    {
        foreach ($players as $player) {
            foreach ($player->curses as $pc) {
                $neg = $game->isDuel() ? $pc->curse->getDuelNegativeEffect() : $pc->curse->negative_effect;
                if (($neg['type'] ?? '') === 'double_negative') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Apply per-round curse stat effects (cooperative mode).
     */
    private function applyCursePerRoundEffects($players, Game $game): array
    {
        $statChanges = [];
        $effects = [];
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

        foreach ($players as $player) {
            foreach ($player->curses as $pc) {
                $neg = $pc->curse->negative_effect;
                $pos = $pc->curse->positive_effect;

                if (($neg['type'] ?? '') === 'stat_per_round' && isset($neg['stat'], $neg['value'])) {
                    if (in_array($neg['stat'], $stats)) {
                        $statChanges[$neg['stat']] = ($statChanges[$neg['stat']] ?? 0) + (int) $neg['value'];
                        $effects[] = [
                            'type' => 'curse_effect',
                            'player' => $player->character->name,
                            'description' => "{$player->character->name}'s curse: {$neg['value']} {$neg['stat']}",
                        ];
                    }
                }

                if (($pos['type'] ?? '') === 'stat_per_round' && isset($pos['stat'], $pos['value'])) {
                    if (in_array($pos['stat'], $stats)) {
                        $statChanges[$pos['stat']] = ($statChanges[$pos['stat']] ?? 0) + (int) $pos['value'];
                        $effects[] = [
                            'type' => 'curse_reward',
                            'player' => $player->character->name,
                            'description' => "{$player->character->name}'s curse reward: +{$pos['value']} {$pos['stat']}",
                        ];
                    }
                }
            }
        }

        return ['stat_changes' => $statChanges, 'effects' => $effects];
    }
}
