<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Dice Themes</h2>
      <div class="header-buttons">
        <button class="btn-primary" :disabled="syncing" @click="syncThemes">
          {{ syncing ? 'Syncing...' : 'Sync from dddice' }}
        </button>
      </div>
    </div>

    <div v-if="syncMessage" class="sync-message">{{ syncMessage }}</div>

    <AdminSearchInput v-model="searchQuery" />

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else-if="!filteredThemes.length" class="empty-state">
      <p>No dice themes found. Click "Sync from dddice" to fetch themes from the API.</p>
    </div>

    <div v-else class="theme-grid">
      <div v-for="theme in filteredThemes" :key="theme.slug" class="theme-card">
        <div class="theme-image-wrap">
          <img v-if="theme.preview_image" :src="theme.preview_image" :alt="theme.name" class="theme-image" />
          <div v-else class="theme-image-placeholder">No preview</div>
        </div>
        <div class="theme-info">
          <div class="theme-name">{{ theme.name }}</div>
          <div v-if="theme.description" class="theme-desc">{{ theme.description }}</div>
          <div class="theme-slug">{{ theme.slug }}</div>
          <div class="theme-badges">
            <span v-if="theme.is_active" class="badge badge-active">Active</span>
            <span v-else class="badge badge-inactive">Inactive</span>
            <span v-if="theme.is_default_unlocked" class="badge badge-default">Default</span>
          </div>
        </div>
        <button class="btn-edit" @click="openEdit(theme)">Edit</button>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="editingTheme" class="modal-overlay" @click.self="editingTheme = null">
      <div class="modal-content">
        <h3>Edit Theme</h3>
        <div class="form-group">
          <label>Display Name</label>
          <input v-model="editForm.name" class="form-input" />
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea v-model="editForm.description" class="form-input form-textarea" rows="3" maxlength="500" placeholder="Short description of this dice theme"></textarea>
        </div>
        <div class="form-group">
          <label class="toggle-label">
            <input type="checkbox" v-model="editForm.is_active" />
            <span>Active</span>
            <span class="toggle-desc">When disabled, theme won't appear in user collections</span>
          </label>
        </div>
        <div class="form-group">
          <label class="toggle-label">
            <input type="checkbox" v-model="editForm.is_default_unlocked" />
            <span>Default Unlocked</span>
            <span class="toggle-desc">Available to all users without needing to unlock</span>
          </label>
        </div>
        <div v-if="editError" class="form-error">{{ editError }}</div>
        <div class="modal-actions">
          <button class="btn-primary" :disabled="editSaving" @click="saveEdit">
            {{ editSaving ? 'Saving...' : 'Save' }}
          </button>
          <button type="button" @click="editingTheme = null">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import AdminSearchInput from './AdminSearchInput.vue';

