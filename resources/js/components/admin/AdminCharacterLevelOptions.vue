<template>
  <div class="admin-section">
    <div class="admin-header">
      <h2>Character Level Options</h2>
      <button class="btn-create" @click="openCreate">+ New Option</button>
    </div>

    <input v-model="searchQuery" type="text" class="search-input" placeholder="Search options..." />

    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Type</th>
          <th>Level</th>
          <th>Character</th>
          <th>Max</th>
          <th>Active</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="opt in filteredOptions" :key="opt.id">
          <td>{{ opt.name }}</td>
          <td><span class="type-badge">{{ opt.type }}</span></td>
          <td>{{ opt.available_at_level }}</td>
          <td>{{ opt.character?.name || 'All' }}</td>
          <td>{{ opt.max_selections || 'Unlimited' }}</td>
          <td><span :class="opt.is_active ? 'status-active' : 'status-inactive'">{{ opt.is_active ? 'Yes' : 'No' }}</span></td>
          <td>
            <button class="btn-edit" @click="openEdit(opt)">Edit</button>
            <button class="btn-delete" @click="deleteOption(opt)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Option' : 'New Option' }}</h3>
        <form @submit.prevent="save">
          <div class="form-grid">
            <div class="form-group">
              <label>Name</label>
              <input v-model="form.name" required />
            </div>
            <div class="form-group">
              <label>Type</label>
              <select v-model="form.type" required>
                <option value="bump_dice_face">Bump Dice Face</option>
                <option value="bump_two_dice_faces">Bump Two Dice Faces</option>
                <option value="start_with_item">Start With Item</option>
                <option value="extra_item_slot">Extra Item Slot</option>
                <option value="passive_stat_bonus">Passive Stat Bonus</option>
                <option value="add_wild">Add Wild</option>
                <option value="card_redraw">Card Redraw</option>
                <option value="start_with_curse">Start With Curse</option>
              </select>
            </div>
            <div class="form-group">
              <label>Available at Level</label>
              <input v-model.number="form.available_at_level" type="number" min="1" :max="maxAvailableLevel" required />
            </div>
            <div class="form-group">
              <label>Character (blank = all)</label>
              <select v-model="form.character_id">
                <option :value="null">All Characters</option>
                <option v-for="c in characters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Max Selections (0 = unlimited)</label>
              <input v-model.number="form.max_selections" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" />
            </div>
            <div class="form-group full-width">
              <label>Description</label>
              <input v-model="form.description" />
            </div>
            <div class="form-group">
              <label>Icon</label>
              <input v-model="form.icon" />
            </div>
            <div class="form-group">
              <label>
                <input type="checkbox" v-model="form.is_active" /> Active
              </label>
            </div>

            <!-- Type-specific config -->
            <div v-if="form.type === 'passive_stat_bonus'" class="form-group">
              <label>Stat</label>
              <select v-model="configStat">
                <option v-for="s in statOptions" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div v-if="form.type === 'passive_stat_bonus'" class="form-group">
              <label>Value</label>
              <input v-model.number="configValue" type="number" min="1" />
            </div>
            <div v-if="form.type === 'start_with_item'" class="form-group">
              <label>Item ID (blank for random)</label>
              <input v-model.number="configItemId" type="number" min="1" />
            </div>
            <div v-if="form.type === 'start_with_curse'" class="form-group">
              <label>Curse ID (blank for random)</label>
              <input v-model.number="configCurseId" type="number" min="1" />
            </div>
          </div>

          <div v-if="formError" class="form-error">{{ formError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-save">{{ editing ? 'Update' : 'Create' }}</button>
            <button type="button" class="btn-cancel" @click="showModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminCharacterLevelOptions',
  data() {
    return {
      options: [],
      characters: [],
      searchQuery: '',
      showModal: false,
      editing: null,
      formError: '',
      form: this.defaultForm(),
      configStat: 'wealth',
      configValue: 1,
      configItemId: null,
      configCurseId: null,
      maxAvailableLevel: 7,
    };
  },
  computed: {
    filteredOptions() {
      const q = this.searchQuery.toLowerCase().trim();
      if (!q) return this.options;
      return this.options.filter(o =>
        o.name.toLowerCase().includes(q) ||
        o.type.toLowerCase().includes(q) ||
        (o.description || '').toLowerCase().includes(q)
      );
    },
    statOptions() {
      return ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
    },
  },
  async mounted() {
    await this.load();
    try {
      const res = await axios.get('/api/admin/characters');
      this.characters = res.data;
    } catch (e) { /* ignore */ }
    try {
      const rulesRes = await axios.get('/api/admin/game-rules');
      const config = rulesRes.data?.advisor_level_config;
      if (config) {
        const parsed = typeof config === 'string' ? JSON.parse(config) : config;
        this.maxAvailableLevel = (parsed.max_level || 8) - 1;
      }
    } catch (e) { /* ignore */ }
  },
  methods: {
    defaultForm() {
      return {
        name: '',
        type: 'bump_dice_face',
        config: null,
        available_at_level: 1,
        character_id: null,
        is_active: true,
        max_selections: 0,
        sort_order: 0,
        description: '',
        icon: '',
      };
    },
    async load() {
      const res = await axios.get('/api/admin/character-level-options');
      this.options = res.data;
    },
    openCreate() {
      this.editing = null;
      this.form = this.defaultForm();
      this.configStat = 'wealth';
      this.configValue = 1;
      this.configItemId = null;
      this.configCurseId = null;
      this.formError = '';
      this.showModal = true;
    },
    openEdit(opt) {
      this.editing = opt.id;
      this.form = {
        name: opt.name,
        type: opt.type,
        config: opt.config,
        available_at_level: opt.available_at_level,
        character_id: opt.character_id,
        is_active: opt.is_active,
        max_selections: opt.max_selections,
        sort_order: opt.sort_order,
        description: opt.description || '',
        icon: opt.icon || '',
      };
      if (opt.type === 'passive_stat_bonus' && opt.config) {
        this.configStat = opt.config.stat || 'wealth';
        this.configValue = opt.config.value || 1;
      }
      if (opt.type === 'start_with_item' && opt.config) {
        this.configItemId = opt.config.item_id || null;
      }
      if (opt.type === 'start_with_curse' && opt.config) {
        this.configCurseId = opt.config.curse_id || null;
      }
      this.formError = '';
      this.showModal = true;
    },
    buildConfig() {
      if (this.form.type === 'passive_stat_bonus') {
        return { stat: this.configStat, value: this.configValue };
      }
      if (this.form.type === 'start_with_item') {
        if (this.configItemId) {
          return { item_id: this.configItemId };
        }
        return { random: true };
      }
      if (this.form.type === 'start_with_curse') {
        if (this.configCurseId) {
          return { curse_id: this.configCurseId };
        }
        return { random: true };
      }
      return null;
    },
    async save() {
      this.formError = '';
      const data = { ...this.form, config: this.buildConfig() };
      try {
        if (this.editing) {
          await axios.put(`/api/admin/character-level-options/${this.editing}`, data);
        } else {
          await axios.post('/api/admin/character-level-options', data);
        }
        this.showModal = false;
        await this.load();
      } catch (e) {
        this.formError = e.response?.data?.message || e.response?.data?.error || 'Error saving';
      }
    },
    async deleteOption(opt) {
      if (!confirm(`Delete "${opt.name}"?`)) return;
      await axios.delete(`/api/admin/character-level-options/${opt.id}`);
      await this.load();
    },
  },
};
</script>

<style scoped>
.admin-section {
  padding: 20px;
}

.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.admin-header h2 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin: 0;
}

