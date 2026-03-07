<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Curses</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input type="file" ref="csvInput" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Curse</button>
      </div>
    </div>

    <div v-if="importResult" class="import-result" :class="importResult.errors.length ? 'import-warn' : 'import-ok'">
      CSV Import: {{ importResult.created }} created, {{ importResult.updated }} updated.
      <span v-if="importResult.errors.length"> {{ importResult.errors.length }} error(s).</span>
      <div v-for="(err, i) in importResult.errors" :key="i" class="import-error-line">{{ err }}</div>
      <button class="import-dismiss" @click="importResult = null">Dismiss</button>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="cards-list">
      <div v-for="curse in filteredCurses" :key="curse.id" class="curse-card">
        <div class="curse-header">
          <h4>{{ curse.name }}</h4>
          <div class="curse-actions">
            <button @click="openEdit(curse)">Edit</button>
            <button class="btn-danger" @click="confirmDelete(curse)">Delete</button>
          </div>
        </div>
        <p class="curse-desc">{{ curse.description }}</p>
        <div class="curse-effects">
          <div class="effect-row effect-neg">
            <span class="effect-label">Penalty:</span>
            <span>{{ formatEffect(curse.negative_effect) }}</span>
          </div>
          <div class="effect-row effect-pos">
            <span class="effect-label">Reward:</span>
            <span>{{ formatEffect(curse.positive_effect) }}</span>
          </div>
          <div v-if="curse.negative_effect_duel" class="effect-row effect-duel">
            <span class="effect-label">Duel Penalty:</span>
            <span>{{ formatEffect(curse.negative_effect_duel) }}</span>
          </div>
          <div v-if="curse.positive_effect_duel" class="effect-row effect-duel">
            <span class="effect-label">Duel Reward:</span>
            <span>{{ formatEffect(curse.positive_effect_duel) }}</span>
          </div>
        </div>
        <div class="curse-meta">
          <span v-if="!curse.is_available" class="curse-tag tag-disabled">Disabled</span>
          <span v-if="curse.image_path" class="curse-tag tag-img">Has Image</span>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Curse' : 'New Curse' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Description (Flavor Text)</label>
            <textarea v-model="form.description" rows="3" required></textarea>
          </div>

          <!-- Negative Effect -->
          <div class="effect-section effect-section-neg">
            <h4 class="section-title section-neg">Negative Effect (Penalty)</h4>
            <div class="form-group">
              <label>Type</label>
              <select v-model="form.neg_type" required>
                <option value="lose_die">Lose Die</option>
                <option value="stat_per_round">Stat Per Round</option>
                <option value="difficulty_modifier">Difficulty Modifier</option>
                <option value="double_negative">Double Negatives</option>
              </select>
            </div>
            <div v-if="form.neg_type === 'stat_per_round'" class="form-group">
              <label>Stat</label>
              <select v-model="form.neg_stat">
                <option v-for="s in statOptions" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div v-if="['lose_die', 'stat_per_round', 'difficulty_modifier'].includes(form.neg_type)" class="form-group">
              <label>Value</label>
              <input v-model.number="form.neg_value" type="number" />
            </div>
          </div>

          <!-- Positive Effect -->
          <div class="effect-section effect-section-pos">
            <h4 class="section-title section-pos">Positive Effect (Reward)</h4>
            <div class="form-group">
              <label>Type</label>
              <select v-model="form.pos_type" required>
                <option value="xp_multiplier">XP Multiplier</option>
                <option value="stat_per_round">Stat Per Round</option>
                <option value="auto_max_stat">Auto Max Stat</option>
                <option value="score_bonus">Score Bonus</option>
                <option value="opponent_difficulty">Opponent Difficulty (Duel)</option>
                <option value="opponent_lose_die">Opponent Lose Die (Duel)</option>
              </select>
            </div>
            <div v-if="form.pos_type === 'stat_per_round'" class="form-group">
              <label>Stat</label>
              <select v-model="form.pos_stat">
                <option v-for="s in statOptions" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div v-if="['xp_multiplier', 'stat_per_round', 'auto_max_stat', 'score_bonus', 'opponent_difficulty', 'opponent_lose_die'].includes(form.pos_type)" class="form-group">
              <label>{{ form.pos_type === 'xp_multiplier' ? 'Multiplier (e.g. 1.5)' : form.pos_type === 'auto_max_stat' ? 'Count' : form.pos_type === 'opponent_lose_die' ? 'Rounds' : 'Value' }}</label>
              <input v-model.number="form.pos_value" type="number" step="any" />
            </div>
          </div>

          <!-- Duel Override -->
          <div class="duel-override-section">
            <label class="duel-toggle-label">
              <input type="checkbox" v-model="form.useDuelOverride" />
              <span>Use different effects for Duel mode</span>
            </label>
            <template v-if="form.useDuelOverride">
              <div class="effect-section effect-section-neg">
                <h4 class="section-title section-neg">Duel Negative Override</h4>
                <div class="form-group">
                  <label>Type</label>
                  <select v-model="form.neg_type_duel">
                    <option value="lose_die">Lose Die</option>
                    <option value="stat_per_round">Stat Per Round</option>
                    <option value="difficulty_modifier">Difficulty Modifier</option>
                    <option value="double_negative">Double Negatives</option>
                  </select>
                </div>
                <div v-if="form.neg_type_duel === 'stat_per_round'" class="form-group">
                  <label>Stat</label>
                  <select v-model="form.neg_stat_duel">
                    <option v-for="s in statOptions" :key="s" :value="s">{{ s }}</option>
                  </select>
                </div>
                <div v-if="['lose_die', 'stat_per_round', 'difficulty_modifier'].includes(form.neg_type_duel)" class="form-group">
                  <label>Value</label>
                  <input v-model.number="form.neg_value_duel" type="number" />
                </div>
              </div>
              <div class="effect-section effect-section-pos">
                <h4 class="section-title section-pos">Duel Positive Override</h4>
                <div class="form-group">
                  <label>Type</label>
                  <select v-model="form.pos_type_duel">
                    <option value="xp_multiplier">XP Multiplier</option>
                    <option value="stat_per_round">Stat Per Round</option>
                    <option value="auto_max_stat">Auto Max Stat</option>
                    <option value="score_bonus">Score Bonus</option>
                    <option value="opponent_difficulty">Opponent Difficulty</option>
                    <option value="opponent_lose_die">Opponent Lose Die</option>
                  </select>
                </div>
                <div v-if="form.pos_type_duel === 'stat_per_round'" class="form-group">
                  <label>Stat</label>
                  <select v-model="form.pos_stat_duel">
                    <option v-for="s in statOptions" :key="s" :value="s">{{ s }}</option>
                  </select>
                </div>
                <div v-if="['xp_multiplier', 'stat_per_round', 'auto_max_stat', 'score_bonus', 'opponent_difficulty', 'opponent_lose_die'].includes(form.pos_type_duel)" class="form-group">
                  <label>Value</label>
                  <input v-model.number="form.pos_value_duel" type="number" step="any" />
                </div>
              </div>
            </template>
          </div>

          <!-- Image Upload -->
          <div v-if="editing" class="form-group">
            <label>Curse Image</label>
            <input type="file" accept="image/*" @change="handleImageUpload" />
            <img v-if="editing.image_path" :src="`/api/storage/${editing.image_path}`" class="preview-img" />
          </div>

          <div class="form-group">
            <label>
              <input type="checkbox" v-model="form.is_available" />
              Available
            </label>
          </div>

          <div v-if="formError" class="form-error">{{ formError }}</div>

          <div class="modal-actions">
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
            <button type="button" @click="showModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';

