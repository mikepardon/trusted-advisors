<template>
  <div class="handoff-overlay">
    <div class="handoff-content">
      <div class="handoff-scroll">
        <div class="handoff-ornament">&#9876;</div>
      </div>
      <h2 class="handoff-title">Player {{ playerNumber }}'s Turn</h2>
      <p class="handoff-character">{{ characterName }}</p>
      <p class="handoff-instruction">Pass the device to this advisor</p>
      <button class="btn-primary handoff-btn" @click="$emit('ready')">
        I'm Ready
      </button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TurnHandoffOverlay',
  props: {
    playerNumber: { type: Number, required: true },
    characterName: { type: String, required: true },
  },
  emits: ['ready'],
};
</script>

<style scoped>
.handoff-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.handoff-content {
  text-align: center;
  padding: 40px 30px;
  max-width: 400px;
}

.handoff-scroll {
  margin-bottom: 20px;
}

.handoff-ornament {
  font-size: 3rem;
  color: var(--accent-gold);
  opacity: 0.7;
}

.handoff-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright, #f0e6d2);
  font-size: 1.8rem;
  margin-bottom: 8px;
}

.handoff-character {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
  margin-bottom: 24px;
}

.handoff-instruction {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 1rem;
  margin-bottom: 30px;
}

.handoff-btn {
  font-size: 1.2rem;
  padding: 14px 48px;
}
</style>
