<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RetentionDashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        $now = now();

        $data = [
            'dau' => DB::table('login_logs')
                ->where('created_at', '>=', $now->copy()->startOfDay())
                ->distinct('user_id')
                ->count('user_id'),
            'wau' => DB::table('login_logs')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->distinct('user_id')
                ->count('user_id'),
            'mau' => DB::table('login_logs')
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->distinct('user_id')
                ->count('user_id'),
            'new_today' => DB::table('users')
                ->where('created_at', '>=', $now->copy()->startOfDay())
                ->where('is_bot', false)
                ->count(),
            'new_week' => DB::table('users')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->where('is_bot', false)
                ->count(),
            'new_month' => DB::table('users')
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->where('is_bot', false)
                ->count(),
        ];

        return response()->json($data);
    }

    public function activeUsers(Request $request): JsonResponse
    {
        $days = match ($request->input('period', '30d')) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $data = DB::table('login_logs')
            ->where('login_logs.created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(login_logs.created_at) as date'),
                DB::raw('COUNT(DISTINCT login_logs.user_id) as active_users'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Also get new users per day
        $newUsers = DB::table('users')
            ->where('created_at', '>=', now()->subDays($days))
            ->where('is_bot', false)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as new_users'),
            )
            ->groupBy('date')
            ->pluck('new_users', 'date');

        $result = $data->map(fn ($row) => [
            'date' => $row->date,
            'active_users' => $row->active_users,
            'new_users' => $newUsers[$row->date] ?? 0,
        ]);

        return response()->json($result);
    }

    public function retentionCohorts(Request $request): JsonResponse
    {
        $cacheKey = 'retention.cohorts.' . md5(json_encode($request->only('cohort_size', 'periods')));

        $data = Cache::remember($cacheKey, 1800, function () use ($request) {
            $cohortWeeks = (int) ($request->input('periods', 8));
            $results = [];

            for ($w = 0; $w < $cohortWeeks; $w++) {
                $cohortStart = now()->subWeeks($cohortWeeks - $w)->startOfWeek();
                $cohortEnd = $cohortStart->copy()->endOfWeek();

                // Users who joined in this week
                $cohortUserIds = DB::table('users')
                    ->where('is_bot', false)
                    ->whereBetween('created_at', [$cohortStart, $cohortEnd])
                    ->pluck('id');

                $cohortSize = $cohortUserIds->count();
                if ($cohortSize === 0) {
                    $results[] = [
                        'week' => $cohortStart->format('M d'),
                        'cohort_size' => 0,
                        'day_1' => 0,
                        'day_7' => 0,
                        'day_14' => 0,
                        'day_30' => 0,
                    ];
                    continue;
                }

                $retention = [];
                foreach ([1, 7, 14, 30] as $day) {
                    $checkDate = $cohortStart->copy()->addDays($day);
                    if ($checkDate->isFuture()) {
                        $retention["day_{$day}"] = null;
                        continue;
                    }

                    $returned = DB::table('login_logs')
                        ->whereIn('user_id', $cohortUserIds)
                        ->where('created_at', '>=', $checkDate->startOfDay())
                        ->where('created_at', '<=', $checkDate->endOfDay())
                        ->distinct('user_id')
                        ->count('user_id');

                    $retention["day_{$day}"] = round(($returned / $cohortSize) * 100, 1);
                }

                $results[] = [
                    'week' => $cohortStart->format('M d'),
                    'cohort_size' => $cohortSize,
                    ...$retention,
                ];
            }

            return $results;
        });

        return response()->json($data);
    }

    public function churnIndicators(): JsonResponse
    {
        // Users who have played at least 1 game but haven't logged in recently
        $playedUsers = DB::table('game_players')
            ->join('users', 'users.id', '=', 'game_players.user_id')
            ->where('users.is_bot', false)
            ->select('users.id', 'users.last_login_at')
            ->distinct()
            ->get();

        $now = now();
        $inactive7 = 0;
        $inactive14 = 0;
        $inactive30 = 0;

        foreach ($playedUsers as $user) {
            if (!$user->last_login_at) {
                $inactive30++;
                $inactive14++;
                $inactive7++;
                continue;
            }
            $lastLogin = \Carbon\Carbon::parse($user->last_login_at);
            $daysSince = $lastLogin->diffInDays($now);

            if ($daysSince >= 30) $inactive30++;
            if ($daysSince >= 14) $inactive14++;
            if ($daysSince >= 7) $inactive7++;
        }

        return response()->json([
            'inactive_7d' => $inactive7,
            'inactive_14d' => $inactive14,
            'inactive_30d' => $inactive30,
        ]);
    }

    public function gameCompletionRate(Request $request): JsonResponse
    {
        $days = match ($request->input('period', '30d')) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $stats = DB::table('games')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
                DB::raw("SUM(CASE WHEN status = 'timed_out' THEN 1 ELSE 0 END) as timed_out"),
                DB::raw('COUNT(*) as total'),
            )
            ->first();

        $total = $stats->total ?: 1;

        return response()->json([
            'completed' => (int) $stats->completed,
            'cancelled' => (int) $stats->cancelled,
            'timed_out' => (int) $stats->timed_out,
            'total' => (int) $stats->total,
            'completion_rate' => round(($stats->completed / $total) * 100, 1),
        ]);
    }
}
