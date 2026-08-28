<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerItem;
use App\Models\Card;
use App\Models\Event;
use App\Models\Item;
use App\Models\User;
use App\Models\UserItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemLoadoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(array $overrides = []): Item
    {
        return Item::create(array_merge([
            'name' => 'Test Item '.uniqid(),
            'description' => 'A test item.',
            'effect' => ['bonus_type' => 'roll_bonus', 'bonus_value' => 1],
            'effect_type' => 'active',
            'is_negative' => false,
            'is_consumable' => false,
            'available_cooperative' => true,
            'available_duel' => false,
            'is_starter' => false,
            'cadence' => Item::CADENCE_PER_GAME,
            'type' => 'weapon',
        ], $overrides));
    }

    private function activeCoopGameWithItem(User $user, Item $item): GamePlayerItem
    {
        $character = Character::create([
            'name' => 'Advisor',
            'description' => 'x',
            'dice' => [[1, 2, 3, 4, 5, 6]],
        ]);

        $game = Game::create([
            'num_players' => 1,
            'total_rounds' => 12,
            'status' => 'active',
            'current_round' => 1,
            'round_phase' => 'selecting',
            'game_mode' => 'single',
            'game_type' => 'cooperative',
            'user_id' => $user->id,
        ]);

        $player = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'character_id' => $character->id,
            'player_number' => 1,
            'is_bot' => false,
        ]);

        return GamePlayerItem::create([
            'game_player_id' => $player->id,
            'item_id' => $item->id,
            'acquired_round' => 0,
        ]);
    }

    public function test_per_game_item_is_permanently_consumed_after_one_use(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem(['cadence' => Item::CADENCE_PER_GAME]);
        $playerItem = $this->activeCoopGameWithItem($user, $item);
        $game = $playerItem->gamePlayer->game;

        $response = $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/use-item", ['game_player_item_id' => $playerItem->id]);

        $response->assertOk()->assertJson(['used' => true]);

        $fresh = $playerItem->fresh();
        // The reported bug: a used item reappeared next round. A per_game item must now be
        // permanently spent (is_used = true) so it can never be used again.
        $this->assertTrue($fresh->is_used);
        $this->assertSame(1, $fresh->used_round);
    }

    public function test_per_round_item_is_not_consumed_and_frees_up_next_round(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem(['cadence' => Item::CADENCE_PER_ROUND]);
        $playerItem = $this->activeCoopGameWithItem($user, $item);
        $game = $playerItem->gamePlayer->game;

        $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/use-item", ['game_player_item_id' => $playerItem->id])
            ->assertOk();

        // Still owned (not permanently consumed), but marked as used this round.
        $afterRoundOne = $playerItem->fresh();
        $this->assertFalse($afterRoundOne->is_used);
        $this->assertSame(1, $afterRoundOne->used_round);

        // Advance a round; the same item may be used again.
        $game->update(['current_round' => 2]);

        $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/use-item", ['game_player_item_id' => $playerItem->id])
            ->assertOk()
            ->assertJson(['used' => true]);

        $afterRoundTwo = $playerItem->fresh();
        $this->assertFalse($afterRoundTwo->is_used);
        $this->assertSame(2, $afterRoundTwo->used_round);
    }

    public function test_passive_item_cannot_be_used_manually(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem(['cadence' => Item::CADENCE_PASSIVE, 'effect' => ['bonus_type' => 'stat_boost', 'bonus_value' => 1, 'stat' => 'wealth']]);
        $playerItem = $this->activeCoopGameWithItem($user, $item);
        $game = $playerItem->gamePlayer->game;

        $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/use-item", ['game_player_item_id' => $playerItem->id])
            ->assertStatus(422);

        $this->assertFalse($playerItem->fresh()->is_used);
    }

    public function test_loadout_rejects_more_than_three_items(): void
    {
        $user = User::factory()->create();
        $ids = collect(range(1, 4))->map(fn () => $this->makeItem(['is_starter' => true])->id)->all();

        $this->actingAs($user)
            ->putJson('/api/loadout', ['item_ids' => $ids])
            ->assertStatus(422);
    }

    public function test_loadout_rejects_unowned_items(): void
    {
        $user = User::factory()->create();
        $unowned = $this->makeItem(['is_starter' => false]);

        $this->actingAs($user)
            ->putJson('/api/loadout', ['item_ids' => [$unowned->id]])
            ->assertStatus(403);
    }

    public function test_loadout_equips_owned_starter_items(): void
    {
        $user = User::factory()->create();
        $a = $this->makeItem(['is_starter' => true]);
        $b = $this->makeItem(['is_starter' => true]);

        $this->actingAs($user)
            ->putJson('/api/loadout', ['item_ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJson(['equipped' => [$a->id, $b->id]]);

        $this->assertSame([$a->id, $b->id], $user->fresh()->equippedItemIds());
        $this->assertTrue(UserItem::where('user_id', $user->id)->where('item_id', $a->id)->value('equipped'));
    }

    public function test_game_start_seeds_the_players_equipped_loadout(): void
    {
        $user = User::factory()->create();
        $equipped = $this->makeItem(['is_starter' => true, 'available_cooperative' => true]);
        UserItem::create(['user_id' => $user->id, 'item_id' => $equipped->id, 'equipped' => true, 'slot' => 1]);

        // Cards/events must exist or the deck-building loop in start() has nothing to draw.
        Card::create([
            'title' => 'Test Card',
            'description' => 'x',
            'sort_order' => 1,
            'difficulty' => 5,
            'positive_effects' => ['wealth' => 1],
            'negative_effects' => ['wealth' => -1],
            'available_cooperative' => true,
            'available_duel' => false,
        ]);
        Event::create(['title' => 'Calm', 'effect' => 'A quiet season.', 'available_cooperative' => true, 'available_duel' => false]);

        $character = Character::create(['name' => 'Advisor', 'description' => 'x', 'dice' => [[1, 2, 3, 4, 5, 6]]]);
        $game = Game::create([
            'num_players' => 1,
            'total_rounds' => 3,
            'status' => 'setup',
            'game_mode' => 'single',
            'game_type' => 'cooperative',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson("/api/games/{$game->id}/start", ['characters' => [$character->id]])
            ->assertOk();

        $player = $game->fresh()->players()->where('player_number', 1)->first();
        $this->assertTrue(
            GamePlayerItem::where('game_player_id', $player->id)->where('item_id', $equipped->id)->exists(),
            'The player should start with their equipped loadout item.',
        );
    }
}
