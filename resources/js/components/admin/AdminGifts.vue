<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Admin Gifts</h2>
      <button class="btn-primary" @click="showModal = true">Send Gift</button>
    </div>

    <!-- Status Tabs -->
    <div class="tabs">
      <button :class="{ active: statusTab === 'all' }" @click="statusTab = 'all'">All</button>
      <button :class="{ active: statusTab === 'sent' }" @click="statusTab = 'sent'">Sent</button>
      <button :class="{ active: statusTab === 'scheduled' }" @click="statusTab = 'scheduled'">Scheduled</button>
      <button :class="{ active: statusTab === 'cancelled' }" @click="statusTab = 'cancelled'">Cancelled</button>
    </div>

    <!-- Past gifts -->
    <div class="list-panel">
      <div v-for="g in filteredGifts" :key="g.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ g.title }}</strong>
            <span :class="'status-badge status-' + (g.status || 'sent')">{{ g.status || 'sent' }}</span>
            <span class="date-badge">{{ formatDate(g.created_at) }}</span>
          </div>
          <div class="list-sub">
            {{ g.note || 'No note' }} &mdash;
            <span v-if="g.reward_xp">{{ g.reward_xp }} XP</span>
            <span v-if="g.reward_coins"> {{ g.reward_coins }} coins</span>
            <span v-if="g.reward_character"> {{ g.reward_character?.name }}</span>
            <span v-if="g.reward_dice_theme"> {{ g.reward_dice_theme?.name }} (dice)</span>
            <span v-if="g.reward_kingdom_style"> {{ g.reward_kingdom_style?.name }} (kingdom)</span>
            &mdash;
            <span v-if="g.target_type && g.target_type !== 'all'">Target: {{ g.target_type }} &mdash; </span>
            <span v-if="g.status !== 'scheduled'">Sent to {{ g.recipient_count }} users</span>
            <span v-else>Scheduled for {{ formatDate(g.scheduled_at) }}</span>
            <span v-if="g.creator"> by {{ g.creator.name }}</span>
          </div>
        </div>
        <div v-if="g.status === 'scheduled'" class="list-actions">
          <button class="btn-danger-sm" @click="cancelGift(g)" :disabled="cancelling === g.id">Cancel</button>
        </div>
      </div>
      <div v-if="filteredGifts.length === 0" class="empty">No gifts found.</div>
    </div>

    <!-- Send Gift Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>Send Gift</h3>
        <form @submit.prevent="confirmSend">
          <div class="form-group">
            <label>Title</label>
            <input v-model="form.title" required placeholder="e.g. Holiday Gift" />
          </div>
          <div class="form-group">
            <label>Note</label>
            <textarea v-model="form.note" rows="2" placeholder="Optional message"></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Reward XP</label>
              <input v-model.number="form.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Reward Coins</label>
              <input v-model.number="form.reward_coins" type="number" min="0" />
            </div>
          </div>
          <div class="form-group">
            <label>Reward Character</label>
            <select v-model="form.reward_character_id">
              <option :value="null">None</option>
              <option v-for="c in characters" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Reward Dice Theme</label>
            <select v-model="form.reward_dice_theme_id">
              <option :value="null">None</option>
              <option v-for="d in diceThemes" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Reward Kingdom Style</label>
            <select v-model="form.reward_kingdom_style_id">
              <option :value="null">None</option>
              <option v-for="ks in kingdomStyles" :key="ks.id" :value="ks.id">{{ ks.name }}</option>
            </select>
          </div>

          <!-- Targeting -->
          <div class="form-divider">Targeting</div>
          <div class="form-group">
            <label>Target</label>
            <select v-model="form.target_type" @change="onTargetTypeChange">
              <option value="all">All Users</option>
              <option value="specific_users">Specific Users</option>
              <option value="segment">User Segment</option>
            </select>
          </div>

          <!-- Specific Users -->
          <div v-if="form.target_type === 'specific_users'" class="form-group">
            <label>User Search</label>
            <input v-model="userSearch" @input="searchUsers" placeholder="Search by name or email..." />
            <div v-if="userSearchResults.length" class="user-results">
              <div v-for="u in userSearchResults" :key="u.id" class="user-result" @click="addUser(u)">
                {{ u.name }} ({{ u.email }})
              </div>
            </div>
            <div class="chip-list">
              <span v-for="uid in form.target_user_ids" :key="uid" class="chip">
                {{ selectedUserNames[uid] || 'User #' + uid }}
                <button type="button" @click="removeUser(uid)">&times;</button>
              </span>
            </div>
          </div>

          <!-- Segment Criteria -->
          <div v-if="form.target_type === 'segment'" class="segment-fields">
            <div class="form-grid">
              <div class="form-group">
                <label>Min Level</label>
                <input v-model.number="form.target_criteria.level_min" type="number" min="1" />
              </div>
              <div class="form-group">
                <label>Max Level</label>
                <input v-model.number="form.target_criteria.level_max" type="number" min="1" />
              </div>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Min ELO</label>
                <input v-model.number="form.target_criteria.elo_min" type="number" />
              </div>
              <div class="form-group">
                <label>Max ELO</label>
                <input v-model.number="form.target_criteria.elo_max" type="number" />
              </div>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Joined After</label>
                <input v-model="form.target_criteria.joined_after" type="date" />
              </div>
              <div class="form-group">
                <label>Joined Before</label>
                <input v-model="form.target_criteria.joined_before" type="date" />
              </div>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Min Games Played</label>
                <input v-model.number="form.target_criteria.min_games" type="number" min="0" />
              </div>
              <div class="form-group">
                <label>Inactive Days</label>
                <input v-model.number="form.target_criteria.inactive_days" type="number" min="0" />
              </div>
            </div>
            <div class="form-group">
              <label>
                <input type="checkbox" v-model="form.target_criteria.is_premium" :true-value="true" :false-value="undefined" />
                Premium Only
              </label>
            </div>
          </div>

          <!-- Preview Count -->
          <div v-if="form.target_type !== 'all'" class="form-group">
            <button type="button" class="btn-secondary" @click="previewCount" :disabled="previewing">
              {{ previewing ? 'Counting...' : 'Preview Recipient Count' }}
            </button>
            <span v-if="previewResult !== null" class="preview-count">{{ previewResult }} users</span>
          </div>

          <!-- Scheduling -->
          <div class="form-divider">Scheduling</div>
          <div class="form-group">
            <label>Schedule For (leave empty to send now)</label>
            <input v-model="form.scheduled_at" type="datetime-local" />
          </div>

          <div v-if="formError" class="form-error">{{ formError }}</div>

          <!-- Confirmation step -->
          <div v-if="showConfirm" class="confirm-box">
            <p>{{ form.scheduled_at ? 'Schedule this gift?' : 'Send this gift now?' }}</p>
            <div class="modal-actions">
              <button type="button" class="btn-primary" :disabled="sending" @click="send">
                {{ sending ? 'Processing...' : (form.scheduled_at ? 'Confirm Schedule' : 'Confirm Send') }}
              </button>
              <button type="button" @click="showConfirm = false">Back</button>
            </div>
          </div>
          <div v-else class="modal-actions">
            <button type="submit" class="btn-primary">Review & Send</button>
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
  name: 'AdminGifts',
  data() {
    return {
      gifts: [],
      characters: [],
      diceThemes: [],
      kingdomStyles: [],
      showModal: false,
      showConfirm: false,
      sending: false,
      cancelling: null,
      formError: '',
      statusTab: 'all',
      userSearch: '',
      userSearchResults: [],
      selectedUserNames: {},
      previewResult: null,
      previewing: false,
      searchTimer: null,
      form: this.emptyForm(),
    };
  },
  computed: {
    filteredGifts() {
      if (this.statusTab === 'all') return this.gifts;
      return this.gifts.filter(g => (g.status || 'sent') === this.statusTab);
    },
  },
  async mounted() {
    await Promise.all([this.load(), this.fetchCharacters(), this.fetchDiceThemes(), this.fetchKingdomStyles()]);
  },
  methods: {
    emptyForm() {
      return {
        title: '', note: '', reward_xp: 0, reward_coins: 0,
        reward_character_id: null, reward_dice_theme_id: null, reward_kingdom_style_id: null,
        target_type: 'all', target_user_ids: [], target_criteria: {},
        scheduled_at: '',
      };
    },
    async load() {
      const res = await axios.get('/api/admin/gifts');
      this.gifts = res.data;
    },
    async fetchCharacters() {
      try { this.characters = (await axios.get('/api/characters')).data; } catch {}
    },
    async fetchDiceThemes() {
      try { this.diceThemes = (await axios.get('/api/admin/dice-themes')).data; } catch {}
    },
    async fetchKingdomStyles() {
      try { this.kingdomStyles = (await axios.get('/api/admin/kingdom-styles')).data; } catch {}
    },
    onTargetTypeChange() {
      this.previewResult = null;
      if (this.form.target_type === 'segment') {
        this.form.target_criteria = {};
      }
    },
    searchUsers() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(async () => {
        if (!this.userSearch || this.userSearch.length < 2) {
          this.userSearchResults = [];
          return;
        }
        try {
          const res = await axios.get('/api/admin/users', { params: { search: this.userSearch } });
          this.userSearchResults = (res.data.data || []).slice(0, 10);
        } catch {}
      }, 300);
    },
    addUser(user) {
      if (!this.form.target_user_ids.includes(user.id)) {
        this.form.target_user_ids.push(user.id);
        this.selectedUserNames[user.id] = user.name;
      }
      this.userSearch = '';
      this.userSearchResults = [];
    },
    removeUser(uid) {
      this.form.target_user_ids = this.form.target_user_ids.filter(id => id !== uid);
    },
    async previewCount() {
      this.previewing = true;
      try {
        const payload = {
          target_type: this.form.target_type,
          target_user_ids: this.form.target_user_ids,
          target_criteria: this.cleanCriteria(),
        };
        const res = await axios.post('/api/admin/gifts/preview-count', payload);
        this.previewResult = res.data.count;
      } catch (e) {
        this.previewResult = null;
      }
      this.previewing = false;
    },
    cleanCriteria() {
      const c = { ...this.form.target_criteria };
      Object.keys(c).forEach(k => {
        if (c[k] === undefined || c[k] === null || c[k] === '') delete c[k];
      });
      return c;
    },
    confirmSend() {
      this.formError = '';
      if (!this.form.title) {
        this.formError = 'Title is required.';
        return;
      }
      if (this.form.reward_xp === 0 && this.form.reward_coins === 0 && !this.form.reward_character_id && !this.form.reward_dice_theme_id && !this.form.reward_kingdom_style_id) {
        this.formError = 'At least one reward must be specified.';
        return;
      }
      if (this.form.target_type === 'specific_users' && this.form.target_user_ids.length === 0) {
        this.formError = 'Select at least one user.';
        return;
      }
      this.showConfirm = true;
    },
    async send() {
      this.sending = true;
      this.formError = '';
      try {
        const payload = {
          ...this.form,
          target_criteria: this.cleanCriteria(),
          scheduled_at: this.form.scheduled_at || undefined,
        };
        await axios.post('/api/admin/gifts', payload);
        this.showModal = false;
        this.showConfirm = false;
        this.form = this.emptyForm();
        this.previewResult = null;
        this.load();
      } catch (e) {
        this.formError = e.response?.data?.error || 'Failed to send gift';
      }
      this.sending = false;
    },
    async cancelGift(gift) {
      this.cancelling = gift.id;
      try {
        await axios.post(`/api/admin/gifts/${gift.id}/cancel`);
        gift.status = 'cancelled';
      } catch {}
      this.cancelling = null;
    },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }

