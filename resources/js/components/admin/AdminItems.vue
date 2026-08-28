<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Items</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input ref="csvInput" type="file" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Item</button>
      </div>
    </div>

    <div v-if="importResult" class="import-result" :class="importResult.errors.length > 0 ? 'import-warn' : 'import-ok'">
      CSV Import: {{ importResult.created }} created, {{ importResult.updated }} updated.
      <span v-if="importResult.errors.length > 0"> {{ importResult.errors.length }} error(s).</span>
      <div v-for="(err, i) in importResult.errors" :key="i" class="import-error-line">{{ err }}</div>
      <button class="import-dismiss" @click="importResult = undefined">Dismiss</button>
    </div>

    <!-- Balance Stats Panel -->
    <div class="balance-panel">
      <div class="balance-header" @click="showBalanceStats = !showBalanceStats">
        <h3 class="balance-title">Balance Stats</h3>
        <button type="button" class="balance-toggle">{{ showBalanceStats ? 'Hide' : 'Show' }}</button>
      </div>
      <div v-if="showBalanceStats && itemBalanceStats" class="balance-body">
        <div class="balance-summary">
          <div class="balance-stat-card">
            <span class="balance-stat-label">Total Items</span>
            <span class="balance-stat-value">{{ itemBalanceStats.count }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-pos-label">Positive</span>
            <span class="balance-stat-value">{{ itemBalanceStats.positiveCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-neg-label">Negative</span>
            <span class="balance-stat-value">{{ itemBalanceStats.negativeCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Consumable</span>
            <span class="balance-stat-value">{{ itemBalanceStats.consumableCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Permanent</span>
            <span class="balance-stat-value">{{ itemBalanceStats.permanentCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg + Bonus</span>
            <span class="balance-stat-value balance-pos">{{ itemBalanceStats.avgPosBonus.toFixed(2) }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg - Bonus</span>
            <span class="balance-stat-value balance-neg">{{ itemBalanceStats.avgNegBonus.toFixed(2) }}</span>
          </div>
        </div>
        <div class="balance-section-row">
          <div class="balance-section">
            <h4 class="balance-section-title">Effect Type Distribution</h4>
            <div class="balance-dist-row">
              <span v-for="(count, type) in itemBalanceStats.effectTypeDist" :key="type" class="balance-dist-badge">
                {{ type }}: {{ count }}
              </span>
            </div>
          </div>
          <div class="balance-section">
            <h4 class="balance-section-title">Bonus Type Distribution</h4>
            <div class="balance-dist-row">
              <span v-for="(count, type) in itemBalanceStats.bonusTypeDist" :key="type" class="balance-dist-badge">
                {{ type.replace(/_/g, ' ') }}: {{ count }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <AdminSearchInput v-model="searchQuery" />

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="cards-list">
      <div v-for="item in filteredItems" :key="item.id" class="item-card">
        <div class="item-header">
          <h4>{{ item.name }}</h4>
          <div class="item-actions">
            <button @click="openPreview(item)">Preview</button>
            <button @click="openEdit(item)">Edit</button>
            <button class="btn-danger" @click="confirmDelete(item)">Delete</button>
          </div>
        </div>
        <p class="item-desc">{{ item.description }}</p>
        <div class="item-meta">
          <span class="item-type">{{ item.effect_type }}</span>
          <span v-if="item.is_negative" class="item-tag tag-neg">Negative</span>
          <span v-if="item.is_consumable" class="item-tag tag-con">Consumable</span>
        </div>
        <div class="item-effect" :class="item.is_negative ? 'effect-neg' : ''">
          {{ item.effect?.bonus_value > 0 ? '+' : '' }}{{ item.effect?.bonus_value }} {{ item.effect?.bonus_type || 'roll_bonus' }}
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Item' : 'New Item' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label>Effect Type</label>
            <select v-model="form.effect_type" required>
              <option value="passive">Passive</option>
              <option value="active">Active</option>
              <option value="immediate">Immediate</option>
            </select>
          </div>
          <div class="form-group">
            <label>Bonus Type</label>
            <select v-model="form.bonus_type" required>
              <option value="roll_bonus">Roll Bonus (+)</option>
              <option value="roll_penalty">Roll Penalty (-)</option>
              <option value="difficulty_reduction">Difficulty Reduction (-)</option>
              <option value="difficulty_increase">Difficulty Increase (+)</option>
              <option value="reroll">Reroll</option>
              <option value="stat_boost">Stat Boost (Immediate)</option>
              <option value="heal_die">Heal Die (Immediate)</option>
              <option value="score_bonus">Score Bonus (Immediate)</option>
              <option value="end_game_multiplier">End-Game Modifier % (Immediate)</option>
              <option value="score_per_round">Score Per Round (Passive)</option>
              <option value="score_multiplier">Score Multiplier (Passive)</option>
              <option value="debuff_roll">Debuff Opponent Roll (Duel)</option>
              <option value="increase_difficulty">Increase Opponent Difficulty (Duel)</option>
              <option value="shield_negative">Shield Negative Effects</option>
              <option value="peek_cards">Peek at Cards (Duel)</option>
              <option value="steal_stat">Steal Stat Point (Duel)</option>
            </select>
          </div>
          <div v-if="form.bonus_type === 'stat_boost' || form.bonus_type === 'steal_stat'" class="form-group">
            <label>Target Stat</label>
            <select v-model="form.stat" required>
              <option value="wealth">Wealth</option>
              <option value="influence">Influence</option>
              <option value="security">Security</option>
              <option value="religion">Religion</option>
              <option value="food">Food</option>
              <option value="happiness">Happiness</option>
            </select>
          </div>
          <div class="form-group">
            <label>Bonus Value</label>
            <input v-model.number="form.bonus_value" type="number" required />
          </div>
          <div class="form-group">
            <label>Target</label>
            <select v-model="form.target">
              <option :value="undefined">Self</option>
              <option value="opponent">Opponent</option>
            </select>
          </div>
          <div class="form-group">
            <label>
              <input v-model="form.is_negative" type="checkbox" />
              Is Negative
            </label>
          </div>
          <div class="form-group">
            <label>
              <input v-model="form.is_consumable" type="checkbox" />
              Is Consumable
            </label>
          </div>

          <div class="form-group">
            <label>Addon</label>
            <select v-model="form.addon_id">
              <option :value="undefined">Base Game</option>
              <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <!-- Duel Effect Override -->
          <div style="border: 1px solid rgba(138, 58, 185, 0.3); background: rgba(138, 58, 185, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
              <input v-model="form.useDuelEffect" type="checkbox" />
              <span style="color: #c890e0; font-weight: 700;">Use different effect for Duel mode</span>
            </label>
            <template v-if="form.useDuelEffect">
              <div class="form-group">
                <label>Duel Bonus Type</label>
                <select v-model="form.bonus_type_duel">
                  <option value="roll_bonus">Roll Bonus (+)</option>
                  <option value="roll_penalty">Roll Penalty (-)</option>
                  <option value="difficulty_reduction">Difficulty Reduction (-)</option>
                  <option value="difficulty_increase">Difficulty Increase (+)</option>
                  <option value="reroll">Reroll</option>
                  <option value="stat_boost">Stat Boost (Immediate)</option>
                  <option value="heal_die">Heal Die (Immediate)</option>
                  <option value="score_bonus">Score Bonus (Immediate)</option>
                  <option value="end_game_multiplier">End-Game Modifier % (Immediate)</option>
                  <option value="debuff_roll">Debuff Opponent Roll</option>
                  <option value="increase_difficulty">Increase Opponent Difficulty</option>
                  <option value="shield_negative">Shield Negative Effects</option>
                  <option value="peek_cards">Peek at Cards</option>
                  <option value="steal_stat">Steal Stat Point</option>
                </select>
              </div>
              <div v-if="form.bonus_type_duel === 'stat_boost'" class="form-group">
                <label>Duel Target Stat</label>
                <select v-model="form.stat_duel">
                  <option value="wealth">Wealth</option>
                  <option value="influence">Influence</option>
                  <option value="security">Security</option>
                  <option value="religion">Religion</option>
                  <option value="food">Food</option>
                  <option value="happiness">Happiness</option>
                </select>
              </div>
              <div class="form-group">
                <label>Duel Bonus Value</label>
                <input v-model.number="form.bonus_value_duel" type="number" />
              </div>
            </template>
          </div>

          <div class="form-group">
            <label style="color: var(--accent-gold); font-weight: 600;">Availability</label>
            <div style="display: flex; gap: 16px; margin-top: 4px;">
              <label><input v-model="form.available_cooperative" type="checkbox" /> Co-op</label>
              <label><input v-model="form.available_duel" type="checkbox" /> Duel</label>
            </div>
          </div>

          <div v-if="formError" class="form-error">{{ formError }}</div>

          <div class="modal-actions">
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
            <button type="button" @click="showModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- In-game preview -->
    <AdminPreviewModal
      :visible="previewItem !== undefined"
      :title="previewItem?.name"
      @close="previewItem = undefined"
    >
      <ItemPreviewCard v-if="previewItem" :item="previewItem" />
    </AdminPreviewModal>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, useTemplateRef } from "vue";
import axios, { isAxiosError } from "axios";
import { useToast } from "../../stores/toast";
import AdminPreviewModal from "./AdminPreviewModal.vue";
import AdminSearchInput from "./AdminSearchInput.vue";
import ItemPreviewCard from "./ItemPreviewCard.vue";

interface ItemEffect {
  bonus_type?: string;
  bonus_value?: number;
  stat?: string;
}

interface Item {
  id: number;
  name: string;
  description: string;
  effect_type?: string;
  effect?: ItemEffect;
  effect_duel?: ItemEffect;
  is_negative?: boolean;
  is_consumable?: boolean;
  target?: string;
  addon_id?: number;
  available_cooperative?: boolean;
  available_duel?: boolean;
}

interface Addon {
  id: number;
  name: string;
}

interface ItemForm {
  name: string;
  description: string;
  effect_type: string;
  bonus_type: string;
  bonus_value: number;
  stat: string;
  is_negative: boolean;
  is_consumable: boolean;
  target: string | undefined;
  addon_id: number | undefined;
  available_cooperative: boolean;
  available_duel: boolean;
  useDuelEffect: boolean;
  bonus_type_duel: string;
  bonus_value_duel: number;
  stat_duel: string;
}

interface ImportResult {
  created: number;
  updated: number;
  errors: string[];
}

interface BalanceStats {
  count: number;
  positiveCount: number;
  negativeCount: number;
  consumableCount: number;
  permanentCount: number;
  avgPosBonus: number;
  avgNegBonus: number;
  bonusTypeDist: Record<string, number>;
  effectTypeDist: Record<string, number>;
}

function defaultForm(): ItemForm {
  return {
    name: "",
    description: "",
    effect_type: "passive",
    bonus_type: "roll_bonus",
    bonus_value: 1,
    stat: "food",
    is_negative: false,
    is_consumable: false,
    target: undefined,
    addon_id: undefined,
    available_cooperative: true,
    available_duel: true,
    useDuelEffect: false,
    bonus_type_duel: "roll_bonus",
    bonus_value_duel: 1,
    stat_duel: "food",
  };
}

function errorMessage(error: unknown, fallback: string): string {
  if (isAxiosError<{ message?: string }>(error)) {
    return error.response?.data?.message ?? error.message;
  }
  return fallback;
}

const toast = useToast();

const items = ref<Item[]>([]);
const addons = ref<Addon[]>([]);
const loading = ref(true);
const searchQuery = ref("");
const showModal = ref(false);
const editing = ref<Item | undefined>(undefined);
const previewItem = ref<Item | undefined>(undefined);
const saving = ref(false);
const formError = ref("");
const importResult = ref<ImportResult | undefined>(undefined);
const showBalanceStats = ref(false);
const form = reactive<ItemForm>(defaultForm());
const csvInput = useTemplateRef<HTMLInputElement>("csvInput");

const filteredItems = computed<Item[]>(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return items.value;
  }
  return items.value.filter((item) =>
    (item.name || "").toLowerCase().includes(query)
    || (item.description || "").toLowerCase().includes(query)
    || (item.effect_type || "").toLowerCase().includes(query),
  );
});

const itemBalanceStats = computed<BalanceStats | undefined>(() => {
  if (items.value.length === 0) {
    return undefined;
  }

  const positiveCount = items.value.filter((item) => !item.is_negative).length;
  const negativeCount = items.value.filter((item) => item.is_negative).length;

  const consumableCount = items.value.filter((item) => item.is_consumable).length;
  const permanentCount = items.value.filter((item) => !item.is_consumable).length;

  const positiveItems = items.value.filter((item) => !item.is_negative && item.effect?.bonus_value != undefined);
  const negativeItems = items.value.filter((item) => item.is_negative && item.effect?.bonus_value != undefined);
  const avgPosBonus = positiveItems.length > 0
    ? positiveItems.reduce((sum, item) => sum + Math.abs(item.effect?.bonus_value ?? 0), 0) / positiveItems.length
    : 0;
  const avgNegBonus = negativeItems.length > 0
    ? negativeItems.reduce((sum, item) => sum + Math.abs(item.effect?.bonus_value ?? 0), 0) / negativeItems.length
    : 0;

  const bonusTypeDistribution: Record<string, number> = {};
  for (const item of items.value) {
    const bonusType = item.effect?.bonus_type || "unknown";
    bonusTypeDistribution[bonusType] = (bonusTypeDistribution[bonusType] || 0) + 1;
  }

  const effectTypeDistribution: Record<string, number> = {};
  for (const item of items.value) {
    const effectType = item.effect_type || "unknown";
    effectTypeDistribution[effectType] = (effectTypeDistribution[effectType] || 0) + 1;
  }

  return {
    count: items.value.length,
    positiveCount,
    negativeCount,
    consumableCount,
    permanentCount,
    avgPosBonus,
    avgNegBonus,
    bonusTypeDist: bonusTypeDistribution,
    effectTypeDist: effectTypeDistribution,
  };
});

async function fetch(): Promise<void> {
  loading.value = true;
  const response = await axios.get<Item[]>("/api/admin/items");
  items.value = response.data;
  loading.value = false;
}

async function fetchAddons(): Promise<void> {
  try {
    const response = await axios.get<Addon[]>("/api/admin/addons");
    addons.value = response.data;
  } catch {
    // ignore
  }
}

function openPreview(item: Item): void {
  previewItem.value = item;
}

function openCreate(): void {
  editing.value = undefined;
  Object.assign(form, defaultForm());
  formError.value = "";
  showModal.value = true;
}

function openEdit(item: Item): void {
  editing.value = item;
  const hasDuelEffect = item.effect_duel != undefined;
  Object.assign(form, {
    name: item.name,
    description: item.description,
    effect_type: item.effect_type || "passive",
    bonus_type: item.effect?.bonus_type || "roll_bonus",
    bonus_value: item.effect?.bonus_value || 1,
    stat: item.effect?.stat || "food",
    is_negative: item.is_negative || false,
    is_consumable: item.is_consumable || false,
    target: item.target || undefined,
    addon_id: item.addon_id || undefined,
    available_cooperative: item.available_cooperative ?? true,
    available_duel: item.available_duel ?? true,
    useDuelEffect: hasDuelEffect,
    bonus_type_duel: item.effect_duel?.bonus_type || item.effect?.bonus_type || "roll_bonus",
    bonus_value_duel: item.effect_duel?.bonus_value ?? item.effect?.bonus_value ?? 1,
    stat_duel: item.effect_duel?.stat || item.effect?.stat || "food",
  });
  formError.value = "";
  showModal.value = true;
}

async function save(): Promise<void> {
  formError.value = "";
  const payload = {
    name: form.name,
    description: form.description,
    effect_type: form.effect_type,
    is_negative: form.is_negative,
    is_consumable: form.is_consumable,
    target: form.target || undefined,
    addon_id: form.addon_id || undefined,
    available_cooperative: form.available_cooperative,
    available_duel: form.available_duel,
    effect: {
      bonus_type: form.bonus_type,
      bonus_value: form.bonus_value,
      ...((form.bonus_type === "stat_boost") && { stat: form.stat }),
    },
    effect_duel: form.useDuelEffect
      ? {
          bonus_type: form.bonus_type_duel,
          bonus_value: form.bonus_value_duel,
          ...((form.bonus_type_duel === "stat_boost") && { stat: form.stat_duel }),
        }
      : undefined,
  };

  saving.value = true;
  try {
    const current = editing.value;
    if (current) {
      await axios.put(`/api/admin/items/${current.id}`, payload);
    } else {
      await axios.post("/api/admin/items", payload);
    }
    showModal.value = false;
    await fetch();
  } catch (error) {
    formError.value = errorMessage(error, "Save failed");
  }
  saving.value = false;
}

function exportCsv(): void {
  window.location.assign("/api/admin/items/export-csv");
}

function triggerImport(): void {
  csvInput.value?.click();
}

async function handleImportFile(event: Event): Promise<void> {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) {
    return;
  }
  const file = target.files?.[0];
  if (!file) {
    return;
  }
  const formData = new FormData();
  formData.append("file", file);
  try {
    const response = await axios.post<ImportResult>("/api/admin/items/import-csv", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    importResult.value = response.data;
    await fetch();
  } catch (error) {
    importResult.value = { created: 0, updated: 0, errors: [errorMessage(error, "Import failed")] };
  }
  target.value = "";
}

async function confirmDelete(item: Item): Promise<void> {
  if (!confirm(`Delete item "${item.name}"?`)) {
    return;
  }
  try {
    await axios.delete(`/api/admin/items/${item.id}`);
    await fetch();
  } catch (error) {
    toast.error(`Delete failed: ${errorMessage(error, "Delete failed")}`);
  }
}

onMounted(async () => {
  await Promise.all([fetch(), fetchAddons()]);
});
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
}

.loading { text-align: center; color: var(--text-secondary); padding: 40px; }

.cards-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
}

.item-card {
  background: var(--bg-secondary);
  border: 1px solid rgba(184, 148, 46, 0.2);
  border-radius: 8px;
  padding: 16px;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.item-header h4 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
}

.item-actions { display: flex; gap: 6px; }
.item-actions button { padding: 5px 12px; font-size: 0.8rem; }

.item-desc {
  color: var(--text-secondary);
  font-size: 0.9rem;
  line-height: 1.4;
  margin-bottom: 8px;
}

.item-meta {
  display: flex;
  gap: 8px;
  margin-bottom: 6px;
}

.item-type {
  color: var(--text-secondary);
  font-size: 0.8rem;
  text-transform: capitalize;
}

.item-tag {
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.tag-neg { background: rgba(192, 57, 43, 0.2); color: #c0392b; }
.tag-con { background: rgba(212, 168, 67, 0.2); color: #d4a843; }

.item-effect {
  color: var(--accent-green);
  font-weight: 700;
  font-size: 0.9rem;
  text-transform: capitalize;
}

.item-effect.effect-neg {
  color: #c0392b;
}

/* Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex; align-items: center; justify-content: center;
  z-index: 200;
}

.modal-content {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  padding: 28px;
  width: 90%; max-width: 550px;
  max-height: 85vh; overflow-y: auto;
}

.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 18px; font-size: 1.3rem;
}

.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 5px; }
.form-group input:not([type="checkbox"]), .form-group textarea, .form-group select {
  width: 100%; background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright); padding: 8px 12px;
  border-radius: 4px; font-family: inherit; font-size: 0.95rem;
}
.form-group textarea { resize: vertical; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin-bottom: 10px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }

.header-buttons {
  display: flex;
  gap: 8px;
}

.btn-csv {
  background: rgba(40, 120, 80, 0.2);
  color: #5ab87a;
  border: 1px solid rgba(40, 120, 80, 0.4);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.btn-csv:hover {
  background: rgba(40, 120, 80, 0.35);
  border-color: rgba(60, 160, 100, 0.6);
}

.import-result {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 0.9rem;
  position: relative;
}

.import-ok {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
}

.import-warn {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.4);
  color: #d4a843;
}

.import-error-line {
  font-size: 0.8rem;
  color: #d05040;
  margin-top: 4px;
}

.import-dismiss {
  position: absolute;
  top: 8px;
  right: 8px;
  background: none;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  font-size: 0.8rem;
  padding: 2px 8px;
}

/* Balance Stats Panel */
.balance-panel {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 18px 22px;
  margin-bottom: 24px;
}

.balance-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.balance-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
}

.balance-toggle {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  border: 1px solid rgba(138, 106, 46, 0.3);
  padding: 4px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  letter-spacing: 1px;
}

.balance-toggle:hover {
  background: rgba(212, 168, 67, 0.25);
}

.balance-body {
  margin-top: 14px;
}

.balance-summary {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.balance-stat-card {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.balance-stat-label {
  color: var(--text-secondary);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.balance-stat-value {
  color: var(--accent-gold);
  font-weight: 700;
  font-size: 0.95rem;
}

.balance-pos-label { color: #27ae60; }
.balance-neg-label { color: #c0392b; }
.balance-pos { color: #27ae60; }
.balance-neg { color: #c0392b; }

.balance-section-row {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.balance-section {
  flex: 1;
  min-width: 200px;
}

.balance-section-title {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 8px;
}

.balance-dist-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.balance-dist-badge {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 0.8rem;
  color: var(--accent-gold);
  text-transform: capitalize;
}
</style>
