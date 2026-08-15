<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cosmetic;
use App\Models\Season;
use App\Models\SeasonPassTier;
use App\Models\User;
use App\Models\UserPassProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeasonPassService
{
    public function activeSeason(): ?Season
    {
        $now = CarbonImmutable::now();

        // The season whose window covers now. Picking by window (not merely is_active)
        // keeps the pass correct if a just-ended season hasn't been closed yet — e.g. the
        // monthly generator opening a new season before ProcessSeasonEnd deactivates the old.
        return Season::query()->active()
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderByDesc('starts_at')
            ->first();
    }

    /**
     * Create the pass tier ladder for a season (idempotent). Shared by the seeder and the
     * monthly-season generator so a freshly opened season always has its rewards.
     */
    public function seedTiers(Season $season): void
    {
        foreach ($this->tierLadder() as $tier) {
            $cosmeticId = $tier['cosmetic'] === null
                ? null
                : Cosmetic::query()->where('slug', $tier['cosmetic'])->value('id');

            SeasonPassTier::updateOrCreate(
                ['season_id' => $season->id, 'tier' => $tier['tier']],
                [
                    'points_required' => $tier['points'],
                    'reward_coins' => $tier['coins'],
                    'reward_cosmetic_id' => $cosmeticId,
                    'name' => $tier['name'],
                ],
            );
        }
    }

    /**
     * The free-track reward ladder: coins fill the in-between tiers; seeded cosmetics are
     * the milestone payoffs.
     *
     * @return list<array{tier: int, points: int, coins: int, cosmetic: string|null, name: string}>
     */
    private function tierLadder(): array
    {
        return [
            ['tier' => 1, 'points' => 150, 'coins' => 200, 'cosmetic' => null, 'name' => '200 Gold'],
            ['tier' => 2, 'points' => 400, 'coins' => 0, 'cosmetic' => 'the-wise', 'name' => 'Title: The Wise'],
            ['tier' => 3, 'points' => 750, 'coins' => 300, 'cosmetic' => null, 'name' => '300 Gold'],
            ['tier' => 4, 'points' => 1150, 'coins' => 0, 'cosmetic' => 'bronze-ring', 'name' => 'Bronze Ring'],
            ['tier' => 5, 'points' => 1600, 'coins' => 0, 'cosmetic' => 'midnight', 'name' => 'Midnight Card Back'],
            ['tier' => 6, 'points' => 2200, 'coins' => 400, 'cosmetic' => null, 'name' => '400 Gold'],
            ['tier' => 7, 'points' => 2900, 'coins' => 0, 'cosmetic' => 'petals', 'name' => 'Falling Petals'],
            ['tier' => 8, 'points' => 3700, 'coins' => 0, 'cosmetic' => 'kingmaker', 'name' => 'Title: Kingmaker'],
            ['tier' => 9, 'points' => 4600, 'coins' => 0, 'cosmetic' => 'silver-ring', 'name' => 'Silver Ring'],
            ['tier' => 10, 'points' => 5600, 'coins' => 500, 'cosmetic' => null, 'name' => '500 Gold'],
            ['tier' => 11, 'points' => 6700, 'coins' => 0, 'cosmetic' => 'dragonsbane', 'name' => 'Title: Dragonsbane'],
            ['tier' => 12, 'points' => 8000, 'coins' => 0, 'cosmetic' => 'royal-ring', 'name' => 'Royal Ring'],
        ];
    }

    public function progressFor(User $user, Season $season): UserPassProgress
    {
        return UserPassProgress::firstOrCreate(
            ['user_id' => $user->id, 'season_id' => $season->id],
            ['points' => 0, 'claimed_tiers' => []],
        );
    }

    /**
     * Award pass points for the active season. No-op when no season is running.
     */
    public function addPoints(User $user, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $season = $this->activeSeason();
        if ($season === null) {
            return;
        }

        // Atomic increment avoids a lost-update race between concurrent awards.
        $this->progressFor($user, $season)->increment('points', $points);
    }

    /** @return Collection<int, SeasonPassTier> */
    public function tiers(Season $season): Collection
    {
        return SeasonPassTier::query()
            ->where('season_id', $season->id)
            ->with('rewardCosmetic')
            ->orderBy('tier')
            ->get();
    }

    /**
     * The full pass state for a user: points, tiers, and per-tier reached/claimed flags.
     *
     * @return array<string, mixed>
     */
    public function state(User $user, Season $season): array
    {
        $progress = $this->progressFor($user, $season);
        $claimed = $progress->claimed_tiers ?? [];
        $points = $progress->points;

        $tiers = $this->tiers($season)->map(function (SeasonPassTier $tier) use ($points, $claimed): array {
            $reached = $points >= $tier->points_required;
            $isClaimed = in_array($tier->tier, $claimed, true);

            return [
                'tier' => $tier->tier,
                'points_required' => $tier->points_required,
                'reward_coins' => $tier->reward_coins,
                'name' => $tier->name,
                'reward_cosmetic' => $tier->rewardCosmetic === null ? null : [
                    'name' => $tier->rewardCosmetic->name,
                    'type' => $tier->rewardCosmetic->type,
                    'rarity' => $tier->rewardCosmetic->rarity,
                    'value' => $tier->rewardCosmetic->value,
                ],
                'reached' => $reached,
                'claimed' => $isClaimed,
                'claimable' => $reached && ! $isClaimed,
            ];
        })->values();

        return [
            'season' => [
                'id' => $season->id,
                'name' => $season->name,
                'ends_at' => $season->ends_at?->toIso8601ZuluString('millisecond'),
            ],
            'points' => $points,
            'current_tier' => $this->currentTier($points, $season),
            'tiers' => $tiers,
        ];
    }

    public function currentTier(int $points, Season $season): int
    {
        return (int) SeasonPassTier::query()
            ->where('season_id', $season->id)
            ->where('points_required', '<=', $points)
            ->max('tier') ?? 0;
    }

    /**
     * Grant a tier's rewards and record the claim, idempotently. The claimed-check
     * runs under a row lock inside the transaction, so two concurrent claims of the
     * same tier can't both pass the controller's pre-check and double-grant: the
     * second caller receives `alreadyClaimed` and nothing is granted twice.
     *
     * @return array{coins: int, cosmetic: string|null, alreadyClaimed: bool}
     */
    public function grantTier(User $user, Season $season, SeasonPassTier $tier): array
    {
        return DB::transaction(function () use ($user, $season, $tier): array {
            $progress = UserPassProgress::query()
                ->where('user_id', $user->id)
                ->where('season_id', $season->id)
                ->lockForUpdate()
                ->first() ?? $this->progressFor($user, $season);

            $claimed = $progress->claimed_tiers ?? [];

            if (in_array($tier->tier, $claimed, true)) {
                return ['coins' => 0, 'cosmetic' => null, 'alreadyClaimed' => true];
            }

            if ($tier->reward_coins > 0) {
                $user->increment('coins', $tier->reward_coins);
                $user->recordCoinTransaction(
                    $tier->reward_coins,
                    'earn',
                    'season_pass',
                    $tier->id,
                    "Season Pass — tier {$tier->tier}",
                );
            }

            $cosmeticName = null;
            if ($tier->rewardCosmetic !== null) {
                $user->grantCosmetic($tier->rewardCosmetic);
                $cosmeticName = $tier->rewardCosmetic->name;
            }

            $claimed[] = $tier->tier;
            $progress->claimed_tiers = array_values(array_unique($claimed));
            $progress->save();

            return ['coins' => $tier->reward_coins, 'cosmetic' => $cosmeticName, 'alreadyClaimed' => false];
        });
    }
}
