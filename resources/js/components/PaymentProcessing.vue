<template>
  <div class="processing-page">
    <div class="card-panel processing-card">
      <!-- Processing state -->
      <div v-if="state === 'processing'" class="state-content">
        <div class="spinner"></div>
        <h2 class="state-title">Processing your payment...</h2>
        <p class="state-desc">Please wait while we confirm your purchase.</p>
      </div>

      <!-- Success state -->
      <div v-else-if="state === 'success'" class="state-content">
        <div class="success-icon">&#10003;</div>
        <h2 class="state-title">{{ successTitle }}</h2>
        <p class="state-desc">{{ successDesc }}</p>
        <div class="state-actions">
          <button class="btn-primary" @click="$router.push('/profile')">Go to Profile</button>
          <button @click="$router.push('/shop')">Back to Shop</button>
        </div>
      </div>

      <!-- Timeout state -->
      <div v-else-if="state === 'timeout'" class="state-content">
        <div class="timeout-icon">&#9203;</div>
        <h2 class="state-title">Payment is taking longer than expected</h2>
        <p class="state-desc">Your premium will activate shortly. You can check your profile for updates.</p>
        <div class="state-actions">
          <button class="btn-primary" @click="$router.push('/profile')">Go to Profile</button>
          <button @click="$router.push('/shop')">Back to Shop</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';

export default {
  name: 'PaymentProcessing',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      state: 'processing', // 'processing' | 'success' | 'timeout'
      successTitle: 'Premium Activated!',
      successDesc: 'You now have access to all premium features.',
      pollInterval: null,
      timeoutTimer: null,
    };
  },
  mounted() {
    this.subscribeToEvents();
    this.startPolling();
    this.timeoutTimer = setTimeout(() => {
      if (this.state === 'processing') {
        this.state = 'timeout';
        this.cleanup();
      }
    }, 60000);
  },
  beforeUnmount() {
    this.cleanup();
  },
  methods: {
    subscribeToEvents() {
      const userId = this.auth.state.user?.id;
      if (!userId || !window.Echo) return;

      this._channel = window.Echo.private(`user.${userId}`);
      this._channel.listen('PremiumStatusChanged', (data) => {
        if (data.status === 'activated') {
          this.successTitle = 'Premium Activated!';
          this.successDesc = 'You now have access to all premium features.';
        } else if (data.status === 'purchase_completed') {
          this.successTitle = 'Purchase Complete!';
          this.successDesc = 'Your item has been unlocked.';
        }
        this.auth.updateUserStats({ is_premium: data.is_premium });
        this.state = 'success';
        this.cleanup();
      });
    },
    startPolling() {
      this.pollInterval = setInterval(async () => {
        try {
          const res = await axios.get('/api/premium/status');
          if (res.data.is_premium && !this.auth.state.user?.is_premium) {
            this.auth.updateUserStats({ is_premium: true });
            this.successTitle = 'Premium Activated!';
            this.successDesc = 'You now have access to all premium features.';
            this.state = 'success';
            this.cleanup();
          }
        } catch {
          // ignore polling errors
        }
      }, 3000);
    },
    cleanup() {
      if (this.pollInterval) {
        clearInterval(this.pollInterval);
        this.pollInterval = null;
      }
      if (this.timeoutTimer) {
        clearTimeout(this.timeoutTimer);
        this.timeoutTimer = null;
      }
      if (this._channel) {
        this._channel.stopListening('PremiumStatusChanged');
        this._channel = null;
      }
    },
  },
};
</script>

<style scoped>
.processing-page {
  max-width: 500px;
  margin: 40px auto;
  padding: 0 16px;
}

.processing-card {
  text-align: center;
  padding: 40px 24px;
}

.state-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.state-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
}

.state-desc {
  color: var(--text-secondary);
  font-size: 0.95rem;
  max-width: 320px;
}

.state-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 12px;
  width: 100%;
  max-width: 260px;
}

.state-actions button {
  width: 100%;
  padding: 10px 20px;
  font-size: 0.95rem;
}

/* Spinner */
.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid rgba(138, 106, 46, 0.2);
  border-top-color: var(--accent-gold);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Success icon */
.success-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: rgba(74, 138, 58, 0.2);
  border: 3px solid var(--accent-green);
  color: var(--accent-green);
  font-size: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Timeout icon */
.timeout-icon {
  font-size: 3rem;
  color: var(--text-secondary);
}
</style>
