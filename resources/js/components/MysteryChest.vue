<template>
  <div class="mystery-chest" :class="[`tier-${tier}`, { opened }]">
    <button v-if="!opened" type="button" class="chest-closed" @click="open">
      <span class="chest-glow"></span>
      <span class="chest-icon">&#127873;</span>
      <span class="chest-hint">Tap to open your reward!</span>
    </button>
    <div v-else class="chest-open">
      <span class="chest-tier">{{ tierLabel }}</span>
      <span class="chest-reward">&#129689; {{ displayCoins }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import { playSound } from "../sounds";
import { haptic, type HapticStyle } from "../haptics";

type ChestTier = "common" | "rare" | "epic" | "legendary";

const { tier, coins } = defineProps<{ tier: ChestTier; coins: number }>();
const emit = defineEmits<{ open: [tier: ChestTier] }>();

const opened = ref(false);
const displayCoins = ref(0);

const tierLabels: Record<ChestTier, string> = {
  common: "Common Chest",
  rare: "Rare Chest!",
  epic: "Epic Chest!!",
  legendary: "LEGENDARY!!!",
};

const tierHaptics: Record<ChestTier, HapticStyle> = {
  common: "light",
  rare: "medium",
  epic: "heavy",
  legendary: "success",
};

const tierLabel = computed(() => tierLabels[tier]);

function animateCoins(): void {
  const start = performance.now();
  const duration = 900;
  const step = (now: number): void => {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    displayCoins.value = Math.round(coins * eased);
    if (progress < 1) {
      requestAnimationFrame(step);
    }
  };
  requestAnimationFrame(step);
}

function open(): void {
  if (opened.value) {
    return;
  }
  opened.value = true;
  playSound(tier === "common" ? "click" : "win");
  haptic(tierHaptics[tier]);
  animateCoins();
  emit("open", tier);
}
</script>

<style scoped>
.mystery-chest {
  display: flex;
  justify-content: center;
  margin: 16px 0;
}

.chest-closed {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 14px 28px;
  background: transparent;
  border: none;
  cursor: pointer;
}

.chest-icon {
  font-size: 3.2rem;
  animation: chest-bob 1.2s ease-in-out infinite;
  filter: drop-shadow(0 0 10px var(--chest-color, #d4a843));
}

.chest-glow {
  position: absolute;
  top: 18px;
  width: 70px;
  height: 70px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--chest-color, #d4a843) 0%, transparent 70%);
  opacity: 0.5;
  animation: chest-pulse 1.2s ease-in-out infinite;
}

.chest-hint {
  font-size: 0.8rem;
  color: var(--text-secondary, #94a3b8);
  font-style: italic;
}

.chest-open {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  animation: chest-reveal 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.chest-tier {
  font-family: 'Cinzel', serif;
  font-weight: 700;
  font-size: 1rem;
  color: var(--chest-color, #d4a843);
  text-shadow: 0 0 8px var(--chest-color, #d4a843);
}

.chest-reward {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--accent-gold, #d4a843);
}

.tier-common {
  --chest-color: #9aa4b2;
}
.tier-rare {
  --chest-color: #4d9de0;
}
.tier-epic {
  --chest-color: #b968c7;
}
.tier-legendary {
  --chest-color: #f2c14e;
}

@keyframes chest-bob {
  0%, 100% { transform: translateY(0) rotate(-2deg); }
  50% { transform: translateY(-6px) rotate(2deg); }
}

@keyframes chest-pulse {
  0%, 100% { transform: scale(1); opacity: 0.4; }
  50% { transform: scale(1.3); opacity: 0.65; }
}

@keyframes chest-reveal {
  0% { transform: scale(0.3); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
</style>
