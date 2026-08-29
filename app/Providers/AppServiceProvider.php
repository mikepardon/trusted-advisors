<?php

namespace App\Providers;

use App\Models\GameRule;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        $this->applyAdminStripeOverrides();
    }

    /**
     * Let an admin-configured Stripe setup (Admin → Payments) override the .env values, so
     * all `config('services.stripe.*')` readers pick it up without touching each call site.
     * Secret values are stored encrypted; the publishable key and price id are plain.
     */
    private function applyAdminStripeOverrides(): void
    {
        try {
            $rules = GameRule::whereIn('key', [
                'stripe_key', 'stripe_secret_enc', 'stripe_webhook_secret_enc', 'stripe_premium_price_id',
            ])->pluck('value', 'key');
        } catch (Throwable) {
            return; // DB not ready (e.g. during install/migrate) — fall back to env.
        }

        if (filled($rules['stripe_key'] ?? null)) {
            config(['services.stripe.key' => $rules['stripe_key']]);
        }
        if (filled($rules['stripe_premium_price_id'] ?? null)) {
            config(['services.stripe.premium_price_id' => $rules['stripe_premium_price_id']]);
        }

        foreach (['stripe_secret_enc' => 'secret', 'stripe_webhook_secret_enc' => 'webhook_secret'] as $ruleKey => $configKey) {
            if (filled($rules[$ruleKey] ?? null)) {
                try {
                    config(["services.stripe.{$configKey}" => decrypt($rules[$ruleKey])]);
                } catch (Throwable) {
                    // Undecryptable (e.g. APP_KEY changed) — leave the env value in place.
                }
            }
        }
    }
}
