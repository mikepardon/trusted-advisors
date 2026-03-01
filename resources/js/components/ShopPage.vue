<template>
  <div class="shop-page">
    <h2 class="section-title">Shop</h2>

    <HintBubble hint-id="shop-coins">
      Earn coins by completing games and claiming achievements. Spend them here on cosmetics and unlocks!
    </HintBubble>

    <div class="coin-balance">
      &#129689; {{ coins }}
    </div>

    <div v-if="loading" class="shop-loading">Loading...</div>

    <div v-else-if="items.length === 0" class="shop-empty">
      No items available in the shop right now.
    </div>

    <div v-else class="shop-grid">
      <div
        v-for="item in items"
        :key="item.id"
        class="shop-card"
        :class="{ owned: item.owned }"
      >
        <div class="shop-card-image">
          <img v-if="item.image_url" :src="item.image_url" :alt="item.name" />
          <div v-else class="shop-card-placeholder">
            {{ item.type === 'character' ? '\u{1F9D9}' : '\u{1F4E6}' }}
          </div>
        </div>
        <div class="shop-card-info">
          <div class="shop-card-type">{{ item.type }}</div>
          <h3 class="shop-card-name">{{ item.name }}</h3>
          <p v-if="item.description" class="shop-card-desc">{{ item.description }}</p>
        </div>
        <div class="shop-card-footer">
          <div v-if="item.owned" class="owned-badge">Owned</div>
          <button
            v-else
            class="buy-btn"
            :disabled="coins < item.price || purchasing === item.id"
            @click="confirmPurchase(item)"
          >
            <span v-if="purchasing === item.id">...</span>
            <span v-else>&#129689; {{ item.price }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Transaction History Toggle -->
    <div v-if="!loading" class="history-section">
      <button class="history-toggle" @click="toggleHistory">
        {{ showHistory ? 'Hide' : 'Show' }} Transaction History
      </button>
      <div v-if="showHistory" class="history-list">
        <div v-if="loadingHistory" class="shop-loading">Loading...</div>
        <div v-else-if="transactions.length === 0" class="shop-empty">No transactions yet.</div>
        <div v-else>
          <div
            v-for="tx in transactions"
            :key="tx.id"
            class="tx-row"
            :class="{ earn: tx.type === 'earn', spend: tx.type === 'spend' }"
          >
            <div class="tx-info">
              <span class="tx-desc">{{ tx.description }}</span>
              <span class="tx-date">{{ formatDate(tx.created_at) }}</span>
            </div>
            <div class="tx-amount">
              {{ tx.amount > 0 ? '+' : '' }}{{ tx.amount }} &#129689;
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="confirmItem" class="modal-overlay" @click.self="confirmItem = null">
      <div class="modal-box">
        <h3>Confirm Purchase</h3>
        <p>
          Buy <strong>{{ confirmItem.name }}</strong> for
          <strong>&#129689; {{ confirmItem.price }}</strong>?
        </p>
        <div class="modal-actions">
          <button class="modal-cancel" @click="confirmItem = null">Cancel</button>
          <button class="modal-confirm" @click="doPurchase(confirmItem)">Buy</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import HintBubble from './HintBubble.vue';
import { useAuth } from '../stores/auth';

