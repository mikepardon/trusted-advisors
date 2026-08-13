<template>
  <div class="achievements-page">
    <h2 class="section-title">Achievements</h2>

    <HintBubble hint-id="achievements-claim">
      Earned an achievement? Tap <strong>Claim</strong> to collect your XP and coin rewards!
    </HintBubble>

    <div v-if="loading" class="ach-loading">Loading...</div>

    <div v-else class="ach-grid">
      <div
        v-for="ach in achievements"
        :key="ach.id"
        class="ach-card"
        :class="{
          earned: ach.earned && ach.claimed,
          unclaimed: ach.earned && !ach.claimed,
          locked: !ach.earned,
        }"
      >
        <div class="ach-icon"><AppIcon v-bind="resolveAchievementIcon(ach.icon)" /></div>
        <div class="ach-info">
          <div class="ach-header">
            <h3 class="ach-name">{{ ach.name }}</h3>
            <div v-if="ach.earned && ach.claimed" class="ach-badge claimed-badge">&#10003;</div>
            <button
              v-else-if="ach.earned && !ach.claimed"
              class="ach-claim-btn"
              :disabled="claiming === ach.id"
              @click="claim(ach)"
            >
              {{ claiming === ach.id ? '...' : claimLabel(ach) }}
            </button>
            <div v-else-if="ach.reward_xp || ach.reward_coins" class="ach-xp-preview">
              <span v-if="ach.reward_xp">+{{ ach.reward_xp }} XP</span>
              <span v-if="ach.reward_coins"> +{{ ach.reward_coins }} &#129689;</span>
            </div>
          </div>
          <p class="ach-desc">{{ ach.description }}</p>
          <div v-if="ach.tier_group && ach.tier > 1" class="ach-tier-label">Tier {{ ach.tier }}</div>
          <!-- Progress bar for unearned trackable achievements -->
          <div v-if="!ach.earned && ach.progress" class="ach-progress">
            <div class="progress-track">
              <div class="progress-fill" :style="{ width: progressPercent(ach.progress) + '%' }"></div>
            </div>
            <span class="progress-text">{{ ach.progress.current }} / {{ ach.progress.target }}</span>
          </div>
        </div>
      </div>
    </div>

    <button v-if="unclaimedCount >= 2" class="claim-all-fab" :disabled="claimingAll" @click="claimAll">
      {{ claimingAll ? 'Claiming...' : `Claim All (${unclaimedCount})` }}
    </button>

    <AchievementClaim
      v-if="claimOverlay"
      :achievement="claimOverlay.achievement"
      :result="claimOverlay.result"
      @dismiss="onClaimDismiss"
    />

    <!-- Batch claim summary overlay -->
    <div v-if="batchClaimOverlay" class="batch-overlay" @click="batchClaimOverlay = null">
      <div class="batch-overlay-card" @click.stop>
        <h3 class="batch-title">Rewards Claimed!</h3>
        <p class="batch-count">{{ batchClaimOverlay.count }} achievements claimed</p>
        <div class="batch-rewards">
          <div v-if="batchClaimOverlay.xp_awarded" class="batch-reward-row">+{{ batchClaimOverlay.xp_awarded }} XP</div>
          <div v-if="batchClaimOverlay.coins_awarded" class="batch-reward-row">+{{ batchClaimOverlay.coins_awarded }} &#129689;</div>
          <div v-if="batchClaimOverlay.leveled_up" class="batch-levelup">Level Up! Now Lv. {{ batchClaimOverlay.new_level }}</div>
        </div>
        <button class="btn-primary batch-dismiss" @click="batchClaimOverlay = null">Continue</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import AchievementClaim from "./AchievementClaim.vue";
import AppIcon from "./AppIcon.vue";
import HintBubble from "./HintBubble.vue";
import { useAuth } from "../stores/auth";
import { resolveAchievementIcon } from "../utils/achievement-icons";

interface AchievementProgress {
  current: number;
  target: number;
}

interface Achievement {
  id: number;
  name: string;
  description: string;
  icon: string;
  earned: boolean;
  claimed: boolean;
  reward_xp?: number;
  reward_coins?: number;
  tier_group?: string;
  tier: number;
  progress?: AchievementProgress;
}

interface ClaimResult {
  new_xp: number;
  new_level: number;
  new_coins: number;
}

interface ClaimOverlay {
  achievement: Achievement;
  result: ClaimResult;
}

interface BatchClaimResult {
  count: number;
  new_xp: number;
  new_level: number;
  new_coins: number;
  xp_awarded?: number;
  coins_awarded?: number;
  leveled_up?: boolean;
}

const { updateUserStats } = useAuth();

const achievements = ref<Achievement[]>([]);
const loading = ref(true);
const claiming = ref<number>();
const claimingAll = ref(false);
const claimOverlay = ref<ClaimOverlay>();
const batchClaimOverlay = ref<BatchClaimResult>();

const unclaimedCount = computed(() => achievements.value.filter((achievement) => achievement.earned && !achievement.claimed).length);

onMounted(async () => {
  await fetchAchievements();
});

function claimLabel(achievement: Achievement): string {
  const parts: string[] = [];
  if (achievement.reward_xp) {
    parts.push(`+${achievement.reward_xp} XP`);
  }
  if (achievement.reward_coins) {
    parts.push(`+${achievement.reward_coins} \u{1FA99}`);
  }
  return parts.length > 0 ? `Claim ${parts.join(" ")}` : "Claim";
}

