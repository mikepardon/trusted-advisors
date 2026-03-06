<?php

namespace App\Services;

use App\Models\AdminGift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GiftTargetingService
{
    public function buildQuery(AdminGift $gift): Builder
    {
        $query = User::where('is_bot', false);

        switch ($gift->target_type) {
            case 'specific_users':
                $userIds = $gift->target_user_ids ?? [];
                $query->whereIn('id', $userIds);
                break;

            case 'segment':
                $this->applySegmentCriteria($query, $gift->target_criteria ?? []);
                break;

            case 'all':
            default:
                // No extra filters — all non-bot users
                break;
        }

        return $query;
    }

    private function applySegmentCriteria(Builder $query, array $criteria): void
    {
        if (isset($criteria['level_min'])) {
            $query->where('level', '>=', (int) $criteria['level_min']);
        }

        if (isset($criteria['level_max'])) {
            $query->where('level', '<=', (int) $criteria['level_max']);
        }

        if (isset($criteria['elo_min'])) {
            $query->where('elo_rating', '>=', (int) $criteria['elo_min']);
        }

        if (isset($criteria['elo_max'])) {
            $query->where('elo_rating', '<=', (int) $criteria['elo_max']);
        }

        if (isset($criteria['joined_after'])) {
            $query->where('created_at', '>=', $criteria['joined_after']);
        }

        if (isset($criteria['joined_before'])) {
            $query->where('created_at', '<=', $criteria['joined_before']);
        }

        if (isset($criteria['min_games'])) {
            $minGames = (int) $criteria['min_games'];
            $query->whereHas('games', function ($q) {
                $q->where('status', 'completed');
            }, '>=', $minGames);
        }

        if (isset($criteria['is_premium'])) {
            $query->where('is_premium', (bool) $criteria['is_premium']);
        }

        if (isset($criteria['inactive_days'])) {
            $cutoff = now()->subDays((int) $criteria['inactive_days']);
            $query->where(function ($q) use ($cutoff) {
                $q->where('last_login_at', '<=', $cutoff)
                    ->orWhereNull('last_login_at');
            });
        }
    }
}
