<?php

namespace App\Services;

use App\Models\CharacterLevelOption;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameRule;
use App\Models\User;
use App\Models\UserCharacter;
use App\Models\UserCharacterUpgrade;

class CharacterProgressionService
{
    /**
     * Award character XP after a game completes.
     * Same formula as user XP but WITHOUT curse multiplier or achievement XP.
     */
    public function awardCharacterXp(User $user, Game $game, $players, bool $isWinner): ?array
    {
        $player = $players->firstWhere('user_id', $user->id);
        if (!$player || !$player->character_id) return null;

        $userCharacter = UserCharacter::where('user_id', $user->id)
            ->where('character_id', $player->character_id)
            ->first();

        if (!$userCharacter) {
            // Auto-create if user has the character but no UserCharacter record
            $userCharacter = UserCharacter::create([
                'user_id' => $user->id,
                'character_id' => $player->character_id,
            ]);
        }

        $globalXpConfig = GameRule::getValue('xp_config', [
            'base_xp' => 50,
            'coop_win_bonus' => 100,
            'duel_win_bonus' => 150,
            'online_multiplier' => 1.5,
        ]);

        $eventXpConfig = $game->rotating_event_id ? $game->rotatingEvent?->xp_config : null;

        $base = $eventXpConfig['base_xp'] ?? $globalXpConfig['base_xp'] ?? 50;
        $bonus = 0;

        if ($isWinner) {
            if ($eventXpConfig && isset($eventXpConfig['win_bonus'])) {
                $bonus = $eventXpConfig['win_bonus'];
            } else {
                $bonus = $game->isDuel()
                    ? ($globalXpConfig['duel_win_bonus'] ?? 150)
                    : ($globalXpConfig['coop_win_bonus'] ?? 100);
            }
        }

        $total = $base + $bonus;

        $onlineMultiplier = $eventXpConfig['online_multiplier'] ?? $globalXpConfig['online_multiplier'] ?? 1.5;
        if ($game->isOnline()) {
            $total = (int) ($total * $onlineMultiplier);
        }

        $oldLevel = $userCharacter->level;
        $oldXp = $userCharacter->xp;
        $userCharacter->xp += $total;
        $userCharacter->level = UserCharacter::calculateLevel($userCharacter->xp);
        $userCharacter->save();

        return [
            'character_id' => $player->character_id,
            'character_name' => $player->character->name ?? 'Unknown',
            'xp_earned' => $total,
            'old_xp' => $oldXp,
            'new_xp' => $userCharacter->xp,
            'old_level' => $oldLevel,
            'new_level' => $userCharacter->level,
            'leveled_up' => $userCharacter->level > $oldLevel,
            'pending_upgrades' => $userCharacter->pendingLevelUpCount(),
        ];
    }