.tabs { display: flex; gap: 4px; margin-bottom: 16px; }
.tabs button { background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); color: var(--text-secondary); padding: 5px 14px; border-radius: 4px; cursor: pointer; font-family: inherit; font-size: 0.85rem; }
.tabs button.active { color: var(--accent-gold); border-color: var(--accent-gold); background: rgba(212, 168, 67, 0.1); }

.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.date-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

.status-badge { font-size: 0.65rem; padding: 1px 8px; border-radius: 3px; font-weight: 600; text-transform: uppercase; }
.status-sent { background: rgba(46, 204, 113, 0.15); color: #2ecc71; }
.status-scheduled { background: rgba(52, 152, 219, 0.15); color: #3498db; }
.status-cancelled { background: rgba(149, 165, 166, 0.15); color: #95a5a6; }
.status-sending { background: rgba(241, 196, 15, 0.15); color: #f1c40f; }

.btn-danger-sm { background: rgba(231, 76, 60, 0.15); border: 1px solid rgba(231, 76, 60, 0.3); color: #e74c3c; padding: 3px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger-sm:hover { background: rgba(231, 76, 60, 0.25); }
.btn-danger-sm:disabled { opacity: 0.5; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 560px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-gold); }
.form-group input[type="checkbox"] { width: auto; margin-right: 6px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-error { color: var(--accent-red, #e74c3c); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.confirm-box { margin-top: 16px; padding: 14px; border: 1px solid rgba(212, 168, 67, 0.3); border-radius: 6px; background: rgba(212, 168, 67, 0.05); }
.confirm-box p { margin-bottom: 10px; color: var(--text-bright); font-size: 0.95rem; }

.form-divider { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin: 18px 0 10px; padding-bottom: 4px; border-bottom: 1px solid rgba(138, 106, 46, 0.2); }

.user-results { background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); border-radius: 4px; max-height: 120px; overflow-y: auto; margin-top: 4px; }
.user-result { padding: 5px 10px; cursor: pointer; font-size: 0.85rem; color: var(--text-bright); }
.user-result:hover { background: rgba(212, 168, 67, 0.1); }

.chip-list { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.chip { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); padding: 2px 8px; border-radius: 12px; font-size: 0.78rem; display: flex; align-items: center; gap: 4px; }
.chip button { background: none; border: none; color: var(--accent-gold); cursor: pointer; font-size: 1rem; padding: 0; line-height: 1; }

.btn-secondary { background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 5px 14px; border-radius: 4px; cursor: pointer; font-family: inherit; }
.btn-secondary:disabled { opacity: 0.5; }
.preview-count { margin-left: 10px; color: var(--text-bright); font-weight: 600; }
</style>