async function fetchAchievements(): Promise<void> {
  loading.value = true;
  try {
    const response = await axios.get<Achievement[]>("/api/achievements");
    achievements.value = response.data;
  } catch {
    // silently fail
  }
  loading.value = false;
}

function progressPercent(progress: AchievementProgress | undefined): number {
  if (!progress || !progress.target) {
    return 0;
  }
  return Math.min(100, Math.round((progress.current / progress.target) * 100));
}

async function claim(achievement: Achievement): Promise<void> {
  claiming.value = achievement.id;
  try {
    const response = await axios.post<ClaimResult>(`/api/achievements/${achievement.id}/claim`);
    const result = response.data;

    // Update auth store
    updateUserStats({ xp: result.new_xp, level: result.new_level, coins: result.new_coins });

    // Show overlay
    claimOverlay.value = { achievement, result };

    // Mark as claimed locally
    achievement.claimed = true;
  } catch {
    // silently fail
  }
  claiming.value = undefined;
}

function onClaimDismiss(): void {
  claimOverlay.value = undefined;
}

async function claimAll(): Promise<void> {
  claimingAll.value = true;
  try {
    const response = await axios.post<BatchClaimResult>("/api/achievements/claim-all");
    const result = response.data;

    updateUserStats({ xp: result.new_xp, level: result.new_level, coins: result.new_coins });

    // Mark all unclaimed as claimed locally
    for (const achievement of achievements.value) {
      if (achievement.earned && !achievement.claimed) {
        achievement.claimed = true;
      }
    }

    batchClaimOverlay.value = result;
  } catch {
    // silently fail
  }
  claimingAll.value = false;
}
</script>

<style scoped>
.achievements-page {
  max-width: 700px;
  margin: 0 auto;
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 16px;
  text-align: center;
}

.ach-loading {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px;
}

.ach-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ach-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 8px;
  transition: all 0.2s;
}

.ach-card.earned {
  border-color: rgba(74, 138, 58, 0.4);
  background: rgba(74, 138, 58, 0.06);
}

.ach-card.unclaimed {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.08);
}

.ach-card.locked {
  opacity: 0.6;
}

.ach-icon {
  font-size: 1.8rem;
  width: 40px;
  text-align: center;
  flex-shrink: 0;
  padding-top: 2px;
}

.ach-info {
  flex: 1;
  min-width: 0;
}

.ach-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.ach-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
  margin: 0;
}

.ach-desc {
  color: var(--text-secondary);
  font-size: 0.8rem;
  margin: 2px 0 0;
}

.ach-tier-label {
  font-size: 0.65rem;
  color: var(--text-secondary);
  opacity: 0.7;
  margin-top: 2px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.claimed-badge {
  font-size: 0.85rem;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: rgba(74, 138, 58, 0.25);
  color: #6abf50;
  font-weight: 700;
  flex-shrink: 0;
}

.ach-claim-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  padding: 3px 12px;
  border-radius: 4px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  cursor: pointer;
  font-weight: 600;
  white-space: nowrap;
  flex-shrink: 0;
  animation: claimPulse 2s ease-in-out infinite;
  transition: background 0.2s;
}

.ach-claim-btn:hover {
  background: rgba(212, 168, 67, 0.3);
}

.ach-claim-btn:disabled {
  opacity: 0.5;
  animation: none;
  cursor: default;
}

.ach-xp-preview {
  font-size: 0.65rem;
  color: var(--text-secondary);
  opacity: 0.6;
  white-space: nowrap;
  flex-shrink: 0;
}

@keyframes claimPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(212, 168, 67, 0.4); }
  50% { box-shadow: 0 0 8px 2px rgba(212, 168, 67, 0.3); }
}

/* Progress bar */
.ach-progress {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.progress-track {
  flex: 1;
  height: 6px;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 3px;
  overflow: hidden;
  border: 1px solid rgba(138, 106, 46, 0.15);
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #8a6a2e, #d4a843);
  border-radius: 3px;
  transition: width 0.4s ease;
  min-width: 0;
}

.progress-text {
  font-size: 0.7rem;
  color: var(--text-secondary);
  white-space: nowrap;
  flex-shrink: 0;
  min-width: 40px;
  text-align: right;
}

/* Claim All FAB */
.claim-all-fab {
  position: sticky;
  bottom: 16px;
  display: block;
  margin: 16px auto 0;
  padding: 10px 28px;
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  font-weight: 700;
  border-radius: 24px;
  border: 2px solid var(--accent-gold);
  background: linear-gradient(180deg, #4a3a24, #2a1f14);
  color: var(--accent-gold);
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(212, 168, 67, 0.3);
  z-index: 10;
  transition: background 0.2s, box-shadow 0.2s;
}

.claim-all-fab:hover {
  background: linear-gradient(180deg, #5a4a34, #3a2a1a);
  box-shadow: 0 4px 20px rgba(212, 168, 67, 0.5);
}

.claim-all-fab:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Batch claim overlay */
.batch-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.batch-overlay-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14);
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 32px 28px;
  text-align: center;
  max-width: 360px;
  width: 90%;
}

.batch-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.4rem;
  margin-bottom: 8px;
}

.batch-count {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-bottom: 16px;
}

.batch-rewards {
  margin-bottom: 20px;
}

.batch-reward-row {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 4px;
}

.batch-levelup {
  color: #6abf50;
  font-family: 'Cinzel', serif;
  font-size: 1.1rem;
  margin-top: 8px;
}

.batch-dismiss {
  padding: 8px 32px;
  font-size: 1rem;
}

@media (max-width: 768px) {
  .ach-icon {
    font-size: 1.4rem;
    width: 32px;
  }
}
</style>
