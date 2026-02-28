<template>
  <div>
    <h2 class="page-title">Daily Challenges</h2>

    <!-- Form -->
    <div class="form-panel">
      <h3>{{ editing ? 'Edit Challenge' : 'New Challenge' }}</h3>
      <form @submit.prevent="save">
        <div class="form-grid">
          <div class="form-row">
            <label>Date</label>
            <input v-model="form.date" type="date" required />
          </div>
          <div class="form-row">
            <label>Title</label>
            <input v-model="form.title" required />
          </div>
          <div class="form-row full">
            <label>Description</label>
            <input v-model="form.description" required />
          </div>
          <div class="form-row">
            <label>Criteria Type</label>
            <select v-model="form.criteria_type">
              <option value="play_game">Play Game</option>
              <option value="win_game">Win Game</option>
              <option value="stat_threshold">Stat Threshold</option>
              <option value="use_character">Use Character</option>
              <option value="no_stat_below">No Stat Below</option>
            </select>
          </div>
          <div class="form-row" v-if="form.criteria_type === 'play_game' || form.criteria_type === 'win_game'">
            <label>Mode</label>
            <select v-model="form.criteria_mode">
              <option value="any">Any</option>
              <option value="single">Solo</option>
              <option value="pass_and_play">Local</option>
              <option value="online">Online</option>
            </select>
          </div>
          <div class="form-row" v-if="form.criteria_type === 'stat_threshold'">
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
          <div class="form-row" v-if="['stat_threshold', 'no_stat_below'].includes(form.criteria_type)">
            <label>Value</label>
            <input v-model.number="form.criteria_value" type="number" min="1" max="20" />
          </div>
          <div class="form-row" v-if="form.criteria_type === 'use_character'">
            <label>Character ID</label>
            <input v-model.number="form.criteria_character_id" type="number" min="1" />
          </div>
          <div class="form-row">
            <label>Reward XP</label>
            <input v-model.number="form.reward_xp" type="number" min="0" />
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
      <div v-for="c in challenges" :key="c.id" class="list-row">
        <div class="list-info">
          <strong>{{ c.title }}</strong>
          <span class="date-badge">{{ c.date }}</span>
          <span v-if="c.is_manual" class="manual-badge">Manual</span>
          <div class="list-sub">{{ c.description }} (+{{ c.reward_xp }} XP)</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="editChallenge(c)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteChallenge(c)">Del</button>
        </div>
      </div>
      <div v-if="challenges.length === 0" class="empty">No challenges yet.</div>
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
      form: {
        date: '', title: '', description: '',
        criteria_type: 'play_game', criteria_mode: 'any',
        criteria_stat: 'wealth', criteria_value: 15,
        criteria_character_id: 1, reward_xp: 100,
      },
      editing: null,
      error: '',
    };
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/daily-challenges');
      this.challenges = res.data;
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
    async save() {
      this.error = '';
      const data = {
        date: this.form.date,
        title: this.form.title,
        description: this.form.description,
        criteria: this.buildCriteria(),
        reward_xp: this.form.reward_xp,
      };
      try {
        if (this.editing) {
          await axios.put(`/api/admin/daily-challenges/${this.editing}`, data);
        } else {
          await axios.post('/api/admin/daily-challenges', data);
        }
        this.resetForm();
        this.load();
      } catch (e) {
        this.error = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    editChallenge(c) {
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
      };
    },
    async deleteChallenge(c) {
      if (!confirm(`Delete "${c.title}"?`)) return;
      await axios.delete(`/api/admin/daily-challenges/${c.id}`);
      this.load();
    },
    resetForm() {
      this.editing = null;
      this.form = {
        date: '', title: '', description: '',
        criteria_type: 'play_game', criteria_mode: 'any',
        criteria_stat: 'wealth', criteria_value: 15,
        criteria_character_id: 1, reward_xp: 100,
      };
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
.form-row.full { grid-column: 1 / -1; }
.form-row label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-row input, .form-row select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.btn-row { display: flex; gap: 8px; margin-top: 14px; }
.error { color: var(--accent-red); font-size: 0.85rem; margin: 8px 0; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.date-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; margin-left: 6px; }
.manual-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); margin-left: 4px; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
