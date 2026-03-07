<template>
  <div class="profile-page">
    <div class="card-panel">
      <h2 class="section-title">Profile</h2>

      <div class="profile-info">
        <div class="profile-avatar">&#9876;</div>
        <div class="profile-details">
          <h3 class="profile-name">
            {{ auth.state.user?.name }}
            <span v-if="auth.state.user?.is_premium" class="premium-badge-inline" title="Premium">&#9733;</span>
          </h3>
          <p class="profile-joined">Level {{ gameStats.level || 1 }} Advisor</p>
        </div>
      </div>

      <!-- Referral Section -->
      <div class="referral-section">
        <h3 class="referral-title">Referral Program</h3>
        <p class="referral-desc">Invite friends! Earn 20 coins when they reach Level 2.</p>

        <div class="referral-code-display">
          <span v-if="referralCode" class="referral-code">{{ referralCode }}</span>
          <span v-else class="referral-code dim">Loading...</span>
        </div>
        <div class="referral-buttons">
          <button class="btn-referral btn-copy" @click="copyCode" :disabled="!referralCode">
            {{ copied ? 'Copied!' : 'Copy Code' }}
          </button>
          <button v-if="canShare" class="btn-referral btn-share" @click="shareCode" :disabled="!referralCode">Share</button>
        </div>

        <div v-if="referralStats" class="referral-stats">
          <div class="ref-stat">
            <span class="ref-stat-value">{{ referralStats.total_referred }}</span>
            <span class="ref-stat-label">Invited</span>
          </div>
          <div class="ref-stat">
            <span class="ref-stat-value">{{ referralStats.verified_count }}</span>
            <span class="ref-stat-label">Verified (Lv.2+)</span>
          </div>
          <div class="ref-stat">
            <span class="ref-stat-value">{{ referralStats.total_coins_earned }}</span>
            <span class="ref-stat-label">Coins Earned</span>
          </div>
        </div>

        <!-- Enter a referral code -->
        <div v-if="!auth.state.user?.referred_by && !referralApplied" class="referral-enter">
          <p class="referral-enter-label">Have a referral code?</p>
          <div class="referral-enter-row">
            <input
              v-model="referralInput"
              type="text"
              class="referral-input"
              placeholder="Enter code"
              maxlength="10"
              @keyup.enter="applyReferralCode"
            />
            <button class="btn-referral btn-apply" @click="applyReferralCode" :disabled="!referralInput.trim() || applyingReferral">
              {{ applyingReferral ? 'Applying...' : 'Apply' }}
            </button>
          </div>
          <p v-if="referralError" class="referral-error">{{ referralError }}</p>
        </div>
        <div v-else-if="referralApplied" class="referral-applied">
          Referral code applied!
        </div>
      </div>
    </div>

    <!-- XP & Level -->
    <div v-if="!statsLoading" class="card-panel">
      <h2 class="section-title">Progression</h2>
      <div class="xp-section">
        <div class="xp-header">
          <span class="xp-level-badge">Lv. {{ gameStats.level || 1 }}</span>
          <span class="xp-text">{{ gameStats.xp || 0 }} / {{ gameStats.xp_for_next_level || 300 }} XP</span>
        </div>
        <div class="xp-bar-track">
          <div class="xp-bar-fill" :style="{ width: xpPercent + '%' }"></div>
        </div>
        <div class="xp-elo-row">
          <div class="elo-display">
            <span class="elo-label">ELO Rating</span>
            <span class="elo-value">{{ gameStats.elo_rating || 1000 }}</span>
          </div>
          <div class="elo-display">
            <span class="elo-label">Login Streak</span>
            <span class="elo-value streak-value">&#128293; {{ gameStats.login_streak || 0 }}</span>
          </div>
          <div class="elo-display">
            <span class="elo-label">Best Streak</span>
            <span class="elo-value">{{ gameStats.max_login_streak || 0 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Game Stats -->
    <div class="card-panel">
      <h2 class="section-title">Campaign Record</h2>
      <div v-if="statsLoading" class="stats-loading">Loading stats...</div>
      <div v-else class="stats-grid">
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.total_games }}</span>
          <span class="stat-label">Total Games</span>
        </div>
        <div class="stat-card stat-win">
          <span class="stat-number">{{ gameStats.total_wins }}</span>
          <span class="stat-label">Victories</span>
        </div>
        <div class="stat-card stat-loss">
          <span class="stat-number">{{ gameStats.total_losses }}</span>
          <span class="stat-label">Defeats</span>
        </div>
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.online_wins }}</span>
          <span class="stat-label">Online Wins</span>
        </div>
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.single_wins }}</span>
          <span class="stat-label">Solo Wins</span>
        </div>
        <div class="stat-card">
          <span class="stat-number">{{ gameStats.pnp_wins }}</span>
          <span class="stat-label">Local Wins</span>
        </div>
      </div>
    </div>

    <!-- Detailed Stats Link (hidden if no payments and not premium) -->
    <div v-if="auth.state.user?.is_premium" class="card-panel" style="text-align: center;">
      <router-link to="/stats" class="btn-primary stats-link">View Detailed Stats</router-link>
    </div>
    <div v-else-if="auth.state.user?.payments_enabled" class="card-panel" style="text-align: center;">
      <router-link to="/stats" class="btn-primary stats-link">
        <span class="lock-icon">&#128274; </span>View Detailed Stats
      </router-link>
    </div>

    <!-- Subscription Management -->
    <div v-if="auth.state.user?.is_premium" class="card-panel">
      <h2 class="section-title">Subscription</h2>
      <div v-if="subLoading" class="stats-loading">Loading subscription details...</div>
      <div v-else-if="subDetails" class="sub-panel">
        <div class="sub-row">
          <span class="sub-label">Platform</span>
          <span class="sub-value sub-platform">{{ platformLabel }}</span>
        </div>
        <div v-if="subDetails.interval" class="sub-row">
          <span class="sub-label">Plan</span>
          <span class="sub-value">{{ intervalLabel }}</span>
        </div>
        <div v-if="subDetails.amount_cents" class="sub-row">
          <span class="sub-label">Price</span>
          <span class="sub-value">{{ formattedPrice }}</span>
        </div>
        <div class="sub-row">
          <span class="sub-label">Status</span>
          <span class="sub-value" :class="subStatusClass">{{ subStatusLabel }}</span>
        </div>
        <div v-if="subDetails.cancel_at_period_end && subDetails.current_period_end" class="sub-row">
          <span class="sub-label">Cancels on</span>
          <span class="sub-value sub-cancelling">{{ formatDate(subDetails.current_period_end) }}</span>
        </div>
        <div v-else-if="subDetails.current_period_end" class="sub-row">
          <span class="sub-label">Next bill date</span>
          <span class="sub-value">{{ formatDate(subDetails.current_period_end) }}</span>
        </div>

        <!-- Stripe: Cancel button -->
        <div v-if="subDetails.platform === 'stripe' && !subDetails.cancel_at_period_end" class="sub-actions">
          <button class="btn-cancel-sub" @click="showCancelConfirm = true">Cancel Subscription</button>
        </div>

        <!-- Apple/Google: external management -->
        <p v-if="subDetails.platform === 'apple'" class="sub-external">Manage your subscription in the App Store.</p>
        <p v-if="subDetails.platform === 'google'" class="sub-external">Manage your subscription in Google Play.</p>
      </div>
    </div>

    <!-- Cancel confirmation modal -->
    <div v-if="showCancelConfirm" class="modal-overlay" @click.self="showCancelConfirm = false">
      <div class="modal-box">
        <h3 class="modal-title">Cancel Subscription?</h3>
        <p class="modal-body">Your premium access will continue until the end of your current billing period. You won't be charged again.</p>
        <div class="modal-actions">
          <button @click="showCancelConfirm = false">Keep Subscription</button>
          <button class="btn-danger" @click="confirmCancel" :disabled="cancelling">
            {{ cancelling ? 'Cancelling...' : 'Yes, Cancel' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Account Management -->
    <div class="card-panel">
      <h2 class="section-title">Account</h2>
      <div class="account-actions">
        <a :href="authServiceUrl + '/dashboard'" target="_blank" rel="noopener" class="btn-primary account-link">
          Manage Account
        </a>
        <button class="btn-logout" @click="handleLogout">Logout</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';
import { useToast } from '../stores/toast';

export default {
  name: 'ProfilePage',
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast };
  },
  data() {
    return {
      gameStats: {},
      statsLoading: true,
      authServiceUrl: import.meta.env.VITE_AUTH_URL || '',
      referralCode: null,
      referralStats: null,
      copied: false,
      canShare: !!navigator.share,
      referralInput: '',
      applyingReferral: false,
      referralError: '',
      referralApplied: false,
      subDetails: null,
      subLoading: false,
      showCancelConfirm: false,
      cancelling: false,
    };
  },
  computed: {
    platformLabel() {
      const p = this.subDetails?.platform;
      if (p === 'stripe') return 'Stripe';
      if (p === 'apple') return 'Apple';
      if (p === 'google') return 'Google Play';
      return p || 'Unknown';
    },
    subStatusLabel() {
      if (this.subDetails?.cancel_at_period_end) {
        return 'Cancelling';
      }
      return this.subDetails?.status === 'active' ? 'Active' : (this.subDetails?.status || 'Active');
    },
    subStatusClass() {
      if (this.subDetails?.cancel_at_period_end) return 'sub-cancelling';
      return this.subDetails?.status === 'active' ? 'sub-active' : '';
    },
    intervalLabel() {
      const interval = this.subDetails?.interval;
      const count = this.subDetails?.interval_count || 1;
      if (!interval) return '';
      const labels = { day: 'Daily', week: 'Weekly', month: 'Monthly', year: 'Yearly' };
      if (count === 1) return labels[interval] || interval;
      return `Every ${count} ${interval}s`;
    },
    formattedPrice() {
      const cents = this.subDetails?.amount_cents;
      const currency = this.subDetails?.currency || 'USD';
      if (!cents && cents !== 0) return '';
      try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(cents / 100);
      } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
      }
    },
    xpPercent() {
      const xp = this.gameStats.xp || 0;
      const level = this.gameStats.level || 1;
      const currentLevelXp = (100 * (level - 1) * level) / 2;
      const nextLevelXp = this.gameStats.xp_for_next_level || (100 * level * (level + 1) / 2);
      const range = nextLevelXp - currentLevelXp;
      if (range <= 0) return 0;
      return Math.min(100, Math.round(((xp - currentLevelXp) / range) * 100));
    },
  },
  async mounted() {
    try {
      const res = await axios.get('/api/auth/stats');
      this.gameStats = res.data;
    } catch {}
    this.statsLoading = false;
    this.fetchReferralData();
    if (this.auth.state.user?.is_premium) {
      this.fetchSubscriptionDetails();
    }
  },
  methods: {
    async handleLogout() {
      await this.auth.logout();
      window.location.reload();
    },
    async fetchReferralData() {
      try {
        const [codeRes, statsRes] = await Promise.allSettled([
          axios.get('/api/referral/code'),
          axios.get('/api/referral/stats'),
        ]);
        if (codeRes.status === 'fulfilled') this.referralCode = codeRes.value.data.code;
        if (statsRes.status === 'fulfilled') this.referralStats = statsRes.value.data;
      } catch {}
    },
    async copyCode() {
      if (!this.referralCode) return;
      try {
        await navigator.clipboard.writeText(this.referralCode);
      } catch {
        // Fallback for non-HTTPS or unsupported contexts
        const ta = document.createElement('textarea');
        ta.value = this.referralCode;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
      }
      this.copied = true;
      setTimeout(() => { this.copied = false; }, 2000);
    },
    async shareCode() {
      if (!this.referralCode || !navigator.share) return;
      try {
        await navigator.share({
          title: 'Join Trusted Advisors!',
          text: `Use my referral code: ${this.referralCode}`,
        });
      } catch {}
    },
    async fetchSubscriptionDetails() {
      this.subLoading = true;
      try {
        const res = await axios.get('/api/premium/details');
        this.subDetails = res.data;
      } catch {
        // fallback to basic info
        this.subDetails = {
          is_premium: true,
          status: 'active',
        };
      }
      this.subLoading = false;
    },
    async confirmCancel() {
      this.cancelling = true;
      try {
        const res = await axios.post('/api/premium/cancel');
        this.showCancelConfirm = false;
        if (this.subDetails) {
          this.subDetails.cancel_at_period_end = true;
          this.subDetails.current_period_end = res.data.ends_at;
        }
      } catch {
        this.toast.error('Failed to cancel subscription. Please try again.');
      }
      this.cancelling = false;
    },
    formatDate(isoString) {
      if (!isoString) return '';
      return new Date(isoString).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    },
    async applyReferralCode() {
      const code = this.referralInput.trim();
      if (!code) return;
      this.applyingReferral = true;
      this.referralError = '';
      try {
        await axios.post('/api/referral/apply', { code });
        this.referralApplied = true;
        this.referralInput = '';
      } catch (e) {
        this.referralError = e.response?.data?.message || 'Invalid referral code.';
      } finally {
        this.applyingReferral = false;
      }
    },
  },
};
</script>

