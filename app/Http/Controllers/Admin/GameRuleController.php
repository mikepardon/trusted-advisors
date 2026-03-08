<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRule;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GameRuleController extends Controller
{
    use AuditsAdminActions;
    public function index(): JsonResponse
    {
        $rules = GameRule::all()->pluck('value', 'key');

        return response()->json($rules);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required',
        ]);

        $oldValue = GameRule::where('key', $key)->value('value');
        $rule = GameRule::updateOrCreate(
            ['key' => $key],
            ['value' => $validated['value']]
        );
        $this->auditLog('update', 'GameRule', $rule->id, ['value' => ['old' => $oldValue, 'new' => $validated['value']]]);

        return response()->json($rule);
    }

    public function removeHomepageBackground(): JsonResponse
    {
        $existing = GameRule::where('key', 'homepage_background_image')->first();
        if ($existing && $existing->value) {
            try {
                Storage::disk('s3')->delete($existing->value);
            } catch (\Exception $e) {
                // ignore delete errors
            }
            $existing->delete();
        }

        return response()->json(['message' => 'Background removed']);
    }

    public static function siteSettings(): JsonResponse
    {
        $bgs = GameRule::whereIn('key', [
            'homepage_background_image',
            'classic_game_background_image',
            'duel_game_background_image',
        ])->pluck('value', 'key');

        return response()->json([
            'homepage_background_url' => ($bgs['homepage_background_image'] ?? null) ? '/api/storage/' . $bgs['homepage_background_image'] : null,
            'classic_game_background_url' => ($bgs['classic_game_background_image'] ?? null) ? '/api/storage/' . $bgs['classic_game_background_image'] : null,
            'duel_game_background_url' => ($bgs['duel_game_background_image'] ?? null) ? '/api/storage/' . $bgs['duel_game_background_image'] : null,
        ]);
    }
}
