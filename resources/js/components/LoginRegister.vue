<template>
  <div class="auth-screen">
    <div class="banner-wrap">
      <img src="/images/banner.png" alt="Trusted Advisors" class="banner-img" />
    </div>

    <div class="card-panel auth-panel">
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
          <label for="auth-name">Username</label>
          <input
            id="auth-name"
            v-model="name"
            type="text"
            autocomplete="username"
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
      name: '',
      password: '',
      passwordConfirmation: '',
      error: '',
      loading: false,
    };
  },
  methods: {
    async submit() {
      this.error = '';
      this.loading = true;
      try {
        if (this.tab === 'login') {
          await this.auth.login(this.name, this.password);
        } else {
          await this.auth.register(this.name, this.password, this.passwordConfirmation);
        }
      } catch (e) {
        const data = e.response?.data;
        if (data?.errors) {
          this.error = Object.values(data.errors).flat().join(' ');
        } else {
          this.error = data?.message || 'Something went wrong';
        }
      }
      this.loading = false;
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