<style scoped>
.profile-page {
  max-width: 600px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 15px;
  text-align: center;
}

.profile-info {
  display: flex;
  align-items: center;
  gap: 16px;
  justify-content: center;
}

.profile-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: rgba(212, 168, 67, 0.15);
  border: 2px solid var(--accent-gold);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.6rem;
}

.profile-details {
  text-align: left;
}

.profile-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
}

.profile-joined {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
}

/* XP Section */
.xp-section {
  text-align: center;
}

.xp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.xp-level-badge {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.xp-text {
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.xp-bar-track {
  width: 100%;
  height: 12px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 6px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  overflow: hidden;
  margin-bottom: 14px;
}

.xp-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 6px;
  transition: width 0.5s ease;
}

.xp-elo-row {
  display: flex;
  justify-content: center;
  gap: 24px;
}

.streak-value {
  font-size: 1.4rem;
}

.elo-display {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.elo-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.elo-value {
  font-family: 'Cinzel', serif;
  font-size: 1.6rem;
  color: var(--accent-gold);
  font-weight: 700;
}

/* Stats grid */
.stats-loading {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.stat-card {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  padding: 14px 10px;
  text-align: center;
}

.stat-number {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.6rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.stat-card .stat-label {
  display: block;
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}

.stat-win .stat-number { color: #6abf50; }
.stat-loss .stat-number { color: #d05040; }

/* Account actions */
.account-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.stats-link {
  display: inline-block;
  padding: 10px 28px;
  font-size: 0.95rem;
  text-decoration: none;
}

.account-link {
  display: inline-block;
  padding: 10px 28px;
  font-size: 0.95rem;
  text-decoration: none;
}

.btn-logout {
  padding: 10px 28px;
  font-size: 0.95rem;
  background: rgba(160, 48, 32, 0.15);
  border: 1px solid rgba(160, 48, 32, 0.4);
  border-radius: 6px;
  color: #d05040;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-logout:hover {
  background: rgba(160, 48, 32, 0.3);
  color: #e06050;
}


/* Referral Section */
.referral-section {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid rgba(138, 106, 46, 0.3);
}

.referral-title {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1rem;
  margin-bottom: 6px;
  text-align: center;
}

.referral-desc {
  color: var(--text-secondary);
  font-size: 0.82rem;
  font-style: italic;
  margin-bottom: 12px;
  text-align: center;
}

.referral-code-display {
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  padding: 10px 14px;
  text-align: center;
  margin-bottom: 8px;
}

.referral-code {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold);
  font-weight: 700;
  letter-spacing: 3px;
}

.referral-code.dim {
  color: var(--text-secondary);
  font-size: 0.9rem;
  letter-spacing: 0;
}

.referral-buttons {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}

.btn-referral {
  flex: 1;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  text-align: center;
}

.btn-copy {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
}

.btn-share {
  background: rgba(67, 160, 212, 0.15);
  border: 1px solid rgba(67, 160, 212, 0.3);
  color: #60b8e0;
}

.referral-stats {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-bottom: 14px;
}

.ref-stat {
  text-align: center;
}

.ref-stat-value {
  display: block;
  font-family: 'Cinzel', serif;
  font-size: 1.3rem;
  color: var(--accent-gold);
  font-weight: 700;
}

.ref-stat-label {
  display: block;
  font-size: 0.7rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.referral-enter {
  padding-top: 10px;
  border-top: 1px solid rgba(138, 106, 46, 0.15);
}

.referral-enter-label {
  color: var(--text-secondary);
  font-size: 0.82rem;
  font-style: italic;
  margin-bottom: 8px;
  text-align: center;
}

.referral-enter-row {
  display: flex;
  gap: 8px;
}

.referral-input {
  flex: 1;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 6px;
  padding: 8px 12px;
  color: var(--text-bright);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  letter-spacing: 1px;
  text-transform: uppercase;
  text-align: center;
}

.referral-input::placeholder {
  color: var(--text-secondary);
  font-style: italic;
  text-transform: none;
  letter-spacing: 0;
}

.btn-apply {
  background: rgba(80, 160, 80, 0.15);
  border: 1px solid rgba(80, 160, 80, 0.3);
  color: #6abf50;
}

.referral-error {
  color: #d05040;
  font-size: 0.8rem;
  margin-top: 6px;
  text-align: center;
}

.referral-applied {
  color: #6abf50;
  font-size: 0.85rem;
  font-style: italic;
  text-align: center;
  padding-top: 10px;
  border-top: 1px solid rgba(138, 106, 46, 0.15);
}

.premium-badge-inline {
  color: var(--accent-gold);
  font-size: 1rem;
  margin-left: 4px;
}

.lock-icon {
  font-size: 0.8rem;
}

/* Subscription panel */
.sub-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sub-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid rgba(138, 106, 46, 0.15);
}

.sub-label {
  font-size: 0.85rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.sub-value {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-weight: 600;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 60%;
}

.sub-platform {
  color: var(--accent-gold);
}

.sub-active {
  color: #6abf50;
}

.sub-cancelling {
  color: #e08040;
}

.sub-actions {
  margin-top: 8px;
  text-align: center;
}

.btn-cancel-sub {
  padding: 8px 24px;
  font-size: 0.9rem;
  background: rgba(160, 48, 32, 0.15);
  border: 1px solid rgba(160, 48, 32, 0.4);
  border-radius: 6px;
  color: #d05040;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-cancel-sub:hover {
  background: rgba(160, 48, 32, 0.3);
  color: #e06050;
}

.sub-external {
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
  text-align: center;
  margin-top: 8px;
}

/* Cancel confirmation modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-box {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 400px;
  width: 100%;
}

.modal-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.2rem;
  margin-bottom: 12px;
  text-align: center;
}

.modal-body {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 20px;
  text-align: center;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.modal-actions button {
  padding: 8px 20px;
  font-size: 0.9rem;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
