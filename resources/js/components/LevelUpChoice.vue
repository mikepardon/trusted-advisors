<template>
  <div class="lvlup-overlay" @click.self="$emit('close')">
    <div class="lvlup-modal">
      <button class="lvlup-close" @click="$emit('close')">&times;</button>
      <h3 class="lvlup-title">Level Up!</h3>
      <p class="lvlup-subtitle">{{ advisor.display_name }} &mdash; Choose upgrade for Level {{ forLevel }}</p>

      <div v-if="loading" class="loading-text">Loading options...</div>
      <div v-else-if="!options.length" class="loading-text">No options available.</div>
      <div v-else class="lvlup-options">
        <div
          v-for="opt in options"
          :key="opt.id"
          class="lvlup-option"
          :class="{ selected: selectedOption?.id === opt.id }"
          @click="selectOption(opt)"
        >
          <div class="lvlup-opt-header">
            <span class="lvlup-opt-name">{{ opt.name }}</span>
          </div>
          <p class="lvlup-opt-desc">{{ opt.description }}</p>

          <!-- Dice picker for bump/wild types -->
          <div v-if="selectedOption?.id === opt.id && needsDicePicker(opt)" class="lvlup-dice-picker" @click.stop>
            <p class="lvlup-picker-label">
              {{ opt.type === 'bump_two_dice_faces' ? 'Select 2 die faces:' : 'Select a die face:' }}
            </p>
            <div v-for="(die, di) in advisor.modified_dice" :key="di" class="lvlup-dice-row">
              <span class="lvlup-dice-label">Die {{ di + 1 }}:</span>
              <span
                v-for="(face, fi) in die"
                :key="fi"
                class="lvlup-dice-face"
                :class="{
                  'lvlup-face-selected': isFaceSelected(di, fi),
                  'lvlup-face-disabled': face === 'WILD' && opt.type !== 'add_wild',
                  'lvlup-face-wild': face === 'WILD',
                }"
                @click="toggleFace(opt, di, fi, face)"
              >{{ face === 'WILD' ? 'W' : face }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Preview -->
      <div v-if="selectedOption && previewText" class="lvlup-preview">
        <span class="lvlup-preview-label">Preview:</span>
        <span class="lvlup-preview-text">{{ previewText }}</span>
      </div>

      <div v-if="error" class="lvlup-error">{{ error }}</div>

      <div class="lvlup-actions">
        <p class="lvlup-warning">This choice is permanent.</p>
        <button
          class="btn-lvlup-confirm"
          :disabled="!canConfirm || saving"
          @click="confirm"
        >{{ saving ? 'Choosing...' : 'Confirm' }}</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../stores/toast';

export default {
  name: 'LevelUpChoice',
  props: {
    advisor: { type: Object, required: true },
  },
  emits: ['close', 'chosen'],
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      loading: true,
      options: [],
      forLevel: null,
      selectedOption: null,
      selectedFaces: [],
      saving: false,
      error: '',
    };
  },
  computed: {
    canConfirm() {
      if (!this.selectedOption) return false;
      const t = this.selectedOption.type;
      if (t === 'bump_dice_face' || t === 'add_wild') return this.selectedFaces.length === 1;
      if (t === 'bump_two_dice_faces') return this.selectedFaces.length === 2;
      return true;
    },
    previewText() {
      if (!this.selectedOption) return '';
      const t = this.selectedOption.type;
      if (t === 'bump_dice_face' && this.selectedFaces.length === 1) {
        const f = this.selectedFaces[0];
        const cur = this.advisor.modified_dice[f.die_index]?.[f.face_index];
        return `Die ${f.die_index + 1}, face ${f.face_index + 1}: ${cur} -> ${Number(cur) + 1}`;
      }
      if (t === 'bump_two_dice_faces' && this.selectedFaces.length === 2) {
        return this.selectedFaces.map(f => {
          const cur = this.advisor.modified_dice[f.die_index]?.[f.face_index];
          return `Die ${f.die_index + 1} face ${f.face_index + 1}: ${cur} -> ${Number(cur) + 1}`;
        }).join(', ');
      }
      if (t === 'add_wild' && this.selectedFaces.length === 1) {
        const f = this.selectedFaces[0];
        const cur = this.advisor.modified_dice[f.die_index]?.[f.face_index];
        return `Die ${f.die_index + 1}, face ${f.face_index + 1}: ${cur} -> WILD`;
      }
      return '';
    },
  },
  async mounted() {
    await this.loadOptions();
  },
  methods: {
    async loadOptions() {
      this.loading = true;
      try {
        const res = await axios.get(`/api/my-advisors/${this.advisor.id}/level-options`);
        this.options = res.data.options;
        this.forLevel = res.data.for_level;
      } catch (e) {
        this.error = e.response?.data?.error || 'Failed to load options';
      }
      this.loading = false;
    },
    needsDicePicker(opt) {
      return ['bump_dice_face', 'bump_two_dice_faces', 'add_wild'].includes(opt.type);
    },
    selectOption(opt) {
      this.selectedOption = opt;
      this.selectedFaces = [];
      this.error = '';
    },
    isFaceSelected(di, fi) {
      return this.selectedFaces.some(f => f.die_index === di && f.face_index === fi);
    },
    toggleFace(opt, di, fi, face) {
      // Can't select WILD faces for bump types
      if (face === 'WILD' && opt.type !== 'add_wild') return;
      // Can't select non-WILD for add_wild (it replaces a non-WILD face)
      if (face === 'WILD' && opt.type === 'add_wild') return;

      const existing = this.selectedFaces.findIndex(f => f.die_index === di && f.face_index === fi);
      if (existing >= 0) {
        this.selectedFaces.splice(existing, 1);
        return;
      }

      const maxFaces = opt.type === 'bump_two_dice_faces' ? 2 : 1;
      if (this.selectedFaces.length >= maxFaces) {
        this.selectedFaces = [];
      }
      this.selectedFaces.push({ die_index: di, face_index: fi });
    },
    buildUserChoice() {
      const t = this.selectedOption.type;
      if (t === 'bump_dice_face' || t === 'add_wild') {
        return this.selectedFaces[0] || null;
      }
      if (t === 'bump_two_dice_faces') {
        return { faces: this.selectedFaces };
      }
      return null;
    },
    async confirm() {
      if (!this.canConfirm) return;
      this.saving = true;
      this.error = '';
      try {
        const res = await axios.post(`/api/my-advisors/${this.advisor.id}/choose-upgrade`, {
          option_id: this.selectedOption.id,
          user_choice: this.buildUserChoice(),
        });
        this.toast.success(`Chose: ${this.selectedOption.name}`);
        this.$emit('chosen', res.data);
      } catch (e) {
        this.error = e.response?.data?.error || 'Failed to choose upgrade';
      }
      this.saving = false;
    },
  },
};
</script>

