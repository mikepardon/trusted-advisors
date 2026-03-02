<template>
  <div class="characters-page">
    <h2 class="page-title">Advisors</h2>

    <div v-if="loading" class="loading-text">Loading advisors...</div>

    <div v-else class="char-grid">
      <div
        v-for="c in characters"
        :key="c.id"
        class="char-card"
        :class="{ 'char-locked': !c.is_unlocked }"
        @click="selectChar(c)"
      >
        <div class="char-img-wrap">
          <img :src="c.image_url || '/images/character.png'" :alt="c.name" class="char-img" />
          <span v-if="!c.is_unlocked" class="char-lock">&#128274;</span>
        </div>
        <span class="char-name">{{ c.name }}</span>
        <span v-if="!c.is_unlocked" class="char-req">{{ c.unlock_requirement }}</span>
      </div>
    </div>

    <!-- Character Detail Modal -->
    <div v-if="selected" class="char-modal-overlay" @click.self="selected = null">
      <div class="char-modal">
        <button class="char-modal-close" @click="selected = null">&times;</button>
        <div class="char-modal-portrait-wrap">
          <img :src="selected.image_url || '/images/character.png'" :alt="selected.name" class="char-modal-portrait" />
        </div>
        <h3 class="char-modal-name">{{ selected.name }}</h3>
        <p class="char-modal-desc">{{ selected.description }}</p>

        <div v-if="selected.dice" class="char-modal-dice">
          <div v-for="(die, di) in selected.dice" :key="di" class="char-dice-row">
            <span class="char-dice-label">Die {{ di + 1 }}:</span>
            <span v-for="(face, fi) in die" :key="fi" class="char-dice-face">{{ face === 'WILD' ? 'W' : face }}</span>
          </div>
        </div>

        <div class="char-modal-wild">
          <span class="char-wild-badge">W = {{ selected.wild_value }}</span>
          <span class="char-wild-desc">{{ selected.wild_ability }}: {{ selected.wild_ability_description }}</span>
        </div>

        <div v-if="!selected.is_unlocked" class="char-modal-locked">
          <span class="char-lock-icon">&#128274;</span>
          <span>{{ selected.unlock_requirement }}</span>
        </div>
        <div v-else-if="selected.unlocked_at" class="char-modal-unlocked">
          <span class="char-unlock-icon">&#9989;</span>
          <span>Unlocked on {{ selected.unlocked_at }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'CharactersPage',
  data() {
    return {
      characters: [],
      loading: true,
      selected: null,
    };
  },
  async mounted() {
    try {
      const res = await axios.get('/api/my-characters');
      this.characters = res.data;
    } catch {
      this.characters = [];
    }
    this.loading = false;
  },
  methods: {
    selectChar(c) {
      this.selected = c;
    },
  },
};
</script>

<style scoped>
.characters-page {
  max-width: 800px;
  margin: 0 auto;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.6rem;
  text-align: center;
  margin-bottom: 20px;
}

.loading-text {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 40px;
}

.char-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 12px;
}

.char-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid var(--border-gold);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.char-card:hover {
  border-color: var(--accent-gold);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.2);
}

.char-locked {
  opacity: 0.5;
  filter: grayscale(0.5);
}

.char-img-wrap {
  position: relative;
  width: 64px;
  height: 64px;
}

.char-img {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-gold);
}

.char-lock {
  position: absolute;
  bottom: -4px;
  right: -4px;
  font-size: 1rem;
  background: rgba(0, 0, 0, 0.7);
  border-radius: 50%;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.char-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.8rem;
  text-align: center;
  font-weight: 700;
}

.char-req {
  font-size: 0.65rem;
  color: var(--text-secondary);
  text-align: center;
}

/* Modal */
.char-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.char-modal {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 360px;
  width: 100%;
  position: relative;
  max-height: 85vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.char-modal-close {
  position: absolute;
  top: 8px;
  right: 12px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.char-modal-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.char-modal-portrait-wrap {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid var(--accent-gold);
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.3);
  margin-bottom: 12px;
}

.char-modal-portrait {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.char-modal-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 6px;
  text-align: center;
}

.char-modal-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
  text-align: center;
  margin-bottom: 14px;
  line-height: 1.5;
}

.char-modal-dice {
  width: 100%;
  margin-bottom: 12px;
}

.char-dice-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  justify-content: center;
}

.char-dice-label {
  color: var(--text-secondary);
  font-size: 0.8rem;
  min-width: 42px;
}

.char-dice-face {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 4px;
  color: var(--text-bright);
  font-size: 0.8rem;
  font-weight: 600;
}

.char-modal-wild {
  width: 100%;
  background: rgba(212, 168, 67, 0.08);
  border-top: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
}

.char-wild-badge {
  display: inline-block;
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 2px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 700;
  margin-bottom: 4px;
}

.char-wild-desc {
  display: block;
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-style: italic;
  line-height: 1.4;
}

.char-modal-locked {
  margin-top: 12px;
  padding: 10px;
  background: rgba(160, 48, 32, 0.1);
  border: 1px solid rgba(160, 48, 32, 0.3);
  border-radius: 6px;
  text-align: center;
  color: var(--accent-red);
  font-size: 0.85rem;
}

.char-lock-icon {
  margin-right: 6px;
}

.char-modal-unlocked {
  margin-top: 12px;
  padding: 10px;
  background: rgba(74, 138, 58, 0.1);
  border: 1px solid rgba(74, 138, 58, 0.3);
  border-radius: 6px;
  text-align: center;
  color: #6abf50;
  font-size: 0.85rem;
}

.char-unlock-icon {
  margin-right: 6px;
}

@media (max-width: 768px) {
  .char-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }

  .char-img-wrap {
    width: 56px;
    height: 56px;
  }

  .char-img {
    width: 56px;
    height: 56px;
  }

  .char-name {
    font-size: 0.7rem;
  }
}
</style>
