<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Addons</h2>
      <button class="btn-primary" @click="openCreate">+ New Addon</button>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="list-panel">
      <div v-for="a in addons" :key="a.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ a.name }}</strong>
            <span class="status-badge" :class="a.is_active ? 'badge-active' : 'badge-inactive'">
              {{ a.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div v-if="a.description" class="list-desc">{{ a.description }}</div>
          <div class="list-counts">
            <span v-if="a.characters_count" class="count-badge">{{ a.characters_count }} characters</span>
            <span v-if="a.events_count" class="count-badge">{{ a.events_count }} events</span>
            <span v-if="a.items_count" class="count-badge">{{ a.items_count }} items</span>
            <span v-if="a.daily_challenges_count" class="count-badge">{{ a.daily_challenges_count }} challenges</span>
            <span v-if="!a.characters_count && !a.events_count && !a.items_count && !a.daily_challenges_count" class="count-badge dim">No content</span>
          </div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(a)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteItem(a)">Del</button>
        </div>
      </div>
      <div v-if="addons.length === 0" class="empty">No addons defined. All content is base game.</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Addon' : 'New Addon' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label>Active</label>
            <select v-model="form.is_active">
              <option :value="true">Yes</option>
              <option :value="false">No</option>
            </select>
          </div>
          <div v-if="formError" class="form-error">{{ formError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary">{{ editing ? 'Update' : 'Create' }}</button>
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
  name: 'AdminAddons',
  setup() { return { toast: useToast() }; },
  data() {
    return {
      addons: [],
      loading: true,
      showModal: false,
      editing: null,
      formError: '',
      form: { name: '', description: '', is_active: true },
    };
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      this.loading = true;
      try {
        const res = await axios.get('/api/admin/addons');
        this.addons = res.data;
      } catch { /* ignore */ }
      this.loading = false;
    },
    openCreate() {
      this.editing = null;
      this.form = { name: '', description: '', is_active: true };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(a) {
      this.editing = a.id;
      this.form = { name: a.name, description: a.description || '', is_active: a.is_active };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';
      try {
        if (this.editing) {
          await axios.put(`/api/admin/addons/${this.editing}`, this.form);
        } else {
          await axios.post('/api/admin/addons', this.form);
        }
        this.showModal = false;
        this.load();
      } catch (e) {
        this.formError = e.response?.data?.message || 'Error';
      }
    },
    async deleteItem(a) {
      if (!confirm(`Delete addon "${a.name}"? Content using it will become base game.`)) return;
      try {
        await axios.delete(`/api/admin/addons/${a.id}`);
        this.load();
      } catch (e) {
        this.toast.error('Delete failed: ' + (e.response?.data?.message || e.message));
      }
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.loading { text-align: center; color: var(--text-secondary); padding: 40px; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: flex-start; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-top { display: flex; align-items: center; gap: 8px; }
.list-info strong { color: var(--accent-gold); }
.list-desc { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.list-counts { display: flex; gap: 6px; margin-top: 4px; flex-wrap: wrap; }
.count-badge { font-size: 0.7rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.15); color: #a0a0d0; }
.count-badge.dim { opacity: 0.5; }
.status-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; text-transform: uppercase; }
.badge-active { background: rgba(74, 138, 58, 0.2); color: #6abf50; }
.badge-inactive { background: rgba(160, 48, 32, 0.2); color: #d05040; }
.list-actions { display: flex; gap: 4px; flex-shrink: 0; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 450px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group textarea { resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
</style>
