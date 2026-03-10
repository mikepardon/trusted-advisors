<template>
  <div class="adv-modal-overlay" @click.self="$emit('close')">
    <div class="adv-modal">
      <button class="adv-modal-close" @click="$emit('close')">&times;</button>

      <!-- Portrait & Name -->
      <div class="adv-portrait-wrap">
        <img :src="advisor.character.image_url || '/images/character.png'" :alt="advisor.display_name" class="adv-portrait" />
        <span class="adv-level-badge-lg">{{ advisor.level }}</span>
      </div>
      <h3 class="adv-name">{{ advisor.display_name }}</h3>
      <p v-if="advisor.incarnation > 1" class="adv-incarnation">Incarnation {{ advisor.incarnation }}</p>

      <!-- XP Bar -->
      <div class="adv-xp-section">
        <div class="adv-xp-bar-wrap">
          <div class="adv-xp-bar" :style="{ width: xpPercent + '%' }"></div>
        </div>
        <span class="adv-xp-label" v-if="advisor.level < (advisor.max_level || 8)">
          Level {{ advisor.level }} &mdash; {{ advisor.xp - advisor.xp_for_current_level }} / {{ advisor.xp_for_next_level - advisor.xp_for_current_level }} XP
        </span>
        <span class="adv-xp-label adv-xp-max" v-else>Level {{ advisor.max_level || 8 }} &mdash; MAX</span>
      </div>

      <!-- Modified Dice -->
      <div class="adv-dice-section">
        <h4 class="adv-section-title">Dice</h4>
        <div v-for="(die, di) in advisor.modified_dice" :key="di" class="adv-dice-row">
          <span class="adv-dice-label">Die {{ di + 1 }}:</span>
          <span
            v-for="(face, fi) in die"
            :key="fi"
            class="adv-dice-face"
            :class="{ 'adv-dice-upgraded': isUpgraded(di, fi) }"
          >{{ face === 'WILD' ? 'W' : face }}</span>
        </div>
      </div>

      <!-- Bonuses Summary -->
      <div v-if="hasBonuses" class="adv-bonuses-section">
        <h4 class="adv-section-title">Bonuses</h4>
        <div class="adv-bonus-list">
          <span v-if="advisor.extra_item_slots > 0" class="adv-bonus-tag">+{{ advisor.extra_item_slots }} Item Slot{{ advisor.extra_item_slots > 1 ? 's' : '' }}</span>
          <span v-if="advisor.card_redraws > 0" class="adv-bonus-tag">{{ advisor.card_redraws }} Card Redraw{{ advisor.card_redraws > 1 ? 's' : '' }}</span>
          <span v-for="(val, stat) in advisor.passive_bonuses" :key="stat" class="adv-bonus-tag">+{{ val }} {{ capitalize(stat) }}</span>
        </div>
      </div>

      <!-- Upgrade Tree -->
      <div class="adv-tree-section">
        <h4 class="adv-section-title">Upgrade Path</h4>
        <div class="adv-tree">
          <div v-for="lvl in ((advisor.max_level || 8) - 1)" :key="lvl" class="adv-tree-node" :class="treeNodeClass(lvl + 1)">
            <span class="adv-tree-level">Lv{{ lvl + 1 }}</span>
            <template v-if="upgradeAtLevel(lvl + 1)">
              <div class="adv-tree-upgrade-info">
                <span class="adv-tree-chosen">{{ upgradeAtLevel(lvl + 1).option_name }}</span>
                <span class="adv-tree-desc">{{ upgradeAtLevel(lvl + 1).option_description }}</span>
                <span v-if="upgradeChoiceLabel(upgradeAtLevel(lvl + 1))" class="adv-tree-choice">{{ upgradeChoiceLabel(upgradeAtLevel(lvl + 1)) }}</span>
              </div>
            </template>
            <span v-else-if="advisor.level >= lvl + 1 && advisor.pending_upgrades > 0 && isNextPending(lvl + 1)" class="adv-tree-available">Available</span>
            <span v-else class="adv-tree-locked">Locked</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="adv-actions">
        <button
          v-if="advisor.pending_upgrades > 0"
          class="btn-adv-levelup"
          @click="$emit('level-up', advisor)"
        >Level Up!</button>
        <button
          v-if="advisor.can_immortalise && !showImmortaliseConfirm"
          class="btn-adv-immortalise"
          @click="showImmortaliseConfirm = true"
        >Immortalise</button>
      </div>

      <!-- Immortalise Confirmation -->
      <div v-if="showImmortaliseConfirm" class="adv-immortalise-confirm">
        <div class="adv-immortalise-icon">&#10047;</div>
        <p class="adv-immortalise-title">Immortalise this advisor?</p>
        <p class="adv-immortalise-desc">They will reset to Level 1 with a new title. Previous upgrades will be lost for this incarnation.</p>
        <div class="adv-immortalise-btns">
          <button class="btn-adv-immortalise-yes" :disabled="immortalising" @click="doImmortalise">
            {{ immortalising ? 'Immortalising...' : 'Confirm' }}
          </button>
          <button class="btn-adv-immortalise-no" :disabled="immortalising" @click="showImmortaliseConfirm = false">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../stores/toast';

