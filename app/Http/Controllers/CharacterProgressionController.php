<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\CharacterLevelOption;
use App\Models\UserCharacter;
use App\Services\CharacterProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterProgressionController extends Controller
{
    public function __construct(
        private CharacterProgressionService $service
    ) {}

    /**
     * List available starter advisors (base game characters) with their upgrade trees.
     */
    public function starterOptions(): JsonResponse
    {
        $characters = Character::whereNull('addon_id')
            ->where('is_available', true)
            ->get();

        $levelOptions = CharacterLevelOption::where('is_active', true)
            ->orderBy('available_at_level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $result = $characters->map(function (Character $char) use ($levelOptions) {
            $charOptions = $levelOptions->filter(function ($opt) use ($char) {
                return $opt->character_id === null || $opt->character_id === $char->id;
            });

            return [
                'id' => $char->id,
                'name' => $char->name,
                'description' => $char->description,
                'image_url' => $char->image_url,
                'dice' => $char->dice,
                'wild_value' => $char->wild_value,
                'wild_ability' => $char->wild_ability,
                'wild_ability_description' => $char->wild_ability_description,
                'starting_bonus' => $char->starting_bonus,
                'level_options' => $charOptions->groupBy('available_at_level')->map(function ($group) {
                    return $group->map(fn ($o) => [
                        'id' => $o->id,
                        'name' => $o->name,
                        'type' => $o->type,
                        'description' => $o->description,
                        'icon' => $o->icon,
                    ])->values();
                }),
            ];
        });

        return response()->json($result);
    }

    /**
     * Choose 2 starter advisors for a new user.
     */
    public function chooseStarters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_ids' => 'required|array|size:2',
            'character_ids.*' => 'required|integer|distinct',
        ]);

        $user = $request->user();

        if ($user->userCharacters()->count() > 0) {
            return response()->json(['error' => 'You already have advisors.'], 422);
        }

        $characters = Character::whereIn('id', $validated['character_ids'])
            ->whereNull('addon_id')
            ->where('is_available', true)
            ->get();

        if ($characters->count() !== 2) {
            return response()->json(['error' => 'Invalid character selection. Choose 2 base game characters.'], 422);
        }

        foreach ($validated['character_ids'] as $charId) {
            UserCharacter::create([
                'user_id' => $user->id,
                'character_id' => $charId,
            ]);
        }

        $data = $user->fresh()->toArray();

        return response()->json($data);
    }

    /**
     * List user's characters with XP, level, upgrades, pending level-ups.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $userCharacters = UserCharacter::where('user_id', $user->id)
            ->with(['character', 'upgrades.option'])
            ->get();

        $result = $userCharacters->map(function (UserCharacter $uc) {
            return [
                'id' => $uc->id,
                'character_id' => $uc->character_id,
                'character' => [
                    'id' => $uc->character->id,
                    'name' => $uc->character->name,
                    'image_url' => $uc->character->image_url,
                    'description' => $uc->character->description,
                    'dice' => $uc->character->dice,
                    'dice_duel' => $uc->character->dice_duel,
                    'wild_value' => $uc->character->wild_value,
                    'wild_ability' => $uc->character->wild_ability,
                    'wild_ability_description' => $uc->character->wild_ability_description,
                ],
                'xp' => $uc->xp,
                'level' => $uc->level,
                'xp_for_next_level' => $uc->level < UserCharacter::getMaxLevel() ? UserCharacter::xpForLevel($uc->level + 1) : null,
                'xp_for_current_level' => UserCharacter::xpForLevel($uc->level),
                'max_level' => UserCharacter::getMaxLevel(),
                'incarnation' => $uc->incarnation,
                'incarnation_name' => $uc->incarnation_name,
                'display_name' => $uc->getDisplayName(),
                'pending_upgrades' => $uc->pendingLevelUpCount(),
                'can_immortalise' => $uc->level >= UserCharacter::getMaxLevel() && $uc->pendingLevelUpCount() === 0,
                'modified_dice' => $uc->getModifiedDice(false),
                'modified_dice_duel' => $uc->getModifiedDice(true),
                'extra_item_slots' => $uc->getExtraItemSlots(),
                'card_redraws' => $uc->getCardRedraws(),
                'passive_bonuses' => $uc->getPassiveBonuses(),
                'upgrades' => $uc->upgrades
                    ->where('incarnation', $uc->incarnation)
                    ->map(fn ($u) => [
                        'id' => $u->id,
                        'chosen_at_level' => $u->chosen_at_level,
                        'option_name' => $u->option->name,
                        'option_type' => $u->option->type,
                        'option_description' => $u->option->description,
                        'option_icon' => $u->option->icon,
                        'user_choice' => $u->user_choice,
                    ])->values(),
            ];
        });

        return response()->json($result);
    }

    /**
     * Available options for next pending level-up.
     */
    public function levelOptions(Request $request, UserCharacter $userCharacter): JsonResponse
    {
        if ($userCharacter->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $nextLevel = $userCharacter->nextPendingLevel();
        if ($nextLevel === null) {
            return response()->json(['error' => 'No pending level-ups'], 422);
        }

        $options = $this->service->getAvailableOptions($userCharacter, $nextLevel);

        // Limit to configured number of random options
        $config = UserCharacter::getLevelConfig();
        $optionsPerLevelUp = $config['options_per_level_up'] ?? 2;
        if ($options->count() > $optionsPerLevelUp) {
            $options = $options->random($optionsPerLevelUp);
        }

        return response()->json([
            'for_level' => $nextLevel,
            'options' => $options->map(fn ($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'type' => $o->type,
                'config' => $o->config,
                'description' => $o->description,
                'icon' => $o->icon,
                'available_at_level' => $o->available_at_level,
                'max_selections' => $o->max_selections,
            ])->values(),
        ]);
    }

    /**
     * Choose a level-up option.
     */
    public function chooseUpgrade(Request $request, UserCharacter $userCharacter): JsonResponse
    {
        if ($userCharacter->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'option_id' => 'required|integer|exists:character_level_options,id',
            'user_choice' => 'nullable|array',
        ]);

        $option = CharacterLevelOption::findOrFail($validated['option_id']);
        $nextLevel = $userCharacter->nextPendingLevel();

        if ($nextLevel === null) {
            return response()->json(['error' => 'No pending level-ups'], 422);
        }

        try {
            $upgrade = $this->service->chooseLevelUpOption(
                $userCharacter,
                $option,
                $nextLevel,
                $validated['user_choice'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $userCharacter->refresh();

        return response()->json([
            'message' => 'Upgrade chosen!',
            'upgrade' => [
                'id' => $upgrade->id,
                'chosen_at_level' => $upgrade->chosen_at_level,
                'option_name' => $option->name,
                'option_type' => $option->type,
                'user_choice' => $upgrade->user_choice,
            ],
            'user_character' => [
                'level' => $userCharacter->level,
                'pending_upgrades' => $userCharacter->pendingLevelUpCount(),
                'modified_dice' => $userCharacter->getModifiedDice(false),
                'modified_dice_duel' => $userCharacter->getModifiedDice(true),
                'extra_item_slots' => $userCharacter->getExtraItemSlots(),
                'card_redraws' => $userCharacter->getCardRedraws(),
                'passive_bonuses' => $userCharacter->getPassiveBonuses(),
            ],
        ]);
    }

    /**
     * Immortalise a max-level character.
     */
    public function immortalise(Request $request, UserCharacter $userCharacter): JsonResponse
    {
        if ($userCharacter->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $this->service->immortalise($userCharacter);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Character immortalised as {$userCharacter->incarnation_name}!",
            'user_character' => [
                'id' => $userCharacter->id,
                'level' => $userCharacter->level,
                'xp' => $userCharacter->xp,
                'incarnation' => $userCharacter->incarnation,
                'incarnation_name' => $userCharacter->incarnation_name,
                'display_name' => $userCharacter->getDisplayName(),
            ],
        ]);
    }
}
