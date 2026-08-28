<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Character;
use App\Models\DailyChallenge;
use App\Models\DailyChallengeEntry;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameCardDeck;
use App\Models\GamePlayer;
use App\Models\GamePlayerItem;
use App\Models\Item;
use App\Models\User;
use App\Services\GameCompletionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyChallengeRunTest extends TestCase
{
    use RefreshDatabase;

    private function seedContent(): array
    {
        $character = Character::create(['name' => 'Daily Advisor', 'description' => 'x', 'dice' => [[1, 2, 3, 4, 5, 6]]]);

        // Several distinct cards so a shuffled deck order is observable.
        for ($i = 1; $i <= 6; $i++) {
            Card::create([
                'title' => "Card {$i}",
                'description' => 'x',
                'sort_order' => $i,
                'difficulty' => 5,
                'positive_effects' => ['wealth' => 1],
                'negative_effects' => ['wealth' => -1],
                'available_cooperative' => true,
                'available_duel' => false,
            ]);
        }
        for ($i = 1; $i <= 4; $i++) {
            Event::create(['title' => "Event {$i}", 'effect' => 'x', 'available_cooperative' => true, 'available_duel' => false]);
        }

        $items = collect(range(1, 3))->map(fn (int $i) => Item::create([
            'name' => "Daily Item {$i}",
            'description' => 'x',
            'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1],
            'effect_type' => 'active',
            'is_negative' => false,
            'is_consumable' => false,
            'available_cooperative' => true,
            'available_duel' => false,
            'is_starter' => true,
            'cadence' => Item::CADENCE_PER_GAME,
            'type' => 'weapon',
        ])->id)->all();

        $challenge = DailyChallenge::create([
            'date' => Carbon::today(),
            'title' => 'Test Daily',
            'description' => 'x',
            'criteria' => [
                'mode' => 'cooperative',
                'rounds' => 3,
                'start' => ['all' => 8],
                'goal' => ['type' => 'stat_threshold_all', 'targets' => ['wealth' => 12]],
                'seed_character_id' => $character->id,
                'seed_loadout' => $items,
            ],
            'reward_xp' => 150,
            'is_manual' => false,
        ]);

        return ['character' => $character, 'challenge' => $challenge, 'items' => $items];
    }

    private function deckOrder(int $gameId): array
    {
        return GameCardDeck::where('game_id', $gameId)->orderBy('position')->pluck('card_id')->all();
    }

    public function test_two_players_get_an_identical_seeded_run(): void
    {
        $this->seedContent();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $gameIdA = $this->actingAs($userA)->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');
        $gameIdB = $this->actingAs($userB)->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');

        $gameA = Game::find($gameIdA);
        $gameB = Game::find($gameIdB);

        // Same seed → identical deck order, event order, and assigned character.
        $this->assertSame($gameA->daily_seed, $gameB->daily_seed);
        $this->assertSame($this->deckOrder($gameIdA), $this->deckOrder($gameIdB));
        $this->assertSame($gameA->event_order, $gameB->event_order);

        $charA = GamePlayer::where('game_id', $gameIdA)->value('character_id');
        $charB = GamePlayer::where('game_id', $gameIdB)->value('character_id');
        $this->assertSame($charA, $charB);
    }

    public function test_content_pools_and_house_rules_reproduce_identically_for_every_player(): void
    {
        $data = $this->seedContent();

        // Restrict the run to a subset of cards/events and enable a house rule.
        $cardPool = array_slice(Card::orderBy('id')->pluck('id')->all(), 0, 4);
        $eventPool = array_slice(Event::orderBy('id')->pluck('id')->all(), 0, 3);
        $data['challenge']->update([
            'criteria' => array_merge($data['challenge']->criteria, [
                'goal' => ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 14],
                'house_rules' => ['no_negative_effects' => true],
                'card_pool' => $cardPool,
                'event_pool' => $eventPool,
            ]),
        ]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $gameIdA = $this->actingAs($userA)->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');
        $gameIdB = $this->actingAs($userB)->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');

        $gameA = Game::find($gameIdA);
        $gameB = Game::find($gameIdB);

        // Deck: identical seeded order, and drawn only from the pool.
        $deckA = $this->deckOrder($gameIdA);
        $this->assertNotEmpty($deckA);
        $this->assertSame($deckA, $this->deckOrder($gameIdB), 'Two players must get the identical seeded deck order.');
        $this->assertSame([], array_values(array_diff(array_unique($deckA), $cardPool)), 'Deck must only contain pool cards.');

        // Event order: identical, and only from the event pool.
        $this->assertSame($gameA->event_order, $gameB->event_order, 'Event order must be identical for every player.');
        $this->assertSame([], array_values(array_diff(array_unique($gameA->event_order), $eventPool)), 'Events must only come from the pool.');

        // House rule persisted into custom_rules, identically for both.
        $this->assertSame(['no_negative_effects' => true], $gameA->custom_rules['house_rules']);
        $this->assertSame($gameA->custom_rules['house_rules'], $gameB->custom_rules['house_rules']);
    }

    public function test_random_starting_stats_house_rule_is_seeded_identically_for_every_player(): void
    {
        $data = $this->seedContent();
        $data['challenge']->update([
            'criteria' => array_merge($data['challenge']->criteria, [
                'house_rules' => ['random_starting_stats' => true],
            ]),
        ]);

        $gameIdA = $this->actingAs(User::factory()->create())->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');
        $gameIdB = $this->actingAs(User::factory()->create())->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');

        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $gameA = Game::find($gameIdA);
        $gameB = Game::find($gameIdB);

        $statsA = collect($stats)->mapWithKeys(fn (string $stat): array => [$stat => $gameA->{$stat}])->all();
        $statsB = collect($stats)->mapWithKeys(fn (string $stat): array => [$stat => $gameB->{$stat}])->all();

        // Randomised, yet identical for both players (seeded), and actually randomised (not all 8).
        $this->assertSame($statsA, $statsB, 'Seeded random starting stats must match for every player.');
        $this->assertNotSame(array_fill_keys($stats, 8), $statsA, 'Starting stats should have been randomised.');
    }

    public function test_generator_command_creates_a_deterministic_playable_challenge(): void
    {
        $this->seedContent(); // seeds a character + starter items (and today's challenge)
        $date = Carbon::today()->addDays(30)->toDateString();

        $this->artisan('app:generate-daily-challenge', ['--date' => $date])->assertSuccessful();

        $challenge = DailyChallenge::whereDate('date', $date)->first();
        $this->assertNotNull($challenge);
        $this->assertFalse($challenge->is_manual);
        $this->assertNotEmpty($challenge->description, 'A blurb should be generated (templated fallback with no API key).');
        $this->assertArrayHasKey('goal', $challenge->criteria);
        $this->assertArrayHasKey('per_stat', $challenge->criteria['start']);
        $this->assertArrayHasKey('house_rules', $challenge->criteria);

        // Regenerating the same date reproduces the identical goal (deterministic by date).
        $goal = $challenge->criteria['goal'];
        $this->artisan('app:generate-daily-challenge', ['--date' => $date, '--force' => true])->assertSuccessful();
        $this->assertSame($goal, DailyChallenge::whereDate('date', $date)->first()->criteria['goal']);
    }

    public function test_per_stat_starting_stats_are_applied_and_identical_for_every_player(): void
    {
        $data = $this->seedContent();
        $data['challenge']->update([
            'criteria' => array_merge($data['challenge']->criteria, [
                'start' => ['all' => 8, 'per_stat' => ['wealth' => 4, 'security' => 3]],
                'goal' => ['type' => 'stat_threshold', 'stat' => 'influence', 'value' => 14],
            ]),
        ]);

        $gameIdA = $this->actingAs(User::factory()->create())->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');
        $gameIdB = $this->actingAs(User::factory()->create())->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');

        $gameA = Game::find($gameIdA);
        $gameB = Game::find($gameIdB);

        // The named weak stats take the per-stat values; untouched stats keep the default 8.
        $this->assertSame(4, $gameA->wealth);
        $this->assertSame(3, $gameA->security);
        $this->assertSame(8, $gameA->influence);

        // Identical for every player.
        foreach (['wealth', 'influence', 'security', 'religion', 'food', 'happiness'] as $stat) {
            $this->assertSame($gameA->{$stat}, $gameB->{$stat}, "Stat {$stat} must match for both players.");
        }
    }

    public function test_daily_run_seeds_the_fixed_loadout_and_flags(): void
    {
        $data = $this->seedContent();
        $user = User::factory()->create();

        $gameId = $this->actingAs($user)->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');
        $game = Game::find($gameId);

        $this->assertTrue($game->is_daily);
        $this->assertSame("daily:".Carbon::today()->toDateString().":{$data['challenge']->id}", $game->daily_seed);

        $player = GamePlayer::where('game_id', $gameId)->first();
        $loadout = GamePlayerItem::where('game_player_id', $player->id)->pluck('item_id')->sort()->values()->all();
        $this->assertSame(collect($data['items'])->sort()->values()->all(), $loadout);
    }

    public function test_daily_deck_excludes_cards_the_assigned_character_cannot_beat(): void
    {
        // A weak character whose three dice top out at 3 each — max roll 9.
        $weak = Character::create(['name' => 'Weak', 'description' => 'x', 'dice' => [[1, 1, 2, 2, 3, 3], [1, 1, 2, 2, 3, 3], [1, 1, 2, 2, 3, 3]]]);

        Card::create(['title' => 'Easy', 'description' => 'x', 'sort_order' => 1, 'difficulty' => 6, 'positive_effects' => ['security' => 1], 'negative_effects' => ['security' => -1], 'available_cooperative' => true, 'available_duel' => false]);
        Card::create(['title' => 'Impossible', 'description' => 'x', 'sort_order' => 2, 'difficulty' => 15, 'positive_effects' => ['security' => 3], 'negative_effects' => ['security' => -1], 'available_cooperative' => true, 'available_duel' => false]);
        Event::create(['title' => 'Calm', 'effect' => 'x', 'available_cooperative' => true, 'available_duel' => false]);

        $item = Item::create(['name' => 'Basic', 'description' => 'x', 'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1], 'effect_type' => 'active', 'is_negative' => false, 'is_consumable' => false, 'available_cooperative' => true, 'available_duel' => false, 'is_starter' => true, 'cadence' => Item::CADENCE_PER_GAME, 'type' => 'weapon']);

        DailyChallenge::create([
            'date' => Carbon::today(),
            'title' => 'Weak Run',
            'description' => 'x',
            'criteria' => ['mode' => 'cooperative', 'rounds' => 3, 'start' => ['all' => 8], 'goal' => ['type' => 'stat_threshold', 'stat' => 'security', 'value' => 10], 'seed_character_id' => $weak->id, 'seed_loadout' => [$item->id]],
            'reward_xp' => 100,
            'is_manual' => false,
        ]);

        $user = User::factory()->create();
        $gameId = $this->actingAs($user)->postJson('/api/daily-challenge/start')->assertOk()->json('game_id');

        $cardIds = GameCardDeck::where('game_id', $gameId)->pluck('card_id')->unique();
        $maxDifficulty = Card::whereIn('id', $cardIds)->max('difficulty');

        $this->assertGreaterThan(0, $cardIds->count());
        $this->assertLessThanOrEqual(9, $maxDifficulty, 'No dealt card should exceed the character max roll of 9.');
    }

    public function test_only_one_attempt_is_allowed_per_day(): void
    {
        $this->seedContent();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/daily-challenge/start')->assertOk();
        // A second attempt is rejected with a conflict.
        $this->actingAs($user)->postJson('/api/daily-challenge/start')->assertStatus(409);

        $this->assertSame(1, Game::where('is_daily', true)->where('user_id', $user->id)->count());
    }

    private function makePastChallenge(array $data): DailyChallenge
    {
        return DailyChallenge::create([
            'date' => Carbon::yesterday(),
            'title' => 'Yesterday',
            'description' => 'x',
            'criteria' => [
                'mode' => 'cooperative', 'rounds' => 3, 'start' => ['all' => 8],
                'goal' => ['type' => 'stat_threshold', 'stat' => 'wealth', 'value' => 12],
                'seed_character_id' => $data['character']->id, 'seed_loadout' => $data['items'],
            ],
            'reward_xp' => 100,
            'is_manual' => false,
        ]);
    }

    public function test_non_premium_cannot_replay_a_past_challenge(): void
    {
        $past = $this->makePastChallenge($this->seedContent());
        $user = User::factory()->create(['is_premium' => false]);

        $this->actingAs($user)
            ->postJson("/api/daily-challenges/{$past->id}/start")
            ->assertStatus(403);
    }

    public function test_premium_can_replay_a_past_challenge(): void
    {
        $past = $this->makePastChallenge($this->seedContent());
        $user = User::factory()->create(['is_premium' => true, 'premium_expires_at' => now()->addYear()]);

        $this->actingAs($user)
            ->postJson("/api/daily-challenges/{$past->id}/start")
            ->assertOk()
            ->assertJsonStructure(['game_id']);
    }

    public function test_history_lists_past_challenges(): void
    {
        $this->makePastChallenge($this->seedContent());
        $user = User::factory()->create(['is_premium' => false]);

        $this->actingAs($user)
            ->getJson('/api/daily-challenges/history')
            ->assertOk()
            ->assertJson(['is_premium' => false])
            ->assertJsonCount(1, 'challenges');
    }

    public function test_challenges_endpoint_returns_today_and_platform_stats(): void
    {
        $data = $this->seedContent();
        $challenge = $data['challenge'];

        // Two other players finished: one won in 5 months, one lost.
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        DailyChallengeEntry::create(['user_id' => $winner->id, 'daily_challenge_id' => $challenge->id, 'status' => 'won', 'rounds_taken' => 5, 'completed_at' => now()]);
        DailyChallengeEntry::create(['user_id' => $loser->id, 'daily_challenge_id' => $challenge->id, 'status' => 'lost', 'completed_at' => now()]);

        $me = User::factory()->create();
        $response = $this->actingAs($me)->getJson('/api/challenges')->assertOk();

        $response->assertJsonPath('today.plays', 2)
            ->assertJsonPath('today.success_rate', 50)
            ->assertJsonPath('today.avg_rounds', 5)
            ->assertJsonPath('today.status', 'pending');
    }

    public function test_meeting_the_goal_wins_and_awards_xp(): void
    {
        $data = $this->seedContent();
        $user = User::factory()->create(['xp' => 0]);

        $game = Game::create([
            'num_players' => 1, 'total_rounds' => 3, 'status' => 'completed',
            'game_mode' => 'single', 'game_type' => 'cooperative', 'user_id' => $user->id,
            'is_daily' => true, 'daily_challenge_id' => $data['challenge']->id,
            'wealth' => 14, // meets the goal (>= 12)
        ]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $user->id, 'character_id' => $data['character']->id, 'player_number' => 1]);
        DailyChallengeEntry::create(['user_id' => $user->id, 'daily_challenge_id' => $data['challenge']->id, 'game_id' => $game->id, 'status' => 'in_progress', 'started_at' => now()]);

        app(GameCompletionService::class)->processCompletion($game->fresh());

        $entry = DailyChallengeEntry::where('user_id', $user->id)->first();
        $this->assertSame('won', $entry->status);
        $this->assertNotNull($entry->completed_at);
        $this->assertGreaterThanOrEqual(150, $user->fresh()->xp);
    }

    public function test_admin_can_create_a_manual_endless_challenge(): void
    {
        $data = $this->seedContent();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')->postJson('/api/admin/daily-challenges', [
            'date' => Carbon::today()->addDay()->toDateString(),
            'title' => 'Manual Race',
            'description' => 'Race to 16 Food.',
            'reward_xp' => 200,
            'criteria' => [
                'mode' => 'cooperative',
                'rounds' => 90,
                'start' => ['all' => 8],
                'goal' => ['type' => 'stat_threshold', 'stat' => 'food', 'value' => 16],
                'seed_character_id' => $data['character']->id,
                'seed_loadout' => array_slice($data['items'], 0, 2),
            ],
        ])->assertStatus(201);

        $challenge = DailyChallenge::where('title', 'Manual Race')->first();
        $this->assertNotNull($challenge);
        $this->assertTrue($challenge->is_manual);
        $this->assertSame('food', $challenge->criteria['goal']['stat']);
        $this->assertSame(16, $challenge->criteria['goal']['value']);
        $this->assertSame($data['character']->id, $challenge->criteria['seed_character_id']);
    }

    public function test_admin_rejects_a_challenge_missing_the_endless_goal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')->postJson('/api/admin/daily-challenges', [
            'date' => Carbon::today()->addDays(2)->toDateString(),
            'title' => 'Broken',
            'description' => 'No goal.',
            'reward_xp' => 100,
            'criteria' => ['mode' => 'cooperative', 'rounds' => 60, 'start' => ['all' => 8]],
        ])->assertStatus(422);
    }

    public function test_quitting_an_in_progress_daily_marks_the_entry_quit(): void
    {
        $data = $this->seedContent();
        $user = User::factory()->create();

        $game = Game::create([
            'num_players' => 1, 'total_rounds' => 3, 'status' => 'active',
            'game_mode' => 'single', 'game_type' => 'cooperative', 'user_id' => $user->id,
            'is_daily' => true, 'daily_challenge_id' => $data['challenge']->id,
            'current_round' => 4, 'wealth' => 5, // below the goal target of 12
        ]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $user->id, 'character_id' => $data['character']->id, 'player_number' => 1]);
        DailyChallengeEntry::create(['user_id' => $user->id, 'daily_challenge_id' => $data['challenge']->id, 'game_id' => $game->id, 'status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($user)
            ->postJson("/api/daily-challenges/{$game->id}/quit")
            ->assertOk()
            ->assertJsonPath('win', false);

        $entry = DailyChallengeEntry::where('user_id', $user->id)->first();
        // Recorded as a deliberate quit (distinct from a stat-collapse loss).
        $this->assertSame('quit', $entry->status);
        $this->assertNotNull($entry->completed_at);
        $this->assertNull($entry->rounds_taken);
        $this->assertSame('completed', $game->fresh()->status);
    }

    public function test_admin_can_list_and_delete_plays_so_a_player_can_replay(): void
    {
        $data = $this->seedContent();
        $challenge = $data['challenge'];
        $admin = User::factory()->create(['is_admin' => true]);
        $player = User::factory()->create(['name' => 'Sir Playsalot']);

        $game = Game::create([
            'num_players' => 1, 'total_rounds' => 3, 'status' => 'completed',
            'game_mode' => 'single', 'game_type' => 'cooperative', 'user_id' => $player->id,
            'is_daily' => true, 'daily_challenge_id' => $challenge->id,
            'current_round' => 7, 'final_score' => 240,
        ]);
        $entry = DailyChallengeEntry::create([
            'user_id' => $player->id, 'daily_challenge_id' => $challenge->id, 'game_id' => $game->id,
            'status' => 'won', 'rounds_taken' => 6, 'completed_at' => now(),
        ]);

        // The plays list shows the player, result, month reached and score.
        $this->actingAs($admin, 'web')
            ->getJson("/api/admin/daily-challenges/{$challenge->id}/entries")
            ->assertOk()
            ->assertJsonPath('entries.0.player', 'Sir Playsalot')
            ->assertJsonPath('entries.0.status', 'won')
            ->assertJsonPath('entries.0.months_reached', 6)
            ->assertJsonPath('entries.0.score', 240);

        // Deleting the play removes it and frees the player to attempt the daily again.
        $this->actingAs($admin, 'web')
            ->deleteJson("/api/admin/daily-challenge-entries/{$entry->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('daily_challenge_entries', ['id' => $entry->id]);
    }

    public function test_cannot_quit_another_players_daily_game(): void
    {
        $data = $this->seedContent();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $game = Game::create([
            'num_players' => 1, 'total_rounds' => 3, 'status' => 'active',
            'game_mode' => 'single', 'game_type' => 'cooperative', 'user_id' => $owner->id,
            'is_daily' => true, 'daily_challenge_id' => $data['challenge']->id,
        ]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $owner->id, 'character_id' => $data['character']->id, 'player_number' => 1]);

        $this->actingAs($intruder)
            ->postJson("/api/daily-challenges/{$game->id}/quit")
            ->assertStatus(403);

        $this->assertSame('active', $game->fresh()->status);
    }

    public function test_missing_the_goal_loses_and_awards_no_challenge_xp(): void
    {
        $data = $this->seedContent();
        $user = User::factory()->create(['xp' => 0]);

        $game = Game::create([
            'num_players' => 1, 'total_rounds' => 3, 'status' => 'completed',
            'game_mode' => 'single', 'game_type' => 'cooperative', 'user_id' => $user->id,
            'is_daily' => true, 'daily_challenge_id' => $data['challenge']->id,
            'wealth' => 5, // below the goal target of 12
        ]);
        GamePlayer::create(['game_id' => $game->id, 'user_id' => $user->id, 'character_id' => $data['character']->id, 'player_number' => 1]);
        DailyChallengeEntry::create(['user_id' => $user->id, 'daily_challenge_id' => $data['challenge']->id, 'game_id' => $game->id, 'status' => 'in_progress', 'started_at' => now()]);

        app(GameCompletionService::class)->processCompletion($game->fresh());

        $entry = DailyChallengeEntry::where('user_id', $user->id)->first();
        $this->assertSame('lost', $entry->status);
        $this->assertNotNull($entry->completed_at);
    }
}
