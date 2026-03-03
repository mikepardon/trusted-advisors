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
          <button v-if="s.is_active" class="btn-sm btn-end" @click="endSeason(s)" :disabled="endingSeasonId === s.id">
            {{ endingSeasonId === s.id ? 'Ending...' : 'End Season' }}
          </button>
          <button class="btn-sm" @click="openRewards(s)">Rewards</button>
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
    <!-- Rewards Modal -->
    <div v-if="showRewardsModal" class="modal-overlay" @click.self="showRewardsModal = false">
      <div class="modal-content rewards-modal">
        <h3>Rewards: {{ rewardsSeason?.name }}</h3>

        <template v-if="rewards.length">
          <div v-for="(group, metric) in groupedRewards" :key="metric" class="metric-group">
            <h4 class="metric-label">{{ metricLabel(metric) }}</h4>
            <table class="rewards-table">
              <thead>
                <tr>
                  <th>Place</th>
                  <th>XP</th>
                  <th>Coins</th>
                  <th>Character</th>
                  <th>Title</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in group" :key="r.id">
                  <td>{{ ordinal(r.placement) }}</td>
                  <td>{{ r.reward_xp }}</td>
                  <td>{{ r.reward_coins }}</td>
                  <td>{{ r.reward_character?.name || '-' }}</td>
                  <td>{{ r.reward_title || '-' }}</td>
                  <td>
                    <button class="btn-sm" @click="editReward(r)">Edit</button>
                    <button class="btn-sm btn-danger" @click="deleteReward(r)">Del</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <p v-else class="empty">No rewards defined yet.</p>

        <h4 class="reward-form-title">{{ editingReward ? 'Edit Reward' : 'Add Reward' }}</h4>
        <form @submit.prevent="saveReward" class="reward-form">
          <div class="reward-form-row">
            <div class="form-group">
              <label>Metric</label>
              <select v-model="rewardForm.metric">
                <option value="elo">ELO Rating</option>
                <option value="score">Highest Score</option>
                <option value="wins">Most Wins</option>
              </select>
            </div>
            <div class="form-group">
              <label>From</label>
              <input v-model.number="rewardForm.placement_from" type="number" min="1" required />
            </div>
            <div class="form-group">
              <label>To</label>
              <input v-model.number="rewardForm.placement_to" type="number" min="1" required />
            </div>
            <div class="form-group">
              <label>XP</label>
              <input v-model.number="rewardForm.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Coins</label>
              <input v-model.number="rewardForm.reward_coins" type="number" min="0" />
            </div>
          </div>
          <div class="reward-form-row">
            <div class="form-group">
              <label>Character</label>
              <select v-model="rewardForm.reward_character_id">
                <option :value="null">None</option>
                <option v-for="c in allCharacters" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Title</label>
              <input v-model="rewardForm.reward_title" type="text" placeholder="e.g. Season Champion" />
            </div>
          </div>
          <div v-if="rewardFormError" class="form-error">{{ rewardFormError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary">{{ editingReward ? 'Update' : 'Add' }}</button>
            <button v-if="editingReward" type="button" @click="resetRewardForm">Cancel Edit</button>
            <button type="button" @click="showRewardsModal = false">Close</button>
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
      // Rewards
      showRewardsModal: false,
      rewardsSeason: null,
      rewards: [],
      allCharacters: [],
      editingReward: null,
      rewardFormError: '',
      rewardForm: { metric: 'elo', placement_from: 1, placement_to: 1, reward_xp: 0, reward_coins: 0, reward_character_id: null, reward_title: '' },
      endingSeasonId: null,
    };
  },
  computed: {
    groupedRewards() {
      const groups = {};
      for (const r of this.rewards) {
        const m = r.metric || 'elo';
        if (!groups[m]) groups[m] = [];
        groups[m].push(r);
      }
      return groups;
    },
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
    async endSeason(s) {
      if (!confirm(`End season "${s.name}" and distribute rewards? This cannot be undone.`)) return;
      this.endingSeasonId = s.id;
      try {
        const res = await axios.post(`/api/admin/seasons/${s.id}/end`);
        alert(`Season ended! ${res.data.rewards_distributed} rewards distributed.`);
        this.load();
      } catch (e) {
        alert(e.response?.data?.error || 'Failed to end season');
      }
      this.endingSeasonId = null;
    },
    async deleteSeason(s) {
      if (!confirm(`Delete "${s.name}"?`)) return;
      await axios.delete(`/api/admin/seasons/${s.id}`);
      this.load();
    },
    async openRewards(s) {
      this.rewardsSeason = s;
      this.resetRewardForm();
      this.showRewardsModal = true;
      const [rewardsRes, charsRes] = await Promise.all([
        axios.get(`/api/admin/seasons/${s.id}/rewards`),
        axios.get('/api/characters'),
      ]);
      this.rewards = rewardsRes.data;
      this.allCharacters = charsRes.data;
    },
    resetRewardForm() {
      this.editingReward = null;
      this.rewardFormError = '';
      this.rewardForm = { metric: 'elo', placement_from: 1, placement_to: 1, reward_xp: 0, reward_coins: 0, reward_character_id: null, reward_title: '' };
    },
    editReward(r) {
      this.editingReward = r.id;
      this.rewardForm = {
        metric: r.metric || 'elo',
        placement_from: r.placement,
        placement_to: r.placement,
        reward_xp: r.reward_xp,
        reward_coins: r.reward_coins,
        reward_character_id: r.reward_character_id,
        reward_title: r.reward_title || '',
      };
    },
    metricLabel(m) {
      const labels = { elo: 'ELO Rating', score: 'Highest Score', wins: 'Most Wins' };
      return labels[m] || m;
    },
    async saveReward() {
      this.rewardFormError = '';
      const from = this.rewardForm.placement_from;
      const to = this.rewardForm.placement_to;
      if (to < from) {
        this.rewardFormError = '"To" must be >= "From"';
        return;
      }
      try {
        if (this.editingReward) {
          // Editing a single reward — use placement_from as the placement
          const payload = { ...this.rewardForm, placement: from };
          await axios.put(`/api/admin/seasons/${this.rewardsSeason.id}/rewards/${this.editingReward}`, payload);
        } else {
          // Create one reward per placement in the range
          for (let p = from; p <= to; p++) {
            const payload = { ...this.rewardForm, placement: p };
            await axios.post(`/api/admin/seasons/${this.rewardsSeason.id}/rewards`, payload);
          }
        }
        const res = await axios.get(`/api/admin/seasons/${this.rewardsSeason.id}/rewards`);
        this.rewards = res.data;
        this.resetRewardForm();
      } catch (e) {
        this.rewardFormError = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    async deleteReward(r) {
      if (!confirm(`Delete reward for ${this.ordinal(r.placement)} place?`)) return;
      await axios.delete(`/api/admin/seasons/${this.rewardsSeason.id}/rewards/${r.id}`);
      this.rewards = this.rewards.filter(rw => rw.id !== r.id);
    },
    ordinal(n) {
      const s = ['th', 'st', 'nd', 'rd'];
      const v = n % 100;
      return n + (s[(v - 20) % 10] || s[v] || s[0]);
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
.btn-end { background: rgba(74, 138, 58, 0.15); color: #6abf50; border-color: rgba(74, 138, 58, 0.3); }
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

/* Rewards modal */
.rewards-modal { max-width: 650px; }
.rewards-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.85rem; }
.rewards-table th { text-align: left; color: var(--accent-gold); font-family: 'Cinzel', serif; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 8px; border-bottom: 1px solid rgba(138, 106, 46, 0.3); }
.rewards-table td { padding: 6px 8px; color: var(--text-bright); border-bottom: 1px solid rgba(138, 106, 46, 0.1); }
.reward-form-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1rem; margin-bottom: 12px; }
.reward-form-row { display: flex; gap: 10px; margin-bottom: 10px; }
.reward-form-row .form-group { flex: 1; }
.metric-group { margin-bottom: 16px; }
.metric-label { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 0.9rem; margin-bottom: 6px; opacity: 0.85; }
</style>
