<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GameRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_stripe_config_and_secrets_are_stored_encrypted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'web')->putJson('/api/admin/payment-settings', [
            'stripe_key' => 'pk_test_public_1234',
            'stripe_secret' => 'sk_test_secret_9876',
            'stripe_webhook_secret' => 'whsec_hook_5555',
            'stripe_premium_price_id' => 'price_abc123',
        ])->assertOk();

        // Non-secret values stored as-is; secrets stored encrypted (not plaintext).
        $this->assertSame('pk_test_public_1234', GameRule::getValue('stripe_key'));
        $this->assertSame('price_abc123', GameRule::getValue('stripe_premium_price_id'));

        $storedSecret = GameRule::getValue('stripe_secret_enc');
        $this->assertNotSame('sk_test_secret_9876', $storedSecret, 'The secret must not be stored in plaintext.');
        $this->assertSame('sk_test_secret_9876', decrypt($storedSecret));
    }

    public function test_settings_returns_masked_secrets_never_raw(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        GameRule::updateOrCreate(['key' => 'stripe_key'], ['value' => 'pk_test_public_1234']);
        GameRule::updateOrCreate(['key' => 'stripe_secret_enc'], ['value' => encrypt('sk_test_secret_9876')]);
        GameRule::updateOrCreate(['key' => 'stripe_premium_price_id'], ['value' => 'price_abc123']);

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/payment-settings')->assertOk();

        // Publishable key + price id shown in full; secret only masked.
        $response->assertJsonPath('stripe.key', 'pk_test_public_1234')
            ->assertJsonPath('stripe.premium_price_id', 'price_abc123')
            ->assertJsonPath('stripe.secret_set', true);
        $this->assertStringEndsWith('9876', $response->json('stripe.secret_masked'));
        $this->assertStringNotContainsString('sk_test_secret', $response->json('stripe.secret_masked'));
    }

    public function test_blank_publishable_key_clears_the_override(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        GameRule::updateOrCreate(['key' => 'stripe_key'], ['value' => 'pk_test_old']);

        $this->actingAs($admin, 'web')->putJson('/api/admin/payment-settings', [
            'stripe_key' => '',
        ])->assertOk();

        $this->assertNull(GameRule::getValue('stripe_key'));
    }

    public function test_loading_plans_without_a_secret_key_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // No admin override in GameRule, and no env secret either → 422 rather than a Stripe call.
        config(['services.stripe.secret' => null]);

        $this->actingAs($admin, 'web')->getJson('/api/admin/stripe-prices')->assertStatus(422);
    }
}
