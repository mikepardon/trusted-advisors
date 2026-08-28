<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ScheduleController extends Controller
{
    /**
     * Registry of scheduled console commands an admin may trigger manually. This mirrors
     * the schedule in routes/console.php; "Run now" here is a manual trigger for when the
     * server cron/worker isn't available or a job needs re-running out of band.
     *
     * @return list<array{key: string, command: string, arguments: array<string, mixed>, label: string, schedule: string, description: string}>
     */
    private function jobs(): array
    {
        return [
            [
                'key' => 'generate-daily-challenge',
                'command' => 'app:generate-daily-challenge',
                // --no-ai here so the manual "Run now" (an HTTP request) can't time out on
                // synchronous AI calls; the real overnight cron omits it and writes AI blurbs.
                'arguments' => ['--ahead' => '6', '--no-ai' => true],
                'label' => 'Generate Daily Challenges',
                'schedule' => 'Daily at 00:12',
                'description' => 'Ensures today plus the next 6 days each have a daily challenge (rolling window). Uses fast templated briefings; the overnight schedule writes AI briefings.',
            ],
            [
                'key' => 'generate-weekly-challenge',
                'command' => 'app:generate-weekly-challenge',
                'arguments' => [],
                'label' => 'Generate Weekly Challenge',
                'schedule' => 'Mondays at 00:01',
                'description' => 'Generates the weekly challenge for the current week if one does not exist.',
            ],
            [
                'key' => 'generate-monthly-season',
                'command' => 'app:generate-monthly-season',
                'arguments' => [],
                'label' => 'Generate Monthly Season',
                'schedule' => 'Daily at 00:10',
                'description' => 'Opens a new monthly season once the previous one has ended.',
            ],
            [
                'key' => 'process-season-end',
                'command' => 'app:process-season-end',
                'arguments' => [],
                'label' => 'Process Season End',
                'schedule' => 'Daily at 00:05',
                'description' => 'Closes any season whose end date has passed and distributes its rewards.',
            ],
            [
                'key' => 'process-league-week',
                'command' => 'app:process-league-week',
                'arguments' => [],
                'label' => 'Process League Week',
                'schedule' => 'Mondays at 00:02',
                'description' => 'Finalises last week\'s league standings and applies promotions/relegations.',
            ],
        ];
    }

    public function index(): JsonResponse
    {
        $jobs = collect($this->jobs())
            ->map(fn (array $job): array => [
                'key' => $job['key'],
                'label' => $job['label'],
                'schedule' => $job['schedule'],
                'description' => $job['description'],
                'command' => $this->displayCommand($job),
            ])
            ->values();

        return response()->json(['jobs' => $jobs]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => ['required', 'string']]);

        $job = collect($this->jobs())->firstWhere('key', $validated['key']);
        if ($job === null) {
            return response()->json(['error' => 'Unknown scheduled job.'], 422);
        }

        Artisan::call($job['command'], $job['arguments']);

        return response()->json([
            'message' => "{$job['label']} ran successfully.",
            'output' => trim(Artisan::output()),
        ]);
    }

    /**
     * @param  array{command: string, arguments: array<string, mixed>}  $job
     */
    private function displayCommand(array $job): string
    {
        $parts = [$job['command']];
        foreach ($job['arguments'] as $name => $value) {
            $parts[] = "{$name}={$value}";
        }

        return implode(' ', $parts);
    }
}