export default {
  name: 'ShopPage',
  components: { HintBubble },
  data() {
    return {
      items: [],
      coins: 0,
      loading: true,
      purchasing: null,
      confirmItem: null,
      showHistory: false,
      loadingHistory: false,
      transactions: [],
    };
  },
  async mounted() {
    await this.fetchShop();
  },
  methods: {
    async fetchShop() {
      this.loading = true;
      try {
        const res = await axios.get('/api/shop');
        this.items = res.data.items;
        this.coins = res.data.coins;
      } catch {}
      this.loading = false;
    },
    confirmPurchase(item) {
      this.confirmItem = item;
    },
    async doPurchase(item) {
      this.confirmItem = null;
      this.purchasing = item.id;
      try {
        const res = await axios.post(`/api/shop/${item.id}/purchase`);
        item.owned = true;
        this.coins = res.data.new_coins;
        const { updateUserStats } = useAuth();
        updateUserStats({ coins: this.coins });
      } catch (e) {
        const msg = e.response?.data?.error || 'Purchase failed.';
        alert(msg);
      }
      this.purchasing = null;
    },
    async toggleHistory() {
      this.showHistory = !this.showHistory;
      if (this.showHistory && this.transactions.length === 0) {
        this.loadingHistory = true;
        try {
          const res = await axios.get('/api/coin-transactions');
          this.transactions = res.data.transactions;
        } catch {}
        this.loadingHistory = false;
      }
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
.shop-page {
  max-width: 700px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 8px;
  text-align: center;
}

.coin-balance {
  text-align: center;
  font-size: 1.4rem;
  color: var(--accent-gold);
  font-weight: 700;
  margin-bottom: 16px;
}

.shop-loading,
.shop-empty {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px;
}

.shop-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}

.shop-card {
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 10px;
  overflow: hidden;
  transition: all 0.2s;
}

.shop-card.owned {
  opacity: 0.6;
}

.shop-card-image {
  width: 100%;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.shop-card-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.shop-card-placeholder {
  font-size: 3rem;
}

.shop-card-info {
  padding: 8px 10px 4px;
  flex: 1;
}

.shop-card-type {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--text-secondary);
  opacity: 0.7;
}

.shop-card-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  margin: 2px 0 4px;
}

.shop-card-desc {
  font-size: 0.72rem;
  color: var(--text-secondary);
  margin: 0;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.shop-card-footer {
  padding: 6px 10px 10px;
}

.owned-badge {
  text-align: center;
  font-size: 0.75rem;
  color: #6abf50;
  font-weight: 600;
  padding: 4px 0;
}

.buy-btn {
  width: 100%;
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  cursor: pointer;
  font-weight: 600;
  transition: background 0.2s;
}

.buy-btn:hover:not(:disabled) {
  background: rgba(212, 168, 67, 0.3);
}

.buy-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

/* Transaction History */
.history-section {
  margin-top: 24px;
  border-top: 1px solid rgba(138, 106, 46, 0.15);
  padding-top: 16px;
}

.history-toggle {
  display: block;
  margin: 0 auto 12px;
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  padding: 5px 16px;
  border-radius: 4px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  transition: background 0.2s;
}

.history-toggle:hover {
  background: rgba(138, 106, 46, 0.1);
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.tx-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.12);
}

.tx-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.tx-desc {
  font-size: 0.8rem;
  color: var(--text-primary);
}

.tx-date {
  font-size: 0.65rem;
  color: var(--text-secondary);
  opacity: 0.7;
}

.tx-amount {
  font-weight: 700;
  font-size: 0.85rem;
  white-space: nowrap;
  margin-left: 12px;
}

.tx-row.earn .tx-amount {
  color: #6abf50;
}

.tx-row.spend .tx-amount {
  color: #cf6679;
}

/* Confirmation Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: var(--bg-secondary);
  border: 1px solid var(--accent-gold);
  border-radius: 12px;
  padding: 20px 24px;
  max-width: 320px;
  width: 90%;
  text-align: center;
}

.modal-box h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin: 0 0 10px;
  font-size: 1.1rem;
}

.modal-box p {
  color: var(--text-primary);
  font-size: 0.9rem;
  margin: 0 0 16px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.modal-cancel,
.modal-confirm {
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  padding: 6px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.2s;
}

.modal-cancel {
  border: 1px solid rgba(138, 106, 46, 0.3);
  background: transparent;
  color: var(--text-secondary);
}

.modal-cancel:hover {
  background: rgba(138, 106, 46, 0.1);
}

.modal-confirm {
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
}

.modal-confirm:hover {
  background: rgba(212, 168, 67, 0.35);
}

@media (max-width: 768px) {
  .shop-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }
}
</style>
