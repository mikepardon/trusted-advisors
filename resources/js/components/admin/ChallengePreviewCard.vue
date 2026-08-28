<template>
  <!-- Briefing for a single endless "race to a target" daily challenge, rendered in the
       game's dark card skin so admins preview it as players see the trial intro. -->
  <div class="cpc-card">
    <span class="cpc-label">Daily Trial</span>
    <h2 class="cpc-title">{{ title || "Untitled Trial" }}</h2>
    <p v-if="description" class="cpc-desc">{{ description }}</p>

    <div class="cpc-goal">
      <span class="cpc-goal-eyebrow">Objective</span>
      <span class="cpc-goal-line">{{ goalLine }}</span>
    </div>

    <div class="cpc-meta">
      <span class="cpc-chip">Start: all stats {{ startAll }}</span>
      <span class="cpc-chip">Rounds cap: {{ roundsCap }}</span>
      <span class="cpc-chip cpc-chip-reward">+{{ rewardXp }} XP</span>
    </div>

    <div class="cpc-divider"><span>Your Appointed Advisor</span></div>
    <p class="cpc-advisor">{{ advisorName }}</p>

    <div class="cpc-divider"><span>You March With</span></div>
    <div v-if="loadoutNames.length > 0" class="cpc-loadout">
      <span v-for="(name, index) in loadoutNames" :key="index" class="cpc-loadout-item">
        {{ name }}
      </span>
    </div>
    <p v-else class="cpc-note">No items for this trial.</p>

    <p class="cpc-warning">One attempt only — this run is the same for every player.</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

interface Goal {
  type?: string;
  stat?: string;
  value?: number;
}

interface Criteria {
  mode?: string;
  rounds?: number;
  start?: { all?: number };
  goal?: Goal;
  seed_character_id?: number;
  seed_loadout?: number[];
}

interface PreviewChallenge {
  title?: string;
  description?: string;
  reward_xp?: number;
  criteria?: Criteria;
}

interface NamedRecord {
  id: number;
  name: string;
}

const {
  challenge,
  characters = [],
  items = [],
} = defineProps<{
  challenge: PreviewChallenge;
  characters?: NamedRecord[];
  items?: NamedRecord[];
}>();

const STAT_LABELS: Record<string, string> = {
  wealth: "Wealth",
  influence: "Influence",
  security: "Security",
  religion: "Religion",
  food: "Food",
  happiness: "Happiness",
};

const title = computed<string | undefined>(() => challenge.title);
const description = computed<string | undefined>(() => challenge.description);
const rewardXp = computed<number>(() => challenge.reward_xp ?? 0);
const startAll = computed<number>(() => challenge.criteria?.start?.all ?? 8);
const roundsCap = computed<number>(() => challenge.criteria?.rounds ?? 0);

const goalLine = computed<string>(() => {
  const goal = challenge.criteria?.goal;
  if (!goal || goal.value === undefined) {
    return "Race to the target";
  }
  const statLabel = (goal.stat && STAT_LABELS[goal.stat]) || goal.stat || "a stat";
  return `Race to ${goal.value} ${statLabel}`;
});

const advisorName = computed<string>(() => {
  const id = challenge.criteria?.seed_character_id;
  if (id === undefined) {
    return "Random advisor (assigned at play)";
  }
  const match = characters.find((entry) => entry.id === id);
  return match ? match.name : `Advisor #${id}`;
});

const loadoutNames = computed<string[]>(() => {
  const ids = challenge.criteria?.seed_loadout ?? [];
  return ids.map((id) => {
    const match = items.find((entry) => entry.id === id);
    return match ? match.name : `Item #${id}`;
  });
});
</script>

<style scoped>
.cpc-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  color: #f3e9d2;
  border: 2px solid #c9a24b;
  border-radius: 12px;
  padding: 24px 20px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(212, 168, 67, 0.08);
}

.cpc-label {
  font-size: 0.65rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #e8c667;
  font-family: "Cinzel", serif;
}

.cpc-title {
  font-family: "Cinzel", serif;
  color: #e8c667;
  font-size: 1.4rem;
  margin: 0.2rem 0 0.4rem;
}

.cpc-desc {
  margin: 0 0 0.9rem;
  font-size: 0.92rem;
  line-height: 1.4;
  opacity: 0.9;
}

.cpc-goal {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: #2f2417;
  border: 1px solid rgba(201, 162, 75, 0.4);
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 12px;
}

.cpc-goal-eyebrow {
  font-size: 0.6rem;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: #c9a24b;
  font-family: "Cinzel", serif;
}

.cpc-goal-line {
  font-family: "Cinzel", serif;
  font-size: 1.05rem;
  color: #f3e9d2;
}

.cpc-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 4px;
}

.cpc-chip {
  font-size: 0.72rem;
  padding: 3px 10px;
  border-radius: 4px;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(201, 162, 75, 0.3);
  color: #e8d5b0;
}

.cpc-chip-reward {
  color: #c9a24b;
  font-weight: 600;
}

.cpc-divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 1.1rem 0 0.7rem;
  color: #c9a24b;
  font-family: "Cinzel", serif;
  font-size: 0.72rem;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}

.cpc-divider::before,
.cpc-divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: rgba(201, 162, 75, 0.3);
}

.cpc-divider span {
  padding: 0 0.75rem;
}

.cpc-advisor {
  margin: 0;
  font-family: "Cinzel", serif;
  font-weight: 700;
  color: #f3e9d2;
}

.cpc-loadout {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.cpc-loadout-item {
  font-size: 0.82rem;
  background: #2f2417;
  border: 1px solid rgba(201, 162, 75, 0.3);
  border-radius: 6px;
  padding: 4px 10px;
}

.cpc-note {
  font-size: 0.85rem;
  opacity: 0.75;
  margin: 0;
}

.cpc-warning {
  margin: 1rem 0 0;
  font-size: 0.75rem;
  color: #c9a24b;
  text-align: center;
  font-style: italic;
}
</style>
