<template>
  <div class="choose-advisors-screen">
    <div class="advisors-header">
      <h2 class="page-title">Choose Your Starting Advisors</h2>
      <p class="page-subtitle">Select 2 advisors to begin your journey</p>
      <div class="selection-counter">
        <span
          class="counter-pip"
          :class="{ filled: selectedIds.length >= 1 }"
        ></span>
        <span
          class="counter-pip"
          :class="{ filled: selectedIds.length >= 2 }"
        ></span>
        <span class="counter-label">{{ selectedIds.length }}/2 chosen</span>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <p class="loading-text">Summoning advisors...</p>
    </div>

    <div v-else class="advisors-grid">
      <div
        v-for="(char, i) in characters"
        :key="char.id"
        class="advisor-card"
        :class="{
          selected: isSelected(char.id),
          disabled: selectedIds.length >= 2 && !isSelected(char.id),
        }"
        :style="{ animationDelay: (i * 60) + 'ms' }"
        @click="toggleSelect(char.id)"
      >
        <!-- Selection checkmark -->
        <Transition name="check">
          <div v-if="isSelected(char.id)" class="selected-badge">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
              <path d="M4 9.5L7.5 13L14 5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </Transition>

        <!-- Portrait -->
        <div class="advisor-portrait-wrap">
          <img
            :src="char.image_url || '/images/character.png'"
            :alt="char.name"
            class="advisor-portrait"
          />
        </div>

        <!-- Name & description -->
        <h3 class="advisor-name">{{ char.name }}</h3>
        <p class="advisor-desc">{{ char.description }}</p>

        <!-- Dice display -->
        <div class="dice-section">
          <span class="section-label">Dice</span>
          <div class="dice-row" v-for="(die, di) in char.dice" :key="di">
            <span
              v-for="(face, fi) in die"
              :key="fi"
              class="dice-face"
              :class="{ wild: face === 'WILD' }"
            >{{ face === 'WILD' ? 'W' : face }}</span>
          </div>
        </div>

        <!-- Wild ability -->
        <div class="ability-section" v-if="char.wild_ability">
          <span class="section-label">Wild Ability</span>
          <span class="ability-name">{{ char.wild_ability }}</span>
          <p class="ability-desc">{{ char.wild_ability_description }}</p>
        </div>

        <!-- Starting bonus -->
        <div class="bonus-section" v-if="char.starting_bonus">
          <span class="section-label">Starting Bonus</span>
          <span class="bonus-value">
            <span v-for="(val, stat) in char.starting_bonus" :key="stat">
              {{ stat }}: +{{ val }}
            </span>
          </span>
        </div>

        <!-- Upgrade path toggle -->
        <button
          class="upgrade-toggle"
          @click.stop="toggleUpgradePath(char.id)"
        >
          {{ expandedUpgrades[char.id] ? 'Hide Upgrade Path' : 'View Upgrade Path' }}
          <span class="toggle-chevron" :class="{ open: expandedUpgrades[char.id] }">&#9662;</span>
        </button>

        <Transition name="expand">
          <div v-if="expandedUpgrades[char.id]" class="upgrade-tree">
            <template v-for="level in sortedLevels(char.level_options)" :key="level">
              <div class="upgrade-level-group">
                <span class="upgrade-level-label">Level {{ level }}</span>
                <div
                  class="upgrade-option"
                  v-for="opt in char.level_options[level]"
                  :key="opt.id"
                >
                  <span v-if="opt.icon" class="upgrade-icon">{{ opt.icon }}</span>
                  <div class="upgrade-details">
                    <span class="upgrade-name">{{ opt.name }}</span>
                    <span class="upgrade-desc">{{ opt.description }}</span>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </Transition>
      </div>
    </div>

    <!-- Confirm button -->
    <div class="confirm-section" v-if="!loading">
      <button
        class="btn-primary confirm-btn"
        :disabled="selectedIds.length !== 2 || submitting"
        @click="submit"
      >
        {{ submitting ? 'Summoning...' : 'Begin Your Journey' }}
      </button>
      <p v-if="error" class="error-msg">{{ error }}</p>
    </div>
  </div>
