<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes the flavour "briefing" for a daily challenge — a short second-person setup
 * ("You are {advisor}, drafted to...") derived from the challenge's actual metrics
 * (assigned advisor, goal, weak stats, curses, house rules).
 *
 * Uses Claude when an Anthropic key is configured (Admin → Settings, or the env key),
 * and falls back to a deterministic templated blurb otherwise so generation never fails.
 */
class ChallengeBlurbGenerator
{
    private const MODEL = 'claude-opus-4-8';

    /**
     * @param  array{
     *     character_name: string,
     *     goal_text: string,
     *     weak_stats: array<string, int>,
     *     strong_stats: array<string, int>,
     *     has_curse: bool,
     *     house_rules: list<string>,
     *     items: list<string>,
     *     rounds: int,
     * }  $context
     */
    public function generate(array $context): string
    {
        return $this->generateWithClaude($context) ?? $this->templatedFallback($context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function generateWithClaude(array $context): ?string
    {
        $apiKey = GameRule::getValue('anthropic_api_key') ?: config('services.anthropic.api_key');
        if (blank($apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => self::MODEL,
                'max_tokens' => 400,
                'system' => $this->systemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $this->userPrompt($context)],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Challenge blurb generation failed', ['status' => $response->status()]);

                return null;
            }

            $text = trim((string) $response->json('content.0.text', ''));

            return $text === '' ? null : $text;
        } catch (Throwable $exception) {
            Log::warning('Challenge blurb generation errored', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a game writer for "Trusted Advisors", a medieval kingdom-management game with the punchy, darkly funny, absurdist humour of the app Reigns.

Write a short in-character BRIEFING for a daily challenge — the scene the player reads before it begins. Rules:
- 2 to 4 sentences. Second person, present tense. Start by naming who they are: "You are {advisor}, ...".
- Weave in the concrete facts you're given: the advisor, the goal they must reach, which stats are dangerously low (or unusually strong), the items they carry into the reign, whether a curse hangs over the land, and any special house rules in force. Name at least one carried item.
- Be vivid and funny, but the facts must stay accurate — if wealth is low, the realm is broke; if the goal is Security, they must fortify.
- Return ONLY the briefing text. No preamble, no quotation marks, no markdown, no "Here is".
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function userPrompt(array $context): string
    {
        $lines = [
            "Advisor: {$context['character_name']}",
            "Goal: {$context['goal_text']}",
            'Months available (soft cap): ' . $context['rounds'],
        ];

        if (filled($context['weak_stats'])) {
            $lines[] = 'Dangerously low stats at the start: ' . $this->describeStats($context['weak_stats']);
        }
        if (filled($context['strong_stats'])) {
            $lines[] = 'Stats in good shape: ' . $this->describeStats($context['strong_stats']);
        }
        if ($context['has_curse']) {
            $lines[] = 'There is a curse hanging over the land.';
        }
        if (filled($context['house_rules'])) {
            $lines[] = 'Special rules in force: ' . implode(', ', $context['house_rules']);
        }
        if (filled($context['items'] ?? [])) {
            $lines[] = 'Items carried into the reign: ' . implode(', ', $context['items']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $items
     */
    private function naturalList(array $items): string
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function describeStats(array $stats): string
    {
        return collect($stats)
            ->map(fn (int $value, string $stat): string => ucfirst($stat) . " ({$value})")
            ->values()
            ->implode(', ');
    }

    /**
     * A deterministic, key-free briefing used when Claude is unavailable.
     *
     * @param  array<string, mixed>  $context
     */
    public function templatedFallback(array $context): string
    {
        $sentences = ["You are {$context['character_name']}, newly drafted to steady a realm that needs you: your task is to {$context['goal_text']}."];

        if (filled($context['weak_stats'])) {
            $sentences[] = 'The coffers and stores tell a grim tale — ' . $this->describeStats($context['weak_stats']) . ' — so tread carefully.';
        }
        if (filled($context['items'] ?? [])) {
            $sentences[] = 'You carry ' . $this->naturalList($context['items']) . ' into the reign.';
        }
        if ($context['has_curse']) {
            $sentences[] = 'Worse still, a curse hangs over the land, and the people have noticed.';
        }
        if (filled($context['house_rules'])) {
            $sentences[] = 'And the usual rules do not apply here: ' . implode(', ', $context['house_rules']) . '.';
        }

        return implode(' ', $sentences);
    }
}
