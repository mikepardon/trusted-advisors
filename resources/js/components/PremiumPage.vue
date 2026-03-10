<template>
  <div class="premium-page">
    <button class="back-btn" @click="$router.push('/')">&#8592; Back</button>

    <!-- Hero -->
    <div class="premium-hero">
      <span class="hero-star">&#9733;</span>
      <h1 class="hero-title">Trusted Advisors Premium</h1>
      <p class="hero-sub">Unlock the full potential of your kingdom</p>
    </div>

    <!-- Already premium -->
    <div v-if="isPremium" class="premium-active-card">
      <span class="active-icon">&#9733;</span>
      <h2>Premium Active</h2>
      <p>You have access to all premium features. Thank you for your support!</p>
      <button class="manage-btn" @click="manageSub">Manage Subscription</button>
    </div>

    <!-- Feature cards -->
    <div class="features-grid">
      <div class="feature-card">
        <span class="feature-icon">&#128202;</span>
        <h3>Detailed Stats & Analytics</h3>
        <p>Deep dive into your game history with win rates, stat trends, character performance, and more.</p>
      </div>
      <div class="feature-card">
        <span class="feature-icon">&#9881;</span>
        <h3>Custom Game Creation</h3>
        <p>Choose starting stats, card and event pools, and house rules like no negative effects or hardcore mode.</p>
      </div>
      <div class="feature-card">
        <span class="feature-icon">&#128274;</span>
        <h3>Private Lobbies</h3>
        <p>Create password-protected games for your friends. Control who joins your kingdom.</p>
      </div>
      <div class="feature-card">
        <span class="feature-icon">&#127942;</span>
        <h3>Tournament Mode</h3>
        <p>Organize bracket-style tournaments for 4, 8, or 16 players. Compete for ultimate glory.</p>
      </div>
      <div class="feature-card">
        <span class="feature-icon">&#128142;</span>
        <h3>Exclusive Cosmetics</h3>
        <p>Access premium-only dice themes, kingdom styles, and advisor skins in the shop.</p>
      </div>
    </div>

    <!-- Pricing -->
    <div v-if="!isPremium && auth.state.user?.payments_enabled" class="pricing-section">
      <div class="price-card">
        <div v-if="priceLoading" class="price-loading">Loading price...</div>
        <template v-else-if="price">
          <div class="price-amount">
            {{ formatPrice(price.amount_cents, price.currency) }}
          </div>
          <div class="price-interval" v-if="price.interval">
            per {{ price.interval_count > 1 ? price.interval_count + ' ' : '' }}{{ price.interval }}{{ price.interval_count > 1 ? 's' : '' }}
          </div>
        </template>
        <button
          class="subscribe-btn"
          :disabled="subscribing"
          @click="subscribe"
        >
          {{ subscribing ? 'Processing...' : 'Subscribe Now' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';
import { useToast } from '../stores/toast';
import { stripeCheckout, getPaymentPlatform, completePurchaseIAP, isWebToNative } from '../services/paymentService';

export default {
  name: 'PremiumPage',
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast };
  },
  data() {
    return {
      price: null,
      priceLoading: true,
      subscribing: false,
    };
  },
  computed: {
    isPremium() {
      return this.auth.state.user?.is_premium;
    },
  },
  async mounted() {
    await this.fetchPrice();
  },
  methods: {
    async fetchPrice() {
      this.priceLoading = true;
      try {
        const res = await axios.get('/api/premium/price');
        this.price = res.data;
      } catch {
        // Price may not be configured
      }
      this.priceLoading = false;
    },
    formatPrice(cents, currency) {
      const amount = (cents / 100).toFixed(2);
      const symbols = { USD: '$', EUR: '\u20AC', GBP: '\u00A3' };
      const symbol = symbols[currency] || currency + ' ';
      return symbol + amount;
    },
    async subscribe() {
      this.subscribing = true;
      try {
        const platform = getPaymentPlatform();
        if (platform === 'stripe') {
          await stripeCheckout('subscription');
        } else {
          const productId = platform === 'apple'
            ? this.price?.apple_product_id
            : this.price?.google_product_id;
          if (!productId) {
            this.toast.error('IAP product not configured.');
            this.subscribing = false;
            return;
          }
          await completePurchaseIAP(productId, true);
          this.auth.state.user.is_premium = true;
          this.toast.success('Premium activated!');
        }
      } catch (e) {
        this.toast.error(e.response?.data?.error || e.message || 'Purchase failed.');
      }
      this.subscribing = false;
    },
    async manageSub() {
      const platform = getPaymentPlatform();
      if (platform === 'apple') {
        window.location.href = 'https://apps.apple.com/account/subscriptions';
        return;
      }
      if (platform === 'google') {
        window.location.href = 'https://play.google.com/store/account/subscriptions';
        return;
      }
      try {
        const res = await axios.get('/api/premium/manage');
        if (res.data.url) {
          window.location.href = res.data.url;
        }
      } catch {
        this.toast.error('Could not open subscription management.');
      }
    },
  },
};
</script>

