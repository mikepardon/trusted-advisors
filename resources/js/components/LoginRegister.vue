<template>
  <div class="auth-screen">
    <div class="banner-wrap">
      <img src="/images/banner.png" alt="Trusted Advisors" class="banner-img" />
    </div>

    <div class="card-panel auth-panel">
      <!-- VERIFICATION STEP -->
      <template v-if="step === 'verify'">
        <h3 class="verify-title">Verify Your Email</h3>
        <p class="verify-desc">A 6-digit code was sent to your email. Enter it below.</p>

        <form @submit.prevent="submitVerify" class="auth-form">
          <div class="form-group">
            <label for="verify-code">Verification Code</label>
            <input
              id="verify-code"
              v-model="verifyCode"
              type="text"
              inputmode="numeric"
              maxlength="6"
              autocomplete="one-time-code"
              placeholder="000000"
              class="code-input"
              required
            />
          </div>

          <p v-if="error" class="auth-error">{{ error }}</p>

          <button type="submit" class="btn-primary submit-btn" :disabled="loading">
            {{ loading ? 'Verifying...' : 'Verify' }}
          </button>

          <p class="resend-link">
            <button
              type="button"
              class="link-btn"
              :disabled="resendCooldown > 0"
              @click="resend"
            >
              {{ resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend Code' }}
            </button>
          </p>
        </form>
      </template>

      <!-- LOGIN / REGISTER FORM -->
      <template v-else>
        <div class="auth-tabs">
          <button
            :class="['tab-btn', { active: tab === 'login' }]"
            @click="tab = 'login'; error = ''"
          >Login</button>
          <button
            :class="['tab-btn', { active: tab === 'register' }]"
            @click="tab = 'register'; error = ''"
          >Register</button>
        </div>

        <form @submit.prevent="submit" class="auth-form">
          <div class="form-group">
            <label for="auth-name">{{ tab === 'login' ? 'Username or Email' : 'Username' }}</label>
            <input
              id="auth-name"
              v-model="name"
              type="text"
              autocomplete="username"
              required
            />
            <div v-if="tab === 'register' && name.length > 0" class="validation-hints">
              <span :class="name.length >= 4 ? 'hint-pass' : 'hint-fail'">Min 4 characters</span>
              <span :class="name.length <= 20 ? 'hint-pass' : 'hint-fail'">Max 20 characters</span>
              <span :class="/^[a-zA-Z0-9]*$/.test(name) ? 'hint-pass' : 'hint-fail'">Letters &amp; numbers only</span>
            </div>
          </div>

          <div v-if="tab === 'register'" class="form-group">
            <label for="auth-email">Email</label>
            <input
              id="auth-email"
              v-model="email"
              type="email"
              autocomplete="email"
              required
            />
          </div>

          <div class="form-group">
            <label for="auth-password">Password</label>
            <input
              id="auth-password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
            />
          </div>

          <div v-if="tab === 'register'" class="form-group">
            <label for="auth-confirm">Confirm Password</label>
            <input
              id="auth-confirm"
              v-model="passwordConfirmation"
              type="password"
              autocomplete="new-password"
              required
            />
          </div>

          <p v-if="error" class="auth-error">{{ error }}</p>

          <button type="submit" class="btn-primary submit-btn" :disabled="loading">
            {{ loading ? 'Please wait...' : (tab === 'login' ? 'Enter the Keep' : 'Join the Council') }}
          </button>
        </form>
      </template>
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
  data() {
    return {
      tab: 'login',
      step: 'form', // 'form' or 'verify'
      name: '',
      email: '',
      password: '',
      passwordConfirmation: '',
      verifyCode: '',
      pendingUserId: null,
      error: '',
      loading: false,
      resendCooldown: 0,
      resendTimer: null,
    };
  },
  beforeUnmount() {
    if (this.resendTimer) clearInterval(this.resendTimer);
  },
  methods: {
    async submit() {
      this.error = '';
      this.loading = true;
      try {
        if (this.tab === 'login') {
          const result = await this.auth.login(this.name, this.password);
          if (result.requires_verification) {
            this.pendingUserId = result.user_id;
            this.step = 'verify';
            this.startResendCooldown();
          }
        } else {
          const result = await this.auth.register(this.name, this.email, this.password, this.passwordConfirmation);
          if (result.requires_verification) {
            this.pendingUserId = result.user_id;
            this.step = 'verify';
            this.startResendCooldown();
          }
        }
      } catch (e) {
        const data = e.response?.data;
        if (data?.requires_verification) {
          this.pendingUserId = data.user_id;
          this.step = 'verify';
          this.startResendCooldown();
        } else if (data?.errors) {
          this.error = Object.values(data.errors).flat().join(' ');
        } else {
          this.error = data?.message || 'Something went wrong';
        }
      }
      this.loading = false;
    },
    async submitVerify() {
      this.error = '';
      this.loading = true;
      try {
        await this.auth.verifyEmail(this.pendingUserId, this.verifyCode);
      } catch (e) {
        const data = e.response?.data;
        if (data?.errors) {
          this.error = Object.values(data.errors).flat().join(' ');
        } else {
          this.error = data?.message || 'Verification failed';
        }
      }
      this.loading = false;
    },
    async resend() {
      this.error = '';
      try {
        await this.auth.resendVerification(this.pendingUserId);
        this.startResendCooldown();
      } catch (e) {
        this.error = e.response?.data?.message || 'Could not resend code';
      }
    },
    startResendCooldown() {
      this.resendCooldown = 60;
      if (this.resendTimer) clearInterval(this.resendTimer);
      this.resendTimer = setInterval(() => {
        this.resendCooldown--;
        if (this.resendCooldown <= 0) {
          clearInterval(this.resendTimer);
          this.resendTimer = null;
        }
      }, 1000);
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
  padding: 24px;
}

.auth-tabs {
  display: flex;
  gap: 0;
  margin-bottom: 24px;
  border-bottom: 2px solid var(--border-gold);
}

.tab-btn {
  flex: 1;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 1.1rem;
  padding: 10px 0;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: -2px;
  border-radius: 0;
  letter-spacing: 1px;
}

.tab-btn:hover {
  color: var(--text-bright);
  transform: none;
  box-shadow: none;
  background: none;
}

.tab-btn.active {
  color: var(--accent-gold);
  border-bottom-color: var(--accent-gold);
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.85rem;
  letter-spacing: 1px;
}

.form-group input {
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1.1rem;
  padding: 10px 14px;
  outline: none;
  transition: border-color 0.2s;
}

.form-group input:focus {
  border-color: var(--accent-gold);
  box-shadow: 0 0 8px rgba(212, 168, 67, 0.2);
}

.code-input {
  text-align: center;
  font-size: 1.8rem !important;
  letter-spacing: 8px;
  font-family: 'Cinzel', serif !important;
}

.validation-hints {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 2px;
}

.validation-hints span {
  font-size: 0.75rem;
  letter-spacing: 0.3px;
}

.hint-pass {
  color: var(--accent-green, #4caf50);
}

.hint-fail {
  color: var(--text-secondary, #a09080);
}

.auth-error {
  color: var(--accent-red);
  font-size: 0.9rem;
  text-align: center;
}

.submit-btn {
  margin-top: 8px;
  font-size: 1.1rem;
  padding: 12px;
  width: 100%;
}

.verify-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: center;
  font-size: 1.2rem;
  margin-bottom: 8px;
}

.verify-desc {
  color: var(--text-secondary);
  text-align: center;
  font-size: 0.9rem;
  margin-bottom: 20px;
}

.resend-link {
  text-align: center;
  margin-top: 4px;
}

.link-btn {
  background: none;
  border: none;
  color: var(--accent-gold);
  cursor: pointer;
  font-size: 0.85rem;
  text-decoration: underline;
  padding: 0;
}

.link-btn:disabled {
  color: var(--text-secondary);
  cursor: default;
  text-decoration: none;
}

@media (max-width: 768px) {
  .auth-panel {
    padding: 18px;
  }

  .tab-btn {
    font-size: 0.95rem;
  }

  .submit-btn {
    font-size: 1rem;
  }
}
</style>
