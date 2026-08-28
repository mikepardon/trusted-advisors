<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Events</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input ref="csvInput" type="file" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Event</button>
        <button class="btn-ai" @click="showAiModal = true">Generate with AI</button>
      </div>
    </div>

    <div v-if="importResult" class="import-result" :class="importResult.errors.length > 0 ? 'import-warn' : 'import-ok'">
      CSV Import: {{ importResult.created }} created, {{ importResult.updated }} updated.
      <span v-if="importResult.errors.length > 0"> {{ importResult.errors.length }} error(s).</span>
      <div v-for="(err, i) in importResult.errors" :key="i" class="import-error-line">{{ err }}</div>
      <button class="import-dismiss" @click="importResult = null">Dismiss</button>
    </div>

    <!-- Balance Stats Panel -->
    <div class="balance-panel">
      <div class="balance-header" @click="showBalanceStats = !showBalanceStats">
        <h3 class="balance-title">Balance Stats</h3>
        <button type="button" class="balance-toggle">{{ showBalanceStats ? 'Hide' : 'Show' }}</button>
      </div>
      <div v-if="showBalanceStats && eventBalanceStats" class="balance-body">
        <div class="balance-summary">
          <div class="balance-stat-card">
            <span class="balance-stat-label">Total Events</span>
            <span class="balance-stat-value">{{ eventBalanceStats.count }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-pos-label">Positive</span>
            <span class="balance-stat-value">{{ eventBalanceStats.positiveCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-neg-label">Negative</span>
            <span class="balance-stat-value">{{ eventBalanceStats.negativeCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Mixed</span>
            <span class="balance-stat-value">{{ eventBalanceStats.mixedCount }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Neutral</span>
            <span class="balance-stat-value">{{ eventBalanceStats.neutralCount }}</span>
          </div>
        </div>
        <div class="balance-section-row">
          <div class="balance-section">
            <h4 class="balance-section-title">Mechanic Distribution</h4>
            <div class="balance-dist-row">
              <span v-for="(count, mech) in eventBalanceStats.mechanicDist" :key="mech" class="balance-dist-badge">
                {{ mech.replace(/_/g, ' ') }}: {{ count }}
              </span>
            </div>
          </div>
        </div>
        <table class="admin-table balance-table">
          <thead>
            <tr>
              <th>Stat</th>
              <th>Total Modifier</th>
              <th>Events w/ Stat</th>
              <th>Avg Modifier</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(data, stat) in eventBalanceStats.perStat" :key="stat">
              <td class="name-col" style="text-transform: capitalize;">{{ stat }}</td>
              <td :class="data.total > 0 ? 'balance-pos' : data.total < 0 ? 'balance-neg' : ''">{{ data.total > 0 ? '+' : '' }}{{ data.total }}</td>
              <td>{{ data.count }}</td>
              <td :class="data.avg > 0 ? 'balance-pos' : data.avg < 0 ? 'balance-neg' : ''">{{ data.avg > 0 ? '+' : '' }}{{ data.avg.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AdminSearchInput v-model="searchQuery" />

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="cards-list">
      <div v-for="ev in filteredEvents" :key="ev.id" class="item-card">
        <div class="item-header">
          <h4>{{ ev.title }}</h4>
          <div class="item-actions">
            <button @click="openPreview(ev)">Preview</button>
            <button @click="openEdit(ev)">Edit</button>
            <button class="btn-danger" @click="confirmDelete(ev)">Delete</button>
          </div>
        </div>
        <p class="item-desc">{{ ev.effect }}</p>
        <div v-if="ev.stat_modifiers" class="item-meta">
          <strong>Stat Modifiers:</strong>
          <span v-for="(val, stat) in ev.stat_modifiers" :key="stat" class="mod-badge" :class="val > 0 ? 'mod-pos' : 'mod-neg'">
            {{ statIcon(stat) }} {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
        </div>
        <div v-if="ev.mechanic" class="item-meta">
          <strong>Mechanic:</strong>
          <span class="mechanic-badge">{{ mechanicLabel(ev.mechanic) }}</span>
        </div>
        <div v-if="ev.stat_modifiers_duel || ev.mechanic_duel" class="item-meta">
          <span class="duel-badge">Duel Override</span>
          <span v-for="(val, stat) in ev.stat_modifiers_duel" :key="'d-' + stat" class="mod-badge" :class="val > 0 ? 'mod-pos' : 'mod-neg'">
            {{ statIcon(stat) }} {{ stat }}: {{ val > 0 ? '+' : '' }}{{ val }}
          </span>
          <span v-if="ev.mechanic_duel" class="mechanic-badge">{{ mechanicLabel(ev.mechanic_duel) }}</span>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Event' : 'New Event' }}</h3>
        <form @submit.prevent="save">
          <div class="form-group">
            <label>Title</label>
            <input v-model="form.title" required />
          </div>
          <div class="form-group">
            <label>Effect Description</label>
            <textarea v-model="form.effect" rows="4" required></textarea>
          </div>

          <div class="form-group">
            <label>Stat Modifiers</label>
            <div class="stat-grid">
              <div v-for="stat in stats" :key="stat.key" class="stat-cell">
                <span class="stat-icon-label" :title="stat.label">{{ stat.icon }}</span>
                <input
                  type="number"
                  :value="form.modifiers[stat.key] || ''"
                  class="stat-input"
                  :placeholder="0"
                  @input="setModifier(stat.key, $event.target.value)"
                />
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Mechanic</label>
            <select v-model="form.mechanic">
              <option :value="null">None</option>
              <option value="stat_modifier">Stat Modifier Only</option>
              <option value="reduce_dice">Reduce Dice</option>
              <option value="grant_items">Grant Items</option>
              <option value="altered_deal">Altered Deal</option>
              <option value="score_event">Score Event</option>
            </select>
          </div>

          <div v-if="form.mechanic === 'reduce_dice'" class="form-group">
            <label>Dice to Remove</label>
            <input v-model.number="form.mechanic_data.amount" type="number" min="1" max="5" placeholder="1" />
          </div>

          <div v-if="form.mechanic === 'grant_items'" class="form-group mechanic-checkbox">
            <label>
              <input v-model="form.mechanic_data.random" type="checkbox" />
              Grant random item to each advisor
            </label>
          </div>

          <div v-if="form.mechanic === 'altered_deal'" class="form-group">
            <label>Positive Cards</label>
            <input v-model.number="form.mechanic_data.positive_cards" type="number" min="0" max="10" placeholder="2" />
            <label class="mt-label">Negative Cards</label>
            <input v-model.number="form.mechanic_data.negative_cards" type="number" min="0" max="10" placeholder="2" />
          </div>

          <div v-if="form.mechanic === 'score_event'" class="form-group">
            <label>Score Per Round</label>
            <input v-model.number="form.mechanic_data.score_per_round" type="number" placeholder="5" />
          </div>

          <!-- Duel Override -->
          <div class="form-group duel-override-toggle">
            <label>
              <input v-model="form.useDuelOverride" type="checkbox" />
              Use different settings for Duel mode
            </label>
          </div>

          <div v-if="form.useDuelOverride" class="duel-override-section">
            <div class="form-group">
              <label>Duel Stat Modifiers</label>
              <div class="stat-grid">
                <div v-for="stat in stats" :key="'duel-' + stat.key" class="stat-cell">
                  <span class="stat-icon-label" :title="stat.label">{{ stat.icon }}</span>
                  <input
                    type="number"
                    :value="form.modifiers_duel[stat.key] || ''"
                    class="stat-input"
                    :placeholder="0"
                    @input="setDuelModifier(stat.key, $event.target.value)"
                  />
                </div>
              </div>
            </div>

            <div class="form-group">
              <label>Duel Mechanic</label>
              <select v-model="form.mechanic_duel">
                <option :value="null">None</option>
                <option value="stat_modifier">Stat Modifier Only</option>
                <option value="reduce_dice">Reduce Dice</option>
                <option value="grant_items">Grant Items</option>
                <option value="altered_deal">Altered Deal</option>
                <option value="score_event">Score Event</option>
              </select>
            </div>

            <div v-if="form.mechanic_duel === 'reduce_dice'" class="form-group">
              <label>Dice to Remove (Duel)</label>
              <input v-model.number="form.mechanic_data_duel.amount" type="number" min="1" max="5" placeholder="1" />
            </div>

            <div v-if="form.mechanic_duel === 'grant_items'" class="form-group mechanic-checkbox">
              <label>
                <input v-model="form.mechanic_data_duel.random" type="checkbox" />
                Grant random item to each player (Duel)
              </label>
            </div>

            <div v-if="form.mechanic_duel === 'altered_deal'" class="form-group">
              <label>Positive Cards (Duel)</label>
              <input v-model.number="form.mechanic_data_duel.positive_cards" type="number" min="0" max="10" placeholder="2" />
              <label class="mt-label">Negative Cards (Duel)</label>
              <input v-model.number="form.mechanic_data_duel.negative_cards" type="number" min="0" max="10" placeholder="2" />
            </div>

            <div v-if="form.mechanic_duel === 'score_event'" class="form-group">
              <label>Score Per Round (Duel)</label>
              <input v-model.number="form.mechanic_data_duel.score_per_round" type="number" placeholder="5" />
            </div>
          </div>

          <div class="form-group">
            <label style="color: var(--accent-gold); font-weight: 600;">Availability</label>
            <div style="display: flex; gap: 16px; margin-top: 4px;">
              <label><input v-model="form.available_cooperative" type="checkbox" /> Co-op</label>
              <label><input v-model="form.available_duel" type="checkbox" /> Duel</label>
            </div>
          </div>

          <div class="form-group">
            <label>Addon</label>
            <select v-model="form.addon_id">
              <option :value="null">Base Game</option>
              <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
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
    <!-- AI Generate Modal -->
    <div v-if="showAiModal" class="modal-overlay" @click.self="showAiModal = false">
      <div class="modal-content">
        <h3>Generate Event with AI</h3>
        <div class="form-group">
          <label>Prompt (optional — describe the event you want)</label>
          <textarea v-model="aiPrompt" rows="3" placeholder="e.g. A plague that only affects left-handed people"></textarea>
        </div>
        <div v-if="aiError" class="form-error">{{ aiError }}</div>
        <div class="modal-actions">
          <button class="btn-primary" :disabled="aiGenerating" @click="generateWithAi">
            {{ aiGenerating ? 'Generating...' : 'Generate' }}
          </button>
          <button type="button" @click="showAiModal = false">Cancel</button>
        </div>
      </div>
    </div>

    <!-- In-game preview -->
    <AdminPreviewModal
      :visible="previewEvent !== undefined"
      :title="previewEvent?.title"
      @close="previewEvent = undefined"
    >
      <EventBanner
        v-if="previewEvent"
        :event="{
          title: previewEvent.title,
          effect: previewEvent.effect,
          stat_modifiers: previewEvent.stat_modifiers ?? undefined,
          mechanic: previewEvent.mechanic,
        }"
      />
    </AdminPreviewModal>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, useTemplateRef } from "vue";
import axios, { isAxiosError } from "axios";
import { useToast } from "../../stores/toast";
import EventBanner from "../EventBanner.vue";
import AdminPreviewModal from "./AdminPreviewModal.vue";
import AdminSearchInput from "./AdminSearchInput.vue";
import { useIcons } from "../../stores/icons";

interface StatIcon {
  key: string;
  label: string;
  short: string;
  type: string;
  value: string;
  icon: string;
}

type StatModifiers = Record<string, number>;
type MechanicData = Record<string, number | boolean>;

interface GameEvent {
  id: number;
  title: string;
  effect: string;
  stat_modifiers: StatModifiers | undefined;
  addon_id: number | undefined;
  mechanic: string | undefined;
  mechanic_data: MechanicData | undefined;
  stat_modifiers_duel: StatModifiers | undefined;
  mechanic_duel: string | undefined;
  mechanic_data_duel: MechanicData | undefined;
  available_cooperative: boolean | undefined;
  available_duel: boolean | undefined;
}

interface Addon {
  id: number;
  name: string;
}

interface ImportResult {
  created: number;
  updated: number;
  errors: string[];
}

interface EventForm {
  title: string;
  effect: string;
  modifiers: StatModifiers;
  addon_id: number | undefined;
  mechanic: string | undefined;
  mechanic_data: MechanicData;
  useDuelOverride: boolean;
  modifiers_duel: StatModifiers;
  mechanic_duel: string | undefined;
  mechanic_data_duel: MechanicData;
  available_cooperative: boolean;
  available_duel: boolean;
}

interface AiEventResponse {
  title?: string;
  effect?: string;
  stat_modifiers?: StatModifiers;
}

interface PerStatBalance {
  total: number;
  count: number;
  avg: number;
}

interface EventBalanceStats {
  count: number;
  perStat: Record<string, PerStatBalance>;
  mechanicDist: Record<string, number>;
  positiveCount: number;
  negativeCount: number;
  mixedCount: number;
  neutralCount: number;
}

function emptyForm(): EventForm {
  return {
    title: "", effect: "", modifiers: {}, addon_id: undefined, mechanic: undefined, mechanic_data: {},
    useDuelOverride: false, modifiers_duel: {}, mechanic_duel: undefined, mechanic_data_duel: {},
    available_cooperative: true, available_duel: true,
  };
}

function errorMessage(error: unknown, fallback: string): string {
  if (isAxiosError<{ message?: string; error?: string }>(error)) {
    return error.response?.data?.message ?? error.response?.data?.error ?? error.message;
  }
  return fallback;
}

const toast = useToast();

const events = ref<GameEvent[]>([]);
const addons = ref<Addon[]>([]);
const loading = ref(true);
const searchQuery = ref("");
const showModal = ref(false);
const editing = ref<GameEvent | undefined>(undefined);
const previewEvent = ref<GameEvent | undefined>(undefined);
const saving = ref(false);
const formError = ref("");
const showAiModal = ref(false);
const aiPrompt = ref("");
const aiGenerating = ref(false);
const aiError = ref("");
const importResult = ref<ImportResult | undefined>(undefined);
const showBalanceStats = ref(false);
const stats: StatIcon[] = useIcons().getStatIcons();
const form = reactive<EventForm>(emptyForm());
const csvInput = useTemplateRef<HTMLInputElement>("csvInput");

const filteredEvents = computed<GameEvent[]>(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return events.value;
  }
  return events.value.filter((event) =>
    (event.title || "").toLowerCase().includes(query)
    || (event.effect || "").toLowerCase().includes(query),
  );
});

function perStatBalance(key: string): PerStatBalance {
  let total = 0;
  let count = 0;
  for (const event of events.value) {
    if (!event.stat_modifiers || event.stat_modifiers[key] === undefined) {
      continue;
    }
    total += event.stat_modifiers[key];
    count++;
  }
  return { total, count, avg: count > 0 ? total / count : 0 };
}

const eventBalanceStats = computed<EventBalanceStats | undefined>(() => {
  if (events.value.length === 0) {
    return undefined;
  }
  const statKeys = ["wealth", "influence", "security", "religion", "food", "happiness"];

  // Per-stat modifier totals and averages
  const perStat: Record<string, PerStatBalance> = {};
  for (const key of statKeys) {
    perStat[key] = perStatBalance(key);
  }

  // Mechanic distribution
  const mechanicDistribution: Record<string, number> = {};
  for (const event of events.value) {
    const mechanic = event.mechanic || "none";
    mechanicDistribution[mechanic] = (mechanicDistribution[mechanic] || 0) + 1;
  }

  // Positive vs negative events
  let positiveCount = 0;
  let negativeCount = 0;
  let mixedCount = 0;
  let neutralCount = 0;
  for (const event of events.value) {
    if (!event.stat_modifiers || Object.keys(event.stat_modifiers).length === 0) {
      neutralCount++;
      continue;
    }
    const values = Object.values(event.stat_modifiers);
    const hasPositive = values.some((value) => value > 0);
    const hasNegative = values.some((value) => value < 0);
    if (hasPositive && hasNegative) {
      mixedCount++;
    } else if (hasPositive) {
      positiveCount++;
    } else if (hasNegative) {
      negativeCount++;
    } else {
      neutralCount++;
    }
  }

  return {
    count: events.value.length,
    perStat,
    mechanicDist: mechanicDistribution,
    positiveCount,
    negativeCount,
    mixedCount,
    neutralCount,
  };
});

async function fetch(): Promise<void> {
  loading.value = true;
  const response = await axios.get<GameEvent[]>("/api/admin/events");
  events.value = response.data;
  loading.value = false;
}

async function fetchAddons(): Promise<void> {
  try {
    const response = await axios.get<Addon[]>("/api/admin/addons");
    addons.value = response.data;
  } catch { /* ignore */ }
}

function mechanicLabel(mechanic: string): string {
  const labels: Record<string, string> = {
    stat_modifier: "Stat Modifier Only",
    reduce_dice: "Reduce Dice",
    grant_items: "Grant Items",
    altered_deal: "Altered Deal",
    score_event: "Score Event",
  };
  return labels[mechanic] || mechanic;
}

function statIcon(stat: string): string {
  const match = stats.find((entry) => entry.key === stat);
  return match ? (match.icon || match.value) : "";
}

function setModifier(key: string, value: string): void {
  const parsed = value === "" ? NaN : Math.trunc(Number(value));
  if (parsed === 0 || Number.isNaN(parsed)) {
    Reflect.deleteProperty(form.modifiers, key);
  } else {
    form.modifiers[key] = parsed;
  }
}

function setDuelModifier(key: string, value: string): void {
  const parsed = value === "" ? NaN : Math.trunc(Number(value));
  if (parsed === 0 || Number.isNaN(parsed)) {
    Reflect.deleteProperty(form.modifiers_duel, key);
  } else {
    form.modifiers_duel[key] = parsed;
  }
}

function openPreview(event: GameEvent): void {
  previewEvent.value = event;
}

function openCreate(): void {
  editing.value = undefined;
  Object.assign(form, emptyForm());
  formError.value = "";
  showModal.value = true;
}

function openEdit(event: GameEvent): void {
  editing.value = event;
  const hasDuelOverride = event.stat_modifiers_duel != undefined || event.mechanic_duel != undefined;
  Object.assign(form, {
    title: event.title,
    effect: event.effect,
    modifiers: { ...event.stat_modifiers },
    addon_id: event.addon_id ?? undefined,
    mechanic: event.mechanic ?? undefined,
    mechanic_data: { ...event.mechanic_data },
    useDuelOverride: hasDuelOverride,
    modifiers_duel: { ...event.stat_modifiers_duel },
    mechanic_duel: event.mechanic_duel ?? undefined,
    mechanic_data_duel: { ...event.mechanic_data_duel },
    available_cooperative: event.available_cooperative ?? true,
    available_duel: event.available_duel ?? true,
  });
  formError.value = "";
  showModal.value = true;
}

async function save(): Promise<void> {
  formError.value = "";

  const statModifiers = Object.keys(form.modifiers).length > 0
    ? { ...form.modifiers }
    : undefined;

  const mechanic = form.mechanic || undefined;
  const mechanicData = mechanic && Object.keys(form.mechanic_data).length > 0
    ? { ...form.mechanic_data }
    : undefined;

  // Duel overrides
  let statModifiersDuel: StatModifiers | undefined;
  let mechanicDuel: string | undefined;
  let mechanicDataDuel: MechanicData | undefined;
  if (form.useDuelOverride) {
    statModifiersDuel = Object.keys(form.modifiers_duel).length > 0
      ? { ...form.modifiers_duel }
      : undefined;
    mechanicDuel = form.mechanic_duel || undefined;
    mechanicDataDuel = mechanicDuel && Object.keys(form.mechanic_data_duel).length > 0
      ? { ...form.mechanic_data_duel }
      : undefined;
  }

  const payload = {
    title: form.title,
    effect: form.effect,
    stat_modifiers: statModifiers,
    addon_id: form.addon_id || undefined,
    mechanic,
    mechanic_data: mechanicData,
    stat_modifiers_duel: statModifiersDuel,
    mechanic_duel: mechanicDuel,
    mechanic_data_duel: mechanicDataDuel,
    available_cooperative: form.available_cooperative,
    available_duel: form.available_duel,
  };

  saving.value = true;
  try {
    const current = editing.value;
    if (current) {
      await axios.put(`/api/admin/events/${current.id}`, payload);
    } else {
      await axios.post("/api/admin/events", payload);
    }
    showModal.value = false;
    await fetch();
  } catch (error) {
    formError.value = errorMessage(error, "Save failed");
  }
  saving.value = false;
}

async function generateWithAi(): Promise<void> {
  aiError.value = "";
  aiGenerating.value = true;
  try {
    const response = await axios.post<AiEventResponse>("/api/admin/ai/generate-event", {
      prompt: aiPrompt.value || undefined,
    });
    const data = response.data;
    showAiModal.value = false;
    aiPrompt.value = "";
    // Open create modal pre-filled with AI data
    editing.value = undefined;
    Object.assign(form, {
      ...emptyForm(),
      title: data.title || "",
      effect: data.effect || "",
      modifiers: { ...data.stat_modifiers },
    });
    formError.value = "";
    showModal.value = true;
  } catch (error) {
    aiError.value = errorMessage(error, "AI generation failed");
  }
  aiGenerating.value = false;
}

function exportCsv(): void {
  window.location.assign("/api/admin/events/export-csv");
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
    const response = await axios.post<ImportResult>("/api/admin/events/import-csv", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    importResult.value = response.data;
    await fetch();
  } catch (error) {
    importResult.value = { created: 0, updated: 0, errors: [errorMessage(error, "Import failed")] };
  }
  target.value = "";
}

async function confirmDelete(event: GameEvent): Promise<void> {
  if (!confirm(`Delete event "${event.title}"?`)) {
    return;
  }
  try {
    await axios.delete(`/api/admin/events/${event.id}`);
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

.header-buttons {
  display: flex;
  gap: 8px;
}

.btn-ai {
  background: rgba(100, 60, 180, 0.2);
  color: #b080e0;
  border: 1px solid rgba(100, 60, 180, 0.4);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.btn-ai:hover {
  background: rgba(100, 60, 180, 0.35);
  border-color: rgba(140, 90, 210, 0.6);
}

.btn-ai:disabled {
  opacity: 0.5;
  cursor: default;
}

.loading { text-align: center; color: var(--text-secondary); padding: 40px; }

.cards-list { display: flex; flex-direction: column; gap: 12px; }

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
  font-size: 1.1rem;
}

.item-actions { display: flex; gap: 6px; }
.item-actions button { padding: 5px 12px; font-size: 0.8rem; }

.item-desc { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 8px; }

.item-meta { font-size: 0.85rem; color: var(--text-secondary); }
.item-meta strong { color: var(--text-primary); }

.mod-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  margin-left: 6px;
  font-size: 0.8rem;
  text-transform: capitalize;
}

.mod-pos { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
.mod-neg { background: rgba(192, 57, 43, 0.2); color: #c0392b; }

.mechanic-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  margin-left: 6px;
  font-size: 0.8rem;
  background: rgba(100, 60, 180, 0.2);
  color: #b080e0;
}

.mechanic-checkbox label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-bright);
  font-size: 0.9rem;
  cursor: pointer;
}

.mechanic-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--accent-gold);
}

.mt-label { margin-top: 8px; }

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
.form-group input:not([type="checkbox"]):not([type="number"]), .form-group textarea, .form-group select {
  width: 100%; background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright); padding: 8px 12px;
  border-radius: 4px; font-family: inherit; font-size: 0.95rem;
}
.form-group textarea { resize: vertical; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-gold); }

/* Stat grid */
.stat-grid {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.stat-cell {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(138, 106, 46, 0.15);
  border-radius: 6px;
  padding: 6px 8px;
}

.stat-icon-label {
  font-size: 1.2rem;
  line-height: 1;
  cursor: help;
}

.stat-input {
  width: 52px;
  background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright);
  padding: 4px 6px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.9rem;
  text-align: center;
}

.stat-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.form-error { color: var(--accent-red); font-size: 0.9rem; margin-bottom: 10px; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }

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

.balance-table {
  font-size: 0.85rem;
}

.name-col {
  color: var(--text-bright);
  font-weight: 600;
}

/* Duel Override */
.duel-override-toggle label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #b080e0;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
}

.duel-override-toggle input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #b080e0;
}

.duel-override-section {
  border: 1px solid rgba(100, 60, 180, 0.3);
  border-radius: 8px;
  padding: 14px;
  margin-bottom: 14px;
  background: rgba(100, 60, 180, 0.06);
}

.duel-override-section .form-group label {
  color: #b080e0;
}

.duel-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  margin-right: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  background: rgba(100, 60, 180, 0.2);
  color: #b080e0;
  border: 1px solid rgba(100, 60, 180, 0.3);
}
</style>
