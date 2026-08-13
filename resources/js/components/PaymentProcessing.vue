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

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import axios from "axios";
import { useAuth } from "../stores/auth";

interface PremiumStatusEvent {
  status?: string;
  is_premium: boolean;
}

interface EchoChannel {
  listen: (event: string, callback: (data: PremiumStatusEvent) => void) => void;
  stopListening: (event: string) => void;
}

interface EchoInstance {
  private: (channel: string) => EchoChannel;
}

const auth = useAuth();

const state = ref<"processing" | "success" | "timeout">("processing");
const successTitle = ref("Premium Activated!");
const successDesc = ref("You now have access to all premium features.");
const pollInterval = ref<ReturnType<typeof setInterval> | undefined>(undefined);
const timeoutTimer = ref<ReturnType<typeof setTimeout> | undefined>(undefined);
const channel = ref<EchoChannel | undefined>(undefined);

function subscribeToEvents(): void {
  const userId = auth.state.user?.id;
  const echo = (window as unknown as { Echo?: EchoInstance }).Echo;
  if (!userId || !echo) {
    return;
  }

  channel.value = echo.private(`user.${userId}`);
  channel.value.listen("PremiumStatusChanged", (data) => {
    if (data.status === "activated") {
      successTitle.value = "Premium Activated!";
      successDesc.value = "You now have access to all premium features.";
    } else if (data.status === "purchase_completed") {
      successTitle.value = "Purchase Complete!";
      successDesc.value = "Your item has been unlocked.";
    }
    auth.updateUserStats({ is_premium: data.is_premium });
    state.value = "success";
    cleanup();
  });
}

function startPolling(): void {
  pollInterval.value = setInterval(async () => {
    try {
      const response = await axios.get<{ is_premium: boolean }>("/api/premium/status");
      if (!response.data.is_premium || auth.state.user?.is_premium) {
        return;
      }
      auth.updateUserStats({ is_premium: true });
      successTitle.value = "Premium Activated!";
      successDesc.value = "You now have access to all premium features.";
      state.value = "success";
      cleanup();
    } catch {
      // ignore polling errors
    }
  }, 3000);
}

function cleanup(): void {
  if (pollInterval.value) {
    clearInterval(pollInterval.value);
    pollInterval.value = undefined;
  }
  if (timeoutTimer.value) {
    clearTimeout(timeoutTimer.value);
    timeoutTimer.value = undefined;
  }
  if (channel.value) {
    channel.value.stopListening("PremiumStatusChanged");
    channel.value = undefined;
  }
}

onMounted(() => {
  subscribeToEvents();
  startPolling();
  timeoutTimer.value = setTimeout(() => {
    if (state.value !== "processing") {
      return;
    }
    state.value = "timeout";
    cleanup();
  }, 60_000);
});

onBeforeUnmount(() => {
  cleanup();
});
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
