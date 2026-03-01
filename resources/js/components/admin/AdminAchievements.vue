<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Achievements</h2>
      <button class="btn-primary" @click="openCreate">+ New Achievement</button>
    </div>

    <!-- Search -->
    <AdminSearchInput v-model="searchQuery" />

    <!-- List -->
    <div class="list-panel">
      <div v-for="a in filteredAchievements" :key="a.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ a.name }}</strong>
            <span class="cat-badge">{{ a.category }}</span>
            <span v-if="a.tier_group" class="tier-badge">{{ a.tier_group }} T{{ a.tier }}</span>
            <span v-if="a.reward_xp" class="xp-badge">+{{ a.reward_xp }} XP</span>
          </div>
          <div class="list-desc">{{ a.description }}</div>
          <div v-if="a.linked_unlockables && a.linked_unlockables.length" class="unlockables-row">
            <span class="unlockables-label">Unlocks:</span>
            <span v-for="u in a.linked_unlockables" :key="u.id" class="unlock-badge">
              {{ u.type }} &mdash; {{ u.entity_name }}
            </span>
          </div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(a)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteAch(a)">Del</button>
        </div>
      </div>
      <div v-if="achievements.length === 0" class="empty">No achievements yet. Run the seeder!</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Achievement' : 'New Achievement' }}</h3>
        <form @submit.prevent="save">
          <div class="form-grid">
            <div class="form-group">
              <label>Key</label>
              <input v-model="form.key" required placeholder="unique_key" />
            </div>
            <div class="form-group">
              <label>Name</label>
              <input v-model="form.name" required />
            </div>
            <div class="form-group full">
              <label>Description</label>
              <input v-model="form.description" required />
            </div>
            <div class="form-group">
              <label>Icon</label>
              <input v-model="form.icon" placeholder="trophy" />
            </div>
            <div class="form-group">
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
            <div class="form-group">
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
            <div class="form-group">
              <label>{{ criteriaUsesValue ? 'Value' : 'Count' }}</label>
              <input v-model.number="form.criteria_amount" type="number" min="1" required />
            </div>
            <div class="form-group">
              <label>Tier Group</label>
              <input v-model="form.tier_group" placeholder="e.g. wins_chain" />
            </div>
            <div class="form-group">
              <label>Tier</label>
              <input v-model.number="form.tier" type="number" min="1" />
            </div>
            <div class="form-group">
              <label>Claim Reward XP</label>
              <input v-model.number="form.reward_xp" type="number" min="0" placeholder="0" />
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
import AdminSearchInput from './AdminSearchInput.vue';

export default {
  name: 'AdminAchievements',
  components: { AdminSearchInput },
  data() {
    return {
      achievements: [],
      searchQuery: '',
      showModal: false,
      editing: null,
      formError: '',
      form: {
        key: '', name: '', description: '', icon: '', category: 'general',
        criteria_type: 'total_wins', criteria_amount: 1,
        tier_group: '', tier: 1, reward_xp: 0,
      },
    };
  },
  computed: {
    criteriaUsesValue() {
      return ['elo_reached', 'level_reached'].includes(this.form.criteria_type);
    },
    filteredAchievements() {
      const q = this.searchQuery.toLowerCase().trim();
      if (!q) return this.achievements;
      return this.achievements.filter(a =>
        a.name.toLowerCase().includes(q) ||
        a.key.toLowerCase().includes(q) ||
        (a.description || '').toLowerCase().includes(q) ||
        (a.category || '').toLowerCase().includes(q) ||
        (a.tier_group || '').toLowerCase().includes(q)
      );
    },
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/achievements');
      this.achievements = res.data;
    },
    openCreate() {
      this.editing = null;
      this.form = {
        key: '', name: '', description: '', icon: '', category: 'general',
        criteria_type: 'total_wins', criteria_amount: 1,
        tier_group: '', tier: 1, reward_xp: 0,
      };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(a) {
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
        tier_group: a.tier_group || '',
        tier: a.tier || 1,
        reward_xp: a.reward_xp || 0,
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';
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
        reward_xp: this.form.reward_xp || 0,
        tier: this.form.tier || 1,
        tier_group: this.form.tier_group || null,
      };

      try {
        if (this.editing) {
          await axios.put(`/api/admin/achievements/${this.editing}`, data);
        } else {
          await axios.post('/api/admin/achievements', data);
        }
        this.showModal = false;
        this.load();
      } catch (e) {
        this.formError = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    async deleteAch(a) {
      if (!confirm(`Delete "${a.name}"?`)) return;
      await axios.delete(`/api/admin/achievements/${a.id}`);
      this.load();
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: flex-start; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.list-info strong { color: var(--accent-gold); }
.list-desc { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.cat-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; text-transform: uppercase; }
.tier-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); }
.xp-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(74, 138, 58, 0.2); color: #6abf50; }
.unlockables-row { margin-top: 4px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.unlockables-label { font-size: 0.75rem; color: var(--text-secondary); }
.unlock-badge { font-size: 0.72rem; padding: 2px 8px; border-radius: 3px; background: rgba(212, 168, 67, 0.1); border: 1px solid rgba(138, 106, 46, 0.2); color: var(--text-bright); }
.list-actions { display: flex; gap: 4px; flex-shrink: 0; }
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
