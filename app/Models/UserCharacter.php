<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class UserCharacter extends Model
{
    protected $fillable = [
        'user_id', 'character_id', 'xp', 'level', 'incarnation',
        'incarnation_name', 'max_item_slots_bonus',
    ];

    protected $casts = [
        'xp' => 'integer',
        'level' => 'integer',
        'incarnation' => 'integer',
        'max_item_slots_bonus' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function upgrades(): HasMany
    {
        return $this->hasMany(UserCharacterUpgrade::class);
    }

    public function currentUpgrades(): HasMany
    {
        return $this->hasMany(UserCharacterUpgrade::class)
            ->where('incarnation', $this->incarnation);
    }

    public static function getLevelConfig(): array
    {
        return Cache::remember('advisor_level_config', 60, function () {
            return GameRule::getValue('advisor_level_config', [
                // Cumulative XP required to reach each level. Deliberately steeper than a
                // flat curve so late levels (and immortalising) are a real commitment.
                'level_xp' => [1 => 0, 2 => 400, 3 => 1000, 4 => 2100, 5 => 4000, 6 => 7000, 7 => 11500, 8 => 18000],
                'max_level' => 8,
                'level_coin_costs' => [4 => 100, 5 => 200, 6 => 350, 7 => 550, 8 => 800],
                'immortalise_base_cost' => 2000,
                'incarnation_cost_multiplier' => 1.2,
            ]);
        });
    }

    public static function xpForLevel(int $level): int
    {
        $levelXp = static::getLevelConfig()['level_xp'] ?? [1 => 0];

        if (isset($levelXp[$level])) {
            return (int) $levelXp[$level];
        }

        // Below the first threshold is 0; above the last defined level clamps to its cost.
        if ($level < 1) {
            return 0;
        }

        return (int) max($levelXp);
    }

    public static function getMaxLevel(): int
    {
        $config = static::getLevelConfig();
        return $config['max_level'] ?? 8;
    }

    /**
     * Coin cost to apply the upgrade for a given level at this incarnation.
     * Levels 1-3 are free; 4-8 cost coins, scaled by the incarnation ramp.
     */
    public function coinCostForLevel(int $level): int
    {
        if ($level <= 3) {
            return 0;
        }

        $costs = static::getLevelConfig()['level_coin_costs'] ?? [4 => 100, 5 => 200, 6 => 350, 7 => 550, 8 => 800];

        return $this->scaleForIncarnation((int) ($costs[$level] ?? 0));
    }

    public function immortaliseCost(): int
    {
        $base = (int) (static::getLevelConfig()['immortalise_base_cost'] ?? 2000);

        return $this->scaleForIncarnation($base);
    }

    /**
     * Scale a base coin cost by the incarnation ramp (×multiplier per prior
     * incarnation). Coins are an integer soft currency, so the result is rounded
     * to a whole coin via brick/math.
     */
    private function scaleForIncarnation(int $base): int
    {
        if ($base <= 0) {
            return 0;
        }

        $multiplier = static::getLevelConfig()['incarnation_cost_multiplier'] ?? 1.2;
        $exponent = max(0, $this->incarnation - 1);
        $factor = BigDecimal::of((string) $multiplier)->power($exponent);

        return BigDecimal::of($base)
            ->multipliedBy($factor)
            ->toScale(0, RoundingMode::HALF_UP)
            ->toInt();
    }

    public static function calculateLevel(int $xp): int
    {
        $maxLevel = static::getMaxLevel();
        $level = 1;
        while ($level < $maxLevel && $xp >= static::xpForLevel($level + 1)) {
            $level++;
        }
        return $level;
    }

    public function canLevelUp(): bool
    {
        return $this->pendingLevelUpCount() > 0;
    }

    public function pendingLevelUpCount(): int
    {
        $calculatedLevel = static::calculateLevel($this->xp);
        $upgradesChosen = $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->count();
        // Upgrades are chosen at levels 2-8, so max is level-1 choices
        $expectedChoices = max(0, $calculatedLevel - 1);
        return max(0, $expectedChoices - $upgradesChosen);
    }

    public function nextPendingLevel(): ?int
    {
        $upgradesChosen = $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->count();
        $nextLevel = $upgradesChosen + 2; // First upgrade is at level 2
        $calculatedLevel = static::calculateLevel($this->xp);
        return $nextLevel <= $calculatedLevel ? $nextLevel : null;
    }

    public function getDisplayName(): string
    {
        if ($this->incarnation_name) {
            return $this->incarnation_name;
        }
        return $this->character->name ?? 'Unknown';
    }

    public function getModifiedDice(bool $duel = false): array
    {
        $baseDice = $duel
            ? ($this->character->getDuelDice())
            : $this->character->dice;

        $upgrades = $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->with('option')
            ->get();

        $dice = json_decode(json_encode($baseDice), true);

        foreach ($upgrades as $upgrade) {
            $type = $upgrade->option->type;
            $choice = $upgrade->user_choice;

            if ($type === 'bump_dice_face' && $choice) {
                $di = $choice['die_index'] ?? 0;
                $fi = $choice['face_index'] ?? 0;
                if (isset($dice[$di][$fi]) && $dice[$di][$fi] !== 'WILD') {
                    $dice[$di][$fi] = (int) $dice[$di][$fi] + 1;
                }
            }

            if ($type === 'bump_two_dice_faces' && $choice) {
                foreach ($choice['faces'] ?? [] as $face) {
                    $di = $face['die_index'] ?? 0;
                    $fi = $face['face_index'] ?? 0;
                    if (isset($dice[$di][$fi]) && $dice[$di][$fi] !== 'WILD') {
                        $dice[$di][$fi] = (int) $dice[$di][$fi] + 1;
                    }
                }
            }

            if ($type === 'add_wild' && $choice) {
                $di = $choice['die_index'] ?? 0;
                $fi = $choice['face_index'] ?? 0;
                if (isset($dice[$di][$fi])) {
                    $dice[$di][$fi] = 'WILD';
                }
            }
        }

        return $dice;
    }

    public function getStartingItems(): array
    {
        $upgrades = $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->with('option')
            ->get();

        $items = [];
        foreach ($upgrades as $upgrade) {
            if ($upgrade->option->type !== 'start_with_item') continue;
            $config = $upgrade->option->config;
            if (!empty($config['item_id'])) {
                $items[] = ['type' => 'specific', 'item_id' => $config['item_id']];
            } elseif (!empty($config['random'])) {
                $items[] = ['type' => 'random'];
            }
        }
        return $items;
    }

    public function getExtraItemSlots(): int
    {
        return $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->whereHas('option', fn ($q) => $q->where('type', 'extra_item_slot'))
            ->count();
    }

    public function getPassiveBonuses(): array
    {
        $upgrades = $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->with('option')
            ->get();

        $bonuses = [];
        foreach ($upgrades as $upgrade) {
            if ($upgrade->option->type !== 'passive_stat_bonus') continue;
            $config = $upgrade->option->config;
            $stat = $config['stat'] ?? null;
            $value = $config['value'] ?? 0;
            if ($stat) {
                $bonuses[$stat] = ($bonuses[$stat] ?? 0) + $value;
            }
        }
        return $bonuses;
    }

    public function getCardRedraws(): int
    {
        return $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->whereHas('option', fn ($q) => $q->where('type', 'card_redraw'))
            ->count();
    }

    public function getStartingCurses(): array
    {
        $upgrades = $this->upgrades()
            ->where('incarnation', $this->incarnation)
            ->with('option')
            ->get();

        $curses = [];
        foreach ($upgrades as $upgrade) {
            if ($upgrade->option->type !== 'start_with_curse') continue;
            $config = $upgrade->option->config;
            if (!empty($config['curse_id'])) {
                $curses[] = ['type' => 'specific', 'curse_id' => $config['curse_id']];
            } elseif (!empty($config['random'])) {
                $curses[] = ['type' => 'random'];
            }
        }
        return $curses;
    }
}
