<template>
  <Transition name="reward-pop">
    <div
      v-if="current"
      class="reward-overlay"
      :class="`tier-${current.tier}`"
      @click="collect"
    >
      <VictoryEffect ref="confetti" />

      <div class="reward-rays"></div>

      <div class="reward-stage">
        <div class="reward-burst">
          <span
            v-for="n in 12"
            :key="n"
            class="coin-spark"
            :style="sparkStyle(n)"
          ></span>
        </div>

        <div class="reward-icon">{{ current.icon }}</div>

        <div class="reward-title">{{ current.title }}</div>

        <div class="reward-amount">
          <span class="amount-coin">&#9673;</span>
          <span class="amount-num">{{ displayAmount }}</span>
        </div>

        <div v-if="tierLabel" class="reward-tier">{{ tierLabel }}</div>
      </div>

      <div class="reward-hint">Tap to collect</div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import VictoryEffect from "./VictoryEffect.vue";
import { useReward, type RewardTier } from "../stores/reward";
import { playSound } from "../sounds";
import { haptic, type HapticStyle } from "../haptics";

const reward = useReward();

const confetti = ref<InstanceType<typeof VictoryEffect>>();
const displayAmount = ref(0);
const dismissTimer = ref<ReturnType<typeof setTimeout> | undefined>(undefined);
const countRaf = ref<number | undefined>(undefined);

const current = computed(() => reward.state.current);

const tierLabels: Record<RewardTier, string> = {
  common: "",
  rare: "Rare Reward!",
  epic: "Epic Reward!!",
  legendary: "LEGENDARY!!!",
};

const tierHaptics: Record<RewardTier, HapticStyle> = {
  common: "medium",
  rare: "medium",
  epic: "heavy",
  legendary: "success",
};

const tierLabel = computed(() => (current.value ? tierLabels[current.value.tier] : ""));

function sparkStyle(index: number): Record<string, string> {
  const angle = (index - 1) * 30;
  const distance = 92 + (index % 3) * 22;
  return {
    "--spark-angle": `${angle}deg`,
    "--spark-dist": `${distance}px`,
    animationDelay: `${(index % 4) * 0.04}s`,
  };
}

function countUp(target: number): void {
  if (countRaf.value !== undefined) {
    cancelAnimationFrame(countRaf.value);
  }
  const start = performance.now();
  const duration = 850;
  const step = (now: number): void => {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    displayAmount.value = Math.round(target * eased);
    if (progress < 1) {
      countRaf.value = requestAnimationFrame(step);
    }
  };
  countRaf.value = requestAnimationFrame(step);
}

function collect(): void {
  if (dismissTimer.value !== undefined) {
    clearTimeout(dismissTimer.value);
    dismissTimer.value = undefined;
  }
  reward.dismiss();
}

// Fire the celebration whenever a new reward becomes current.
watch(
  () => current.value?.id,
  (id) => {
    if (id === undefined || current.value === undefined) {
      displayAmount.value = 0;
      return;
    }

    const tier = current.value.tier;
    displayAmount.value = 0;
    countUp(current.value.amount);
    playSound(tier === "common" ? "clickCard" : "win");
    haptic(tierHaptics[tier]);

    if (tier === "epic" || tier === "legendary") {
      // Wait a tick so the freshly-mounted canvas has sized itself.
      requestAnimationFrame(() => confetti.value?.fire("gold_rain", tier === "legendary" ? 180 : 100));
    }

    if (dismissTimer.value !== undefined) {
      clearTimeout(dismissTimer.value);
    }
    dismissTimer.value = setTimeout(collect, 5000);
  },
  { immediate: true },
);
</script>

<style scoped>
.reward-overlay {
  position: fixed;
  inset: 0;
  z-index: 2500;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: radial-gradient(
    ellipse at 50% 42%,
    rgba(30, 22, 12, 0.82),
    rgba(6, 4, 2, 0.92) 70%
  );
  backdrop-filter: blur(3px);
  cursor: pointer;
  --glow: #d4a843;
}

