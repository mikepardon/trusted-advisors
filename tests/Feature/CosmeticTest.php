<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cosmetic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CosmeticTest extends TestCase
{
    use RefreshDatabase;

    private function title(string $slug, bool $default = false): Cosmetic
    {
        return Cosmetic::create([
            'type' => 'title',
            'slug' => $slug,
            'name' => ucfirst($slug),
            'rarity' => 'common',
            'value' => ucfirst($slug),
            'is_default' => $default,
            'is_available' => true,
            'sort' => 0,
        ]);
    }

    public function test_index_marks_default_cosmetics_owned_and_others_locked(): void
    {
        $default = $this->title('novice', default: true);
        $locked = $this->title('the-wise');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->getJson('/api/cosmetics')->assertOk();

        $byId = collect($response->json('cosmetics'))->keyBy('id');
        $this->assertTrue($byId[$default->id]['owned']);
        $this->assertFalse($byId[$locked->id]['owned']);
    }

    public function test_equipping_a_default_title_sets_the_active_slot(): void
    {
        $novice = $this->title('novice', default: true);
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->postJson("/api/cosmetics/{$novice->id}/equip")
            ->assertOk()
            ->assertJsonPath('active.title', 'novice');

        $this->assertSame('novice', $user->fresh()->active_title_slug);
    }

    public function test_cannot_equip_an_unowned_cosmetic(): void
    {
        $locked = $this->title('kingmaker');
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->postJson("/api/cosmetics/{$locked->id}/equip")
            ->assertStatus(403);

        // The failed equip leaves the slot on its default ("The Newbie") title.
        $this->assertSame('the-newbie', $user->fresh()->active_title_slug);
    }

    public function test_equipping_a_granted_cosmetic_succeeds(): void
    {
        $granted = $this->title('dragonsbane');
        $user = User::factory()->create();
        $user->grantCosmetic($granted);

        $this->actingAs($user, 'web')->postJson("/api/cosmetics/{$granted->id}/equip")
            ->assertOk()
            ->assertJsonPath('active.title', 'dragonsbane');
    }

    public function test_unequip_clears_the_active_slot(): void
    {
        $novice = $this->title('novice', default: true);
        $user = User::factory()->create(['active_title_slug' => 'novice']);

        $this->actingAs($user, 'web')->postJson('/api/cosmetics/unequip', ['type' => 'title'])
            ->assertOk()
            ->assertJsonPath('active.title', null);

        $this->assertNull($user->fresh()->active_title_slug);
    }

    public function test_equipping_a_crest_part_updates_the_crest_config(): void
    {
        $style = Cosmetic::create([
            'type' => 'crest_style',
            'slug' => 'style-chevron',
            'name' => 'Chevron',
            'rarity' => 'common',
            'value' => '3',
            'is_default' => true,
            'is_available' => true,
            'sort' => 0,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->postJson("/api/cosmetics/{$style->id}/equip")
            ->assertOk()
            ->assertJsonPath('active.crest.crest_style', 'style-chevron');

        $this->assertSame('style-chevron', $user->fresh()->crest_config['crest_style']);
    }
}