export default {
  name: 'AdvisorDetailModal',
  props: {
    advisor: { type: Object, required: true },
  },
  emits: ['close', 'level-up', 'updated'],
  setup() {
    return { toast: useToast() };
  },
  data() {
    return { immortalising: false, showImmortaliseConfirm: false };
  },
  computed: {
    xpPercent() {
      if (this.advisor.level >= (this.advisor.max_level || 8)) return 100;
      if (!this.advisor.xp_for_next_level) return 0;
      const range = this.advisor.xp_for_next_level - this.advisor.xp_for_current_level;
      if (range <= 0) return 100;
      return Math.min(100, ((this.advisor.xp - this.advisor.xp_for_current_level) / range) * 100);
    },
    hasBonuses() {
      return this.advisor.extra_item_slots > 0
        || this.advisor.card_redraws > 0
        || Object.keys(this.advisor.passive_bonuses || {}).length > 0;
    },
  },
  methods: {
    capitalize(s) {
      return s.charAt(0).toUpperCase() + s.slice(1);
    },
    upgradeAtLevel(lvl) {
      return (this.advisor.upgrades || []).find(u => u.chosen_at_level === lvl);
    },
    isNextPending(lvl) {
      const chosen = (this.advisor.upgrades || []).length;
      return lvl === chosen + 2;
    },
    treeNodeClass(lvl) {
      const upgrade = this.upgradeAtLevel(lvl);
      if (upgrade) return 'chosen';
      if (this.advisor.level >= lvl && this.isNextPending(lvl)) return 'available';
      if (this.advisor.level >= lvl) return 'past';
      return 'locked';
    },
    upgradeChoiceLabel(upgrade) {
      const c = upgrade.user_choice;
      if (!c) return '';
      const t = upgrade.option_type;
      if (t === 'bump_dice_face' || t === 'add_wild') {
        return `Die ${(c.die_index ?? 0) + 1}, Face ${(c.face_index ?? 0) + 1}`;
      }
      if (t === 'bump_two_dice_faces' && c.faces) {
        return c.faces.map(f => `Die ${(f.die_index ?? 0) + 1} Face ${(f.face_index ?? 0) + 1}`).join(', ');
      }
      return '';
    },
    isUpgraded(dieIdx, faceIdx) {
      const baseDice = this.advisor.character.dice;
      const modDice = this.advisor.modified_dice;
      if (!baseDice || !modDice) return false;
      const baseVal = baseDice[dieIdx]?.[faceIdx];
      const modVal = modDice[dieIdx]?.[faceIdx];
      return baseVal !== modVal;
    },
    async doImmortalise() {
      this.immortalising = true;
      try {
        const res = await axios.post(`/api/my-advisors/${this.advisor.id}/immortalise`);
        this.toast.success(res.data.message);
        this.$emit('updated');
        this.$emit('close');
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to immortalise');
      }
      this.immortalising = false;
    },
  },
};
</script>

<style scoped>
.adv-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.adv-modal {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 380px;
  width: 100%;
  position: relative;
  max-height: 85vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.adv-modal-close {
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

.adv-modal-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.adv-portrait-wrap {
  position: relative;
  width: 100px;
  height: 100px;
  margin-bottom: 8px;
}

.adv-portrait {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--accent-gold);
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.3);
}

.adv-level-badge-lg {
  position: absolute;
  bottom: -4px;
  right: -4px;
  background: linear-gradient(135deg, var(--accent-gold), #b08830);
  color: #1a0f05;
  font-size: 0.85rem;
  font-weight: 800;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--bg-primary);
  font-family: 'Cinzel', serif;
}

.adv-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin: 0 0 2px;
  text-align: center;
}

.adv-incarnation {
  color: #b06adf;
  font-size: 0.75rem;
  font-style: italic;
  margin: 0 0 10px;
}

.adv-xp-section {
  width: 100%;
  margin-bottom: 14px;
}

.adv-xp-bar-wrap {
  width: 100%;
  height: 6px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 4px;
}

.adv-xp-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--accent-gold), #f0d060);
  border-radius: 3px;
  transition: width 0.3s;
}

.adv-xp-label {
  display: block;
  text-align: center;
  font-size: 0.72rem;
  color: var(--text-secondary);
}

