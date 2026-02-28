<template>
  <div>
    <h2 class="page-title">Seasons</h2>

    <!-- Create form -->
    <div class="form-panel">
      <h3>{{ editing ? 'Edit Season' : 'New Season' }}</h3>
      <form @submit.prevent="save">
        <div class="form-row">
          <label>Name</label>
          <input v-model="form.name" required />
        </div>
        <div class="form-row">
          <label>Starts At</label>
          <input v-model="form.starts_at" type="datetime-local" required />
        </div>
        <div class="form-row">
          <label>Ends At</label>
          <input v-model="form.ends_at" type="datetime-local" required />
        </div>
        <div class="form-row">
          <label>
            <input type="checkbox" v-model="form.is_active" /> Active
          </label>
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
      <div v-for="s in seasons" :key="s.id" class="list-row">
        <div class="list-info">
          <strong>{{ s.name }}</strong>
          <span v-if="s.is_active" class="active-badge">Active</span>
          <div class="list-dates">{{ formatDate(s.starts_at) }} &mdash; {{ formatDate(s.ends_at) }}</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="editSeason(s)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteSeason(s)">Del</button>
        </div>
      </div>
      <div v-if="seasons.length === 0" class="empty">No seasons yet.</div>
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
      form: { name: '', starts_at: '', ends_at: '', is_active: false },
      editing: null,
      error: '',
    };
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/seasons');
      this.seasons = res.data;
    },
    async save() {
      this.error = '';
      try {
        const data = { ...this.form };
        if (this.editing) {
          await axios.put(`/api/admin/seasons/${this.editing}`, data);
        } else {
          await axios.post('/api/admin/seasons', data);
        }
        this.resetForm();
        this.load();
      } catch (e) {
        this.error = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    editSeason(s) {
      this.editing = s.id;
      this.form = {
        name: s.name,
        starts_at: s.starts_at?.slice(0, 16) || '',
        ends_at: s.ends_at?.slice(0, 16) || '',
        is_active: s.is_active,
      };
    },
    async deleteSeason(s) {
      if (!confirm(`Delete "${s.name}"?`)) return;
      await axios.delete(`/api/admin/seasons/${s.id}`);
      this.load();
    },
    resetForm() {
      this.editing = null;
      this.form = { name: '', starts_at: '', ends_at: '', is_active: false };
      this.error = '';
    },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    },
  },
};
</script>

<style scoped>
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); text-align: center; margin-bottom: 24px; font-size: 1.6rem; }
.form-panel { background: var(--bg-secondary); border: 1px solid var(--border-gold); border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.form-panel h3 { font-family: 'Cinzel', serif; color: var(--text-bright); margin-bottom: 12px; }
.form-row { margin-bottom: 10px; }
.form-row label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-row input[type="text"], .form-row input[type="datetime-local"], .form-row input { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-row input:focus { outline: none; border-color: var(--accent-gold); }
.btn-row { display: flex; gap: 8px; margin-top: 12px; }
.error { color: var(--accent-red); font-size: 0.85rem; margin: 8px 0; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-dates { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.active-badge { font-size: 0.7rem; padding: 1px 6px; border-radius: 3px; background: rgba(74, 138, 58, 0.2); color: #6abf50; margin-left: 6px; font-weight: 600; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
</style>
