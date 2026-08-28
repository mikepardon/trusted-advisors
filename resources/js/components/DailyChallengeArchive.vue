<template>
  <Teleport to="body">
    <Transition appear name="dca">
      <div v-if="open" class="dca-overlay" @click.self="$emit('close')">
        <div class="dca-modal">
          <button class="dca-close" aria-label="Close" @click="$emit('close')">&times;</button>

          <span class="dca-label">Challenge Archive</span>
          <h2 class="dca-title">Past Daily Trials</h2>
          <p v-if="!isPremium" class="dca-upsell">
            🔒 Replaying past challenges is a premium feature.
          </p>

          <div v-if="loading" class="dca-empty">Loading…</div>
          <div v-else-if="challenges.length === 0" class="dca-empty">
            No past challenges yet — check back tomorrow.
          </div>

          <div v-else class="dca-list">
            <div v-for="challenge in challenges" :key="challenge.id" class="dca-item">
              <div class="dca-item-main">
                <span class="dca-item-date">{{ challenge.date }}</span>
                <span class="dca-item-title">{{ challenge.title }}</span>
                <span class="dca-item-goal">{{ challenge.description }}</span>
              </div>
              <div class="dca-item-action">
                <span v-if="challenge.status === 'won'" class="dca-badge dca-badge--won">Won ✓</span>
                <span v-else-if="challenge.status === 'lost'" class="dca-badge dca-badge--lost">Lost</span>
                <button
                  v-else-if="challenge.status === 'in_progress' && challenge.game_id"
                  class="dca-play"
                  @click="resume(challenge)"
                >
                  Resume
                </button>
                <button
                  v-else-if="isPremium"
                  class="dca-play"
                  :disabled="startingId === challenge.id"
                  @click="play(challenge)"
                >
                  {{ startingId === challenge.id ? "…" : "Play" }}
                </button>
                <span v-else class="dca-lock">🔒</span>
              </div>
            </div>
          </div>

          <span v-if="error" class="dca-error">{{ error }}</span>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import axios from "axios";
import { ref, watch } from "vue";
import { useRouter } from "vue-router";

interface ArchiveChallenge {
  id: number;
  date: string;
  title: string;
  description: string;
  reward_xp: number;
  status: string;
  game_id?: number;
}

const { open = false } = defineProps<{ open?: boolean }>();
defineEmits<{ close: [] }>();

const router = useRouter();
const loading = ref(false);
const challenges = ref<ArchiveChallenge[]>([]);
const isPremium = ref(false);
const startingId = ref<number | undefined>(undefined);
const error = ref<string | undefined>(undefined);

async function load(): Promise<void> {
  loading.value = true;
  error.value = undefined;
  try {
    const { data } = await axios.get<{
      challenges: ArchiveChallenge[];
      is_premium: boolean;
    }>("/api/daily-challenges/history");
    challenges.value = data.challenges;
    isPremium.value = data.is_premium;
  } catch {
    error.value = "Could not load the archive.";
  } finally {
    loading.value = false;
  }
}

async function play(challenge: ArchiveChallenge): Promise<void> {
  startingId.value = challenge.id;
  error.value = undefined;
  try {
    const { data } = await axios.post<{ game_id: number }>(
      `/api/daily-challenges/${challenge.id}/start`,
    );
    void router.push(`/game/${data.game_id}`);
  } catch {
    error.value = "Could not start that challenge.";
    startingId.value = undefined;
  }
}

function resume(challenge: ArchiveChallenge): void {
  if (challenge.game_id) {
    void router.push(`/game/${challenge.game_id}`);
  }
}

watch(
  () => open,
  (isOpen) => {
    if (isOpen) {
      void load();
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.dca-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}
.dca-modal {
  position: relative;
  background: #241b12;
  color: #f3e9d2;
  border: 2px solid #c9a24b;
  border-radius: 12px;
  width: min(560px, 100%);
  max-height: 88vh;
  overflow-y: auto;
  padding: 1.5rem;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.55);
}
.dca-close {
  position: absolute;
  top: 0.6rem;
  right: 0.8rem;
  background: none;
  border: none;
  color: #f3e9d2;
  font-size: 1.8rem;
  line-height: 1;
  cursor: pointer;
}
.dca-label {
  font-size: 0.65rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #e8c667;
  font-family: "Cinzel", serif;
}
.dca-title {
  font-family: "Cinzel", serif;
  color: #e8c667;
  font-size: 1.4rem;
  margin: 0.2rem 0 0.5rem;
}
.dca-upsell {
  margin: 0 0 0.75rem;
  font-size: 0.85rem;
  color: #c9a24b;
}
.dca-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.dca-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  background: #2f2417;
  border-radius: 8px;
  padding: 0.6rem 0.75rem;
}
.dca-item-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.dca-item-date {
  font-size: 0.65rem;
  color: #c9a24b;
  letter-spacing: 1px;
}
.dca-item-title {
  font-family: "Cinzel", serif;
  font-weight: 700;
  font-size: 0.9rem;
}
.dca-item-goal {
  font-size: 0.75rem;
  opacity: 0.75;
}
.dca-item-action {
  flex-shrink: 0;
}
.dca-play {
  background: #c9a24b;
  color: #241b12;
  border: none;
  border-radius: 6px;
  padding: 0.4rem 0.9rem;
  font-weight: 700;
  font-family: "Cinzel", serif;
  cursor: pointer;
}
.dca-play:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.dca-badge {
  font-size: 0.8rem;
  font-weight: 700;
  font-family: "Cinzel", serif;
}
.dca-badge--won {
  color: #6abf50;
}
.dca-badge--lost {
  color: #c98a7a;
}
.dca-lock {
  font-size: 1.1rem;
  opacity: 0.7;
}
.dca-empty {
  padding: 1.5rem;
  text-align: center;
  opacity: 0.7;
}
.dca-error {
  display: block;
  margin-top: 0.75rem;
  color: #f0a0a0;
  font-size: 0.8rem;
}
.dca-enter-active,
.dca-leave-active {
  transition: opacity 0.2s ease;
}
.dca-enter-from,
.dca-leave-to {
  opacity: 0;
}
</style>
