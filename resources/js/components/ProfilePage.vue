<template>
  <div class="profile-page">
    <div class="card-panel">
      <h2 class="section-title">Profile</h2>

      <div class="profile-info">
        <div class="profile-avatar">&#9876;</div>
        <div class="profile-details">
          <h3 class="profile-name">{{ auth.state.user?.name }}</h3>
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

    <!-- My Advisors -->
    <div class="card-panel">
      <h2 class="section-title">My Advisors</h2>
      <div v-if="charsLoading" class="stats-loading">Loading advisors...</div>
      <div v-else class="char-grid">
        <div
          v-for="c in myCharacters"
          :key="c.id"
          class="char-card"
          :class="{ 'char-locked': !c.is_unlocked }"
          @click="c.is_unlocked ? (selectedChar = c) : null"
        >
          <img
            :src="c.image_url || '/images/character.png'"
            :alt="c.name"
            class="char-portrait"
            :class="{ 'char-grayscale': !c.is_unlocked }"
          />
          <span class="char-name">{{ c.name }}</span>
          <span v-if="!c.is_unlocked" class="char-lock">&#128274; {{ c.unlock_requirement }}</span>
        </div>
      </div>
    </div>

    <!-- Character detail modal -->
    <div v-if="selectedChar" class="char-modal-overlay" @click.self="selectedChar = null">
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

    <!-- Detailed Stats Link -->
    <div class="card-panel" style="text-align: center;">
      <router-link to="/stats" class="btn-primary stats-link">View Detailed Stats</router-link>
    </div>

    <!-- Account Management -->
    <div class="card-panel">
      <h2 class="section-title">Account</h2>
      <div class="account-actions">
        <a :href="authServiceUrl + '/settings'" target="_blank" rel="noopener" class="btn-primary account-link">
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

export default {
  name: 'ProfilePage',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      gameStats: {},
      statsLoading: true,
      myCharacters: [],
      charsLoading: true,
      selectedChar: null,
      authServiceUrl: import.meta.env.VITE_AUTH_URL || '',
      referralCode: null,
      referralStats: null,
      copied: false,
      canShare: !!navigator.share,
      referralInput: '',
      applyingReferral: false,
      referralError: '',
      referralApplied: false,
    };
  },
  computed: {
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
    const [statsRes, charsRes] = await Promise.allSettled([
      axios.get('/api/auth/stats'),
      axios.get('/api/my-characters'),
    ]);
    this.gameStats = statsRes.status === 'fulfilled' ? statsRes.value.data : {};
    this.statsLoading = false;
    this.myCharacters = charsRes.status === 'fulfilled' ? charsRes.value.data : [];
    this.charsLoading = false;
    this.fetchReferralData();
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
        this.copied = true;
        setTimeout(() => { this.copied = false; }, 2000);
      } catch {}
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

/* My Advisors */
.char-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.char-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px 6px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  cursor: pointer;
  transition: border-color 0.2s;
}

.char-card:hover:not(.char-locked) {
  border-color: var(--accent-gold);
}

.char-locked {
  opacity: 0.5;
  cursor: default;
}

.char-portrait {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid rgba(138, 106, 46, 0.3);
}

.char-grayscale {
  filter: grayscale(1);
}

.char-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.75rem;
  text-align: center;
}

.char-lock {
  font-size: 0.6rem;
  color: var(--text-secondary);
  text-align: center;
  line-height: 1.2;
}

/* Character detail modal */
.char-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

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

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .char-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>