.adv-xp-max {
  color: var(--accent-gold);
  font-weight: 700;
}

.adv-section-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.85rem;
  margin: 0 0 8px;
  padding-bottom: 4px;
  border-bottom: 1px solid rgba(212, 168, 67, 0.15);
}

.adv-dice-section {
  width: 100%;
  margin-bottom: 12px;
}

.adv-dice-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  justify-content: center;
}

.adv-dice-label {
  color: var(--text-secondary);
  font-size: 0.8rem;
  min-width: 42px;
}

.adv-dice-face {
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

.adv-dice-face.adv-dice-upgraded {
  background: rgba(90, 184, 122, 0.2);
  border-color: rgba(90, 184, 122, 0.5);
  color: #5ab87a;
}

.adv-bonuses-section {
  width: 100%;
  margin-bottom: 12px;
}

.adv-bonus-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
}

.adv-bonus-tag {
  font-size: 0.7rem;
  padding: 3px 8px;
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 4px;
  color: var(--accent-gold);
}

.adv-tree-section {
  width: 100%;
  margin-bottom: 14px;
}

.adv-tree {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.adv-tree-node {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.72rem;
}

.adv-tree-node.chosen {
  background: rgba(90, 184, 122, 0.08);
  border-left: 3px solid #5ab87a;
}

.adv-tree-node.available {
  background: rgba(212, 168, 67, 0.08);
  border-left: 3px solid var(--accent-gold);
}

.adv-tree-node.past,
.adv-tree-node.locked {
  opacity: 0.5;
  border-left: 3px solid rgba(255, 255, 255, 0.1);
}

.adv-tree-level {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  min-width: 32px;
  font-weight: 600;
}

.adv-tree-upgrade-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.adv-tree-chosen {
  color: #5ab87a;
}

.adv-tree-desc {
  color: var(--text-secondary);
  font-size: 0.62rem;
  line-height: 1.3;
  opacity: 0.8;
}

.adv-tree-choice {
  color: var(--accent-gold);
  font-size: 0.6rem;
  opacity: 0.7;
}

.adv-tree-available {
  color: var(--accent-gold);
  font-weight: 600;
}

.adv-tree-locked {
  color: var(--text-secondary);
  font-style: italic;
}

.adv-actions {
  display: flex;
  gap: 10px;
  margin-top: 4px;
}

.btn-adv-levelup {
  padding: 10px 24px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 700;
  background: linear-gradient(180deg, #2a6e3a, #1a4a26);
  border: 2px solid #5ab87a;
  color: white;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 3px 0 rgba(0,0,0,0.3);
}

.btn-adv-levelup:hover {
  box-shadow: 0 3px 0 rgba(0,0,0,0.3), 0 0 12px rgba(90, 184, 122, 0.3);
}

.btn-adv-immortalise {
  padding: 10px 24px;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  font-weight: 700;
  background: linear-gradient(180deg, #5a2a7a, #3a1a4a);
  border: 2px solid #b06adf;
  color: white;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 3px 0 rgba(0,0,0,0.3);
}

.btn-adv-immortalise:hover {
  box-shadow: 0 3px 0 rgba(0,0,0,0.3), 0 0 12px rgba(176, 106, 223, 0.3);
}

.btn-adv-immortalise:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.adv-immortalise-confirm {
  width: 100%;
  margin-top: 8px;
  padding: 16px;
  background: rgba(90, 42, 122, 0.12);
  border: 1px solid rgba(176, 106, 223, 0.35);
  border-radius: 8px;
  text-align: center;
}

.adv-immortalise-icon {
  font-size: 1.6rem;
  color: #b06adf;
  margin-bottom: 6px;
}

.adv-immortalise-title {
  font-family: 'Cinzel', serif;
  color: #b06adf;
  font-size: 0.9rem;
  font-weight: 700;
  margin: 0 0 6px;
}

.adv-immortalise-desc {
  color: var(--text-secondary);
  font-size: 0.72rem;
  line-height: 1.4;
  margin: 0 0 12px;
}

.adv-immortalise-btns {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.btn-adv-immortalise-yes {
  padding: 8px 20px;
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  font-weight: 700;
  background: linear-gradient(180deg, #5a2a7a, #3a1a4a);
  border: 2px solid #b06adf;
  color: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-adv-immortalise-yes:hover:not(:disabled) {
  box-shadow: 0 0 12px rgba(176, 106, 223, 0.3);
}

.btn-adv-immortalise-yes:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-adv-immortalise-no {
  padding: 8px 20px;
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: var(--text-secondary);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-adv-immortalise-no:hover {
  border-color: rgba(255, 255, 255, 0.3);
  color: var(--text-bright);
}
</style>
