<template>
  <div class="season-page">
    <div v-if="loading" class="season-loading">Loading season...</div>

    <template v-else-if="seasonData">
      <!-- Season Header -->
      <div class="card-panel">
        <h2 class="section-title">{{ seasonData.season.name }}</h2>
        <p class="season-dates">
          {{ formatDate(seasonData.season.starts_at) }} &mdash; {{ formatDate(seasonData.season.ends_at) }}
        </p>

        <!-- Time progress bar -->
        <div class="time-progress">
          <div class="time-bar-track">
            <div class="time-bar-fill" :style="{ width: timePercent + '%' }"></div>
          </div>
          <span class="time-label">{{ timeLabel }}</span>
        </div>

        <!-- User rank -->
        <div v-if="seasonData.user_rank" class="user-rank">
          Your Rank: <strong>#{{ seasonData.user_rank }}</strong> of {{ seasonData.total_players }}
        </div>
      </div>

      <!-- Placement Rewards -->
      <div v-if="seasonData.season.rewards && seasonData.season.rewards.length > 0" class="card-panel">
        <h2 class="section-title">Placement Rewards</h2>
        <div class="rewards-list">
          <div v-for="r in seasonData.season.rewards" :key="r.id" class="reward-row" :class="'reward-place-' + r.placement">
            <div class="reward-place">{{ ordinal(r.placement) }}</div>
            <div class="reward-details">
              <span v-if="r.reward_xp" class="reward-tag">+{{ r.reward_xp }} XP</span>
              <span v-if="r.reward_coins" class="reward-tag reward-coins">+{{ r.reward_coins }} &#129689;</span>
              <span v-if="r.reward_character" class="reward-tag reward-char">
                <img v-if="r.reward_character.image_url" :src="r.reward_character.image_url" class="reward-char-img" />
                {{ r.reward_character.name }}
              </span>
              <span v-if="r.reward_title" class="reward-tag reward-title-tag">&#127942; {{ r.reward_title }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Leaderboard -->
      <div class="card-panel">
        <h2 class="section-title">Leaderboard</h2>
        <div v-if="seasonData.leaderboard.length === 0" class="empty-lb">No games played yet this season.</div>
        <div v-else class="lb-list">
          <div
            v-for="entry in seasonData.leaderboard"
            :key="entry.user_id"
            class="lb-row"
            :class="{ 'lb-highlight': entry.user_id === currentUserId }"
          >
            <span class="lb-rank">#{{ entry.rank }}</span>
            <span class="lb-name">{{ entry.name }}</span>
            <span class="lb-stat">{{ entry.wins }}W</span>
            <span class="lb-stat lb-elo">{{ entry.elo_rating }} ELO</span>
          </div>
        </div>
      </div>

      <!-- Season selector -->
      <div v-if="allSeasons.length > 1" class="card-panel">
        <h2 class="section-title">Other Seasons</h2>
        <div class="season-picker">
          <button
            v-for="s in allSeasons"
            :key="s.id"
            class="season-pick-btn"
            :class="{ active: s.id === selectedSeasonId }"
            @click="loadSeason(s.id)"
          >
            {{ s.name }}
            <span v-if="s.is_active" class="active-dot"></span>
          </button>
        </div>
      </div>
    </template>

    <div v-else class="season-loading">No active season found.</div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { useAuth } from "../stores/auth";

interface SeasonRewardCharacter {
  name: string;
  image_url: string | undefined;
}

interface SeasonReward {
  id: number;
  placement: number;
  reward_xp: number | undefined;
  reward_coins: number | undefined;
  reward_character: SeasonRewardCharacter | undefined;
  reward_title: string | undefined;
}

interface Season {
  id: number;
  name: string;
  starts_at: string;
  ends_at: string;
  is_active: boolean;
  rewards: SeasonReward[] | undefined;
}

interface LeaderboardEntry {
  user_id: number;
  rank: number;
  name: string;
  wins: number;
  elo_rating: number;
}

interface SeasonData {
  season: Season;
  user_rank: number | undefined;
  total_players: number;
  leaderboard: LeaderboardEntry[];
}

const auth = useAuth();

const loading = ref(true);
const seasonData = ref<SeasonData | undefined>(undefined);
const allSeasons = ref<Season[]>([]);
const selectedSeasonId = ref<number | undefined>(undefined);

const currentUserId = computed(() => auth.state.user?.id);

