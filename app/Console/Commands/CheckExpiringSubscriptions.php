<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Subscription;

class CheckExpiringSubscriptions extends Command
{
    protected $signature = 'app:check-expiring-subscriptions';
    protected $description = 'Check and deactivate expired premium subscriptions';

    public function handle(): int
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        // Find premium users whose subscription has expired
        $expiredUsers = User::where('is_premium', true)
            ->whereNotNull('premium_expires_at')
            ->where('premium_expires_at', '<', now())
            ->get();

        $deactivated = 0;
        $renewed = 0;

        foreach ($expiredUsers as $user) {
            $activeSub = $user->activeSubscription();

            if ($activeSub && $activeSub->platform === 'stripe' && $activeSub->subscription_id) {
                // Re-check with Stripe
                try {
                    $sub = Subscription::retrieve($activeSub->subscription_id);

                    if ($sub->status === 'active') {
                        // Still active - update expiry
                        $periodEnd = \Carbon\Carbon::createFromTimestamp($sub->current_period_end);

                        $activeSub->update([
                            'current_period_end' => $periodEnd,
                            'cancel_at_period_end' => $sub->cancel_at_period_end,
                        ]);

                        $user->premium_expires_at = $periodEnd;
                        $user->save();
                        $renewed++;
                        continue;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to check Stripe subscription for user {$user->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Deactivate
            UserSubscription::where('user_id', $user->id)
                ->whereIn('status', ['active', 'canceled', 'past_due'])
                ->update(['status' => 'expired']);

            $user->is_premium = false;
            $user->premium_expires_at = null;
            $user->save();
            $deactivated++;

            Log::info("Deactivated expired premium for user {$user->id} ({$user->name})");
        }

        $this->info("Checked expiring subscriptions: {$renewed} renewed, {$deactivated} deactivated.");

        return self::SUCCESS;
    }
}