.search-input {
  width: 100%;
  padding: 8px 12px;
  margin-bottom: 12px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-bright);
  font-size: 0.85rem;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}

.admin-table th,
.admin-table td {
  padding: 8px 10px;
  text-align: left;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
}

.admin-table th {
  color: var(--text-secondary);
  font-size: 0.75rem;
  text-transform: uppercase;
}

.type-badge {
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.72rem;
}

.status-active { color: #5ab87a; }
.status-inactive { color: #e07070; }

.btn-create {
  padding: 8px 16px;
  background: linear-gradient(180deg, #2a6e3a, #1a4a26);
  border: 2px solid #5ab87a;
  color: white;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
}

.btn-edit,
.btn-delete {
  padding: 4px 10px;
  font-size: 0.72rem;
  border-radius: 4px;
  cursor: pointer;
  margin-right: 4px;
}

.btn-edit {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
}

.btn-delete {
  background: rgba(224, 112, 112, 0.15);
  border: 1px solid rgba(224, 112, 112, 0.3);
  color: #e07070;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.88);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
}

.modal-content {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin: 0 0 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group.full-width {
  grid-column: span 2;
}

.form-group label {
  color: var(--text-secondary);
  font-size: 0.75rem;
}

.form-group input,
.form-group select {
  padding: 6px 10px;
  background: var(--bg-primary);
  border: 1px solid var(--border-gold);
  border-radius: 4px;
  color: var(--text-bright);
  font-size: 0.82rem;
}

.form-error {
  color: #e07070;
  font-size: 0.78rem;
  margin: 10px 0;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
  justify-content: flex-end;
}

.btn-save {
  padding: 8px 20px;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-medium));
  border: 2px solid var(--accent-gold);
  color: var(--accent-gold);
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
}

.btn-cancel {
  padding: 8px 20px;
  background: transparent;
  border: 1px solid var(--border-gold);
  color: var(--text-secondary);
  border-radius: 6px;
  cursor: pointer;
}
</style>
