<template>
  <div>
    <h2 class="page-title">Unlockables</h2>

    <!-- Form -->
    <div class="form-panel">
      <h3>{{ editing ? 'Edit Unlockable' : 'New Unlockable' }}</h3>
      <form @submit.prevent="save">
        <div class="form-grid">
          <div class="form-row">
            <label>Type</label>
            <select v-model="form.type">
              <option value="character">Character</option>
              <option value="item">Item</option>
            </select>
          </div>
          <div class="form-row">
            <label>Entity ID</label>
            <input v-model.number="form.entity_id" type="number" required min="1" />
          </div>
          <div class="form-row">
            <label>Unlock Method</label>
            <select v-model="form.unlock_method">
              <option value="level">Level</option>
              <option value="achievement">Achievement</option>
            </select>
          </div>
          <div class="form-row">
            <label>{{ form.unlock_method === 'level' ? 'Required Level' : 'Achievement ID' }}</label>
            <input v-model.number="form.unlock_value" type="number" required min="1" />
          </div>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <div class="btn-row">
          <button type="submit" class="btn-primary">{{ editing ? 'Update' : 'Create' }}</button>
          <button v-if="editing" type="button" class="btn-secondary" @click="resetForm">Cancel</button>
        </div>
      </form>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="u in unlockables" :key="u.id" class="list-row">
        <div class="list-info">
          <strong>{{ u.type }} #{{ u.entity_id }}</strong>
          <span class="method-badge">{{ u.unlock_method }}</span>
          <div class="list-sub">{{ u.unlock_method === 'level' ? 'Level ' + u.unlock_value : 'Achievement #' + u.unlock_value }}</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="editItem(u)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteItem(u)">Del</button>
        </div>
      </div>
      <div v-if="unlockables.length === 0" class="empty">No unlockables defined.</div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminUnlockables',
  data() {
    return {
      unlockables: [],
      form: { type: 'character', entity_id: '', unlock_method: 'level', unlock_value: 1 },
      editing: null,
      error: '',
    };
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/unlockables');
      this.unlockables = res.data;
    },
    async save() {
      this.error = '';
      try {
        if (this.editing) {
          await axios.put(`/api/admin/unlockables/${this.editing}`, this.form);
        } else {
          await axios.post('/api/admin/unlockables', this.form);
        }
        this.resetForm();
        this.load();
      } catch (e) {
        this.error = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    editItem(u) {
      this.editing = u.id;
      this.form = { type: u.type, entity_id: u.entity_id, unlock_method: u.unlock_method, unlock_value: u.unlock_value };
    },
    async deleteItem(u) {
      if (!confirm('Delete this unlockable?')) return;
      await axios.delete(`/api/admin/unlockables/${u.id}`);
      this.load();
    },
    resetForm() {
      this.editing = null;
      this.form = { type: 'character', entity_id: '', unlock_method: 'level', unlock_value: 1 };
      this.error = '';
    },
  },
};
</script>

<style scoped>
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); text-align: center; margin-bottom: 24px; font-size: 1.6rem; }
.form-panel { background: var(--bg-secondary); border: 1px solid var(--border-gold); border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.form-panel h3 { font-family: 'Cinzel', serif; color: var(--text-bright); margin-bottom: 12px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-row label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-row input, .form-row select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.btn-row { display: flex; gap: 8px; margin-top: 14px; }
.error { color: var(--accent-red); font-size: 0.85rem; margin: 8px 0; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.method-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); margin-left: 6px; text-transform: uppercase; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
