<template>
  <canvas ref="canvas" class="dice-overlay"></canvas>
</template>

<script>
import dddiceService from '../dddiceService';

export default {
  name: 'DiceOverlay',
  data() {
    return {
      ready: false,
    };
  },
  async mounted() {
    if (dddiceService.isAvailable()) {
      const ok = await dddiceService.init(this.$refs.canvas);
      this.ready = ok;
    }
  },
  beforeUnmount() {
    dddiceService.destroy();
    this.ready = false;
  },
  methods: {
    isReady() {
      return this.ready && dddiceService.isReady();
    },
    async rollDice(diceSpecs) {
      if (!this.isReady()) return;
      await dddiceService.roll(diceSpecs);
    },
    clear() {
      dddiceService.clear();
    },
  },
};
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
