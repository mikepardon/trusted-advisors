<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GameRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_the_anthropic_key_stores_it_and_never_returns_it_raw(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'web')
            ->putJson('/api/admin/settings/anthropic-key', ['api_key' => 'sk-ant-secret-abcd1234'])
            ->assertOk();

        // The raw key is persisted for the AI generators to read...
        $this->assertSame('sk-ant-secret-abcd1234', GameRule::getValue('anthropic_api_key'));

        // ...but the response only ever exposes a masked form ending in the last four chars.
        $masked = $response->json('anthropic.masked');
        $this->assertStringEndsWith('1234', $masked);
        $this->assertStringNotContainsString('secret', $masked);
        $this->assertTrue($response->json('anthropic.set'));
    }

    public function test_settings_index_reports_a_stored_key_as_set_without_leaking_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        GameRule::updateOrCreate(['key' => 'anthropic_api_key'], ['value' => 'sk-ant-longlivedvalue9999']);

        $response = $this->actingAs($admin, 'web')
            ->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('anthropic.set', true);

        $this->assertStringEndsWith('9999', $response->json('anthropic.masked'));
        $this->assertStringNotContainsString('longlived', $response->json('anthropic.masked'));
    }

    public function test_clearing_the_anthropic_key_removes_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        GameRule::updateOrCreate(['key' => 'anthropic_api_key'], ['value' => 'sk-ant-tobecleared0000']);

        $this->actingAs($admin, 'web')
            ->deleteJson('/api/admin/settings/anthropic-key')
            ->assertOk()
            ->assertJsonPath('anthropic.set', false);

        $this->assertNull(GameRule::getValue('anthropic_api_key'));
    }

    public function test_too_short_a_key_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')
            ->putJson('/api/admin/settings/anthropic-key', ['api_key' => 'short'])
            ->assertStatus(422);
    }

    public function test_schedule_index_lists_the_known_jobs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')
            ->getJson('/api/admin/schedule')
            ->assertOk()
            ->assertJsonStructure(['jobs' => [['key', 'label', 'schedule', 'description', 'command']]]);
    }

    public function test_running_an_unknown_scheduled_job_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')
            ->postJson('/api/admin/schedule/run', ['key' => 'rm-rf-everything'])
            ->assertStatus(422);
    }

    public function test_running_an_allowlisted_scheduled_job_succeeds(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // process-season-end is a safe no-op against an empty season table.
        $this->actingAs($admin, 'web')
            ->postJson('/api/admin/schedule/run', ['key' => 'process-season-end'])
            ->assertOk()
            ->assertJsonStructure(['message', 'output']);
    }
}
