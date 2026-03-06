<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAdminGiftToUsers;
use App\Models\AdminGift;
use App\Services\GiftTargetingService;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGiftController extends Controller
{
    use AuditsAdminActions;

    public function index(): JsonResponse
    {
        $gifts = AdminGift::with('creator:id,name', 'rewardCharacter:id,name', 'rewardDiceTheme:id,name,slug', 'rewardKingdomStyle:id,name,slug')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($gifts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
            'reward_xp' => 'integer|min:0',
            'reward_coins' => 'integer|min:0',
            'reward_character_id' => 'nullable|exists:characters,id',
            'reward_dice_theme_id' => 'nullable|exists:dice_themes,id',
            'reward_kingdom_style_id' => 'nullable|exists:kingdom_styles,id',
            'target_type' => 'in:all,specific_users,segment',
            'target_user_ids' => 'nullable|array',
            'target_user_ids.*' => 'integer|exists:users,id',
            'target_criteria' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        // At least one reward must be set
        if (($validated['reward_xp'] ?? 0) === 0 && ($validated['reward_coins'] ?? 0) === 0 && empty($validated['reward_character_id']) && empty($validated['reward_dice_theme_id']) && empty($validated['reward_kingdom_style_id'])) {
            return response()->json(['error' => 'At least one reward must be specified.'], 422);
        }

        $isScheduled = !empty($validated['scheduled_at']);

        $gift = AdminGift::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'recipient_count' => 0,
            'status' => $isScheduled ? 'scheduled' : 'sent',
        ]);

        $this->auditLog('create', 'AdminGift', $gift->id, null, [
            'target_type' => $gift->target_type,
            'status' => $gift->status,
        ]);

        if (!$isScheduled) {
            SendAdminGiftToUsers::dispatchSync($gift, $validated);
        }

        return response()->json(
            $gift->fresh()->load('creator:id,name', 'rewardCharacter:id,name', 'rewardDiceTheme:id,name,slug', 'rewardKingdomStyle:id,name,slug'),
            201
        );
    }

    public function previewRecipientCount(Request $request, GiftTargetingService $targeting): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:all,specific_users,segment',
            'target_user_ids' => 'nullable|array',
            'target_user_ids.*' => 'integer',
            'target_criteria' => 'nullable|array',
        ]);

        $gift = new AdminGift($validated);
        $count = $targeting->buildQuery($gift)->count();

        return response()->json(['count' => $count]);
    }

    public function cancel(AdminGift $gift): JsonResponse
    {
        if ($gift->status !== 'scheduled') {
            return response()->json(['error' => 'Only scheduled gifts can be cancelled.'], 422);
        }

        $gift->update(['status' => 'cancelled']);

        $this->auditLog('cancel', 'AdminGift', $gift->id);

        return response()->json($gift);
    }
}
