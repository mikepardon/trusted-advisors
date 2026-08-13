<template>
  <div class="splash-overlay" :class="{ 'splash-fade-out': fadingOut }">
    <div class="splash-content">
      <div class="splash-progress-bg">
        <div class="splash-progress-bar" :style="{ width: progress + '%' }"></div>
      </div>
      <p class="splash-blurb">{{ currentBlurb }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";

const emit = defineEmits<{ done: [] }>();

const progress = ref(0);
const currentBlurb = ref("");
const fadingOut = ref(false);
const blurbs = [
  "Restocking the granary...",
  "Sharpening the swords...",
  "Consulting the oracle...",
  "Rallying the troops...",
  "Forging alliances...",
  "Scouting the borders...",
  "Training the squires...",
  "Brewing the mead...",
  "Polishing the crown...",
  "Reading the scrolls...",
  "Feeding the horses...",
  "Raising the banners...",
  "Lighting the torches...",
  "Summoning the council...",
];

const progressTimer = ref<ReturnType<typeof setInterval>>();
const blurbTimer = ref<ReturnType<typeof setInterval>>();

function randomBlurb(): string {
  return blurbs[Math.floor(Math.random() * blurbs.length)];
}

function fadeOut(): void {
  clearInterval(blurbTimer.value);
  fadingOut.value = true;
  setTimeout(() => {
    emit("done");
  }, 500);
}

onMounted(() => {
  currentBlurb.value = randomBlurb();

  const duration = 3000;
  const interval = 30;
  const step = (100 / duration) * interval;
  progressTimer.value = setInterval(() => {
    progress.value = Math.min(progress.value + step, 100);
    if (progress.value >= 100) {
      clearInterval(progressTimer.value);
      fadeOut();
    }
  }, interval);

  blurbTimer.value = setInterval(() => {
    currentBlurb.value = randomBlurb();
  }, 600);
});

onBeforeUnmount(() => {
  clearInterval(progressTimer.value);
  clearInterval(blurbTimer.value);
});
</script>

<style scoped>
.splash-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: var(--bg-primary, #0d0a06);
  background-image:
    radial-gradient(ellipse at 50% 40%, rgba(212, 168, 67, 0.06) 0%, transparent 60%);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: opacity 0.5s ease;
}

.splash-fade-out {
  opacity: 0;
  pointer-events: none;
}

.splash-content {
  text-align: center;
  padding: 20px;
  max-width: 400px;
  width: 100%;
}

.splash-logo {
  max-width: 400px;
  width: 100%;
  height: auto;
  margin-bottom: 40px;
  filter: drop-shadow(0 4px 16px rgba(212, 168, 67, 0.4));
}

.splash-progress-bg {
  width: 100%;
  height: 6px;
  background: rgba(138, 106, 46, 0.2);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 20px;
}

.splash-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--accent-gold, #d4a843), var(--accent-gold-bright, #e8c468));
  border-radius: 3px;
  transition: width 0.03s linear;
}

.splash-blurb {
  color: var(--text-secondary, #a08a6a);
  font-family: 'Crimson Text', Georgia, serif;
  font-style: italic;
  font-size: 1rem;
  min-height: 1.5em;
}
</style>
