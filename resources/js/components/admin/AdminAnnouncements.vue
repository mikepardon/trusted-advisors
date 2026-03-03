<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Announcements</h2>
      <button class="btn-primary" @click="openCreate">New Announcement</button>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="a in announcements" :key="a.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ a.title }}</strong>
            <span :class="['status-badge', a.is_active ? 'active' : 'inactive']">
              {{ a.is_active ? 'Active' : 'Inactive' }}
            </span>
            <span v-if="a.starts_at || a.ends_at" class="date-badge">
              {{ formatDateRange(a.starts_at, a.ends_at) }}
            </span>
          </div>
          <div class="list-sub">
            {{ truncate(a.description, 80) }}
            &mdash; {{ a.dismissal_count || 0 }} dismissed
            <span v-if="a.creator"> &mdash; by {{ a.creator.name }}</span>
          </div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(a)">Edit</button>
          <button class="btn-sm btn-danger" @click="remove(a)">Delete</button>
        </div>
      </div>
      <div v-if="announcements.length === 0" class="empty">No announcements yet.</div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Announcement' : 'New Announcement' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Title</label>
            <input v-model="form.title" required placeholder="Announcement title" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="3" required placeholder="Announcement body text"></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Link URL</label>
              <input v-model="form.link_url" placeholder="https://..." />
            </div>
            <div class="form-group">
              <label>Link Label</label>
              <input v-model="form.link_label" placeholder="Learn more" />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Starts At</label>
              <input v-model="form.starts_at" type="datetime-local" />
            </div>
            <div class="form-group">
              <label>Ends At</label>
              <input v-model="form.ends_at" type="datetime-local" />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Sort Order</label>
              <input v-model.number="form.sort_order" type="number" />
            </div>
            <div class="form-group">
              <label>Active</label>
              <label class="toggle-label">
                <input type="checkbox" v-model="form.is_active" />
                <span>{{ form.is_active ? 'Yes' : 'No' }}</span>
              </label>
            </div>
          </div>
          <div v-if="formError" class="form-error">{{ formError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}
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

export default {
  name: 'AdminAnnouncements',
  data() {
    return {
      announcements: [],
      showModal: false,
      editing: null,
      saving: false,
      formError: '',
      form: this.emptyForm(),
    };
  },
  async mounted() {
    await this.load();
  },
  methods: {
    emptyForm() {
      return {
        title: '',
        description: '',
        link_url: '',
        link_label: '',
        is_active: true,
        starts_at: '',
        ends_at: '',
        sort_order: 0,
      };
    },
    async load() {
      const res = await axios.get('/api/admin/announcements');
      this.announcements = res.data;
    },
    openCreate() {
      this.editing = null;
      this.form = this.emptyForm();
      this.formError = '';
      this.showModal = true;
    },
    openEdit(a) {
      this.editing = a;
      this.form = {
        title: a.title,
        description: a.description,
        link_url: a.link_url || '',
        link_label: a.link_label || '',
        is_active: a.is_active,
        starts_at: a.starts_at ? a.starts_at.slice(0, 16) : '',
        ends_at: a.ends_at ? a.ends_at.slice(0, 16) : '',
        sort_order: a.sort_order || 0,
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.formError = '';
      try {
        const payload = { ...this.form };
        if (!payload.link_url) payload.link_url = null;
        if (!payload.link_label) payload.link_label = null;
        if (!payload.starts_at) payload.starts_at = null;
        if (!payload.ends_at) payload.ends_at = null;

        if (this.editing) {
          await axios.put(`/api/admin/announcements/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/announcements', payload);
        }
        this.showModal = false;
        await this.load();
      } catch (e) {
        this.formError = e.response?.data?.message || 'Failed to save';
      }
      this.saving = false;
    },
    async remove(a) {
      if (!window.confirm(`Delete announcement "${a.title}"?`)) return;
      try {
        await axios.delete(`/api/admin/announcements/${a.id}`);
        await this.load();
      } catch (e) {
        alert(e.response?.data?.message || 'Failed to delete');
      }
    },
    formatDateRange(start, end) {
      const fmt = (d) => d ? new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : '';
      if (start && end) return `${fmt(start)} – ${fmt(end)}`;
      if (start) return `From ${fmt(start)}`;
      if (end) return `Until ${fmt(end)}`;
      return '';
    },
    truncate(str, len) {
      if (!str) return '';
      return str.length > len ? str.slice(0, len) + '...' : str;
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; gap: 12px; }
.list-info { flex: 1; min-width: 0; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.list-actions { display: flex; gap: 6px; flex-shrink: 0; }
.status-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; font-weight: 600; }
.status-badge.active { background: rgba(46, 160, 67, 0.2); color: #4ade80; }
.status-badge.inactive { background: rgba(160, 48, 32, 0.2); color: #d05040; }
.date-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
.btn-sm { padding: 4px 10px; font-size: 0.8rem; border-radius: 4px; cursor: pointer; background: rgba(212, 168, 67, 0.1); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-secondary); }
.btn-sm:hover { color: var(--accent-gold); border-color: var(--accent-gold); }
.btn-danger { color: #d05040; border-color: rgba(160, 48, 32, 0.4); }
.btn-danger:hover { color: #ff6050; border-color: #d05040; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 550px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-gold); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.toggle-label { display: flex; align-items: center; gap: 8px; cursor: pointer; padding-top: 4px; }
.toggle-label input[type="checkbox"] { width: auto; }
</style>
