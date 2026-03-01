<template>
  <transition name="overlay-fade">
    <div v-if="visible" class="item-discard-overlay">
      <div class="item-discard-content">
        <h3 class="discard-title">Too Many Items!</h3>
        <p class="discard-subtitle">
          {{ playerData.character_name }} is carrying too many items. Choose one to discard:
        </p>

        <div class="items-list">
          <div
            v-for="item in playerData.items"
            :key="item.id"
            class="discard-item"
            :class="{
              cursed: item.is_cursed,
              selected: selectedId === item.id,
              disabled: item.is_cursed,
            }"
            @click="!item.is_cursed && selectItem(item.id)"
          >
            <span class="item-name">{{ item.item_name }}</span>
            <span v-if="item.is_cursed" class="cursed-label">Cursed</span>
            <span v-else-if="selectedId === item.id" class="selected-label">Discard</span>
          </div>
        </div>

        <button
          class="btn-primary discard-btn"
          :disabled="!selectedId || discarding"
          @click="confirmDiscard"
        >
          {{ discarding ? 'Discarding...' : 'Confirm Discard' }}
        </button>
      </div>
    </div>
  </transition>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ItemDiscard',
  props: {
    gameId: { type: [String, Number], required: true },
    playerData: { type: Object, required: true },
  },
  emits: ['discarded'],
  data() {
    return {
      visible: false,
      selectedId: null,
      discarding: false,
    };
  },
  mounted() {
    this.visible = true;
  },
  methods: {
    selectItem(id) {
      this.selectedId = this.selectedId === id ? null : id;
    },
    async confirmDiscard() {
      if (!this.selectedId) return;
      this.discarding = true;
      try {
        const res = await axios.post(`/api/games/${this.gameId}/discard-item`, {
          game_player_item_id: this.selectedId,
        });
        this.$emit('discarded', res.data.items_over_limit);
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to discard item');
      }
      this.discarding = false;
    },
  },
};
</script>

<style scoped>
.item-discard-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  animation: fadeIn 0.4s ease;
}

.item-discard-content {
  text-align: center;
  max-width: 420px;
  width: 90%;
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 28px 22px;
}

.discard-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.3rem;
  margin: 0 0 8px;
}

.discard-subtitle {
  color: var(--text-secondary, #a09080);
  font-size: 0.9rem;
  margin: 0 0 20px;
  line-height: 1.4;
}

.items-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

.discard-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border: 2px solid rgba(138, 106, 46, 0.3);
  border-radius: 8px;
  background: rgba(26, 18, 9, 0.6);
  cursor: pointer;
  transition: all 0.2s;
}

.discard-item:not(.disabled):hover {
  border-color: var(--accent-gold, #c9a84c);
  background: rgba(212, 168, 67, 0.08);
}

.discard-item.selected {
  border-color: #c0392b;
  background: rgba(192, 57, 43, 0.12);
}

.discard-item.cursed {
  opacity: 0.5;
  cursor: not-allowed;
  border-color: rgba(192, 57, 43, 0.3);
}

.item-name {
  font-family: 'Cinzel', serif;
  color: var(--text-bright, #e8d5b0);
  font-size: 0.95rem;
}

.cursed-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #c0392b;
  border: 1px solid #c0392b;
  border-radius: 3px;
  padding: 1px 6px;
}

.selected-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #e57373;
  font-weight: 600;
}

.discard-btn {
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  padding: 10px 30px;
}

.discard-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.overlay-fade-leave-active {
  transition: opacity 0.3s ease;
}

.overlay-fade-leave-to {
  opacity: 0;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>
