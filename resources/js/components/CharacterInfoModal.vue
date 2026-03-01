<template>
  <div class="char-modal-overlay" @click.self="$emit('close')">
    <div class="char-modal">
      <button class="char-modal-close" @click="$emit('close')">&times;</button>

      <!-- Character header -->
      <div class="char-header">
        <img v-if="character.image_url" :src="character.image_url" :alt="character.name" class="char-image" />
        <div v-else class="char-image-placeholder">{{ character.name?.charAt(0) || '?' }}</div>
        <div class="char-title">
          <h2 class="char-name">{{ character.name }}</h2>
          <p v-if="character.description" class="char-desc">{{ character.description }}</p>
        </div>
      </div>

      <!-- Dice -->
      <div class="char-section">
        <h3 class="section-label">Dice ({{ activeDice }}/3 active)</h3>
        <div class="dice-list">
          <div v-for="(die, dIdx) in character.dice" :key="dIdx" class="die-group" :class="{ 'die-lost': dIdx >= activeDice }">
            <span class="die-label">Die {{ dIdx + 1 }}</span>
            <div class="die-faces">
              <span v-for="(face, fIdx) in die" :key="fIdx" class="die-face" :class="{ 'face-wild': face === 'WILD' }">
                {{ face }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Ability -->
      <div class="char-section">
        <h3 class="section-label">Ability</h3>
        <div class="ability-info">
          <strong class="ability-name">{{ abilityDisplayName }}</strong>
          <span v-if="abilityUses !== null" class="ability-uses">({{ abilityUses }} use{{ abilityUses !== 1 ? 's' : '' }} left)</span>
          <p class="ability-desc">{{ character.wild_ability_description || 'No description' }}</p>
        </div>
      </div>

      <!-- Items -->
      <div v-if="items.length" class="char-section">
        <h3 class="section-label">Items ({{ items.length }})</h3>
        <div class="item-list">
          <div v-for="pi in items" :key="pi.id" class="item-row">
            <span class="item-name">{{ pi.item?.name || 'Unknown' }}</span>
            <span class="item-effect">{{ pi.item?.description || '' }}</span>
          </div>
        </div>
      </div>
      <div v-else class="char-section">
        <h3 class="section-label">Items</h3>
        <p class="no-items">No items held</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CharacterInfoModal',
  props: {
    character: { type: Object, required: true },
    activeDice: { type: Number, default: 3 },
    abilityUses: { type: Number, default: null },
    items: { type: Array, default: () => [] },
  },
  emits: ['close'],
  computed: {
    abilityDisplayName() {
      const name = this.character.wild_ability || '';
      return name.charAt(0).toUpperCase() + name.slice(1);
    },
  },
};
</script>

<style scoped>
.char-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 950;
  padding: 20px;
}

.char-modal {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 420px;
  width: 100%;
  max-height: 80vh;
  overflow-y: auto;
  position: relative;
}

.char-modal-close {
  position: absolute;
  top: 10px;
  right: 14px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.6rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.char-modal-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.char-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
}

.char-image {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-gold);
}

.char-image-placeholder {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent-gold), #8a6a14);
  color: var(--wood-dark);
  font-family: 'Cinzel', serif;
  font-size: 1.8rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--border-gold);
  flex-shrink: 0;
}

.char-title {
  flex: 1;
  min-width: 0;
}

.char-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.2rem;
  margin: 0;
}

.char-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin: 4px 0 0;
}

.char-section {
  margin-bottom: 14px;
}

.section-label {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  margin-bottom: 6px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.2);
  padding-bottom: 4px;
}

.dice-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.die-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.die-group.die-lost {
  opacity: 0.35;
}

.die-label {
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  color: var(--text-secondary);
  width: 40px;
  flex-shrink: 0;
}

.die-faces {
  display: flex;
  gap: 4px;
}

.die-face {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 4px;
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  color: var(--text-bright);
  font-weight: 700;
}

.face-wild {
  color: var(--accent-gold);
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  font-size: 0.55rem;
}

.ability-info {
  padding: 8px 10px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 6px;
  border: 1px solid rgba(138, 106, 46, 0.2);
}

.ability-name {
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.95rem;
}

.ability-uses {
  color: var(--text-secondary);
  font-size: 0.8rem;
  margin-left: 6px;
}

.ability-desc {
  color: var(--text-primary);
  font-size: 0.85rem;
  margin: 4px 0 0;
}

.item-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.item-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  padding: 4px 0;
}

.item-name {
  color: var(--accent-gold);
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.item-effect {
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-style: italic;
}

.no-items {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
}

@media (max-width: 768px) {
  .char-modal {
    padding: 18px;
  }

  .char-image,
  .char-image-placeholder {
    width: 50px;
    height: 50px;
    font-size: 1.4rem;
  }

  .die-face {
    width: 24px;
    height: 24px;
    font-size: 0.65rem;
  }

  .face-wild {
    font-size: 0.5rem;
  }
}
</style>
