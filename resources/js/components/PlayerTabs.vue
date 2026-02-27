<template>
  <div class="player-tabs">
    <button
      v-for="player in players"
      :key="player.player_number"
      class="player-tab"
      :class="{
        active: player.player_number === activePlayer,
        selected: player.has_assigned,
      }"
      @click="$emit('switch', player.player_number)"
    >
      <span class="tab-name">P{{ player.player_number }}: {{ player.character_name }}</span>
      <span v-if="player.has_assigned" class="tab-status done">&#10003;</span>
      <span v-else class="tab-status waiting">...</span>
    </button>
  </div>
</template>

<script>
export default {
  name: 'PlayerTabs',
  props: {
    players: { type: Array, required: true },
    activePlayer: { type: Number, default: 1 },
  },
  emits: ['switch'],
};
</script>

<style scoped>
.player-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.player-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: var(--bg-card);
  border: 2px solid rgba(138, 106, 46, 0.25);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.9rem;
}

.player-tab:hover {
  border-color: var(--border-gold);
}

.player-tab.active {
  border-color: var(--accent-gold);
  background: linear-gradient(135deg, var(--bg-card), #3a2a1a);
  box-shadow: 0 0 15px rgba(212, 168, 67, 0.2);
}

.player-tab.selected {
  opacity: 0.7;
}

.tab-name {
  color: var(--text-bright);
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
}

.tab-status {
  font-size: 0.9rem;
  font-weight: 700;
}

.tab-status.done {
  color: var(--accent-green);
}

.tab-status.waiting {
  color: var(--text-secondary);
}
</style>
