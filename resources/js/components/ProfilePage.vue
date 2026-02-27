<template>
  <div class="profile-page">
    <div class="card-panel">
      <h2 class="section-title">Profile</h2>

      <div class="profile-info">
        <div class="profile-avatar">&#9876;</div>
        <div class="profile-details">
          <h3 class="profile-name">{{ auth.state.user?.name }}</h3>
          <p class="profile-joined">Member of the realm</p>
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

    <!-- Change Password -->
    <div class="card-panel">
      <h2 class="section-title">Change Password</h2>
      <form @submit.prevent="changePassword" class="password-form">
        <div class="form-group">
          <label>Current Password</label>
          <input v-model="pw.current_password" type="password" required />
        </div>
        <div class="form-group">
          <label>New Password</label>
          <input v-model="pw.new_password" type="password" required minlength="4" />
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <input v-model="pw.new_password_confirmation" type="password" required minlength="4" />
        </div>
        <p v-if="pwError" class="pw-error">{{ pwError }}</p>
        <p v-if="pwSuccess" class="pw-success">{{ pwSuccess }}</p>
        <button type="submit" class="btn-primary pw-btn" :disabled="pwSaving">
          {{ pwSaving ? 'Saving...' : 'Update Password' }}
        </button>
      </form>
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
      pw: { current_password: '', new_password: '', new_password_confirmation: '' },
      pwError: '',
      pwSuccess: '',
      pwSaving: false,
    };
  },
  async mounted() {
    try {
      const res = await axios.get('/api/auth/stats');
      this.gameStats = res.data;
    } catch {
      this.gameStats = {};
    }
    this.statsLoading = false;
  },
  methods: {
    async changePassword() {
      this.pwError = '';
      this.pwSuccess = '';

      if (this.pw.new_password !== this.pw.new_password_confirmation) {
        this.pwError = 'Passwords do not match.';
        return;
      }

      this.pwSaving = true;
      try {
        await axios.post('/api/auth/change-password', this.pw);
        this.pwSuccess = 'Password updated successfully.';
        this.pw = { current_password: '', new_password: '', new_password_confirmation: '' };
      } catch (e) {
        this.pwError = e.response?.data?.message || 'Failed to update password.';
      }
      this.pwSaving = false;
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

/* Password form */
.password-form {
  max-width: 360px;
  margin: 0 auto;
}

.form-group {
  margin-bottom: 12px;
}

.form-group label {
  display: block;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 4px;
}

.form-group input {
  width: 100%;
  background: var(--bg-primary);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--text-bright);
  padding: 8px 12px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
}

.form-group input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.pw-error {
  color: var(--accent-red);
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.pw-success {
  color: var(--accent-green);
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.pw-btn {
  display: block;
  margin: 0 auto;
  padding: 8px 24px;
  font-size: 0.95rem;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
