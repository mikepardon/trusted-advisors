<template>
  <div class="callback-screen">
    <div class="card-panel callback-panel">
      <div v-if="error" class="callback-error">
        <h3 class="error-title">Authentication Failed</h3>
        <p class="error-message">{{ error }}</p>
        <p class="redirect-msg">Redirecting to sign in...</p>
      </div>
      <div v-else class="callback-loading">
        <p class="loading-text">Signing you in...</p>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuth } from '../stores/auth';

export default {
  name: 'AuthCallback',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      error: '',
    };
  },
  async mounted() {
    const params = new URLSearchParams(window.location.search);
    const code = params.get('code');
    const state = params.get('state');

    if (!code) {
      this.error = params.get('error_description') || 'No authorization code received.';
      setTimeout(() => this.$router.push('/'), 3000);
      return;
    }

    try {
      await this.auth.handleCallback(code, state);
      if (this.auth.state.user?.needs_username) {
        this.$router.push('/choose-username');
      } else {
        this.$router.push('/');
      }
    } catch (e) {
      this.error = e.response?.data?.message || e.message || 'Authentication failed.';
      setTimeout(() => this.$router.push('/'), 3000);
    }
  },
};
</script>

<style scoped>
.callback-screen {
  max-width: 440px;
  margin: 80px auto 0;
}

.callback-panel {
  padding: 40px 24px;
  text-align: center;
}

.loading-text {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.2rem;
}

.error-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-red);
  font-size: 1.2rem;
  margin-bottom: 10px;
}

.error-message {
  color: var(--text-primary);
  font-size: 0.95rem;
  margin-bottom: 16px;
}

.redirect-msg {
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
}
</style>
