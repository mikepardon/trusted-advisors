<template>
  <div v-if="event" class="event-banner" :class="{ 'event-expanded': expanded }" @click="expanded = !expanded">
    <div class="event-header">
      <p class="event-summary">{{ effectSummary }}</p>
      <span class="event-toggle">{{ expanded ? '&#9650;' : '&#9660;' }}</span>
    </div>
    <h3 v-if="expanded" class="event-title">{{ event.title }}</h3>
    <p v-if="expanded" class="event-effect">{{ event.effect }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";

interface BannerEvent {
  title?: string;
  effect?: string;
  mechanic?: string;
  stat_modifiers?: Record<string, number>;
  mechanic_data?: {
    amount?: number;
    positive_cards?: number;
    negative_cards?: number;
  };
}

const { event = undefined } = defineProps<{
  event?: BannerEvent;
}>();

const expanded = ref(false);

const effectSummary = computed(() => {
  if (!event) {
    return "";
  }
  // Build summary from stat_modifiers if available
  if (event.stat_modifiers && Object.keys(event.stat_modifiers).length > 0) {
    return Object.entries(event.stat_modifiers)
      .map(([stat, value]) => `${stat.charAt(0).toUpperCase() + stat.slice(1)} ${value > 0 ? "+" : ""}${value} each round`)
      .join(", ");
  }
  // Fallback: use mechanic description
  if (event.mechanic === "reduce_dice") {
    const amount = event.mechanic_data?.amount || 1;
    return `Each advisor loses ${amount} die`;
  }
  if (event.mechanic === "altered_deal") {
    const pos = event.mechanic_data?.positive_cards || 1;
    const neg = event.mechanic_data?.negative_cards || 1;
    return `Deal ${pos} positive, ${neg} negative cards`;
  }
  if (event.mechanic === "grant_items") {
    return "All advisors receive an item";
  }
  // Final fallback: truncate effect text
  if (event.effect && event.effect.length > 40) {
    return event.effect.slice(0, 40) + "...";
  }
  return event.effect || "";
});
</script>

<style scoped>
.event-banner {
  background: linear-gradient(135deg, #2c1810, #4a2020);
  border: 1px solid #8b4513;
  border-radius: 8px;
  padding: 14px 20px;
  cursor: pointer;
  transition: border-color 0.2s;
}

.event-banner:hover {
  border-color: #c0392b;
}

.event-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.event-toggle {
  color: #c0392b;
  font-size: 0.7rem;
}

.event-summary {
  color: var(--accent-gold);
  font-weight: 600;
  font-size: 0.85rem;
  margin: 0;
}

.event-title {
  font-family: 'Cinzel', serif;
  color: #e8a040;
  font-size: 1.1rem;
  margin-bottom: 6px;
  margin-top: 8px;
}

.event-effect {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
  line-height: 1.4;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid rgba(139, 69, 19, 0.3);
}

/* ---- Mobile compact ---- */
@media (max-width: 768px) {
  .event-banner {
    padding: 12px 16px;
  }

  .event-title {
    font-size: 0.95rem;
    margin-bottom: 4px;
  }

  .event-summary {
    font-size: 0.8rem;
  }

  .event-effect {
    font-size: 0.82rem;
  }
}
</style>
