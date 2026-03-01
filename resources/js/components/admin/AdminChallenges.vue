<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Daily Challenges</h2>
      <button class="btn-primary" @click="openCreate">+ New Challenge</button>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="c in challenges" :key="c.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ c.title }}</strong>
            <span class="date-badge">{{ c.date }}</span>
            <span v-if="c.is_manual" class="manual-badge">Manual</span>
            <span v-if="c.addon_id" class="addon-badge">Addon</span>
          </div>
          <div class="list-sub">{{ c.description }} (+{{ c.reward_xp }} XP)</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(c)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteChallenge(c)">Del</button>
        </div>
      </div>
      <div v-if="challenges.length === 0" class="empty">No challenges yet.</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Challenge' : 'New Challenge' }}</h3>
        <form @submit.prevent="save">
          <div class="form-grid">
            <div class="form-group">
              <label>Date</label>
              <input v-model="form.date" type="date" required />
            </div>
            <div class="form-group">
              <label>Title</label>
              <input v-model="form.title" required />
            </div>
            <div class="form-group full">
              <label>Description</label>
              <input v-model="form.description" required />
            </div>
            <div class="form-group">
              <label>Criteria Type</label>
              <select v-model="form.criteria_type">
                <option value="play_game">Play Game</option>
                <option value="win_game">Win Game</option>
                <option value="stat_threshold">Stat Threshold</option>
                <option value="use_character">Use Character</option>
                <option value="no_stat_below">No Stat Below</option>
              </select>
            </div>
            <div class="form-group" v-if="form.criteria_type === 'play_game' || form.criteria_type === 'win_game'">
              <label>Mode</label>
              <select v-model="form.criteria_mode">
                <option value="any">Any</option>
                <option value="single">Solo</option>
                <option value="pass_and_play">Local</option>
                <option value="online">Online</option>
              </select>
            </div>
            <div class="form-group" v-if="form.criteria_type === 'stat_threshold'">
              <label>Stat</label>
              <select v-model="form.criteria_stat">
                <option value="wealth">Wealth</option>
                <option value="influence">Influence</option>
                <option value="security">Security</option>
                <option value="religion">Religion</option>
                <option value="food">Food</option>
                <option value="happiness">Happiness</option>
              </select>
            </div>
            <div class="form-group" v-if="['stat_threshold', 'no_stat_below'].includes(form.criteria_type)">
              <label>Value</label>
              <input v-model.number="form.criteria_value" type="number" min="1" max="20" />
            </div>
            <div class="form-group" v-if="form.criteria_type === 'use_character'">
              <label>Character ID</label>
              <input v-model.number="form.criteria_character_id" type="number" min="1" />
            </div>
            <div class="form-group">
              <label>Reward XP</label>
              <input v-model.number="form.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Addon</label>
              <select v-model="form.addon_id">
                <option :value="null">Base Game</option>
                <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
            </div>
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
  name: 'AdminChallenges',
  data() {
    return {
      challenges: [],
      addons: [],
      showModal: false,
      editing: null,
      formError: '',
      form: {
        date: '', title: '', description: '',
        criteria_type: 'play_game', criteria_mode: 'any',
        criteria_stat: 'wealth', criteria_value: 15,
        criteria_character_id: 1, reward_xp: 100, addon_id: null,
      },
    };
  },
  async mounted() { await Promise.all([this.load(), this.fetchAddons()]); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/daily-challenges');
      this.challenges = res.data;
    },
    async fetchAddons() {
      try {
        const res = await axios.get('/api/admin/addons');
        this.addons = res.data;
      } catch { /* ignore */ }
    },
    buildCriteria() {
      const t = this.form.criteria_type;
      if (t === 'play_game') return { type: t, mode: this.form.criteria_mode };
      if (t === 'win_game') return { type: t, mode: this.form.criteria_mode };
      if (t === 'stat_threshold') return { type: t, stat: this.form.criteria_stat, value: this.form.criteria_value };
      if (t === 'use_character') return { type: t, character_id: this.form.criteria_character_id };
      if (t === 'no_stat_below') return { type: t, value: this.form.criteria_value };
      return { type: t };
    },
    openCreate() {
      this.editing = null;
      this.form = {
        date: '', title: '', description: '',
        criteria_type: 'play_game', criteria_mode: 'any',
        criteria_stat: 'wealth', criteria_value: 15,
        criteria_character_id: 1, reward_xp: 100, addon_id: null,
      };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(c) {
      this.editing = c.id;
      const cr = c.criteria || {};
      this.form = {
        date: c.date,
        title: c.title,
        description: c.description,
        criteria_type: cr.type || 'play_game',
        criteria_mode: cr.mode || 'any',
        criteria_stat: cr.stat || 'wealth',
        criteria_value: cr.value || 15,
        criteria_character_id: cr.character_id || 1,
        reward_xp: c.reward_xp,
        addon_id: c.addon_id || null,
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';
      const data = {
        date: this.form.date,
        title: this.form.title,
        description: this.form.description,
        criteria: this.buildCriteria(),
        reward_xp: this.form.reward_xp,
        addon_id: this.form.addon_id || null,
      };
      try {
        if (this.editing) {
          await axios.put(`/api/admin/daily-challenges/${this.editing}`, data);
        } else {
          await axios.post('/api/admin/daily-challenges', data);
        }
        this.showModal = false;
        this.load();
      } catch (e) {
        this.formError = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    async deleteChallenge(c) {
      if (!confirm(`Delete "${c.title}"?`)) return;
      await axios.delete(`/api/admin/daily-challenges/${c.id}`);
      this.load();
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
.list-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.date-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; }
.manual-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); }
.addon-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(138, 58, 138, 0.2); color: #c080d0; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 550px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group.full { grid-column: 1 / -1; }
.form-group { margin-bottom: 0; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
