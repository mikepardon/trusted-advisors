<template>
  <div class="shop-page">
    <h2 class="section-title">Shop</h2>

    <HintBubble hint-id="shop-coins">
      Earn coins by completing games and claiming achievements. Spend them here on cosmetics and unlocks!
    </HintBubble>

    <div class="coin-balance">
      &#129689; {{ coins }}
    </div>

    <!-- Premium Subscription Card -->
    <div v-if="!loading && !isPremium && paymentsEnabled" class="premium-card">
      <div class="premium-card-header">
        <span class="premium-crown">&#9733;</span>
        <h3 class="premium-title">Premium</h3>
      </div>
      <p class="premium-desc">Unlock detailed statistics, exclusive content, and more!</p>
      <button class="premium-btn" @click="$router.push('/premium')">
        View Premium
      </button>
    </div>

    <div v-if="!loading && isPremium" class="premium-active-badge">
      <span class="premium-crown">&#9733;</span> Premium Active
    </div>

    <!-- Category Tabs -->
    <div v-if="!loading && items.length" class="shop-tabs-wrap">
      <div class="shop-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="shop-tab"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="shop-loading">Loading...</div>

    <div v-else-if="items.length === 0" class="shop-empty">
      No items available in the shop right now.
    </div>

    <div v-else-if="filteredItems.length === 0" class="shop-empty">
      No items in this category.
    </div>

    <div v-else class="shop-grid">
      <div
        v-for="item in filteredItems"
        :key="item.id"
        class="shop-card"
        :class="{ owned: item.owned }"
      >
        <!-- Image area -->
        <div class="shop-card-image">
          <img v-if="item.type === 'character'" :src="item.image_url || '/images/character.png'" :alt="item.name" class="char-img" />
          <img v-else-if="item.type === 'dice_theme' && item.preview_image" :src="item.preview_image" :alt="item.name" />
          <div v-else-if="item.type === 'kingdom_style'" class="shop-card-ks-preview" :data-kingdom-style="item.slug" :data-ks-anim="item.css_vars?.border_anim || 'none'" :style="ksPreviewStyle(item)">
            <div class="ks-mini-bar ks-mini-safe"></div>
            <div class="ks-mini-bar ks-mini-caution"></div>
            <div class="ks-mini-bar ks-mini-safe" style="width:60%"></div>
          </div>
          <div v-else class="shop-card-placeholder">
            {{ typeIcon(item.type) }}
          </div>
          <div v-if="item.owned" class="owned-overlay">OWNED</div>
        </div>

        <!-- Info -->
        <div class="shop-card-info">
          <div class="shop-card-type">{{ typeLabel(item.type) }}</div>
          <h3 class="shop-card-name">{{ item.name }}</h3>
          <p v-if="item.description" class="shop-card-desc">{{ item.description }}</p>
        </div>

        <!-- Action buttons -->
        <div class="shop-card-footer">
          <div class="shop-card-actions">
            <button v-if="item.type === 'character'" class="action-btn" @click="viewCharacter(item)">View</button>
            <button v-if="item.type === 'dice_theme'" class="action-btn" :disabled="tryingDice === item.id" @click="tryDice(item)">
              {{ tryingDice === item.id ? '...' : 'Try' }}
            </button>
            <button v-if="item.type === 'kingdom_style'" class="action-btn" @click="viewKingdomStyle(item)">View</button>
          </div>
          <button
            v-if="item.owned"
            class="buy-btn gift-btn"
            :disabled="!item.price || coins < item.price"
            @click="openGiftPicker(item)"
          >
            &#127873; Buy Gift
          </button>
          <template v-else>
            <button
              v-if="item.price"
              class="buy-btn"
              :disabled="coins < item.price || purchasing === item.id"
              @click="confirmPurchase(item)"
            >
              <span v-if="purchasing === item.id">...</span>
              <span v-else>&#129689; {{ item.price }}</span>
            </button>
            <button
              v-if="item.cash_price_cents"
              class="buy-btn cash-btn"
              :disabled="purchasing === item.id"
              @click="purchaseCash(item)"
            >
              ${{ (item.cash_price_cents / 100).toFixed(2) }}
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- Restore Purchases (mobile only) -->
    <div v-if="!loading && isNativeApp" class="restore-section">
      <button class="restore-btn" :disabled="restoring" @click="doRestorePurchases">
        {{ restoring ? 'Restoring...' : 'Restore Purchases' }}
      </button>
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
        <h3>Purchase</h3>
        <p>
          <strong>{{ confirmItem.name }}</strong> &mdash;
          <strong>&#129689; {{ confirmItem.price }}</strong>
        </p>
        <div class="modal-actions modal-actions-col">
          <button class="modal-confirm" @click="doPurchase(confirmItem)">Buy for Myself</button>
          <button class="modal-confirm gift-action-btn" @click="openGiftPicker(confirmItem); confirmItem = null">Gift a Friend</button>
          <button class="modal-cancel" @click="confirmItem = null">Cancel</button>
        </div>
      </div>
    </div>

    <!-- Friend Picker Modal -->
    <div v-if="giftItem" class="modal-overlay" @click.self="giftItem = null">
      <div class="modal-box friend-picker-box">
        <h3>Gift to Friend</h3>
        <p>
          Send <strong>{{ giftItem.name }}</strong> (&#129689; {{ giftItem.price }})
        </p>
        <div v-if="friendsLoading" class="shop-loading">Loading friends...</div>
        <div v-else-if="!friends.length" class="shop-empty" style="padding: 10px;">No friends yet. Add friends first!</div>
        <div v-else class="friend-list">
          <div
            v-for="f in friends"
            :key="f.id"
            class="friend-row"
            @click="confirmGift(f)"
          >
            <div class="friend-info">
              <span class="friend-name">{{ f.name }}</span>
              <span class="friend-level">Lv. {{ f.level || 1 }}</span>
            </div>
            <span class="friend-select-icon">&#127873;</span>
          </div>
        </div>
        <button class="modal-cancel" style="margin-top: 10px; width: 100%;" @click="giftItem = null">Cancel</button>
      </div>
    </div>

    <!-- Gift Confirm Modal -->
    <div v-if="giftingTo" class="modal-overlay" @click.self="giftingTo = null">
      <div class="modal-box">
        <h3>Confirm Gift</h3>
        <p>
          Gift <strong>{{ giftingTo.item.name }}</strong> to
          <strong>{{ giftingTo.friend.name }}</strong> for
          <strong>&#129689; {{ giftingTo.item.price }}</strong>?
        </p>
        <div class="modal-actions">
          <button class="modal-cancel" @click="giftingTo = null">Cancel</button>
          <button class="modal-confirm" :disabled="gifting" @click="doGift">
            {{ gifting ? '...' : 'Send Gift' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Character Detail Modal -->
    <div v-if="selectedChar" class="modal-overlay" @click.self="selectedChar = null">
      <div class="char-modal-card">
        <img :src="selectedChar.image_url || '/images/character.png'" :alt="selectedChar.name" class="char-modal-portrait" />
        <h3 class="char-modal-name">{{ selectedChar.name }}</h3>
        <p class="char-modal-desc">{{ selectedChar.description }}</p>
        <div v-if="selectedChar.dice" class="char-modal-dice">
          <div v-for="(die, di) in selectedChar.dice" :key="di" class="dice-row">
            <span class="dice-label">Die {{ di + 1 }}:</span>
            <span class="dice-face" v-for="(face, fi) in die" :key="fi">{{ face }}</span>
          </div>
        </div>
        <div v-if="selectedChar.wild_ability" class="char-modal-wild">
          <span class="wild-badge">W = {{ selectedChar.wild_value }}</span>
          <span class="wild-desc">{{ selectedChar.wild_ability }}: {{ selectedChar.wild_ability_description }}</span>
        </div>
        <button class="btn-primary char-modal-close" @click="selectedChar = null">Close</button>
      </div>
    </div>

    <!-- Kingdom Style Preview Modal -->
    <div v-if="previewStyle" class="modal-overlay" @click.self="previewStyle = null">
      <div class="ks-modal-card">
        <h3 class="ks-modal-title">{{ previewStyle.name }}</h3>
        <div
          class="ks-modal-preview"
          :data-kingdom-style="previewStyle.slug"
          :data-ks-anim="previewStyle.css_vars?.border_anim || 'none'"
          :style="ksModalStyle(previewStyle)"
        >
          <div class="ks-modal-name" :style="{ color: previewStyle.css_vars?.name_accent || 'var(--accent-gold)' }">Kingdom Name</div>
          <div class="ks-modal-bars">
            <div class="ks-modal-bar" :style="{ background: previewStyle.css_vars?.bar_safe || '#27ae60', width: '85%' }"></div>
            <div class="ks-modal-bar" :style="{ background: previewStyle.css_vars?.bar_caution || '#d4a843', width: '45%' }"></div>
            <div class="ks-modal-bar" :style="{ background: previewStyle.css_vars?.bar_safe || '#27ae60', width: '65%' }"></div>
            <div class="ks-modal-bar" :style="{ background: previewStyle.css_vars?.bar_safe || '#27ae60', width: '75%' }"></div>
            <div class="ks-modal-bar" :style="{ background: previewStyle.css_vars?.bar_caution || '#d4a843', width: '35%' }"></div>
            <div class="ks-modal-bar" :style="{ background: previewStyle.css_vars?.bar_safe || '#27ae60', width: '55%' }"></div>
          </div>
          <div class="ks-modal-total" :style="{ color: previewStyle.css_vars?.total_accent || 'var(--accent-gold)' }">72</div>
        </div>
        <p v-if="previewStyle.description" class="ks-modal-desc">{{ previewStyle.description }}</p>
        <button class="btn-primary char-modal-close" @click="previewStyle = null">Close</button>
      </div>
    </div>

    <!-- Persistent dice canvas (always mounted, pointer-events: none) -->
    <canvas ref="diceCanvas" class="dice-canvas-persistent"></canvas>
  </div>
</template>

<script>
import axios from 'axios';
import HintBubble from './HintBubble.vue';
import { useAuth } from '../stores/auth';
import { useToast } from '../stores/toast';
import { createDddiceInstance, isDddiceAvailable } from '../dddiceService';
import { isWebToNative, getPaymentPlatform, stripeCheckout, completePurchaseIAP, restorePurchases, restorePurchasesBackend } from '../services/paymentService';
import '../styles/kingdom-styles.css';

export default {
  name: 'ShopPage',
  components: { HintBubble },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      items: [],
      coins: 0,
      isPremium: false,
      premiumProduct: null,
      loading: true,
      purchasing: null,
      premiumLoading: false,
      restoring: false,
      confirmItem: null,
      showHistory: false,
      loadingHistory: false,
      transactions: [],
      selectedChar: null,
      previewStyle: null,
      diceInstance: null,
      activeTab: 'dice_theme',
      giftItem: null,
      friends: [],
      friendsLoading: false,
      giftingTo: null,
      gifting: false,
      tryingDice: null,
      isNativeApp: isWebToNative(),
    };
  },
  computed: {
    paymentsEnabled() {
      return useAuth().state.user?.payments_enabled ?? true;
    },
    tabs() {
      const typeCounts = {};
      for (const item of this.items) {
        typeCounts[item.type] = (typeCounts[item.type] || 0) + 1;
      }
      const allTabs = [
        { key: 'dice_theme', icon: '\u{1F3B2}', label: 'Dice', count: typeCounts.dice_theme || 0 },
        { key: 'character', icon: '\u{1F9D9}', label: 'Advisors', count: typeCounts.character || 0 },
        { key: 'kingdom_style', icon: '\u{1F3F0}', label: 'Styles', count: typeCounts.kingdom_style || 0 },
      ];
      return allTabs.filter(t => t.count > 0);
    },
    filteredItems() {
      return this.items.filter(i => i.type === this.activeTab);
    },
  },
  async mounted() {
    await this.fetchShop();
    this.initDiceCanvas();
  },
  methods: {
    typeLabel(type) {
      const labels = {
        character: 'Character',
        dice_theme: 'Dice',
        kingdom_style: 'Kingdom Style',
        item: 'Item',
      };
      return labels[type] || type;
    },
    typeIcon(type) {
      const icons = {
        character: '\u{1F9D9}',
        dice_theme: '\u{1F3B2}',
        kingdom_style: '\u{1F3F0}',
        item: '\u{1F4E6}',
      };
      return icons[type] || '\u{1F4E6}';
    },
    async fetchShop() {
      this.loading = true;
      try {
        const res = await axios.get('/api/shop');
        this.items = res.data.items;
        this.coins = res.data.coins;
        this.isPremium = res.data.is_premium;
        this.premiumProduct = res.data.premium_product;
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
        this.toast.error(msg);
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
    // --- Character view ---
    viewCharacter(item) {
      this.selectedChar = item;
    },
    // --- Dice canvas (persistent) ---
    async initDiceCanvas() {
      if (!isDddiceAvailable()) return;
      const canvas = this.$refs.diceCanvas;
      if (!canvas) return;
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
      this.diceInstance = createDddiceInstance();
      await this.diceInstance.init(canvas);
      this._onResize = () => {
        if (this.$refs.diceCanvas && this.diceInstance) {
          this.$refs.diceCanvas.width = window.innerWidth;
          this.$refs.diceCanvas.height = window.innerHeight;
          this.diceInstance.resize(window.innerWidth, window.innerHeight);
        }
      };
      window.addEventListener('resize', this._onResize);
    },
    async tryDice(item) {
      if (!this.diceInstance?.isReady() || this.tryingDice) return;
      this.tryingDice = item.id;
      const slug = item.slug;
      try {
        await this.diceInstance.roll([
          { theme: slug, value: Math.ceil(Math.random() * 6) },
          { theme: slug, value: Math.ceil(Math.random() * 6) },
          { theme: slug, value: Math.ceil(Math.random() * 6) },
        ]);
      } catch {}
      setTimeout(() => { this.tryingDice = null; }, 2000);
    },
    // --- Gifting ---
    async openGiftPicker(item) {
      this.giftItem = item;
      this.friendsLoading = true;
      try {
        const res = await axios.get('/api/friends');
        this.friends = (res.data.friends || []).map(f => ({
          id: f.user.id,
          name: f.user.name,
          level: f.user.level,
        }));
      } catch {
        this.friends = [];
      }
      this.friendsLoading = false;
    },
    confirmGift(friend) {
      this.giftingTo = { item: this.giftItem, friend };
    },
    async doGift() {
      if (!this.giftingTo) return;
      this.gifting = true;
      const { item, friend } = this.giftingTo;
      try {
        const res = await axios.post(`/api/shop/${item.id}/gift`, { friend_id: friend.id });
        this.coins = res.data.new_coins;
        const { updateUserStats } = useAuth();
        updateUserStats({ coins: this.coins });
        this.toast.success(res.data.message);
        this.giftingTo = null;
        this.giftItem = null;
      } catch (e) {
        const msg = e.response?.data?.error || 'Gift failed.';
        this.toast.error(msg);
      }
      this.gifting = false;
    },
    // --- Premium / Cash purchases ---
    async subscribePremium() {
      this.premiumLoading = true;
      try {
        const platform = getPaymentPlatform();
        if (platform === 'stripe') {
          await stripeCheckout('subscription');
        } else {
          const productId = platform === 'apple'
            ? this.premiumProduct?.apple_product_id
            : this.premiumProduct?.google_product_id;
          await completePurchaseIAP(productId);
          this.isPremium = true;
        }
      } catch (e) {
        const msg = e.response?.data?.error || e.message || 'Purchase failed.';
        this.toast.error(msg);
      }
      this.premiumLoading = false;
    },
    async purchaseCash(item) {
      this.purchasing = item.id;
      try {
        const platform = getPaymentPlatform();
        if (platform === 'stripe') {
          await stripeCheckout('one_time', item.id);
        } else {
          const productId = platform === 'apple' ? item.apple_product_id : item.google_product_id;
          await completePurchaseIAP(productId);
          item.owned = true;
          await this.fetchShop();
        }
      } catch (e) {
        const msg = e.response?.data?.error || e.message || 'Purchase failed.';
        this.toast.error(msg);
      }
      this.purchasing = null;
    },
    async doRestorePurchases() {
      this.restoring = true;
      try {
        const platform = getPaymentPlatform();
        const purchases = await restorePurchases();
        const receipts = purchases.map(p => ({
          product_id: p.productId || p.product_id,
          transaction_id: p.transactionId || p.transaction_id,
          receipt_data: p.receiptData || p.receipt_data,
        }));
        if (receipts.length) {
          const res = await restorePurchasesBackend(platform, receipts);
          this.isPremium = res.data.is_premium;
          this.toast.success(res.data.message);
        } else {
          this.toast.show('No purchases to restore.');
        }
        await this.fetchShop();
      } catch (e) {
        this.toast.error(e.message || 'Failed to restore purchases.');
      }
      this.restoring = false;
    },
    // --- Kingdom style view ---
    viewKingdomStyle(item) {
      this.previewStyle = item;
    },
    ksPreviewStyle(item) {
      const s = {};
      if (item.css_vars) {
        const cv = item.css_vars;
        if (cv.border_color) s['--ks-border-color'] = cv.border_color;
        if (cv.border_glow) s['--ks-border-glow'] = cv.border_glow;
        if (cv.border_color_rgb) s['--ks-border-color-rgb'] = cv.border_color_rgb;
        if (cv.bg_tint) s['--ks-bg-tint'] = cv.bg_tint;
        if (cv.bg_color) s['--ks-bg-color'] = cv.bg_color;
        if (cv.bar_safe) s['--ks-bar-safe'] = cv.bar_safe;
        if (cv.bar_caution) s['--ks-bar-caution'] = cv.bar_caution;
      }
      if (item.background_image_url) {
        s.backgroundImage = `linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url(${item.background_image_url})`;
        s.backgroundSize = 'cover';
        s.backgroundPosition = 'center';
      }
      return s;
    },
    ksModalStyle(item) {
      const s = {};
      if (item.css_vars) {
        const cv = item.css_vars;
        if (cv.border_color) s['--ks-border-color'] = cv.border_color;
        if (cv.border_glow) s['--ks-border-glow'] = cv.border_glow;
        if (cv.border_color_rgb) s['--ks-border-color-rgb'] = cv.border_color_rgb;
        if (cv.bg_tint) s['--ks-bg-tint'] = cv.bg_tint;
        if (cv.bg_color) s['--ks-bg-color'] = cv.bg_color;
        if (cv.name_accent) s['--ks-name-accent'] = cv.name_accent;
        if (cv.total_accent) s['--ks-total-accent'] = cv.total_accent;
        if (cv.bar_safe) s['--ks-bar-safe'] = cv.bar_safe;
        if (cv.bar_caution) s['--ks-bar-caution'] = cv.bar_caution;
      }
      const borderColor = item.css_vars?.border_color || 'transparent';
      s.border = `2px solid ${borderColor}`;
      if (item.background_image_url) {
        s.backgroundImage = `linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)), url(${item.background_image_url})`;
        s.backgroundSize = 'cover';
        s.backgroundPosition = 'center';
      } else {
        s.backgroundColor = item.css_vars?.bg_color || item.css_vars?.bg_tint || 'var(--bg-primary)';
      }
      return s;
    },
  },
  beforeUnmount() {
    if (this.diceInstance) {
      this.diceInstance.destroy();
      this.diceInstance = null;
    }
    if (this._onResize) {
      window.removeEventListener('resize', this._onResize);
    }
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

/* Category Tabs */
.shop-tabs-wrap {
  margin-bottom: 16px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

.shop-tabs-wrap::-webkit-scrollbar {
  display: none;
}

.shop-tabs {
  display: flex;
  gap: 6px;
  justify-content: center;
  min-width: min-content;
}

.shop-tab {
  padding: 6px 16px;
  font-size: 0.8rem;
  white-space: nowrap;
}

.shop-tab.active {
  background: var(--accent-gold);
  border-color: var(--accent-gold);
  color: black;
  box-shadow: 0 4px 0 #7a5a14, inset 0 1px 0 rgba(255,255,255,0.2);
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
  opacity: 1;
}

.shop-card-image {
  width: 100%;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.15);
  overflow: hidden;
  position: relative;
}

.owned-overlay {
  position: absolute;
  top: 8px;
  right: 8px;
  background: rgba(39, 174, 96, 0.85);
  color: #fff;
  font-family: 'Cinzel', serif;
  font-size: 0.6rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.shop-card-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.shop-card-image .char-img {
  border-radius: 0;
}

.shop-card-placeholder {
  font-size: 3rem;
}

/* Kingdom style inline preview on card */
.shop-card-ks-preview {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  padding: 16px;
  border: 2px solid var(--ks-border-color, transparent);
  box-shadow: var(--ks-border-glow, none);
  background-color: var(--ks-bg-color, var(--ks-bg-tint, rgba(0,0,0,0.15)));
}

.ks-mini-bar {
  height: 6px;
  border-radius: 3px;
  width: 80%;
}

.ks-mini-safe {
  background: var(--ks-bar-safe, #27ae60);
}

.ks-mini-caution {
  background: var(--ks-bar-caution, #d4a843);
  width: 50%;
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

.shop-card-actions {
  display: flex;
  gap: 4px;
  margin-bottom: 6px;
}

.action-btn {
  flex: 1;
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  padding: 4px 8px;
  border-radius: 4px;
  border: 1px solid rgba(67, 160, 212, 0.3);
  background: rgba(67, 160, 212, 0.1);
  color: #60b8e0;
  cursor: pointer;
  transition: background 0.2s;
}

.action-btn:hover {
  background: rgba(67, 160, 212, 0.25);
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

.modal-actions-col {
  flex-direction: column;
  gap: 8px;
}

.gift-action-btn {
  border-color: rgba(67, 160, 212, 0.4);
  background: rgba(67, 160, 212, 0.15);
  color: #60b8e0;
}

.gift-action-btn:hover {
  background: rgba(67, 160, 212, 0.3);
}

.gift-btn {
  border-color: rgba(67, 160, 212, 0.4);
  background: rgba(67, 160, 212, 0.15);
  color: #60b8e0;
  font-size: 0.75rem;
}

.gift-btn:hover:not(:disabled) {
  background: rgba(67, 160, 212, 0.3);
}

/* Friend Picker */
.friend-picker-box {
  max-width: 340px;
  max-height: 80vh;
  overflow-y: auto;
}

.friend-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 250px;
  overflow-y: auto;
}

.friend-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: background 0.2s;
}

.friend-row:hover {
  background: rgba(212, 168, 67, 0.15);
}

.friend-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.friend-name {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.85rem;
  font-weight: 600;
}

.friend-level {
  font-size: 0.7rem;
  color: var(--text-secondary);
}

.friend-select-icon {
  font-size: 1.2rem;
}

/* Character Detail Modal */
.char-modal-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14);
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 340px;
  width: 90%;
  text-align: center;
}

.char-modal-portrait {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--accent-gold);
  margin-bottom: 12px;
}

.char-modal-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.2rem;
  margin-bottom: 8px;
}

.char-modal-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.85rem;
  margin-bottom: 12px;
}

.char-modal-dice {
  margin-bottom: 12px;
}

.char-modal-dice .dice-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  justify-content: center;
}

.char-modal-dice .dice-label {
  color: var(--text-secondary);
  font-size: 0.8rem;
  min-width: 42px;
}

.char-modal-dice .dice-face {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 4px;
  color: var(--text-bright);
  font-size: 0.8rem;
  font-weight: 600;
}

.char-modal-wild {
  background: rgba(212, 168, 67, 0.08);
  border-top: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 6px;
  padding: 8px;
  margin-bottom: 16px;
}

.char-modal-wild .wild-badge {
  display: inline-block;
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 2px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 700;
  margin-bottom: 4px;
}

.char-modal-wild .wild-desc {
  display: block;
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-style: italic;
}

.char-modal-close {
  padding: 8px 28px;
}

/* Kingdom Style Preview Modal */
.ks-modal-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14);
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 360px;
  width: 90%;
  text-align: center;
}

