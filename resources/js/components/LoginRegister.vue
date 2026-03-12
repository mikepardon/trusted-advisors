<template>
  <div class="auth-screen">
    <div class="banner-wrap">
      <img src="/images/banner.png" alt="Trusted Advisors" class="banner-img" />
    </div>

    <div class="card-panel auth-panel">
      <p class="auth-desc">Sign in to begin your adventure</p>

      <button class="btn-primary signin-btn" @click="signIn()">
        Sign In with MPGames
      </button>

      <div class="divider">
        <span>or</span>
      </div>

      <div class="social-buttons">
        <button class="social-btn google-btn" @click="signIn('google')">
          <svg class="social-icon" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
          Continue with Google
        </button>

        <button class="social-btn apple-btn" @click="signIn('apple')">
          <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
          Continue with Apple
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuth } from '../stores/auth';

export default {
  name: 'LoginRegister',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  methods: {
    signIn(provider = null) {
      this.auth.startLogin(provider);
    },
  },
};
</script>

<style scoped>
.auth-screen {
  max-width: 440px;
  margin: 0 auto;
}

.banner-wrap {
  text-align: center;
  margin-bottom: 25px;
}

.banner-img {
  max-width: 100%;
  height: auto;
}

.auth-panel {
  padding: 32px 24px;
  text-align: center;
}

.auth-desc {
  color: var(--text-secondary);
  font-size: 1rem;
  margin-bottom: 24px;
  font-style: italic;
}

.signin-btn {
  font-size: 1.1rem;
  padding: 14px 32px;
  width: 100%;
}

.divider {
  display: flex;
  align-items: center;
  margin: 20px 0;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid var(--border-color, #334155);
}

.divider span {
  padding: 0 12px;
  color: var(--text-secondary, #94a3b8);
  font-size: 0.85rem;
}

.social-buttons {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.social-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 12px 20px;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: background 0.15s, opacity 0.15s;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  letter-spacing: 0;
}

.social-btn:hover {
  transform: none;
  box-shadow: none;
}

.google-btn:hover {
  background: #f0f0f0;
}

.apple-btn:hover {
  background: #1a1a1a;
}

.social-icon {
  width: 20px;
  height: 20px;
  margin-right: 10px;
}

.google-btn {
  background: #fff;
  color: #333;
}

.apple-btn {
  background: #000;
  color: #fff;
  border: 1px solid var(--border-color, #334155);
}

@media (max-width: 768px) {
  .auth-panel {
    padding: 24px 18px;
  }

  .signin-btn {
    font-size: 1rem;
  }
}
</style>
