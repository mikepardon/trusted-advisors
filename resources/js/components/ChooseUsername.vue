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

      <div class="referral-group">
        <label class="referral-label">Have a referral code? <span class="optional-tag">(optional)</span></label>
        <input
          v-model="referralCode"
          type="text"
          class="referral-input"
          placeholder="Enter referral code"
          maxlength="10"
        />
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

<script setup lang="ts">
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import axios, { isAxiosError } from "axios";
import { useAuth, type User } from "../stores/auth";

const auth = useAuth();
const router = useRouter();

const username = ref("");
const referralCode = ref("");
const available = ref<boolean | undefined>(undefined);
const checking = ref(false);
const submitting = ref(false);
const validationError = ref("");
const debounceTimer = ref<ReturnType<typeof setTimeout>>();

const canSubmit = computed(
  () =>
    username.value.length >= 3 &&
    username.value.length <= 20 &&
    !validationError.value &&
    available.value === true &&
    !checking.value,
);

function onInput(): void {
  available.value = undefined;
  validationError.value = "";

  if (debounceTimer.value) {
    clearTimeout(debounceTimer.value);
  }

  const value = username.value.trim();

  if (!value) {
    return;
  }

  if (value.length < 3) {
    validationError.value = "At least 3 characters";
    return;
  }

  if (!/^[a-zA-Z0-9]+$/.test(value)) {
    validationError.value = "Letters and numbers only";
    return;
  }

  checking.value = true;
  debounceTimer.value = setTimeout(() => checkAvailability(value), 500);
}

async function checkAvailability(name: string): Promise<void> {
  try {
    const response = await axios.get<{ available: boolean }>(
      `/api/auth/check-username/${encodeURIComponent(name)}`,
    );
    // Only apply if input hasn't changed
    if (username.value.trim().toLowerCase() === name.toLowerCase()) {
      available.value = response.data.available;
    }
  } catch {
    validationError.value = "Could not check availability";
  } finally {
    checking.value = false;
  }
}

async function submit(): Promise<void> {
  if (!canSubmit.value) {
    return;
  }
  submitting.value = true;

  try {
    const payload: { username: string; referral_code?: string } = {
      username: username.value.trim(),
    };
    if (referralCode.value.trim()) {
      payload.referral_code = referralCode.value.trim();
    }
    const response = await axios.post<User>("/api/auth/set-username", payload);
    auth.state.user = response.data;
    router.push("/");
  } catch (error) {
    validationError.value = submitErrorMessage(error);
  } finally {
    submitting.value = false;
  }
}

function submitErrorMessage(error: unknown): string {
  if (isAxiosError(error)) {
    const data = error.response?.data;
    return data?.errors?.username?.[0] ?? data?.message ?? "Something went wrong";
  }
  return "Something went wrong";
}
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

.referral-group {
  margin-bottom: 24px;
  text-align: left;
}

.referral-label {
  display: block;
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-bottom: 6px;
}

.optional-tag {
  font-style: italic;
  opacity: 0.7;
}

.referral-input {
  width: 100%;
  padding: 10px 14px;
  font-size: 0.95rem;
  font-family: 'Cinzel', serif;
  background: var(--bg-primary);
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 6px;
  color: var(--text-primary);
  outline: none;
  letter-spacing: 1px;
  text-transform: uppercase;
  text-align: center;
  transition: border-color 0.2s;
}

.referral-input:focus {
  border-color: var(--accent-gold);
}

.referral-input::placeholder {
  color: var(--text-secondary);
  opacity: 0.5;
  text-transform: none;
  letter-spacing: 0;
}

.confirm-btn {
  width: 100%;
  padding: 14px;
  font-size: 1.1rem;
  letter-spacing: 2px;
}
</style>