<style scoped>
.lvlup-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.9);
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.lvlup-modal {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid #5ab87a;
  border-radius: 12px;
  padding: 24px;
  max-width: 440px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
}

.lvlup-close {
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

.lvlup-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.lvlup-title {
  font-family: 'Cinzel', serif;
  color: #5ab87a;
  font-size: 1.4rem;
  text-align: center;
  margin: 0 0 4px;
}

.lvlup-subtitle {
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-align: center;
  margin: 0 0 16px;
}

.loading-text {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px;
}

.lvlup-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.lvlup-option {
  padding: 12px;
  border: 2px solid rgba(212, 168, 67, 0.2);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.02);
  cursor: pointer;
  transition: all 0.15s;
}

.lvlup-option:hover {
  border-color: rgba(212, 168, 67, 0.4);
}

.lvlup-option.selected {
  border-color: #5ab87a;
  background: rgba(90, 184, 122, 0.06);
}

.lvlup-opt-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.lvlup-opt-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  font-weight: 700;
}

.lvlup-opt-desc {
  color: var(--text-secondary);
  font-size: 0.78rem;
  margin: 0;
  line-height: 1.4;
}

.lvlup-dice-picker {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.lvlup-picker-label {
  color: var(--text-secondary);
  font-size: 0.72rem;
  margin: 0 0 6px;
}

.lvlup-dice-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  justify-content: center;
}

.lvlup-dice-label {
  color: var(--text-secondary);
  font-size: 0.75rem;
  min-width: 42px;
}

.lvlup-dice-face {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  background: rgba(212, 168, 67, 0.12);
  border: 2px solid rgba(212, 168, 67, 0.2);
  border-radius: 4px;
  color: var(--text-bright);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.lvlup-dice-face:hover:not(.lvlup-face-disabled) {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.2);
}

.lvlup-dice-face.lvlup-face-selected {
  border-color: #5ab87a;
  background: rgba(90, 184, 122, 0.25);
  color: #5ab87a;
}

.lvlup-dice-face.lvlup-face-disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.lvlup-dice-face.lvlup-face-wild {
  color: var(--accent-gold);
}

.lvlup-preview {
  padding: 8px 12px;
  background: rgba(90, 184, 122, 0.06);
  border: 1px solid rgba(90, 184, 122, 0.2);
  border-radius: 6px;
  margin-bottom: 12px;
  text-align: center;
}

.lvlup-preview-label {
  color: var(--text-secondary);
  font-size: 0.72rem;
  margin-right: 6px;
}

.lvlup-preview-text {
  color: #5ab87a;
  font-size: 0.78rem;
  font-weight: 600;
}

.lvlup-error {
  color: #e07070;
  font-size: 0.78rem;
  text-align: center;
  margin-bottom: 8px;
}

.lvlup-actions {
  text-align: center;
}

.lvlup-warning {
  color: var(--text-secondary);
  font-size: 0.68rem;
  font-style: italic;
  margin: 0 0 8px;
}

.btn-lvlup-confirm {
  padding: 10px 32px;
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  font-weight: 700;
  background: linear-gradient(180deg, #2a6e3a, #1a4a26);
  border: 2px solid #5ab87a;
  color: white;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 3px 0 rgba(0,0,0,0.3);
}

.btn-lvlup-confirm:hover:not(:disabled) {
  box-shadow: 0 3px 0 rgba(0,0,0,0.3), 0 0 12px rgba(90, 184, 122, 0.3);
}

.btn-lvlup-confirm:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
