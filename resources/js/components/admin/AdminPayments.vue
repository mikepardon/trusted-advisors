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
        <h3 class="sub-title">Payment System</h3>
        <div class="toggle-row">
          <label class="toggle-label">Payments &amp; Premium</label>
          <button
            class="toggle-btn"
            :class="settings.payments_enabled ? 'toggle-on' : 'toggle-off'"
            @click="togglePayments"
          >
            {{ settings.payments_enabled ? 'ON' : 'OFF' }}
          </button>
          <span class="toggle-hint">{{ settings.payments_enabled ? 'Premium features, shop upsells, and payment options are visible to users.' : 'All payment-related UI is hidden from users.' }}</span>
        </div>
      </div>

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
        <button class="btn-primary" :disabled="saving" style="margin-top: 12px;" @click="saveSettings">
          {{ saving ? 'Saving...' : 'Save Settings' }}
        </button>
        <span v-if="saveMessage" class="save-msg">{{ saveMessage }}</span>
      </div>

      <div class="card-panel">
        <h3 class="sub-title">Stripe Config</h3>
        <p class="config-hint">Configured via .env: STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, STRIPE_PREMIUM_PRICE_ID</p>
        <p v-if="settings.premium_price_id" class="config-value">
          Premium Price ID: <code>{{ settings.premium_price_id }}</code>
        </p>
        <p v-else class="config-value">
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

<script setup lang="ts">
import axios, { isAxiosError } from "axios";
import { onMounted, reactive, ref, watch } from "vue";
import { useToast } from "../../stores/toast";

interface AppReviewTrigger {
  type: string;
  value: number;
}

interface PaymentSettings {
  payments_enabled: boolean;
  premium_price_id: string;
  app_review_enabled: boolean;
  app_review_trigger: AppReviewTrigger;
}

interface Subscriber {
  id: number;
  name: string;
  email: string;
  platform?: string;
  premium_expires_at?: string;
}

interface PurchaseUser {
  name?: string;
}

interface Purchase {
  id: number;
  user?: PurchaseUser;
  user_id: number;
  platform: string;
  status: string;
  product_id: string;
  type: string;
  amount_cents: number;
  currency?: string;
  created_at?: string;
}

const toast = useToast();

const tab = ref("settings");
const settings = reactive<PaymentSettings>({
  payments_enabled: true,
  premium_price_id: "",
  app_review_enabled: false,
  app_review_trigger: { type: "games_completed", value: 3 },
});
const saving = ref(false);
const saveMessage = ref("");
const subscribers = ref<Subscriber[]>([]);
const loadingSubs = ref(false);
const purchases = ref<Purchase[]>([]);
const loadingPurchases = ref(false);

async function loadSettings(): Promise<void> {
  try {
    const response = await axios.get<Partial<PaymentSettings>>("/api/admin/payment-settings");
    settings.payments_enabled = response.data.payments_enabled ?? true;
    settings.premium_price_id = response.data.premium_price_id || "";
    settings.app_review_enabled = response.data.app_review_enabled || false;
    settings.app_review_trigger = response.data.app_review_trigger || { type: "games_completed", value: 3 };
  } catch {
    // ignore load errors
  }
}

async function togglePayments(): Promise<void> {
  settings.payments_enabled = !settings.payments_enabled;
  try {
    await axios.put("/api/admin/payment-settings", {
      payments_enabled: settings.payments_enabled,
    });
    toast.success(settings.payments_enabled ? "Payments enabled" : "Payments disabled");
  } catch {
    settings.payments_enabled = !settings.payments_enabled;
    toast.error("Failed to update");
  }
}

async function saveSettings(): Promise<void> {
  saving.value = true;
  saveMessage.value = "";
  try {
    await axios.put("/api/admin/payment-settings", {
      app_review_enabled: settings.app_review_enabled,
      app_review_trigger: settings.app_review_trigger,
    });
    saveMessage.value = "Saved!";
    setTimeout(() => {
      saveMessage.value = "";
    }, 2000);
  } catch (error) {
    const message = isAxiosError<{ message?: string }>(error) ? error.response?.data?.message : undefined;
    saveMessage.value = `Error: ${message || "Failed"}`;
  }
  saving.value = false;
}

async function loadSubscribers(): Promise<void> {
  loadingSubs.value = true;
  try {
    const response = await axios.get<{ subscribers: Subscriber[] }>("/api/admin/subscribers");
    subscribers.value = response.data.subscribers;
  } catch {
    // ignore load errors
  }
  loadingSubs.value = false;
}

async function loadPurchases(): Promise<void> {
  loadingPurchases.value = true;
  try {
    const response = await axios.get<{ purchases: Purchase[] }>("/api/admin/purchases");
    purchases.value = response.data.purchases;
  } catch {
    // ignore load errors
  }
  loadingPurchases.value = false;
}

async function revokePremium(subscriber: Subscriber): Promise<void> {
  if (!confirm(`Revoke premium from ${subscriber.name}?`)) {
    return;
  }
  try {
    await axios.post(`/api/admin/users/${subscriber.id}/revoke-premium`);
    subscribers.value = subscribers.value.filter((s) => s.id !== subscriber.id);
  } catch (error) {
    const message = isAxiosError<{ message?: string }>(error) ? error.response?.data?.message : undefined;
    toast.error(message || "Failed");
  }
}

function formatDate(d: string | undefined): string {
  if (!d) {
    return "N/A";
  }
  return new Date(d).toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
}

watch(tab, (value) => {
  if (value === "subscribers" && subscribers.value.length === 0) {
    loadSubscribers();
  }
  if (value === "purchases" && purchases.value.length === 0) {
    loadPurchases();
  }
});

onMounted(() => {
  loadSettings();
});
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

.toggle-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.toggle-label { color: var(--text-primary); font-size: 0.9rem; font-weight: 600; min-width: 140px; }
.toggle-btn {
  padding: 6px 18px; border-radius: 20px; border: 1px solid; cursor: pointer;
  font-family: 'Cinzel', serif; font-size: 0.8rem; font-weight: 700; letter-spacing: 1px;
  transition: all 0.2s;
}
.toggle-on { background: rgba(76, 175, 80, 0.15); border-color: rgba(76, 175, 80, 0.4); color: #4caf50; }
.toggle-off { background: rgba(231, 76, 60, 0.15); border-color: rgba(231, 76, 60, 0.4); color: #e74c3c; }
.toggle-hint { color: var(--text-secondary); font-size: 0.78rem; font-style: italic; }

@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
