<template>
  <div>
    <h2 class="page-title">Payments &amp; Premium</h2>

    <!-- Tabs -->
    <div class="admin-tabs">
      <button class="admin-tab" :class="{ active: tab === 'settings' }" @click="tab = 'settings'">Settings</button>
      <button class="admin-tab" :class="{ active: tab === 'subscribers' }" @click="tab = 'subscribers'">Subscribers</button>
      <button class="admin-tab" :class="{ active: tab === 'purchases' }" @click="tab = 'purchases'">Purchases</button>
    </div>

    <!-- Settings Tab -->
    <div v-if="tab === 'settings'" class="tab-content">
      <div class="card-panel">
        <h3 class="sub-title">In-App Review</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Review Prompting</label>
            <select v-model="settings.app_review_enabled">
              <option :value="true">Enabled</option>
              <option :value="false">Disabled</option>
            </select>
          </div>
          <div class="form-group">
            <label>Trigger Type</label>
            <select v-model="settings.app_review_trigger.type">
              <option value="games_completed">Games Completed</option>
              <option value="level">Player Level</option>
            </select>
          </div>
          <div class="form-group">
            <label>Trigger Value</label>
            <input v-model.number="settings.app_review_trigger.value" type="number" min="1" />
          </div>
        </div>
        <button class="btn-primary" @click="saveSettings" :disabled="saving" style="margin-top: 12px;">
          {{ saving ? 'Saving...' : 'Save Settings' }}
        </button>
        <span v-if="saveMsg" class="save-msg">{{ saveMsg }}</span>
      </div>

      <div class="card-panel">
        <h3 class="sub-title">Stripe Config</h3>
        <p class="config-hint">Configured via .env: STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, STRIPE_PREMIUM_PRICE_ID</p>
        <p class="config-value" v-if="settings.premium_price_id">
          Premium Price ID: <code>{{ settings.premium_price_id }}</code>
        </p>
        <p class="config-value" v-else>
          Premium Price ID: <em>Not configured</em>
        </p>
      </div>
    </div>

    <!-- Subscribers Tab -->
    <div v-if="tab === 'subscribers'" class="tab-content">
      <div v-if="loadingSubs" class="loading">Loading subscribers...</div>
      <div v-else-if="subscribers.length === 0" class="empty">No premium subscribers.</div>
      <div v-else class="list-panel">
        <div v-for="s in subscribers" :key="s.id" class="list-row">
          <div class="list-info">
            <div class="list-top">
              <strong>{{ s.name }}</strong>
              <span class="type-badge">{{ s.platform || 'unknown' }}</span>
            </div>
            <div class="list-sub">{{ s.email }} &mdash; Expires: {{ formatDate(s.premium_expires_at) }}</div>
          </div>
          <div class="list-actions">
            <button class="btn-sm btn-danger" @click="revokePremium(s)">Revoke</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Purchases Tab -->
    <div v-if="tab === 'purchases'" class="tab-content">
      <div v-if="loadingPurchases" class="loading">Loading purchases...</div>
      <div v-else-if="purchases.length === 0" class="empty">No purchases recorded.</div>
      <div v-else class="list-panel">
        <div v-for="p in purchases" :key="p.id" class="list-row">
          <div class="list-info">
            <div class="list-top">
              <strong>{{ p.user?.name || 'User #' + p.user_id }}</strong>
              <span class="type-badge">{{ p.platform }}</span>
              <span class="method-badge" :class="'status-' + p.status">{{ p.status }}</span>
            </div>
            <div class="list-sub">
              {{ p.product_id }} &mdash;
              {{ p.type }} &mdash;
              ${{ (p.amount_cents / 100).toFixed(2) }} {{ p.currency?.toUpperCase() }} &mdash;
              {{ formatDate(p.created_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';

export default {
  name: 'AdminPayments',
  setup() { return { toast: useToast() }; },
  data() {
    return {
      tab: 'settings',
      settings: {
        premium_price_id: '',
        app_review_enabled: false,
        app_review_trigger: { type: 'games_completed', value: 3 },
      },
      saving: false,
      saveMsg: '',
      subscribers: [],
      loadingSubs: false,
      purchases: [],
      loadingPurchases: false,
    };
  },
  watch: {
    tab(val) {
      if (val === 'subscribers' && this.subscribers.length === 0) this.loadSubscribers();
      if (val === 'purchases' && this.purchases.length === 0) this.loadPurchases();
    },
  },
  async mounted() {
    this.loadSettings();
  },
  methods: {
    async loadSettings() {
      try {
        const res = await axios.get('/api/admin/payment-settings');
        this.settings = {
          premium_price_id: res.data.premium_price_id || '',
          app_review_enabled: res.data.app_review_enabled || false,
          app_review_trigger: res.data.app_review_trigger || { type: 'games_completed', value: 3 },
        };
      } catch {}
    },
    async saveSettings() {
      this.saving = true;
      this.saveMsg = '';
      try {
        await axios.put('/api/admin/payment-settings', {
          app_review_enabled: this.settings.app_review_enabled,
          app_review_trigger: this.settings.app_review_trigger,
        });
        this.saveMsg = 'Saved!';
        setTimeout(() => { this.saveMsg = ''; }, 2000);
      } catch (e) {
        this.saveMsg = 'Error: ' + (e.response?.data?.message || 'Failed');
      }
      this.saving = false;
    },
    async loadSubscribers() {
      this.loadingSubs = true;
      try {
        const res = await axios.get('/api/admin/subscribers');
        this.subscribers = res.data.subscribers;
      } catch {}
      this.loadingSubs = false;
    },
    async loadPurchases() {
      this.loadingPurchases = true;
      try {
        const res = await axios.get('/api/admin/purchases');
        this.purchases = res.data.purchases;
      } catch {}
      this.loadingPurchases = false;
    },
    async revokePremium(subscriber) {
      if (!confirm(`Revoke premium from ${subscriber.name}?`)) return;
      try {
        await axios.post(`/api/admin/users/${subscriber.id}/revoke-premium`);
        this.subscribers = this.subscribers.filter(s => s.id !== subscriber.id);
      } catch (e) {
        this.toast.error(e.response?.data?.message || 'Failed');
      }
    },
    formatDate(d) {
      if (!d) return 'N/A';
      return new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; margin-bottom: 16px; }
.sub-title { font-family: 'Cinzel', serif; color: var(--text-bright); font-size: 1rem; margin-bottom: 12px; }

.admin-tabs { display: flex; gap: 4px; margin-bottom: 16px; }
.admin-tab {
  padding: 8px 16px; border-radius: 6px; border: 1px solid rgba(138, 106, 46, 0.3);
  background: transparent; color: var(--text-secondary); cursor: pointer;
  font-family: 'Cinzel', serif; font-size: 0.8rem; transition: all 0.2s;
}
.admin-tab:hover { background: rgba(138, 106, 46, 0.1); }
.admin-tab.active { background: rgba(212, 168, 67, 0.15); border-color: var(--accent-gold); color: var(--accent-gold); }

.form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }

.config-hint { color: var(--text-secondary); font-size: 0.8rem; font-style: italic; margin-bottom: 6px; }
.config-value { color: var(--text-primary); font-size: 0.85rem; }
.config-value code { background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px; font-size: 0.82rem; }

.save-msg { margin-left: 12px; font-size: 0.85rem; color: #6abf50; }

.loading, .empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 8px; }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.type-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; text-transform: uppercase; }
.method-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; text-transform: uppercase; }
.status-completed { background: rgba(106, 191, 80, 0.15); color: #6abf50; }
.status-pending { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); }
.status-failed { background: rgba(160, 48, 32, 0.15); color: #d05040; }
.status-refunded { background: rgba(100, 100, 160, 0.15); color: #a0a0d0; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }

@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
