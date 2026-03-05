<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Rotating Events</h2>
      <button class="btn-primary" @click="openCreate">New Event</button>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="e in events" :key="e.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ e.name }}</strong>
            <span :class="['status-badge', isActive(e) ? 'active' : 'inactive']">
              {{ isActive(e) ? 'Active' : 'Inactive' }}
            </span>
            <span class="date-badge">{{ formatDateRange(e.starts_at, e.ends_at) }}</span>
          </div>
          <div class="list-sub">
            {{ truncate(e.description, 80) }}
            &mdash; {{ e.game_type }} / {{ e.game_mode }}
            <span v-if="e.reward_coins"> &mdash; {{ e.reward_coins }} coins</span>
            <span v-if="e.creator"> &mdash; by {{ e.creator.name }}</span>
          </div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(e)">Edit</button>
          <button class="btn-sm btn-danger" @click="remove(e)">Delete</button>
        </div>
      </div>
      <div v-if="events.length === 0" class="empty">No rotating events yet.</div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Event' : 'New Event' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required placeholder="Event name" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="3" required placeholder="Event description"></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Game Type</label>
              <select v-model="form.game_type">
                <option value="cooperative">Cooperative</option>
                <option value="duel">Duel</option>
              </select>
            </div>
            <div class="form-group">
              <label>Game Mode</label>
              <select v-model="form.game_mode">
                <option value="single">Single</option>
                <option value="pass_and_play">Pass & Play</option>
                <option value="online">Online</option>
              </select>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Reward Coins</label>
              <input v-model.number="form.reward_coins" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Starting Stats Override</label>
              <input v-model.number="modStartingStats" type="number" min="1" max="20" placeholder="Default (8)" />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>XP Multiplier</label>
              <input v-model.number="modXpMultiplier" type="number" step="0.5" min="0.5" placeholder="Default (1)" />
            </div>
            <div class="form-group">
              <label>Image URL</label>
              <input v-model="form.image_url" placeholder="Optional" />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Starts At</label>
              <input v-model="form.starts_at" type="datetime-local" required />
            </div>
            <div class="form-group">
              <label>Ends At</label>
              <input v-model="form.ends_at" type="datetime-local" required />
            </div>
          </div>
          <div class="form-group">
            <label>
              <input type="checkbox" v-model="form.is_active" /> Active
            </label>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn-secondary" @click="showModal = false">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
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
  name: 'AdminRotatingEvents',
  setup() { return { toast: useToast() }; },
  data() {
    return {
      events: [],
      showModal: false,
      editing: null,
      saving: false,
      form: this.emptyForm(),
      modStartingStats: null,
      modXpMultiplier: null,
    };
  },
  async mounted() {
    await this.fetch();
  },
  methods: {
    emptyForm() {
      return {
        name: '',
        description: '',
        image_url: '',
        game_type: 'cooperative',
        game_mode: 'single',
        reward_coins: 0,
        starts_at: '',
        ends_at: '',
        is_active: true,
      };
    },
    async fetch() {
      try {
        const res = await axios.get('/api/admin/rotating-events');
        this.events = res.data;
      } catch {}
    },
    openCreate() {
      this.editing = null;
      this.form = this.emptyForm();
      this.modStartingStats = null;
      this.modXpMultiplier = null;
      this.showModal = true;
    },
    openEdit(e) {
      this.editing = e;
      this.form = {
        name: e.name,
        description: e.description,
        image_url: e.image_url || '',
        game_type: e.game_type,
        game_mode: e.game_mode,
        reward_coins: e.reward_coins,
        starts_at: e.starts_at ? e.starts_at.substring(0, 16) : '',
        ends_at: e.ends_at ? e.ends_at.substring(0, 16) : '',
        is_active: e.is_active,
      };
      this.modStartingStats = e.modifiers?.starting_stats || null;
      this.modXpMultiplier = e.modifiers?.xp_multiplier || null;
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      const payload = { ...this.form };
      const modifiers = {};
      if (this.modStartingStats) modifiers.starting_stats = this.modStartingStats;
      if (this.modXpMultiplier) modifiers.xp_multiplier = this.modXpMultiplier;
      payload.modifiers = Object.keys(modifiers).length ? modifiers : null;

      try {
        if (this.editing) {
          await axios.put(`/api/admin/rotating-events/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/rotating-events', payload);
        }
        this.showModal = false;
        await this.fetch();
      } catch (e) {
        this.toast.error(e.response?.data?.message || 'Failed to save');
      }
      this.saving = false;
    },
    async remove(e) {
      if (!confirm(`Delete "${e.name}"?`)) return;
      try {
        await axios.delete(`/api/admin/rotating-events/${e.id}`);
        await this.fetch();
      } catch {}
    },
    isActive(e) {
      if (!e.is_active) return false;
      const now = new Date();
      return new Date(e.starts_at) <= now && new Date(e.ends_at) >= now;
    },
    formatDateRange(start, end) {
      if (!start || !end) return '';
      const s = new Date(start).toLocaleDateString();
      const e = new Date(end).toLocaleDateString();
      return `${s} - ${e}`;
    },
    truncate(str, len) {
      if (!str) return '';
      return str.length > len ? str.substring(0, len) + '...' : str;
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.4rem; }
.list-panel { display: flex; flex-direction: column; gap: 8px; }
.list-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--bg-secondary); border: 1px solid rgba(138,106,46,0.2); border-radius: 8px; }
.list-info { flex: 1; min-width: 0; }
.list-top { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 4px; }
.list-top strong { color: var(--text-bright); }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); }
.list-actions { display: flex; gap: 6px; flex-shrink: 0; }
.status-badge { padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
.status-badge.active { background: rgba(74,138,58,0.2); color: #6abf50; }
.status-badge.inactive { background: rgba(160,48,32,0.2); color: #d05040; }
.date-badge { font-size: 0.7rem; color: var(--text-secondary); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
.btn-sm { padding: 4px 12px; font-size: 0.8rem; border-radius: 4px; cursor: pointer; background: rgba(212,168,67,0.1); border: 1px solid rgba(212,168,67,0.3); color: var(--accent-gold); }
.btn-danger { background: rgba(160,48,32,0.15); border-color: rgba(160,48,32,0.3); color: #d05040; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 200; display: flex; align-items: center; justify-content: center; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 12px; padding: 24px; max-width: 500px; width: 95%; max-height: 90vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 16px; }
.form-group { margin-bottom: 12px; }
.form-group label { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 4px; }
.form-group input, .form-group textarea, .form-group select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--border-gold); border-radius: 6px; color: var(--text-primary); font-family: inherit; font-size: 0.9rem; padding: 8px 10px; outline: none; box-sizing: border-box; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--accent-gold); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.btn-secondary { padding: 8px 16px; background: none; border: 1px solid rgba(138,106,46,0.4); color: var(--text-secondary); border-radius: 6px; cursor: pointer; }
</style>
