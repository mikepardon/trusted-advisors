<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeChestTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_available_for_a_user_who_has_never_claimed(): void
    {
        $user = User::factory()->create(['free_chest_claimed_at' => null]);

        $this->actingAs($user, 'web')->getJson('/api/free-chest')
            ->assertOk()
            ->assertJson([
                'available' => true,
                'next_available_at' => null,
            ]);
    }

    public function test_claiming_awards_coins_within_range_and_sets_the_cooldown(): void
    {
        $user = User::factory()->create(['coins' => 100, 'free_chest_claimed_at' => null]);

        $response = $this->actingAs($user, 'web')->postJson('/api/free-chest/claim')->assertOk();

        $coins = $response->json('coins');
        $this->assertGreaterThanOrEqual(20, $coins);
        $this->assertLessThanOrEqual(60, $coins);

        $fresh = $user->fresh();
        $this->assertSame(100 + $coins, $fresh->coins);
        $this->assertSame($fresh->coins, $response->json('new_coins'));
        $this->assertNotNull($fresh->free_chest_claimed_at);
        $this->assertNotNull($response->json('next_available_at'));
    }

    public function test_a_second_claim_within_the_cooldown_is_rejected(): void
    {
        $user = User::factory()->create(['free_chest_claimed_at' => null]);

        $this->actingAs($user, 'web')->postJson('/api/free-chest/claim')->assertOk();
        $this->actingAs($user, 'web')->postJson('/api/free-chest/claim')->assertStatus(422);
    }

    public function test_status_reports_unavailable_while_on_cooldown(): void
    {
        $user = User::factory()->create(['free_chest_claimed_at' => now()->subHour()]);

        $this->actingAs($user, 'web')->getJson('/api/free-chest')
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_status_is_available_again_once_the_cooldown_has_elapsed(): void
    {
        $user = User::factory()->create(['free_chest_claimed_at' => now()->subHours(13)]);

        $this->actingAs($user, 'web')->getJson('/api/free-chest')
            ->assertOk()
            ->assertJson(['available' => true]);
    }
}