    /**
     * Get available level-up options for a user character at a given level.
     */
    public function getAvailableOptions(UserCharacter $userCharacter, int $forLevel): \Illuminate\Support\Collection
    {
        $options = CharacterLevelOption::where('is_active', true)
            ->where('available_at_level', '<=', $forLevel)
            ->where(function ($q) use ($userCharacter) {
                $q->whereNull('character_id')
                    ->orWhere('character_id', $userCharacter->character_id);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Filter by max_selections constraint
        return $options->filter(function ($option) use ($userCharacter) {
            if ($option->max_selections <= 0) return true;

            $timesChosen = $userCharacter->upgrades()
                ->where('incarnation', $userCharacter->incarnation)
                ->where('character_level_option_id', $option->id)
                ->count();

            return $timesChosen < $option->max_selections;
        });
    }

    /**
     * Choose a level-up option for a user character.
     */
    public function chooseLevelUpOption(UserCharacter $userCharacter, CharacterLevelOption $option, int $forLevel, ?array $userChoice = null): UserCharacterUpgrade
    {
        // Validate the level slot is available
        $nextPending = $userCharacter->nextPendingLevel();
        if ($nextPending === null || $nextPending !== $forLevel) {
            throw new \InvalidArgumentException("Cannot choose upgrade for level {$forLevel}. Next pending level is " . ($nextPending ?? 'none'));
        }

        // Validate option is available for this level
        if ($option->available_at_level > $forLevel) {
            throw new \InvalidArgumentException("Option '{$option->name}' is not available until level {$option->available_at_level}");
        }

        // Validate max_selections
        if ($option->max_selections > 0) {
            $timesChosen = $userCharacter->upgrades()
                ->where('incarnation', $userCharacter->incarnation)
                ->where('character_level_option_id', $option->id)
                ->count();

            if ($timesChosen >= $option->max_selections) {
                throw new \InvalidArgumentException("Option '{$option->name}' has reached its maximum selections ({$option->max_selections})");
            }
        }

        // Validate character_id constraint
        if ($option->character_id && $option->character_id !== $userCharacter->character_id) {
            throw new \InvalidArgumentException("Option '{$option->name}' is not available for this character");
        }

        // Validate user_choice for types that require it
        $this->validateUserChoice($option, $userChoice);

        $upgrade = UserCharacterUpgrade::create([
            'user_character_id' => $userCharacter->id,
            'character_level_option_id' => $option->id,
            'chosen_at_level' => $forLevel,
            'incarnation' => $userCharacter->incarnation,
            'user_choice' => $userChoice,
        ]);

        // Recompute denormalized max_item_slots_bonus
        if ($option->type === 'extra_item_slot') {
            $userCharacter->max_item_slots_bonus = $userCharacter->getExtraItemSlots();
            $userCharacter->save();
        }

        return $upgrade;
    }

    /**
     * Immortalise a max-level character: reset to level 1 with new incarnation.
     */
    public function immortalise(UserCharacter $userCharacter): UserCharacter
    {
        $maxLevel = UserCharacter::getMaxLevel();
        if ($userCharacter->level < $maxLevel) {
            throw new \InvalidArgumentException("Character must be max level ({$maxLevel}) to immortalise");
        }

        // Check all level-up choices are made
        if ($userCharacter->pendingLevelUpCount() > 0) {
            throw new \InvalidArgumentException('All pending level-up choices must be made before immortalising');
        }

        $userCharacter->xp = 0;
        $userCharacter->level = 1;
        $userCharacter->incarnation++;
        $userCharacter->incarnation_name = $this->generateIncarnationName(
            $userCharacter->character->name,
            $userCharacter->incarnation
        );
        $userCharacter->max_item_slots_bonus = 0;
        $userCharacter->save();

        return $userCharacter;
    }

    /**
     * Generate incarnation display name with Roman numeral.
     */
    private function generateIncarnationName(string $baseName, int $incarnation): string
    {
        if ($incarnation <= 1) return $baseName;

        $numerals = [
            2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V',
            6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
        ];

        $numeral = $numerals[$incarnation] ?? (string) $incarnation;
        return "{$baseName} {$numeral}";
    }

    /**
     * Compute modified dice for a user character, applying all upgrades.
     */
    public function computeModifiedDice(UserCharacter $userCharacter, bool $duel = false): array
    {
        return $userCharacter->getModifiedDice($duel);
    }

    /**
     * Assign starter characters to a new user.
     */
    public function assignStarterCharacters(User $user): void
    {
        $starterIds = GameRule::getValue('starter_character_ids', [1, 2]);

        foreach ($starterIds as $charId) {
            UserCharacter::firstOrCreate([
                'user_id' => $user->id,
                'character_id' => $charId,
            ]);
        }
    }

    /**
     * Validate user_choice for upgrade types that require it.
     */
    private function validateUserChoice(CharacterLevelOption $option, ?array $userChoice): void
    {
        switch ($option->type) {
            case 'bump_dice_face':
                if (!$userChoice || !isset($userChoice['die_index']) || !isset($userChoice['face_index'])) {
                    throw new \InvalidArgumentException('bump_dice_face requires die_index and face_index');
                }
                break;

            case 'bump_two_dice_faces':
                if (!$userChoice || empty($userChoice['faces']) || count($userChoice['faces']) !== 2) {
                    throw new \InvalidArgumentException('bump_two_dice_faces requires exactly 2 faces');
                }
                foreach ($userChoice['faces'] as $face) {
                    if (!isset($face['die_index']) || !isset($face['face_index'])) {
                        throw new \InvalidArgumentException('Each face requires die_index and face_index');
                    }
                }
                break;

            case 'add_wild':
                if (!$userChoice || !isset($userChoice['die_index']) || !isset($userChoice['face_index'])) {
                    throw new \InvalidArgumentException('add_wild requires die_index and face_index');
                }
                break;

            // Types that don't need user_choice
            case 'start_with_item':
            case 'start_with_curse':
            case 'extra_item_slot':
            case 'passive_stat_bonus':
            case 'card_redraw':
                break;
        }
    }
}
