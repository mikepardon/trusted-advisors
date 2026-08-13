<template>
  <canvas ref="canvas" class="dice-overlay"></canvas>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from "vue";
import dddiceService from "../dddice-service";

const ready = ref(false);
const canvas = useTemplateRef<HTMLCanvasElement>("canvas");

onMounted(async () => {
  if (!dddiceService.isAvailable()) {
    return;
  }
  const ok = await dddiceService.init(canvas.value);
  ready.value = ok;
});

onBeforeUnmount(() => {
  dddiceService.destroy();
  ready.value = false;
});

function isReady(): boolean {
  return ready.value && dddiceService.isReady();
}

async function rollDice(diceSpecs: unknown): Promise<void> {
  if (!isReady()) {
    return;
  }
  await dddiceService.roll(diceSpecs);
}

function clear(): void {
  dddiceService.clear();
}

defineExpose({ isReady, rollDice, clear });
</script>

<style scoped>
.dice-overlay {
  position: fixed;
  inset: 10px;
  width: calc(100% - 20px);
  height: calc(100% - 20px);
  pointer-events: none;
  z-index: 850;
}
</style>
