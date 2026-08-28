<template>
  <!-- Single achievement rendered in the in-game parchment card style, mirroring
       the medal presentation from AchievementsList.vue so admins see it as players do. -->
  <div class="parchment-card">
    <div class="ach-medallion">
      <AppIcon :type="resolvedIcon.type" :value="resolvedIcon.value" size="lg" />
    </div>

    <h3 class="parchment-title">{{ achievement.name || 'Unnamed Achievement' }}</h3>

    <!-- Tags row -->
    <div class="tag-row">
      <span v-if="achievement.category" class="type-tag cat-tag">{{ achievement.category }}</span>
      <span v-if="tierLabel" class="type-tag tier-tag">{{ tierLabel }}</span>
    </div>

    <div class="parchment-divider"><span class="divider-ornament">&#9830;</span></div>

    <p class="parchment-desc">{{ achievement.description || '' }}</p>

    <div class="parchment-divider divider-thin"><span class="divider-ornament small">&#8226;</span></div>

    <!-- Reward chip -->
    <div class="outcome-chips">
      <span class="stat-chip chip-reward">+{{ rewardXp }} XP</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import AppIcon from "../AppIcon.vue";
import { resolveAchievementIcon, type ResolvedAchievementIcon } from "../../utils/achievement-icons";

interface PreviewAchievement {
  name: string;
  description: string;
  icon: string | undefined;
  category: string;
  reward_xp: number | undefined;
  tier: number | undefined;
  tier_group: string | undefined;
}

const { achievement } = defineProps<{
  achievement: PreviewAchievement;
}>();

const resolvedIcon = computed<ResolvedAchievementIcon>(() =>
  resolveAchievementIcon(achievement.icon),
);

const rewardXp = computed<number>(() => achievement.reward_xp ?? 0);

const tierLabel = computed<string | undefined>(() => {
  if (!achievement.tier_group || !achievement.tier || achievement.tier <= 1) {
    return undefined;
  }
  return `Tier ${achievement.tier}`;
});
</script>

<style scoped>
.parchment-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 24px 20px;
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(212, 168, 67, 0.08);
}

.ach-medallion {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  margin-bottom: 10px;
  background: radial-gradient(circle at 50% 40%, #4a3620, #241a0f);
  border: 2px solid rgba(240, 192, 80, 0.5);
  box-shadow: inset 0 1px 4px rgba(0, 0, 0, 0.5), 0 0 12px rgba(240, 192, 80, 0.15);
}

.parchment-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 6px;
  line-height: 1.3;
}

.tag-row {
  display: flex;
  gap: 6px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.type-tag {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-radius: 3px;
  padding: 1px 8px;
}

.cat-tag {
  color: #a0a0d0;
  border: 1px solid rgba(160, 160, 208, 0.6);
}

.tier-tag {
  color: var(--accent-gold, #c9a84c);
  border: 1px solid rgba(212, 168, 67, 0.6);
}

.parchment-divider {
  position: relative;
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold, #6b5b3a), transparent);
  margin: 12px 0;
}

.parchment-divider.divider-thin {
  background: linear-gradient(90deg, transparent, rgba(138, 106, 46, 0.4), transparent);
  margin: 8px 0;
}

.divider-ornament {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #2a1f14;
  color: var(--accent-gold, #c9a84c);
  padding: 0 8px;
  font-size: 0.7rem;
}

.divider-ornament.small {
  font-size: 0.5rem;
  color: var(--text-secondary, #a09080);
}

.parchment-desc {
  color: var(--text-primary, #e8d5b0);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  flex: 1;
}

.outcome-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  justify-content: center;
}

.stat-chip {
  padding: 3px 12px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  font-family: 'Cinzel', serif;
}

.chip-reward {
  background: rgba(74, 138, 58, 0.2);
  color: #6abf50;
}
</style>
