<template>
  <div v-if="challenge && challenge.title" class="daily-banner">
    <div class="banner-row">
      <div class="banner-text">
        <span class="banner-label">Daily</span>
        <div class="banner-info">
          <span class="banner-title">{{ challenge.title }}</span>
          <p class="banner-desc">{{ challenge.description }}</p>
        </div>
      </div>
    </div>

    <div class="banner-footer">
      <span class="banner-reward">+{{ challenge.reward_xp }} XP</span>

      <button
        v-if="challenge.status === 'pending'"
        class="banner-btn"
        @click="showIntro = true"
      >
        Play
      </button>
      <button
        v-else-if="challenge.status === 'in_progress' && challenge.game_id"
        class="banner-btn"
        @click="resumeRun"
      >
        Resume
      </button>
      <span v-else-if="challenge.status === 'won'" class="banner-result won">Completed ✓</span>
      <span v-else-if="challenge.status === 'lost'" class="banner-result lost">Better luck tomorrow</span>
    </div>

    <span v-if="error" class="banner-error">{{ error }}</span>

    <button class="banner-archive" @click="showArchive = true">
      View past challenges
    </button>

    <DailyChallengeIntro
      :open="showIntro"
      :challenge="challenge"
      :starting="starting"
      @close="showIntro = false"
      @begin="startRun"
    />
    <DailyChallengeArchive :open="showArchive" @close="showArchive = false" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import DailyChallengeIntro from "./DailyChallengeIntro.vue";
import DailyChallengeArchive from "./DailyChallengeArchive.vue";

interface LoadoutItem {
  name: string;
  type?: string;
  cadence?: string;
  description?: string;
}

interface DailyCharacter {
  name: string;
  image_url?: string;
  description?: string;
  wild_ability_description?: string;
}

interface DailyChallenge {
  title: string | undefined;
  description: string | undefined;
  reward_xp: number | undefined;
  status: string | undefined;
  game_id: number | undefined;
  completed: boolean | undefined;
  character?: DailyCharacter;
  loadout?: LoadoutItem[];
}

const router = useRouter();
const challenge = ref<DailyChallenge | undefined>(undefined);
const starting = ref(false);
const showIntro = ref(false);
const showArchive = ref(false);
const error = ref<string | undefined>(undefined);

onMounted(async () => {
  try {
    const response = await axios.get<DailyChallenge | undefined>("/api/daily-challenge");
    if (response.data) {
      challenge.value = response.data;
    }
  } catch {
    // Ignore: banner simply stays hidden if the challenge fails to load.
  }
});

async function startRun(): Promise<void> {
  starting.value = true;
  error.value = undefined;
  try {
    const { data } = await axios.post<{ game_id: number }>("/api/daily-challenge/start");
    void router.push(`/game/${data.game_id}`);
  } catch {
    error.value = "Could not start today's challenge.";
    starting.value = false;
    showIntro.value = false;
  }
}

function resumeRun(): void {
  if (challenge.value?.game_id) {
    void router.push(`/game/${challenge.value.game_id}`);
  }
}
</script>

<style scoped>
.daily-banner {
  background: rgba(13, 10, 6, 0.65);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 10px;
}

.banner-row {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  gap: 10px;
}

.banner-text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.banner-label {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.18);
  padding: 2px 8px;
  border-radius: 3px;
  font-weight: 700;
  flex-shrink: 0;
  margin-top: 2px;
  font-family: "Cinzel", serif;
}

.banner-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 2px;
}

.banner-title {
  font-family: "Cinzel", serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  font-weight: 700;
}

.banner-desc {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.8rem;
  line-height: 1.4;
}

.banner-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-top: 10px;
}

.banner-reward {
  font-size: 0.75rem;
  color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.1);
  padding: 2px 8px;
  border-radius: 3px;
  font-family: "Cinzel", serif;
  letter-spacing: 0.5px;
}

.banner-btn {
  background: var(--accent-gold, #d4a843);
  color: #1a1409;
  border: none;
  border-radius: 6px;
  padding: 5px 16px;
  font-weight: 700;
  font-family: "Cinzel", serif;
  cursor: pointer;
}

.banner-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.banner-result {
  font-size: 0.8rem;
  font-family: "Cinzel", serif;
}

.banner-result.won {
  color: #7bd88f;
}

.banner-result.lost {
  color: var(--text-secondary);
}

.banner-error {
  display: block;
  margin-top: 6px;
  color: #f0a0a0;
  font-size: 0.75rem;
}

.banner-archive {
  display: block;
  margin: 8px auto 0;
  background: none;
  border: none;
  color: var(--accent-gold, #d4a843);
  font-size: 0.72rem;
  text-decoration: underline;
  cursor: pointer;
  opacity: 0.85;
}
</style>
