<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RotatingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClearOnEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_a_card_pool_on_update_nulls_the_column(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $event = RotatingEvent::create([
            'name' => 'Test Event',
            'description' => 'A test rotating event',
            'game_type' => 'cooperative',
            'game_mode' => 'single',
            'card_pool' => [1, 2, 3],
            'affects_elo' => false,
            'reward_coins' => 0,
            'is_active' => true,
            'visibility' => 'all',
            'starts_at' => now(),
            'ends_at' => now()->addWeek(),
            'created_by' => $admin->id,
        ]);

        $this->assertSame([1, 2, 3], $event->fresh()->card_pool);

        // The admin edit form omits card_pool when the pool is cleared. The
        // update must null the column rather than retain the old value.
        $response = $this->actingAs($admin, 'web')
            ->putJson("/api/admin/rotating-events/{$event->id}", ['name' => 'Updated Event']);

        $response->assertOk();
        $this->assertNull($event->fresh()->card_pool);
        $this->assertSame('Updated Event', $event->fresh()->name);
    }
}
