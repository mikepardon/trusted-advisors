<template>
  <Teleport to="body">
    <Transition appear name="dci">
      <div v-if="open && challenge" class="dci-overlay" @click.self="$emit('close')">
        <div class="dci-modal">
          <button class="dci-close" aria-label="Close" @click="$emit('close')">&times;</button>

          <span class="dci-label">Daily Trial</span>
          <h2 class="dci-title">{{ challenge.title }}</h2>
          <p class="dci-goal">{{ challenge.description }}</p>
          <p v-if="challenge.reward_xp" class="dci-reward">Reward: +{{ challenge.reward_xp }} XP</p>

          <div class="dci-divider"><span>Your Appointed Advisor</span></div>

          <div v-if="challenge.character" class="dci-advisor">
            <img
              v-if="challenge.character.image_url"
              :src="challenge.character.image_url"
              :alt="challenge.character.name"
              class="dci-advisor-img"
            />
            <div class="dci-advisor-info">
              <span class="dci-advisor-name">{{ challenge.character.name }}</span>
              <p v-if="challenge.character.description" class="dci-advisor-desc">
                {{ challenge.character.description }}
              </p>
              <p v-if="challenge.character.wild_ability_description" class="dci-advisor-wild">
                ✦ {{ challenge.character.wild_ability_description }}
              </p>
            </div>
          </div>
          <p v-else class="dci-note">You will be assigned an advisor for this trial.</p>

          <div class="dci-divider"><span>You March With</span></div>

          <div v-if="challenge.loadout && challenge.loadout.length > 0" class="dci-items">
            <div v-for="(item, index) in challenge.loadout" :key="index" class="dci-item">
              <span class="dci-item-glyph">{{ glyph(item.type) }}</span>
              <div class="dci-item-text">
                <span class="dci-item-name">
                  {{ item.name }}
                  <span class="dci-item-cadence">{{ cadenceLabel(item.cadence) }}</span>
                </span>
                <span class="dci-item-desc">{{ item.description }}</span>
              </div>
            </div>
          </div>
          <p v-else class="dci-note">No items for this trial.</p>

          <p class="dci-warning">One attempt only — this run is the same for every player today.</p>

          <div class="dci-actions">
            <button class="dci-cancel" @click="$emit('close')">Not yet</button>
            <button class="dci-begin" :disabled="starting" @click="$emit('begin')">
              {{ starting ? "Entering…" : "Begin the Trial" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
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
  title?: string;
  description?: string;
  reward_xp?: number;
  character?: DailyCharacter;
  loadout?: LoadoutItem[];
}

const {
  open = false,
  challenge = undefined,
  starting = false,
} = defineProps<{
  open?: boolean;
  challenge?: DailyChallenge;
  starting?: boolean;
}>();

defineEmits<{ close: []; begin: [] }>();

const TYPE_GLYPHS: Record<string, string> = {
  weapon: "⚔️",
  armour: "🛡️",
  potion: "🧪",
  scroll: "📜",
  relic: "✨",
  coin: "💰",
  hex: "🔮",
};

const CADENCE_LABELS: Record<string, string> = {
  passive: "Always active",
  per_round: "Once per round",
  per_game: "Once per game",
};

function glyph(type?: string): string {
  return (type && TYPE_GLYPHS[type]) || "⚔";
}

function cadenceLabel(cadence?: string): string {
  return (cadence && CADENCE_LABELS[cadence]) || "";
}
</script>

<style scoped>
.dci-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}
.dci-modal {
  position: relative;
  background: #241b12;
  color: #f3e9d2;
  border: 2px solid #c9a24b;
  border-radius: 12px;
  width: min(560px, 100%);
  max-height: 90vh;
  overflow-y: auto;
  padding: 1.5rem;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.55);
}
.dci-close {
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
.dci-label {
  font-size: 0.65rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #e8c667;
  font-family: "Cinzel", serif;
}
.dci-title {
  font-family: "Cinzel", serif;
  color: #e8c667;
  font-size: 1.5rem;
  margin: 0.2rem 0 0.4rem;
}
.dci-goal {
  margin: 0;
  font-size: 0.95rem;
}
.dci-reward {
  margin: 0.4rem 0 0;
  color: #c9a24b;
  font-size: 0.85rem;
}
.dci-divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 1.1rem 0 0.7rem;
  color: #c9a24b;
  font-family: "Cinzel", serif;
  font-size: 0.75rem;
  letter-spacing: 1.5px;
  text-transform: uppercase;
}
.dci-divider::before,
.dci-divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: rgba(201, 162, 75, 0.3);
}
.dci-divider span {
  padding: 0 0.75rem;
}
.dci-advisor {
  display: flex;
  gap: 0.9rem;
  align-items: flex-start;
}
.dci-advisor-img {
  width: 72px;
  height: 72px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid rgba(201, 162, 75, 0.4);
  flex-shrink: 0;
}
.dci-advisor-info {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.dci-advisor-name {
  font-weight: 700;
  font-family: "Cinzel", serif;
  color: #f3e9d2;
}
.dci-advisor-desc {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.8;
  line-height: 1.3;
}
.dci-advisor-wild {
  margin: 0.2rem 0 0;
  font-size: 0.78rem;
  color: #c9a24b;
}
.dci-items {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.dci-item {
  display: flex;
  gap: 0.6rem;
  align-items: flex-start;
  background: #2f2417;
  border-radius: 8px;
  padding: 0.5rem 0.6rem;
}
.dci-item-glyph {
  font-size: 1.3rem;
}
.dci-item-text {
  display: flex;
  flex-direction: column;
}
.dci-item-name {
  font-weight: 600;
  font-size: 0.88rem;
}
.dci-item-cadence {
  font-size: 0.7rem;
  color: #c9a24b;
  margin-left: 0.4rem;
}
.dci-item-desc {
  font-size: 0.75rem;
  opacity: 0.75;
  line-height: 1.25;
}
.dci-note {
  font-size: 0.85rem;
  opacity: 0.75;
  margin: 0;
}
.dci-warning {
  margin: 1rem 0 0;
  font-size: 0.75rem;
  color: #c9a24b;
  text-align: center;
  font-style: italic;
}
.dci-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1rem;
}
.dci-cancel {
  flex: 1;
  background: transparent;
  color: #f3e9d2;
  border: 1px solid rgba(201, 162, 75, 0.4);
  border-radius: 8px;
  padding: 0.6rem;
  cursor: pointer;
}
.dci-begin {
  flex: 2;
  background: #c9a24b;
  color: #241b12;
  border: none;
  border-radius: 8px;
  padding: 0.6rem;
  font-weight: 700;
  font-family: "Cinzel", serif;
  cursor: pointer;
}
.dci-begin:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.dci-enter-active,
.dci-leave-active {
  transition: opacity 0.2s ease;
}
.dci-enter-from,
.dci-leave-to {
  opacity: 0;
}
</style>