export default {
  name: 'AdminDice',
  components: { AdminSearchInput },
  data() {
    return {
      themes: [],
      loading: true,
      syncing: false,
      syncMessage: '',
      searchQuery: '',
      editingTheme: null,
      editForm: { name: '', description: '', is_active: true, is_default_unlocked: false },
      editSaving: false,
      editError: '',
    };
  },
  computed: {
    filteredThemes() {
      const q = this.searchQuery.toLowerCase().trim();
      if (!q) return this.themes;
      return this.themes.filter(t =>
        t.name.toLowerCase().includes(q) ||
        t.slug.toLowerCase().includes(q)
      );
    },
  },
  async mounted() {
    await this.fetch();
  },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const res = await axios.get('/api/admin/dice-themes');
        this.themes = res.data;
      } catch {
        // ignore
      }
      this.loading = false;
    },
    async syncThemes() {
      this.syncing = true;
      this.syncMessage = '';
      try {
        const res = await axios.post('/api/admin/dice-themes/sync');
        this.themes = res.data.themes || [];
        this.syncMessage = res.data.message || 'Sync complete';
        setTimeout(() => { this.syncMessage = ''; }, 4000);
      } catch (e) {
        this.syncMessage = 'Sync failed: ' + (e.response?.data?.error || e.message);
      }
      this.syncing = false;
    },
    openEdit(theme) {
      this.editingTheme = theme;
      this.editForm = {
        name: theme.name || '',
        description: theme.description || '',
        is_active: theme.is_active ?? true,
        is_default_unlocked: theme.is_default_unlocked ?? false,
      };
      this.editError = '';
    },
    async saveEdit() {
      this.editSaving = true;
      this.editError = '';
      try {
        const res = await axios.put(`/api/admin/dice-themes/${this.editingTheme.id}`, this.editForm);
        const idx = this.themes.findIndex(t => t.id === this.editingTheme.id);
        if (idx !== -1) {
          this.themes[idx] = { ...this.themes[idx], ...res.data };
        }
        this.editingTheme = null;
      } catch (e) {
        this.editError = e.response?.data?.message || 'Save failed';
      }
      this.editSaving = false;
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

.header-buttons {
  display: flex;
  gap: 8px;
}

.sync-message {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
  padding: 10px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 0.9rem;
}

.loading {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}

.empty-state {
  text-align: center;
  color: var(--text-secondary);
  padding: 60px 20px;
  font-size: 0.95rem;
}

.theme-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}

.theme-card {
  background: var(--bg-secondary);
  border: 1px solid rgba(138, 106, 46, 0.25);
  border-radius: 8px;
  overflow: hidden;
  transition: border-color 0.2s;
  display: flex;
  flex-direction: column;
}

.theme-card:hover {
  border-color: var(--accent-gold);
}

.theme-image-wrap {
  width: 100%;
  aspect-ratio: 1;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.theme-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.theme-image-placeholder {
  color: var(--text-secondary);
  font-size: 0.8rem;
  opacity: 0.5;
}

.theme-info {
  padding: 10px 12px;
  flex: 1;
}

.theme-name {
  color: var(--text-bright);
  font-weight: 600;
  font-size: 0.9rem;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.theme-desc {
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-style: italic;
  margin-bottom: 4px;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.theme-slug {
  color: var(--text-secondary);
  font-size: 0.75rem;
  opacity: 0.7;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 6px;
}

.theme-badges {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

.badge {
  font-size: 0.65rem;
  padding: 1px 6px;
  border-radius: 3px;
  font-weight: 600;
}

.badge-active {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
}

.badge-inactive {
  background: rgba(160, 48, 32, 0.15);
  border: 1px solid rgba(160, 48, 32, 0.3);
  color: #d05040;
}

.badge-default {
  background: rgba(67, 160, 212, 0.15);
  border: 1px solid rgba(67, 160, 212, 0.3);
  color: #60b8e0;
}

.btn-edit {
  margin: 0 12px 10px;
  padding: 5px 12px;
  font-size: 0.8rem;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--accent-gold);
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: all 0.2s;
}

.btn-edit:hover {
  background: rgba(212, 168, 67, 0.25);
  border-color: var(--accent-gold);
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.modal-content {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  padding: 28px;
  width: 90%;
  max-width: 420px;
}

.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 18px;
  font-size: 1.1rem;
}

.form-group {
  margin-bottom: 14px;
}

.form-group > label:not(.toggle-label) {
  display: block;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 5px;
}

.form-input {
  width: 100%;
  background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright);
  padding: 8px 12px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
}

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

.form-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.toggle-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex-wrap: wrap;
  cursor: pointer;
  color: var(--text-bright);
  font-size: 0.95rem;
}

.toggle-label input[type="checkbox"] {
  margin-top: 3px;
}

.toggle-desc {
  width: 100%;
  font-size: 0.78rem;
  color: var(--text-secondary);
  font-style: italic;
  padding-left: 24px;
}

.form-error {
  color: var(--accent-red);
  font-size: 0.9rem;
  margin-bottom: 10px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 18px;
}
</style>