<style scoped>
.premium-page {
  max-width: 600px;
  margin: 0 auto;
  padding: 0 16px 100px;
}

.back-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-size: 0.85rem;
  padding: 6px 14px;
  cursor: pointer;
  margin-bottom: 8px;
  border-radius: 6px;
  letter-spacing: 0;
}

.back-btn:hover {
  color: var(--text-bright);
  border-color: var(--border-gold);
}

.premium-hero {
  text-align: center;
  padding: 16px 0 16px;
}

.hero-star {
  font-size: 2.4rem;
  color: var(--accent-gold);
  display: block;
  margin-bottom: 4px;
  filter: drop-shadow(0 0 12px rgba(212, 168, 67, 0.5));
}

.hero-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin: 0 0 4px;
}

.hero-sub {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
  margin: 0;
}

.premium-active-card {
  text-align: center;
  padding: 24px;
  margin-bottom: 24px;
  background: rgba(106, 191, 80, 0.08);
  border: 2px solid rgba(106, 191, 80, 0.3);
  border-radius: 12px;
}

.premium-active-card .active-icon {
  font-size: 2rem;
  color: #6abf50;
}

.premium-active-card h2 {
  font-family: 'Cinzel', serif;
  color: #6abf50;
  font-size: 1.2rem;
  margin: 8px 0;
}

.premium-active-card p {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin: 0 0 16px;
}

.manage-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  padding: 8px 20px;
  border-radius: 6px;
  border: 1px solid rgba(106, 191, 80, 0.4);
  background: rgba(106, 191, 80, 0.12);
  color: #6abf50;
  cursor: pointer;
}

.manage-btn:hover {
  background: rgba(106, 191, 80, 0.25);
}

.features-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.feature-card {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.25);
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.feature-icon {
  font-size: 1.3rem;
  flex-shrink: 0;
  margin-top: 1px;
}

.feature-card h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.85rem;
  margin: 0 0 2px;
}

.feature-card p {
  color: var(--text-secondary);
  font-size: 0.78rem;
  margin: 0;
  line-height: 1.35;
}

@media (max-width: 480px) {
  .feature-card {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
}

.pricing-section {
  text-align: center;
}

.price-card {
  background: linear-gradient(135deg, rgba(212, 168, 67, 0.12), rgba(180, 120, 30, 0.06));
  border: 2px solid var(--accent-gold);
  border-radius: 14px;
  padding: 20px 20px;
}

.price-loading {
  color: var(--text-secondary);
  font-style: italic;
  padding: 12px 0;
}

.price-amount {
  font-family: 'Cinzel', serif;
  font-size: 1.8rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.price-interval {
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 14px;
}

.subscribe-btn {
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  padding: 12px 36px;
  border-radius: 10px;
  border: 2px solid var(--accent-gold);
  background: linear-gradient(180deg, rgba(212, 168, 67, 0.25), rgba(180, 120, 30, 0.15));
  color: var(--accent-gold);
  cursor: pointer;
  font-weight: 700;
  transition: all 0.2s;
  margin-top: 8px;
}

.subscribe-btn:hover:not(:disabled) {
  background: linear-gradient(180deg, rgba(212, 168, 67, 0.4), rgba(180, 120, 30, 0.25));
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.3);
}

.subscribe-btn:disabled {
  opacity: 0.5;
  cursor: default;
}
</style>