.ks-modal-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  margin-bottom: 14px;
}

.ks-modal-preview {
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 14px;
  position: relative;
  overflow: hidden;
}

.ks-modal-name {
  font-family: 'Cinzel', serif;
  font-size: 0.95rem;
  text-align: center;
  margin-bottom: 10px;
}

.ks-modal-bars {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.ks-modal-bar {
  height: 8px;
  border-radius: 4px;
}

.ks-modal-total {
  text-align: center;
  font-weight: 700;
  font-size: 1rem;
  margin-top: 8px;
}

.ks-modal-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.82rem;
  margin-bottom: 14px;
}

/* Persistent dice canvas */
.dice-canvas-persistent {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 70px;
  width: 100%;
  height: calc(100% - 70px);
  pointer-events: none;
  z-index: 200;
}

/* Premium card */
.premium-card {
  background: linear-gradient(135deg, rgba(212, 168, 67, 0.15), rgba(180, 120, 30, 0.08));
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
  text-align: center;
}

.premium-card-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 8px;
}

.premium-crown {
  font-size: 1.3rem;
  color: var(--accent-gold);
}

.premium-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.2rem;
  margin: 0;
}

.premium-desc {
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin: 0 0 12px;
}

.premium-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  padding: 10px 24px;
  border-radius: 8px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  cursor: pointer;
  font-weight: 700;
  transition: background 0.2s;
}

.premium-btn:hover:not(:disabled) {
  background: rgba(212, 168, 67, 0.35);
}

.premium-btn:disabled {
  opacity: 0.5;
  cursor: default;
}

.premium-active-badge {
  text-align: center;
  padding: 10px;
  margin-bottom: 16px;
  background: rgba(106, 191, 80, 0.1);
  border: 1px solid rgba(106, 191, 80, 0.3);
  border-radius: 8px;
  color: #6abf50;
  font-family: 'Cinzel', serif;
  font-weight: 600;
  font-size: 0.9rem;
}

/* Cash buy button */
.cash-btn {
  border-color: #6abf50;
  background: rgba(106, 191, 80, 0.12);
  color: #6abf50;
  margin-top: 4px;
}

.cash-btn:hover:not(:disabled) {
  background: rgba(106, 191, 80, 0.25);
}

/* Restore purchases */
.restore-section {
  text-align: center;
  margin-bottom: 16px;
}

.restore-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  padding: 8px 20px;
  border-radius: 6px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
}

.restore-btn:hover:not(:disabled) {
  background: rgba(138, 106, 46, 0.1);
}

.restore-btn:disabled {
  opacity: 0.5;
}

@media (max-width: 768px) {
  .shop-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }
}
</style>
