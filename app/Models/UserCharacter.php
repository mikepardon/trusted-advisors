<?php

namespace App\Models;

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
                'xp_per_level' => 100,
                'max_level' => 8,
            ]);
        });
    }

    public static function xpForLevel(int $level): int
    {
        $config = static::getLevelConfig();
        $xpPerLevel = $config['xp_per_level'] ?? 100;
        return (int) ($xpPerLevel * ($level - 1) * $level / 2);
    }

    public static function getMaxLevel(): int
    {
        $config = static::getLevelConfig();
        return $config['max_level'] ?? 8;
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
