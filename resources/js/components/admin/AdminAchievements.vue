<template>
  <div>
    <h2 class="page-title">Achievements</h2>

    <!-- Form -->
    <div class="form-panel">
      <h3>{{ editing ? 'Edit Achievement' : 'New Achievement' }}</h3>
      <form @submit.prevent="save">
        <div class="form-grid">
          <div class="form-row">
            <label>Key</label>
            <input v-model="form.key" required placeholder="unique_key" />
          </div>
          <div class="form-row">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-row full">
            <label>Description</label>
            <input v-model="form.description" required />
          </div>
          <div class="form-row">
            <label>Icon</label>
            <input v-model="form.icon" placeholder="trophy" />
          </div>
          <div class="form-row">
            <label>Category</label>
            <select v-model="form.category">
              <option value="milestone">Milestone</option>
              <option value="streak">Streak</option>
              <option value="excellence">Excellence</option>
              <option value="duel">Duel</option>
              <option value="elo">ELO</option>
              <option value="progression">Progression</option>
              <option value="exploration">Exploration</option>
              <option value="general">General</option>
            </select>
          </div>
          <div class="form-row">
            <label>Criteria Type</label>
            <select v-model="form.criteria_type">
              <option value="total_wins">Total Wins</option>
              <option value="win_streak">Win Streak</option>
              <option value="games_played">Games Played</option>
              <option value="duel_wins">Duel Wins</option>
              <option value="perfect_stats">Perfect Stats</option>
              <option value="elo_reached">ELO Reached</option>
              <option value="level_reached">Level Reached</option>
              <option value="unique_characters">Unique Characters</option>
            </select>
          </div>
          <div class="form-row">
            <label>{{ criteriaUsesValue ? 'Value' : 'Count' }}</label>
            <input v-model.number="form.criteria_amount" type="number" min="1" required />
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
      <div v-for="a in achievements" :key="a.id" class="list-row">
        <div class="list-info">
          <strong>{{ a.name }}</strong> <span class="cat-badge">{{ a.category }}</span>
          <div class="list-desc">{{ a.description }}</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="editAch(a)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteAch(a)">Del</button>
        </div>
      </div>
      <div v-if="achievements.length === 0" class="empty">No achievements yet. Run the seeder!</div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminAchievements',
  data() {
    return {
      achievements: [],
      form: { key: '', name: '', description: '', icon: '', category: 'general', criteria_type: 'total_wins', criteria_amount: 1 },
      editing: null,
      error: '',
    };
  },
  computed: {
    criteriaUsesValue() {
      return ['elo_reached', 'level_reached'].includes(this.form.criteria_type);
    },
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/achievements');
      this.achievements = res.data;
    },
    async save() {
      this.error = '';
      const criteria = this.criteriaUsesValue
        ? { type: this.form.criteria_type, value: this.form.criteria_amount }
        : { type: this.form.criteria_type, count: this.form.criteria_amount };

      const data = {
        key: this.form.key,
        name: this.form.name,
        description: this.form.description,
        icon: this.form.icon || null,
        category: this.form.category,
        criteria,
      };

      try {
        if (this.editing) {
          await axios.put(`/api/admin/achievements/${this.editing}`, data);
        } else {
          await axios.post('/api/admin/achievements', data);
        }
        this.resetForm();
        this.load();
      } catch (e) {
        this.error = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    editAch(a) {
      this.editing = a.id;
      const c = a.criteria || {};
      this.form = {
        key: a.key,
        name: a.name,
        description: a.description,
        icon: a.icon || '',
        category: a.category,
        criteria_type: c.type || 'total_wins',
        criteria_amount: c.count || c.value || 1,
      };
    },
    async deleteAch(a) {
      if (!confirm(`Delete "${a.name}"?`)) return;
      await axios.delete(`/api/admin/achievements/${a.id}`);
      this.load();
    },
    resetForm() {
      this.editing = null;
      this.form = { key: '', name: '', description: '', icon: '', category: 'general', criteria_type: 'total_wins', criteria_amount: 1 };
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
.form-row { margin-bottom: 0; }
.form-row label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-row input, .form-row select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-row input:focus, .form-row select:focus { outline: none; border-color: var(--accent-gold); }
.btn-row { display: flex; gap: 8px; margin-top: 14px; }
.error { color: var(--accent-red); font-size: 0.85rem; margin: 8px 0; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-desc { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.cat-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; margin-left: 6px; text-transform: uppercase; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
