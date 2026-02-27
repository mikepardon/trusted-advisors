<template>
  <div class="waiting-overlay">
    <div class="waiting-content">
      <h2 class="waiting-title">Waiting for Other Advisors...</h2>
      <div class="player-status-list">
        <div
          v-for="player in playerStatus"
          :key="player.player_number"
          :class="['status-row', { 'status-done': player.has_assigned }]"
        >
          <span class="status-icon">{{ player.has_assigned ? '&#10003;' : '&#9679;' }}</span>
          <span class="status-name">{{ player.character_name }}</span>
          <span class="status-label">{{ player.has_assigned ? 'Ready' : 'Deciding...' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WaitingOverlay',
  props: {
    playerStatus: { type: Array, required: true },
  },
};
</script>

<style scoped>
.waiting-overlay {
  text-align: center;
  padding: 30px 20px;
  background: var(--bg-secondary, #1a1209);
  border: 1px solid rgba(212, 168, 67, 0.25);
  border-radius: 8px;
  margin: 20px 0;
}

.waiting-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 20px;
}

.player-status-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: 300px;
  margin: 0 auto;
}

.status-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-radius: 6px;
  background: rgba(26, 18, 9, 0.6);
  border: 1px solid rgba(138, 106, 46, 0.2);
}

.status-done {
  border-color: var(--accent-green, #4a8a3a);
  background: rgba(74, 138, 58, 0.08);
}

.status-icon {
  font-size: 1.1rem;
  width: 20px;
  color: var(--text-secondary);
}

.status-done .status-icon {
  color: var(--accent-green, #4a8a3a);
}

.status-name {
  flex: 1;
  font-family: 'Cinzel', serif;
  color: var(--text-bright, #f0e6d2);
  text-align: left;
}

.status-label {
  font-size: 0.85rem;
  font-style: italic;
  color: var(--text-secondary);
}

.status-done .status-label {
  color: var(--accent-green, #4a8a3a);
}
</style>
