<template>
  <Teleport to="body">
    <Transition appear name="lp">
      <div v-if="open" class="lp-overlay" @click.self="$emit('close')">
        <div class="lp-modal">
          <div class="lp-header">
            <span class="lp-title">Choose Your Loadout</span>
            <button class="lp-close" aria-label="Close" @click="$emit('close')">&times;</button>
          </div>

          <p class="lp-sub">
            Pick up to {{ maxEquipped }} items to bring into your next game.
            <strong>{{ selected.length }}/{{ maxEquipped }}</strong> selected.
          </p>

          <div v-if="loading" class="lp-empty">Loading…</div>
          <div v-else-if="items.length === 0" class="lp-empty">
            You don't own any items yet.
          </div>

          <div v-else class="lp-body">
            <div v-for="group in grouped" :key="group.type" class="lp-group">
              <span class="lp-group-title">{{ group.label }}</span>
              <div class="lp-grid">
                <button
                  v-for="item in group.items"
                  :key="item.id"
                  type="button"
                  class="lp-option"
                  :class="{ active: isSelected(item.id), disabled: isDisabled(item.id) }"
                  :disabled="isDisabled(item.id)"
                  @click="toggle(item.id)"
                >
                  <span class="lp-glyph">{{ glyph(item.type) }}</span>
                  <span class="lp-name">{{ item.name }}</span>
                  <span class="lp-cadence">{{ cadenceLabel(item.cadence) }}</span>
                  <span class="lp-desc">{{ item.description }}</span>
                </button>
              </div>
            </div>
          </div>

          <div class="lp-footer">
            <span v-if="error" class="lp-error">{{ error }}</span>
            <button class="lp-save" :disabled="saving" @click="save">
              {{ saving ? "Saving…" : "Save Loadout" }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import axios, { isAxiosError } from "axios";
import { computed, ref, watch } from "vue";

interface LoadoutItem {
  id: number;
  name: string;
  description?: string;
  type?: string;
  cadence?: string;
  equipped?: boolean;
}

const { open = false } = defineProps<{ open?: boolean }>();
const emit = defineEmits<{ close: [] }>();

const TYPE_GLYPHS: Record<string, string> = {
  weapon: "⚔️",
  armour: "🛡️",
  potion: "🧪",
  scroll: "📜",
  relic: "✨",
  coin: "💰",
  hex: "🔮",
};

const TYPE_LABELS: Record<string, string> = {
  weapon: "Weapons",
  armour: "Armour",
  potion: "Potions",
  scroll: "Scrolls",
  relic: "Relics",
  coin: "Treasures",
  hex: "Hexes",
};

const CADENCE_LABELS: Record<string, string> = {
  passive: "Always active",
  per_round: "Once per round",
  per_game: "Once per game",
};

const loading = ref(false);
const saving = ref(false);
const error = ref<string | undefined>(undefined);
const items = ref<LoadoutItem[]>([]);
const selected = ref<number[]>([]);
const maxEquipped = ref(3);

function glyph(type?: string): string {
  return (type && TYPE_GLYPHS[type]) || "⚔";
}

function cadenceLabel(cadence?: string): string {
  return (cadence && CADENCE_LABELS[cadence]) || "";
}

const grouped = computed(() => {
  const byType = new Map<string, LoadoutItem[]>();
  for (const item of items.value) {
    const key = item.type ?? "relic";
    const bucket = byType.get(key) ?? [];
    bucket.push(item);
    byType.set(key, bucket);
  }
  return [...byType].map(([type, groupItems]) => ({
    type,
    label: TYPE_LABELS[type] ?? "Items",
    items: groupItems,
  }));
});

function isSelected(id: number): boolean {
  return selected.value.includes(id);
}

function isDisabled(id: number): boolean {
  return !isSelected(id) && selected.value.length >= maxEquipped.value;
}

function toggle(id: number): void {
  if (isSelected(id)) {
    selected.value = selected.value.filter((existing) => existing !== id);
    return;
  }
  if (selected.value.length >= maxEquipped.value) {
    return;
  }
  selected.value = [...selected.value, id];
}

async function load(): Promise<void> {
  loading.value = true;
  error.value = undefined;
  try {
    const { data } = await axios.get<{
      items: LoadoutItem[];
      equipped: number[];
      max_equipped: number;
    }>("/api/loadout");
    items.value = data.items;
    selected.value = [...data.equipped];
    maxEquipped.value = data.max_equipped;
  } catch {
    error.value = "Could not load your items.";
  } finally {
    loading.value = false;
  }
}

async function save(): Promise<void> {
  saving.value = true;
  error.value = undefined;
  try {
    await axios.put("/api/loadout", { item_ids: selected.value });
    emit("close");
  } catch (error_) {
    error.value =
      isAxiosError(error_) && typeof error_.response?.data?.message === "string"
        ? error_.response.data.message
        : "Could not save your loadout.";
  } finally {
    saving.value = false;
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
.lp-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}
.lp-modal {
  background: #2a2118;
  color: #f3e9d2;
  border: 2px solid #c9a24b;
  border-radius: 12px;
  width: min(680px, 100%);
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
}
.lp-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem 0.5rem;
}
.lp-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #e8c667;
}
.lp-close {
  background: none;
  border: none;
  color: #f3e9d2;
  font-size: 1.75rem;
  line-height: 1;
  cursor: pointer;
}
.lp-sub {
  padding: 0 1rem;
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  opacity: 0.85;
}
.lp-body {
  overflow-y: auto;
  padding: 0 1.25rem 0.5rem;
}
.lp-group {
  margin-bottom: 1rem;
}
.lp-group-title {
  display: block;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.7;
  margin-bottom: 0.4rem;
}
.lp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 0.5rem;
}
.lp-option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.15rem;
  padding: 0.5rem;
  background: #372c1f;
  border: 2px solid transparent;
  border-radius: 8px;
  color: inherit;
  cursor: pointer;
  text-align: left;
}
.lp-option.active {
  border-color: #e8c667;
  background: #45361f;
}
.lp-option.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.lp-glyph {
  font-size: 1.2rem;
}
.lp-name {
  font-weight: 600;
  font-size: 0.8rem;
  line-height: 1.15;
}
.lp-cadence {
  font-size: 0.62rem;
  color: #c9a24b;
}
.lp-desc {
  font-size: 0.66rem;
  opacity: 0.7;
  line-height: 1.2;
}
.lp-empty {
  padding: 2rem 1.25rem;
  text-align: center;
  opacity: 0.7;
}
.lp-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 0.75rem 1.25rem 1rem;
  border-top: 1px solid rgba(201, 162, 75, 0.3);
}
.lp-error {
  color: #f0a0a0;
  font-size: 0.85rem;
  margin-right: auto;
}
.lp-save {
  background: #c9a24b;
  color: #2a2118;
  border: none;
  border-radius: 8px;
  padding: 0.55rem 1.1rem;
  font-weight: 700;
  cursor: pointer;
}
.lp-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.lp-enter-active,
.lp-leave-active {
  transition: opacity 0.2s ease;
}
.lp-enter-from,
.lp-leave-to {
  opacity: 0;
}
</style>
