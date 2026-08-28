<template>
  <!-- Single item rendered in the in-game parchment card style, mirroring the
       inventory card from PlayerItems.vue so admins see it as players do. -->
  <div class="parchment-card" :class="{ cursed: item.is_negative }">
    <div class="card-ornament">{{ typeGlyph }}</div>
    <h3 class="parchment-title">{{ item.name || 'Unknown Item' }}</h3>

    <!-- Tags row -->
    <div class="tag-row">
      <span v-if="item.is_negative" class="type-tag cursed-tag">Negative</span>
      <span v-if="cadenceLabel" class="type-tag reusable-tag">{{ cadenceLabel }}</span>
      <span v-else-if="item.is_consumable" class="type-tag used-tag">Consumable</span>
      <span v-if="item.target === 'opponent'" class="type-tag opponent-tag">Targets Opponent</span>
    </div>

    <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

    <p class="parchment-desc">{{ item.description || '' }}</p>

    <div class="parchment-divider divider-thin"><span class="divider-ornament small">&#8226;</span></div>

    <!-- Effect in stat-chip style -->
    <div v-if="effectSummary" class="outcome-chips">
      <span class="stat-chip" :class="effectChipClass">{{ effectSummary }}</span>
    </div>

    <div class="card-meta">
      <span v-if="item.effect_type" class="meta-type">{{ item.effect_type }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

interface ItemEffect {
  bonus_type?: string;
  bonus_value?: number;
  stat?: string;
}

interface PreviewItem {
  name?: string;
  description?: string;
  effect_type?: string;
  effect?: ItemEffect;
  type?: string;
  cadence?: string;
  target?: string;
  is_negative?: boolean;
  is_consumable?: boolean;
}

const { item } = defineProps<{
  item: PreviewItem;
}>();

// Emoji glyph per item type, used for the card ornament (mirrors PlayerItems.vue).
const TYPE_GLYPHS: Record<string, string> = {
  weapon: "⚔️",
  armour: "🛡️",
  potion: "🧪",
  scroll: "📜",
  relic: "✨",
  coin: "💰",
  hex: "🔮",
};

const CADENCE_LABELS: Record<string, string> = {
  passive: "Always Active",
  per_round: "Reusable",
  per_game: "Single Use",
};

const typeGlyph = computed<string>(() => (item.type && TYPE_GLYPHS[item.type]) || "⚔");

const cadenceLabel = computed<string | undefined>(() =>
  item.cadence ? CADENCE_LABELS[item.cadence] : undefined,
);

const effectSummary = computed<string>(() => {
  const effect = item.effect;
  if (!effect) {
    return "";
  }
  const type = effect.bonus_type || "";
  const value = effect.bonus_value ?? 0;
  switch (type) {
    case "roll_bonus": {
      return `+${value} to roll`;
    }
    case "roll_penalty": {
      return `${value} to roll`;
    }
    case "difficulty_reduction": {
      return `-${Math.abs(value)} difficulty`;
    }
    case "difficulty_increase": {
      return `+${Math.abs(value)} difficulty`;
    }
    case "score_bonus": {
      return `${value > 0 ? "+" : ""}${value} renown`;
    }
    case "stat_boost": {
      return `+${value} ${effect.stat || "stat"}`;
    }
    case "heal_die": {
      return "Recover a lost die";
    }
    case "shield_negative": {
      return "Block negative effects";
    }
    case "debuff_roll": {
      return `${value} to opponent roll`;
    }
    case "increase_difficulty": {
      return `+${Math.abs(value)} opponent difficulty`;
    }
    case "peek_cards": {
      return "Peek at opponent cards";
    }
    case "steal_stat": {
      return `Steal ${value} stat point`;
    }
    default: {
      return item.description || "Single-use effect";
    }
  }
});

const effectChipClass = computed<string>(() => {
  const type = item.effect?.bonus_type || "";
  if (type === "roll_bonus" || type === "difficulty_reduction") {
    return "chip-positive";
  }
  if (type === "debuff_roll" || type === "increase_difficulty") {
    return "chip-negative";
  }
  return "chip-neutral";
});
</script>

<style scoped>
.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 24px 20px;
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(212, 168, 67, 0.08);
}

.parchment-card.cursed {
  border-color: rgba(192, 57, 43, 0.7);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5), 0 0 15px rgba(192, 57, 43, 0.15);
}

.card-ornament {
  font-size: 2rem;
  color: var(--accent-gold, #c9a84c);
  opacity: 0.5;
  margin-bottom: 8px;
}

.parchment-card.cursed .card-ornament {
  color: #c0392b;
}

.parchment-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 6px;
  line-height: 1.3;
}

.parchment-card.cursed .parchment-title {
  color: #e07060;
}

.tag-row {
  display: flex;
  gap: 6px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.type-tag {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-radius: 3px;
  padding: 1px 8px;
}

.cursed-tag {
  color: #c0392b;
  border: 1px solid #c0392b;
}

.reusable-tag {
  color: #67c23a;
  border: 1px solid #67c23a;
}

.used-tag {
  color: var(--text-secondary, #a09080);
  border: 1px solid var(--text-secondary, #a09080);
}

.opponent-tag {
  color: #e57373;
  border: 1px solid #e57373;
}

.parchment-divider {
  position: relative;
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold, #6b5b3a), transparent);
  margin: 12px 0;
}

.parchment-divider.divider-thin {
  background: linear-gradient(90deg, transparent, rgba(138, 106, 46, 0.4), transparent);
  margin: 8px 0;
}

.divider-ornament {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #2a1f14;
  color: var(--accent-gold, #c9a84c);
  padding: 0 8px;
  font-size: 0.7rem;
}

.divider-ornament.small {
  font-size: 0.5rem;
  color: var(--text-secondary, #a09080);
}

.parchment-desc {
  color: var(--text-primary, #e8d5b0);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  flex: 1;
}

.outcome-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  justify-content: center;
}

.stat-chip {
  padding: 3px 12px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  font-family: 'Cinzel', serif;
}

.chip-positive {
  background: rgba(39, 174, 96, 0.15);
  color: #4caf50;
}

.chip-negative {
  background: rgba(192, 57, 43, 0.15);
  color: #e57373;
}

.chip-neutral {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold, #c9a84c);
}

.card-meta {
  margin-top: 10px;
}

.meta-type {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary, #a09080);
  opacity: 0.7;
}
</style>
