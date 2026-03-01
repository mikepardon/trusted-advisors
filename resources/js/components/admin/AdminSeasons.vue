<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Seasons</h2>
      <button class="btn-primary" @click="openCreate">+ New Season</button>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="s in seasons" :key="s.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ s.name }}</strong>
            <span v-if="s.is_active" class="active-badge">Active</span>
          </div>
          <div class="list-dates">{{ formatDate(s.starts_at) }} &mdash; {{ formatDate(s.ends_at) }}</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(s)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteSeason(s)">Del</button>
        </div>
      </div>
      <div v-if="seasons.length === 0" class="empty">No seasons yet.</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Season' : 'New Season' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Starts At</label>
            <input v-model="form.starts_at" type="datetime-local" required />
          </div>
          <div class="form-group">
            <label>Ends At</label>
            <input v-model="form.ends_at" type="datetime-local" required />
          </div>
          <div class="form-group">
            <label>
              <input type="checkbox" v-model="form.is_active" /> Active
            </label>
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

export default {
  name: 'AdminSeasons',
  data() {
    return {
      seasons: [],
      showModal: false,
      editing: null,
      formError: '',
      form: { name: '', starts_at: '', ends_at: '', is_active: false },
    };
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/seasons');
      this.seasons = res.data;
    },
    openCreate() {
      this.editing = null;
      this.form = { name: '', starts_at: '', ends_at: '', is_active: false };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(s) {
      this.editing = s.id;
      this.form = {
        name: s.name,
        starts_at: s.starts_at?.slice(0, 16) || '',
        ends_at: s.ends_at?.slice(0, 16) || '',
        is_active: s.is_active,
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';
      try {
        const data = { ...this.form };
        if (this.editing) {
          await axios.put(`/api/admin/seasons/${this.editing}`, data);
        } else {
          await axios.post('/api/admin/seasons', data);
        }
        this.showModal = false;
        this.load();
      } catch (e) {
        this.formError = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    async deleteSeason(s) {
      if (!confirm(`Delete "${s.name}"?`)) return;
      await axios.delete(`/api/admin/seasons/${s.id}`);
      this.load();
    },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 8px; }
.list-dates { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.active-badge { font-size: 0.7rem; padding: 1px 6px; border-radius: 3px; background: rgba(74, 138, 58, 0.2); color: #6abf50; font-weight: 600; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 450px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input:not([type="checkbox"]), .form-group select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
</style>