export default {
  name: 'AdminCurses',
  setup() { return { toast: useToast() }; },
  data() {
    return {
      curses: [],
      loading: true,
      searchQuery: '',
      showModal: false,
      editing: null,
      saving: false,
      formError: '',
      importResult: null,
      statOptions: ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'],
      form: this.defaultForm(),
    };
  },
  computed: {
    filteredCurses() {
      const q = this.searchQuery.toLowerCase().trim();
      if (!q) return this.curses;
      return this.curses.filter(c =>
        (c.name || '').toLowerCase().includes(q) ||
        (c.description || '').toLowerCase().includes(q)
      );
    },
  },
  async mounted() {
    await this.fetch();
  },
  methods: {
    defaultForm() {
      return {
        name: '', description: '',
        neg_type: 'lose_die', neg_stat: 'wealth', neg_value: 1,
        pos_type: 'xp_multiplier', pos_stat: 'wealth', pos_value: 1.5,
        useDuelOverride: false,
        neg_type_duel: 'lose_die', neg_stat_duel: 'wealth', neg_value_duel: 1,
        pos_type_duel: 'xp_multiplier', pos_stat_duel: 'wealth', pos_value_duel: 1.5,
        is_available: true,
      };
    },
    async fetch() {
      this.loading = true;
      const res = await axios.get('/api/admin/curses');
      this.curses = res.data;
      this.loading = false;
    },
    buildEffect(type, stat, value) {
      const eff = { type };
      if (type === 'stat_per_round') {
        eff.stat = stat;
        eff.value = value;
      } else if (type === 'lose_die' || type === 'difficulty_modifier') {
        eff.value = value;
      } else if (type === 'xp_multiplier') {
        eff.value = value;
      } else if (type === 'auto_max_stat') {
        eff.count = value;
      } else if (type === 'score_bonus' || type === 'opponent_difficulty') {
        eff.value = value;
      } else if (type === 'opponent_lose_die') {
        eff.rounds = value;
      }
      return eff;
    },
    parseEffect(effect, prefix) {
      if (!effect) return {};
      return {
        [`${prefix}_type`]: effect.type || 'lose_die',
        [`${prefix}_stat`]: effect.stat || 'wealth',
        [`${prefix}_value`]: effect.value ?? effect.count ?? effect.rounds ?? 1,
      };
    },
    openCreate() {
      this.editing = null;
      this.form = this.defaultForm();
      this.formError = '';
      this.showModal = true;
    },
    openEdit(curse) {
      this.editing = curse;
      this.form = {
        name: curse.name,
        description: curse.description,
        ...this.parseEffect(curse.negative_effect, 'neg'),
        ...this.parseEffect(curse.positive_effect, 'pos'),
        useDuelOverride: curse.negative_effect_duel != null || curse.positive_effect_duel != null,
        neg_type_duel: curse.negative_effect_duel?.type || curse.negative_effect?.type || 'lose_die',
        neg_stat_duel: curse.negative_effect_duel?.stat || curse.negative_effect?.stat || 'wealth',
        neg_value_duel: curse.negative_effect_duel?.value ?? curse.negative_effect_duel?.count ?? curse.negative_effect_duel?.rounds ?? curse.negative_effect?.value ?? 1,
        pos_type_duel: curse.positive_effect_duel?.type || curse.positive_effect?.type || 'xp_multiplier',
        pos_stat_duel: curse.positive_effect_duel?.stat || curse.positive_effect?.stat || 'wealth',
        pos_value_duel: curse.positive_effect_duel?.value ?? curse.positive_effect_duel?.count ?? curse.positive_effect_duel?.rounds ?? curse.positive_effect?.value ?? 1.5,
        is_available: curse.is_available ?? true,
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';
      const payload = {
        name: this.form.name,
        description: this.form.description,
        negative_effect: this.buildEffect(this.form.neg_type, this.form.neg_stat, this.form.neg_value),
        positive_effect: this.buildEffect(this.form.pos_type, this.form.pos_stat, this.form.pos_value),
        negative_effect_duel: this.form.useDuelOverride
          ? this.buildEffect(this.form.neg_type_duel, this.form.neg_stat_duel, this.form.neg_value_duel)
          : null,
        positive_effect_duel: this.form.useDuelOverride
          ? this.buildEffect(this.form.pos_type_duel, this.form.pos_stat_duel, this.form.pos_value_duel)
          : null,
        is_available: this.form.is_available,
      };

      this.saving = true;
      try {
        if (this.editing) {
          await axios.put(`/api/admin/curses/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/curses', payload);
        }
        this.showModal = false;
        await this.fetch();
      } catch (e) {
        this.formError = e.response?.data?.message || 'Save failed';
      }
      this.saving = false;
    },
    async handleImageUpload(event) {
      if (!this.editing) return;
      const file = event.target.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append('image', file);
      try {
        await axios.post(`/api/admin/curses/${this.editing.id}/image`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        await this.fetch();
        this.toast.success('Image uploaded');
      } catch (e) {
        this.toast.error('Upload failed: ' + (e.response?.data?.message || e.message));
      }
    },
    async confirmDelete(curse) {
      if (!confirm(`Delete curse "${curse.name}"?`)) return;
      try {
        await axios.delete(`/api/admin/curses/${curse.id}`);
        await this.fetch();
      } catch (e) {
        this.toast.error('Delete failed: ' + (e.response?.data?.message || e.message));
      }
    },
    formatEffect(effect) {
      if (!effect) return '—';
      const t = effect.type;
      if (t === 'lose_die') return `Lose ${effect.value || 1} die`;
      if (t === 'stat_per_round') return `${effect.value > 0 ? '+' : ''}${effect.value} ${effect.stat}/round`;
      if (t === 'difficulty_modifier') return `+${effect.value || 1} difficulty`;
      if (t === 'double_negative') return 'Double negative effects';
      if (t === 'xp_multiplier') return `${effect.value}x XP`;
      if (t === 'auto_max_stat') return `Auto-max ${effect.count || 1} stat(s)`;
      if (t === 'score_bonus') return `+${effect.value} score`;
      if (t === 'opponent_difficulty') return `+${effect.value} opponent difficulty`;
      if (t === 'opponent_lose_die') return `Opponent loses die for ${effect.rounds || 1} round(s)`;
      return JSON.stringify(effect);
    },
    exportCsv() {
      window.location.href = '/api/admin/curses/export-csv';
    },
    triggerImport() {
      this.$refs.csvInput.click();
    },
    async handleImportFile(event) {
      const file = event.target.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append('file', file);
      try {
        const res = await axios.post('/api/admin/curses/import-csv', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.importResult = res.data;
        await this.fetch();
      } catch (e) {
        this.importResult = { created: 0, updated: 0, errors: [e.response?.data?.message || 'Import failed'] };
      }
      event.target.value = '';
    },
  },
};
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
}
.loading { text-align: center; color: var(--text-secondary); padding: 40px; }

.btn-csv {
  background: rgba(40, 120, 80, 0.2);
  color: #5ab87a;
  border: 1px solid rgba(40, 120, 80, 0.4);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  transition: all 0.2s;
}
.btn-csv:hover {
  background: rgba(40, 120, 80, 0.35);
  border-color: rgba(60, 160, 100, 0.6);
}

.import-result {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 0.9rem;
  position: relative;
}
.import-ok {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
}
.import-warn {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.4);
  color: #d4a843;
}
.import-error-line {
  font-size: 0.8rem;
  color: #d05040;
  margin-top: 4px;
}
.import-dismiss {
  position: absolute;
  top: 8px;
  right: 8px;
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 0.8rem;
  padding: 2px 8px;
}

.cards-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 12px;
}

.curse-card {
  background: var(--bg-secondary);
  border: 1px solid rgba(128, 0, 128, 0.25);
  border-radius: 8px;
  padding: 16px;
}

.curse-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.curse-header h4 {
  font-family: 'Cinzel', serif;
  color: #c890e0;
  font-size: 1rem;
}
.curse-actions { display: flex; gap: 6px; }
.curse-actions button { padding: 5px 12px; font-size: 0.8rem; }

.curse-desc {
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.4;
  margin-bottom: 8px;
}

.curse-effects { margin-bottom: 8px; }
.effect-row { font-size: 0.85rem; margin-bottom: 3px; }
.effect-label { font-weight: 700; margin-right: 6px; }
.effect-neg { color: #c0392b; }
.effect-pos { color: #d4a843; }
.effect-duel { color: #c890e0; font-style: italic; }

.curse-meta { display: flex; gap: 8px; }
.curse-tag {
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.tag-disabled { background: rgba(192, 57, 43, 0.2); color: #c0392b; }
.tag-img { background: rgba(212, 168, 67, 0.2); color: #d4a843; }

/* Effect sections in form */
.effect-section {
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 12px;
}
.effect-section-neg {
  border: 1px solid rgba(192, 57, 43, 0.3);
  background: rgba(192, 57, 43, 0.05);
}
.effect-section-pos {
  border: 1px solid rgba(212, 168, 67, 0.3);
  background: rgba(212, 168, 67, 0.05);
}
.section-title { font-family: 'Cinzel', serif; font-size: 0.95rem; margin-bottom: 8px; }
.section-neg { color: #c0392b; }
.section-pos { color: #d4a843; }

.duel-override-section {
  border: 1px solid rgba(138, 58, 185, 0.3);
  background: rgba(138, 58, 185, 0.05);
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 12px;
}
.duel-toggle-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: #c890e0;
  font-weight: 700;
  margin-bottom: 8px;
}

.preview-img {
  max-width: 120px;
  border-radius: 6px;
  margin-top: 8px;
}

/* Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex; align-items: center; justify-content: center;
  z-index: 200;
}
.modal-content {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  padding: 28px;
  width: 90%; max-width: 600px;
  max-height: 85vh; overflow-y: auto;
}
.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 18px; font-size: 1.3rem;
}
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 5px; }
.form-group input:not([type="checkbox"]):not([type="file"]), .form-group textarea, .form-group select {
  width: 100%; background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright); padding: 8px 12px;
  border-radius: 4px; font-family: inherit; font-size: 0.95rem;
}
.form-group textarea { resize: vertical; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin-bottom: 10px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.header-buttons { display: flex; gap: 8px; }
</style>