.tier-rare { --glow: #4d9de0; }
.tier-epic { --glow: #b072e0; }
.tier-legendary { --glow: #ffd54a; }

/* Rotating light rays behind the reward */
.reward-rays {
  position: absolute;
  width: 150vmax;
  height: 150vmax;
  background: repeating-conic-gradient(
    from 0deg,
    color-mix(in srgb, var(--glow) 22%, transparent) 0deg 8deg,
    transparent 8deg 22deg
  );
  opacity: 0.3;
  animation: ray-spin 14s linear infinite;
  pointer-events: none;
}

.tier-legendary .reward-rays {
  opacity: 0.42;
  animation-duration: 9s;
}

@keyframes ray-spin {
  to { transform: rotate(360deg); }
}

.reward-stage {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  animation: stage-in 0.5s cubic-bezier(0.18, 1.4, 0.4, 1) both;
}

@keyframes stage-in {
  0% { transform: scale(0.4); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}

/* Coin particle burst */
.reward-burst {
  position: absolute;
  top: 34px;
  left: 50%;
  width: 0;
  height: 0;
}

.coin-spark {
  position: absolute;
  top: 0;
  left: 0;
  width: 11px;
  height: 11px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, #ffe9a8, #e0a92e 70%);
  box-shadow: 0 0 9px rgba(240, 200, 120, 0.85);
  transform: rotate(var(--spark-angle)) translateX(0);
  animation: spark-fly 0.95s ease-out forwards;
}

@keyframes spark-fly {
  0% {
    opacity: 0;
    transform: rotate(var(--spark-angle)) translateX(0) scale(0.4);
  }
  18% { opacity: 1; }
  100% {
    opacity: 0;
    transform: rotate(var(--spark-angle)) translateX(var(--spark-dist)) scale(1);
  }
}

.reward-icon {
  font-size: 4.4rem;
  line-height: 1;
  filter: drop-shadow(0 0 18px var(--glow));
  animation: icon-pop 0.6s cubic-bezier(0.18, 1.5, 0.4, 1) both, icon-bob 2.2s ease-in-out 0.6s infinite;
}

@keyframes icon-pop {
  0% { transform: scale(0) rotate(-20deg); }
  100% { transform: scale(1) rotate(0); }
}

@keyframes icon-bob {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-7px); }
}

.reward-title {
  font-family: "Cinzel", serif;
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--text-secondary, #cbb98c);
  margin-top: 6px;
}

.reward-amount {
  display: flex;
  align-items: center;
  gap: 8px;
}

.amount-coin {
  font-size: 1.8rem;
  color: var(--glow);
  filter: drop-shadow(0 0 8px var(--glow));
}

.amount-num {
  font-family: "Cinzel", serif;
  font-size: 3.4rem;
  font-weight: 800;
  color: #fff6dc;
  text-shadow:
    0 0 18px var(--glow),
    0 3px 6px rgba(0, 0, 0, 0.6);
}

.reward-tier {
  font-family: "Cinzel", serif;
  font-size: 0.95rem;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--glow);
  text-shadow: 0 0 12px var(--glow);
  animation: tier-flash 1.1s ease-in-out infinite;
}

@keyframes tier-flash {
  0%, 100% { opacity: 0.8; }
  50% { opacity: 1; }
}

.reward-hint {
  position: absolute;
  bottom: 12%;
  font-size: 0.8rem;
  font-style: italic;
  color: var(--text-secondary, #94a3b8);
  animation: hint-pulse 1.6s ease-in-out infinite;
}

@keyframes hint-pulse {
  0%, 100% { opacity: 0.4; }
  50% { opacity: 0.85; }
}

/* Overlay enter / leave */
.reward-pop-enter-active {
  transition: opacity 0.25s ease;
}
.reward-pop-leave-active {
  transition: opacity 0.3s ease;
}
.reward-pop-enter-from,
.reward-pop-leave-to {
  opacity: 0;
}
</style>