</template>

<script>
import { useAuth } from '../stores/auth';
import axios from 'axios';

export default {
  name: 'ChooseAdvisors',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      characters: [],
      selectedIds: [],
      expandedUpgrades: {},
      loading: true,
      submitting: false,
      error: '',
    };
  },
  async mounted() {
    try {
      const res = await axios.get('/api/starter-advisors');
      this.characters = res.data;
    } catch {
      this.error = 'Failed to load advisors. Please refresh.';
    } finally {
      this.loading = false;
    }
  },
  methods: {
    isSelected(id) {
      return this.selectedIds.includes(id);
    },
    toggleSelect(id) {
      if (this.isSelected(id)) {
        this.selectedIds = this.selectedIds.filter(x => x !== id);
      } else if (this.selectedIds.length < 2) {
        this.selectedIds.push(id);
      }
    },
    toggleUpgradePath(id) {
      this.expandedUpgrades = {
        ...this.expandedUpgrades,
        [id]: !this.expandedUpgrades[id],
      };
    },
    sortedLevels(levelOptions) {
      if (!levelOptions) return [];
      return Object.keys(levelOptions)
        .map(Number)
        .sort((a, b) => a - b);
    },
    async submit() {
      if (this.selectedIds.length !== 2) return;
      this.submitting = true;
      this.error = '';

      try {
        const res = await axios.post('/api/choose-starter-advisors', {
          character_ids: this.selectedIds,
        });
        this.auth.state.user = res.data;
        this.$router.push('/');
      } catch (e) {
        this.error =
          e.response?.data?.error ||
          e.response?.data?.message ||
          'Something went wrong. Please try again.';
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>

<style scoped>
.choose-advisors-screen {
  max-width: 720px;
  margin: 0 auto;
  padding: 40px 16px 100px;
}

/* ── Header ── */
.advisors-header {
  text-align: center;
  margin-bottom: 32px;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
  margin-bottom: 6px;
  letter-spacing: 1px;
}

.page-subtitle {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 16px;
}

.selection-counter {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(26, 21, 32, 0.7);
  border: 1px solid var(--border-gold);
  border-radius: 20px;
  padding: 6px 14px;
}

.counter-pip {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: 2px solid var(--border-gold);
  background: transparent;
  transition: all 0.3s ease;
}

.counter-pip.filled {
  background: #5ab87a;
  border-color: #5ab87a;
  box-shadow: 0 0 8px rgba(90, 184, 122, 0.5);
}

.counter-label {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.75rem;
  margin-left: 4px;
}

/* ── Loading ── */
.loading-state {
  text-align: center;
  padding: 60px 0;
}

.loading-text {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
}

/* ── Grid ── */
.advisors-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}

@media (max-width: 640px) {
  .advisors-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 400px) {
  .advisors-grid {
    grid-template-columns: 1fr;
  }
}

/* ── Card ── */
.advisor-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px 12px 12px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  cursor: pointer;
  transition: border-color 0.25s, box-shadow 0.25s, transform 0.2s, opacity 0.25s;
  animation: cardReveal 0.4s ease both;
}

@keyframes cardReveal {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.advisor-card:hover {
  border-color: var(--accent-gold);
  box-shadow: 0 0 14px rgba(212, 168, 67, 0.18);
  transform: translateY(-2px);
}

.advisor-card.selected {
  border-color: #5ab87a;
  box-shadow: 0 0 16px rgba(90, 184, 122, 0.3), inset 0 0 30px rgba(90, 184, 122, 0.04);
}

.advisor-card.disabled {
  opacity: 0.45;
  cursor: default;
  pointer-events: none;
}

/* ── Selected badge ── */
.selected-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 26px;
  height: 26px;
  background: #5ab87a;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(90, 184, 122, 0.4);
  z-index: 2;
}

.check-enter-active {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.15s;
}
.check-leave-active {
  transition: transform 0.15s, opacity 0.15s;
}
.check-enter-from {
  transform: scale(0);
  opacity: 0;
}
.check-leave-to {
  transform: scale(0);
  opacity: 0;
}

/* ── Portrait ── */
.advisor-portrait-wrap {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid var(--border-gold);
  margin-bottom: 8px;
  flex-shrink: 0;
}

.advisor-portrait {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ── Name & desc ── */
.advisor-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
  text-align: center;
  margin-bottom: 4px;
}

.advisor-desc {
  color: var(--text-secondary);
  font-size: 0.72rem;
  text-align: center;
  line-height: 1.35;
  margin-bottom: 10px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ── Section labels ── */
.section-label {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 0.6rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 1.5px;
  margin-bottom: 4px;
}

/* ── Dice ── */
.dice-section {
  width: 100%;
  margin-bottom: 8px;
  text-align: center;
}

.dice-row {
  display: flex;
  justify-content: center;
  gap: 3px;
  margin-bottom: 3px;
}

.dice-face {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-primary);
  background: rgba(26, 21, 32, 0.8);
  border: 1px solid var(--border-gold);
  border-radius: 4px;
}

.dice-face.wild {
  background: rgba(212, 168, 67, 0.2);
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

/* ── Ability ── */
.ability-section {
  width: 100%;
  text-align: center;
  margin-bottom: 8px;
}

.ability-name {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  color: var(--text-primary);
  margin-bottom: 2px;
}

.ability-desc {
  font-size: 0.68rem;
  color: var(--text-secondary);
  line-height: 1.3;
}

/* ── Starting bonus ── */
.bonus-section {
  width: 100%;
  text-align: center;
  margin-bottom: 8px;
}

.bonus-value {
  display: flex;
  justify-content: center;
  gap: 8px;
  font-size: 0.72rem;
  color: #5ab87a;
  text-transform: capitalize;
}

/* ── Upgrade path ── */
.upgrade-toggle {
  display: flex;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 0.7rem;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: 4px;
  transition: color 0.2s;
  margin-top: 4px;
}

.upgrade-toggle:hover {
  color: var(--accent-gold);
}

.toggle-chevron {
  font-size: 0.6rem;
  transition: transform 0.25s;
}

.toggle-chevron.open {
  transform: rotate(180deg);
}

/* Expand transition */
.expand-enter-active {
  transition: max-height 0.35s ease, opacity 0.25s ease;
  overflow: hidden;
}
.expand-leave-active {
  transition: max-height 0.25s ease, opacity 0.15s ease;
  overflow: hidden;
}
.expand-enter-from,
.expand-leave-to {
  max-height: 0;
  opacity: 0;
}
.expand-enter-to,
.expand-leave-from {
  max-height: 800px;
  opacity: 1;
}

.upgrade-tree {
  width: 100%;
  margin-top: 8px;
  border-top: 1px solid rgba(138, 106, 46, 0.2);
  padding-top: 8px;
}

.upgrade-level-group {
  margin-bottom: 8px;
}

.upgrade-level-label {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 0.6rem;
  color: var(--accent-gold);
  opacity: 0.7;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.upgrade-option {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  padding: 4px 6px;
  border-radius: 4px;
  background: rgba(26, 21, 32, 0.5);
  margin-bottom: 3px;
}

.upgrade-icon {
  font-size: 0.85rem;
  flex-shrink: 0;
  margin-top: 1px;
}

.upgrade-details {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.upgrade-name {
  font-size: 0.68rem;
  color: var(--text-primary);
  font-weight: 600;
}

.upgrade-desc {
  font-size: 0.62rem;
  color: var(--text-secondary);
  line-height: 1.3;
}

/* ── Confirm ── */
.confirm-section {
  text-align: center;
  margin-top: 28px;
  position: sticky;
  bottom: 0;
  padding: 16px 0 20px;
  background: linear-gradient(to top, var(--bg-primary) 60%, transparent);
}

.confirm-btn {
  min-width: 260px;
  padding: 14px 32px;
  font-size: 1.05rem;
  letter-spacing: 2px;
}

.error-msg {
  color: var(--accent-red);
  font-size: 0.85rem;
  margin-top: 10px;
}
</style>
