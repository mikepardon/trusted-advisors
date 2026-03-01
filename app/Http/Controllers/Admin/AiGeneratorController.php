<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiGeneratorController extends Controller
{
    private function getApiKey(): string
    {
        $key = config('services.anthropic.api_key');
        if (!$key) {
            abort(500, 'ANTHROPIC_API_KEY not configured');
        }
        return $key;
    }

    private function callClaude(string $systemPrompt, string $userPrompt): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->getApiKey(),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-sonnet-4-5-20250929',
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            abort(502, 'Claude API error: ' . $response->body());
        }

        $text = $response->json('content.0.text', '');

        // Extract JSON from the response (may be wrapped in ```json blocks)
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            $text = $matches[1];
        }

        $data = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            abort(422, 'Failed to parse AI response as JSON. Raw: ' . substr($text, 0, 500));
        }

        return $data;
    }

    public function generateCharacter(Request $request)
    {
        $request->validate([
            'prompt' => 'nullable|string|max:500',
        ]);

        $userPrompt = $request->input('prompt', 'Generate a unique medieval advisor character');

        $system = <<<'PROMPT'
You are a game content designer for "Trusted Advisors", a medieval board game with the humor style of the app Reigns — punchy, darkly funny, absurdist medieval humor.

Generate a single character (advisor) as JSON. The character has:
- name: A memorable, funny medieval name
- description: 2-3 sentences, witty and humorous, describing the advisor
- wild_value: integer 2-5 (the bonus value when WILD is rolled)
- wild_ability: one of: inspire, rally, divine, gamble, shadow, wisdom
- wild_ability_description: A short humorous description of what happens when they use their special ability
- dice: array of 3 arrays, each with 6 values. Values are integers 1-5 or the string "WILD". Each die should have exactly one "WILD" face. The dice should feel balanced but unique.

Return ONLY valid JSON, no markdown wrapping. Example format:
{
  "name": "Sir Bumblethwaite",
  "description": "A knight who once defeated a dragon by boring it to sleep with stories about his childhood.",
  "wild_value": 3,
  "wild_ability": "inspire",
  "wild_ability_description": "Tells an incredibly long story that somehow motivates everyone",
  "dice": [[3, 3, 4, 2, 2, "WILD"], [4, 4, 3, 1, 1, "WILD"], [2, 3, 3, 2, 3, "WILD"]]
}
PROMPT;

        $data = $this->callClaude($system, $userPrompt);

        return response()->json($data);
    }

    public function generateCard(Request $request)
    {
        $request->validate([
            'prompt' => 'nullable|string|max:500',
            'category' => 'nullable|string|in:military,political,economic,religious,social,crisis',
        ]);

        $userPrompt = $request->input('prompt', 'Generate a unique medieval decision card');
        $category = $request->input('category');
        if ($category) {
            $userPrompt .= " in the {$category} category";
        }

        $system = <<<'PROMPT'
You are a game content designer for "Trusted Advisors", a medieval board game with the humor style of the app Reigns — punchy, darkly funny, absurdist medieval humor.

Generate a single decision card as JSON. The card represents a situation the kingdom faces. It has:
- title: Short, punchy title (2-5 words)
- description: 1-2 sentences describing the situation, humorous
- difficulty: integer 3-12 (how hard to succeed - most cards should be 5-9)
- category: one of: military, political, economic, religious, social
- positive_effects: object with stat changes on success. Stats: wealth, influence, security, religion, food, happiness. Values typically +1 to +3. May include special keys: draw_item (1), recover_die (1), remove_curse (1)
- negative_effects: object with stat changes on failure (always applied). Values typically -1 to -3. May include: lose_die (1), discard_item (1)
- positive_flavor: Short witty text describing the successful outcome
- negative_flavor: Short witty text describing the failure outcome

Return ONLY valid JSON, no markdown wrapping. Example:
{
  "title": "Tax the Taxman",
  "description": "The royal tax collector has been skimming profits. Time to tax the taxer.",
  "difficulty": 7,
  "category": "economic",
  "positive_effects": {"wealth": 2, "influence": 1},
  "negative_effects": {"wealth": -1, "happiness": -1},
  "positive_flavor": "The taxman pays up. Irony is a dish best served with interest.",
  "negative_flavor": "He's been hiding money longer than you've been king. He's better at this."
}
PROMPT;

        $data = $this->callClaude($system, $userPrompt);

        return response()->json($data);
    }

    public function generateEvent(Request $request)
    {
        $request->validate([
            'prompt' => 'nullable|string|max:500',
        ]);

        $userPrompt = $request->input('prompt', 'Generate a unique medieval kingdom event');

        $system = <<<'PROMPT'
You are a game content designer for "Trusted Advisors", a medieval board game with the humor style of the app Reigns — punchy, darkly funny, absurdist medieval humor.

Generate a single kingdom event as JSON. Events are things that happen between rounds that affect the kingdom's stats. It has:
- title: Memorable, funny event name (2-6 words)
- effect: 1-3 sentences describing what happens, humorous and dramatic
- stat_modifiers: object with stat changes. Stats: wealth, influence, security, religion, food, happiness. Use values from -3 to +3. Not all stats need to be affected. Usually 2-4 stats are modified.

Return ONLY valid JSON, no markdown wrapping. Example:
{
  "title": "The Great Cheese Shortage",
  "effect": "The kingdom's cheese reserves have mysteriously vanished. Suspicion falls on the mice, but they have an alibi. Everyone is unhappy and slightly hungry.",
  "stat_modifiers": {"food": -2, "happiness": -1}
}
PROMPT;

        $data = $this->callClaude($system, $userPrompt);

        return response()->json($data);
    }
}