const timePercent = computed(() => {
  if (!seasonData.value?.season) {
    return 0;
  }
  const start = new Date(seasonData.value.season.starts_at).getTime();
  const end = new Date(seasonData.value.season.ends_at).getTime();
  const now = Date.now();
  if (now >= end) {
    return 100;
  }
  if (now <= start) {
    return 0;
  }
  return Math.round(((now - start) / (end - start)) * 100);
});

const timeLabel = computed(() => {
  if (!seasonData.value?.season) {
    return "";
  }
  const end = new Date(seasonData.value.season.ends_at).getTime();
  const now = Date.now();
  if (now >= end) {
    return "Season ended";
  }
  const diff = end - now;
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  if (days > 1) {
    return `${days} days remaining`;
  }
  const hours = Math.floor(diff / (1000 * 60 * 60));
  return `${hours} hours remaining`;
});

async function loadSeason(id: number): Promise<void> {
  selectedSeasonId.value = id;
  loading.value = true;
  try {
    const response = await axios.get<SeasonData>(`/api/seasons/${id}`);
    seasonData.value = response.data;
  } catch {
    seasonData.value = undefined;
  }
  loading.value = false;
}

function formatDate(date: string | undefined): string {
  if (!date) {
    return "";
  }
  return new Date(date).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
}

function ordinal(n: number): string {
  const suffixes = ["th", "st", "nd", "rd"];
  const value = n % 100;
  return n + (suffixes[(value - 20) % 10] || suffixes[value] || suffixes[0]);
}

onMounted(async () => {
  try {
    const response = await axios.get<Season[]>("/api/seasons");
    allSeasons.value = response.data;
    const active = allSeasons.value.find((s) => s.is_active) || allSeasons.value[0];
    if (active) {
      await loadSeason(active.id);
    }
  } catch {
    // Ignore load failures; the empty state is rendered instead.
  }
  loading.value = false;
});
</script>

<style scoped>
.season-page {
  max-width: 600px;
  margin: 0 auto;
}

.season-loading {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 40px;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 12px;
  text-align: center;
}

.season-dates {
  text-align: center;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 12px;
}

/* Time progress */
.time-progress {
  margin-bottom: 12px;
}

.time-bar-track {
  width: 100%;
  height: 10px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 5px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  overflow: hidden;
  margin-bottom: 4px;
}

.time-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 5px;
  transition: width 0.5s ease;
}

.time-label {
  display: block;
  text-align: center;
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.user-rank {
  text-align: center;
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 1rem;
}

.user-rank strong {
  color: var(--accent-gold);
  font-size: 1.2rem;
}

/* Rewards */
.rewards-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.reward-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
}

.reward-place-1 {
  border-color: rgba(255, 215, 0, 0.4);
  background: rgba(255, 215, 0, 0.06);
}

.reward-place-2 {
  border-color: rgba(192, 192, 192, 0.4);
  background: rgba(192, 192, 192, 0.04);
}

.reward-place-3 {
  border-color: rgba(205, 127, 50, 0.4);
  background: rgba(205, 127, 50, 0.04);
}

.reward-place {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  font-weight: 700;
  min-width: 40px;
}

.reward-details {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.reward-tag {
  font-size: 0.8rem;
  padding: 2px 8px;
  border-radius: 4px;
  background: rgba(212, 168, 67, 0.12);
  color: var(--accent-gold);
}

.reward-coins {
  color: #d4a843;
}

.reward-char {
  display: flex;
  align-items: center;
  gap: 4px;
}

.reward-char-img {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  object-fit: cover;
}

.reward-title-tag {
  background: rgba(74, 138, 58, 0.15);
  color: #6abf50;
}

/* Leaderboard */
.empty-lb {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 20px;
}

.lb-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.lb-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 6px;
}

.lb-highlight {
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
}

.lb-rank {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-weight: 700;
  min-width: 36px;
}

.lb-name {
  flex: 1;
  color: var(--text-bright);
}

.lb-stat {
  color: var(--text-secondary);
  font-size: 0.85rem;
  min-width: 36px;
  text-align: right;
}

.lb-elo {
  color: var(--accent-gold);
}

/* Season picker */
.season-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
}

.season-pick-btn {
  padding: 6px 14px;
  font-size: 0.85rem;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  color: var(--text-secondary);
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: border-color 0.2s;
  position: relative;
}

.season-pick-btn.active {
  border-color: var(--accent-gold);
  color: var(--accent-gold);
}

.active-dot {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #6abf50;
  margin-left: 4px;
  vertical-align: middle;
}
</style>
