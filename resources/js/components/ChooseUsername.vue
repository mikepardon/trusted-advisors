<template>
  <div class="choose-username-screen">
    <div class="card-panel username-panel">
      <h2 class="panel-title">Choose Your Username</h2>

      <div class="warning-banner">
        <span class="warning-icon">&#9888;</span>
        This cannot be changed later — choose wisely!
      </div>

      <div class="input-group">
        <input
          v-model="username"
          type="text"
          class="username-input"
          placeholder="Enter username..."
          maxlength="20"
          @input="onInput"
        />
        <div class="input-feedback">
          <span v-if="checking" class="status-checking">Checking...</span>
          <span v-else-if="validationError" class="status-invalid">&#10008; {{ validationError }}</span>
          <span v-else-if="available === true" class="status-available">&#10004; Available</span>
          <span v-else-if="available === false" class="status-taken">&#10008; Already taken</span>
        </div>
        <div class="char-count">{{ username.length }}/20</div>
      </div>

      <button
        class="btn-primary confirm-btn"
        :disabled="!canSubmit || submitting"
        @click="submit"
      >
        {{ submitting ? 'Confirming...' : 'Confirm' }}
      </button>
    </div>
  </div>
</template>

<script>
import { useAuth } from '../stores/auth';
import axios from 'axios';

export default {
  name: 'ChooseUsername',
  setup() {
    const auth = useAuth();
    return { auth };
  },
  data() {
    return {
      username: '',
      available: null,
      checking: false,
      submitting: false,
      validationError: '',
      debounceTimer: null,
    };
  },
  computed: {
    canSubmit() {
      return (
        this.username.length >= 3 &&
        this.username.length <= 20 &&
        !this.validationError &&
        this.available === true &&
        !this.checking
      );
    },
  },
  methods: {
    onInput() {
      this.available = null;
      this.validationError = '';

      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer);
      }

      const val = this.username.trim();

      if (!val) return;

      if (val.length < 3) {
        this.validationError = 'At least 3 characters';
        return;
      }

      if (!/^[a-zA-Z0-9]+$/.test(val)) {
        this.validationError = 'Letters and numbers only';
        return;
      }

      this.checking = true;
      this.debounceTimer = setTimeout(() => this.checkAvailability(val), 500);
    },

    async checkAvailability(name) {
      try {
        const res = await axios.get(`/api/auth/check-username/${encodeURIComponent(name)}`);
        // Only apply if input hasn't changed
        if (this.username.trim().toLowerCase() === name.toLowerCase()) {
          this.available = res.data.available;
        }
      } catch {
        this.validationError = 'Could not check availability';
      } finally {
        this.checking = false;
      }
    },

    async submit() {
      if (!this.canSubmit) return;
      this.submitting = true;

      try {
        const res = await axios.post('/api/auth/set-username', {
          username: this.username.trim(),
        });
        this.auth.state.user = res.data;
        this.$router.push('/');
      } catch (e) {
        const msg = e.response?.data?.errors?.username?.[0]
          || e.response?.data?.message
          || 'Something went wrong';
        this.validationError = msg;
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>

<style scoped>
.choose-username-screen {
  max-width: 440px;
  margin: 80px auto 0;
  padding: 0 16px;
}

.username-panel {
  padding: 32px 24px;
  text-align: center;
}

.panel-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
  margin-bottom: 20px;
}

.warning-banner {
  background: rgba(160, 48, 32, 0.15);
  border: 1px solid var(--accent-red);
  border-radius: 6px;
  padding: 10px 14px;
  color: #e8a090;
  font-size: 0.85rem;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  text-align: left;
}

.warning-icon {
  font-size: 1.1rem;
  flex-shrink: 0;
}

.input-group {
  position: relative;
  margin-bottom: 24px;
}

.username-input {
  width: 100%;
  padding: 12px 14px;
  font-size: 1.1rem;
  font-family: 'Cinzel', serif;
  background: var(--bg-primary);
  border: 2px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  outline: none;
  transition: border-color 0.2s;
}

.username-input:focus {
  border-color: var(--accent-gold);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.2);
}

.username-input::placeholder {
  color: var(--text-secondary);
  opacity: 0.6;
}

.input-feedback {
  min-height: 24px;
  margin-top: 8px;
  font-size: 0.85rem;
}

.status-checking {
  color: var(--text-secondary);
}

.status-available {
  color: var(--accent-green);
}

.status-taken,
.status-invalid {
  color: var(--accent-red);
}

.char-count {
  position: absolute;
  right: 12px;
  top: 14px;
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.confirm-btn {
  width: 100%;
  padding: 14px;
  font-size: 1.1rem;
  letter-spacing: 2px;
}
</style>
