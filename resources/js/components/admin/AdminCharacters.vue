<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Characters</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input ref="csvInput" type="file" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Character</button>
        <button class="btn-ai" @click="showAiModal = true">Generate with AI</button>
      </div>
    </div>

    <div v-if="importResult" class="import-result" :class="importResult.errors.length > 0 ? 'import-warn' : 'import-ok'">
      CSV Import: {{ importResult.created }} created, {{ importResult.updated }} updated.
      <span v-if="importResult.errors.length > 0"> {{ importResult.errors.length }} error(s).</span>
      <div v-for="(err, i) in importResult.errors" :key="i" class="import-error-line">{{ err }}</div>
      <button class="import-dismiss" @click="importResult = undefined">Dismiss</button>
    </div>

    <!-- Dice per player count rules -->
    <div class="rules-panel">
      <h3 class="rules-title">Dice per Advisor Count</h3>
      <p class="rules-desc">How many dice each advisor rolls, based on party size.</p>
      <div class="rules-grid">
        <div v-for="n in 6" :key="n" class="rule-cell">
          <label class="rule-label">{{ n }} {{ n === 1 ? 'Advisor' : 'Advisors' }}</label>
          <input
            type="number"
            min="1"
            max="3"
            :value="diceRules[n]"
            class="rule-input"
            @change="updateDiceRule(n, $event)"
          />
          <span class="rule-dice-text">{{ diceRules[n] === 1 ? 'die' : 'dice' }}</span>
        </div>
      </div>
      <p v-if="rulesSaved" class="rules-saved">Saved!</p>
    </div>

    <!-- Balance Stats Panel -->
    <div class="rules-panel balance-panel">
      <div class="balance-header" @click="showBalanceStats = !showBalanceStats">
        <h3 class="rules-title">Balance Stats</h3>
        <button type="button" class="balance-toggle">{{ showBalanceStats ? 'Hide' : 'Show' }}</button>
      </div>
      <div v-if="showBalanceStats && characterBalanceStats" class="balance-body">
        <div class="balance-summary">
          <div class="balance-stat-card">
            <span class="balance-stat-label">Overall Avg Roll</span>
            <span class="balance-stat-value">{{ characterBalanceStats.overallAvg.toFixed(2) }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Highest Roller</span>
            <span class="balance-stat-value">{{ characterBalanceStats.highest.name }} ({{ characterBalanceStats.highest.totalAvg.toFixed(2) }})</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Lowest Roller</span>
            <span class="balance-stat-value">{{ characterBalanceStats.lowest.name }} ({{ characterBalanceStats.lowest.totalAvg.toFixed(2) }})</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg Wild Value</span>
            <span class="balance-stat-value">{{ characterBalanceStats.avgWild.toFixed(2) }}</span>
          </div>
        </div>
        <div class="balance-wild-dist">
          <span class="balance-dist-label">Wild Value Distribution:</span>
          <span v-for="(count, val) in characterBalanceStats.wildDist" :key="val" class="balance-dist-badge">
            {{ val }}: {{ count }}
          </span>
        </div>
        <table class="admin-table balance-table">
          <thead>
            <tr>
              <th>Character</th>
              <th>Die 1 Avg</th>
              <th>Die 2 Avg</th>
              <th>Die 3 Avg</th>
              <th>Total Avg</th>
              <th>Wild</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in characterBalanceStats.sorted" :key="c.name">
              <td class="name-col">{{ c.name }}</td>
              <td>{{ c.dieAvgs[0]?.toFixed(2) || '-' }}</td>
              <td>{{ c.dieAvgs[1]?.toFixed(2) || '-' }}</td>
              <td>{{ c.dieAvgs[2]?.toFixed(2) || '-' }}</td>
              <td class="balance-total">{{ c.totalAvg.toFixed(2) }}</td>
              <td>{{ c.wildValue }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AdminSearchInput v-model="searchQuery" />

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <SortableHeader label="ID" field="id" :current-sort="sortField" :current-dir="sortDirection" @sort="toggleSort" />
            <th>Image</th>
            <SortableHeader label="Name" field="name" :current-sort="sortField" :current-dir="sortDirection" @sort="toggleSort" />
            <th>Description</th>
            <SortableHeader label="Wild" field="wild_value" :current-sort="sortField" :current-dir="sortDirection" @sort="toggleSort" />
            <th>Ability</th>
            <th>Die 1</th>
            <th>Die 2</th>
            <th>Die 3</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="c in filteredCharacters" :key="c.id">
            <tr>
              <td>{{ c.id }}</td>
              <td class="image-col">
                <img :src="c.image_url || '/images/character.png'" class="char-thumb" />
              </td>
              <td class="name-col">
                {{ c.name }}
                <span v-if="c.is_available === false" class="unavailable-tag">Unavailable</span>
                <div v-if="c.unlock_info && c.unlock_info.length > 0" class="unlock-tags">
                  <span v-for="u in c.unlock_info" :key="u.id" class="unlock-tag">
                    {{ u.method === 'level' ? 'Lvl ' + u.value : 'Achievement #' + u.value }}
                  </span>
                </div>
              </td>
              <td class="desc-col">{{ truncate(c.description, 60) }}</td>
              <td>{{ c.wild_value }}</td>
              <td>{{ c.wild_ability }}</td>
              <td class="dice-col">{{ c.dice[0]?.join(', ') }}</td>
              <td class="dice-col">{{ c.dice[1]?.join(', ') }}</td>
              <td class="dice-col">{{ c.dice[2]?.join(', ') }}</td>
              <td class="actions-col">
                <button @click="openEdit(c)">Edit</button>
                <button class="btn-danger" @click="confirmDelete(c)">Delete</button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Character' : 'New Character' }}</h3>
        <form @submit.prevent="save">
          <div v-if="editing" class="form-group">
            <label>Character Image</label>
            <div class="image-upload-row">
              <img :src="editing.image_url || '/images/character.png'" class="image-preview" />
              <button type="button" class="upload-btn" @click="showMediaLibrary = true">Choose from Media Library</button>
              <span v-if="imageUploaded" class="upload-success">Updated!</span>
            </div>
          </div>

          <div class="form-group">
            <label>Name</label>
            <input v-model="form.name" required />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="4" required></textarea>
          </div>
          <div class="form-group">
            <label>Wild Value (numeric value when WILD is rolled)</label>
            <input v-model.number="form.wild_value" type="number" min="1" max="10" required />
          </div>
          <div class="form-group">
            <label>Wild Ability (e.g. inspire, rally, divine, gamble, shadow, wisdom)</label>
            <input v-model="form.wild_ability" required />
          </div>
          <div class="form-group">
            <label>Wild Ability Description</label>
            <input v-model="form.wild_ability_description" />
          </div>
          <div class="form-group">
            <label>Die 1 (6 faces, comma-separated: numbers or WILD)</label>
            <input v-model="die1Input" placeholder="3, 3, 4, 2, 2, WILD" required />
          </div>
          <div class="form-group">
            <label>Die 2 (6 faces)</label>
            <input v-model="die2Input" placeholder="4, 4, 3, 1, 1, WILD" required />
          </div>
          <div class="form-group">
            <label>Die 3 (6 faces)</label>
            <input v-model="die3Input" placeholder="2, 3, 3, 2, 3, WILD" required />
          </div>

          <!-- Duel Dice Override -->
          <div style="border: 1px solid rgba(138, 58, 185, 0.3); background: rgba(138, 58, 185, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
              <input v-model="form.useDuelDice" type="checkbox" />
              <span style="color: #c890e0; font-weight: 700;">Use different dice for Duel mode</span>
            </label>
            <template v-if="form.useDuelDice">
              <div class="form-group">
                <label>Duel Wild Value</label>
                <input v-model.number="form.wild_value_duel" type="number" min="1" max="10" />
              </div>
              <div class="form-group">
                <label>Duel Wild Ability</label>
                <input v-model="form.wild_ability_duel" />
              </div>
              <div class="form-group">
                <label>Duel Wild Ability Description</label>
                <input v-model="form.wild_ability_description_duel" />
              </div>
              <div class="form-group">
                <label>Duel Die 1 (6 faces, comma-separated)</label>
                <input v-model="die1DuelInput" placeholder="3, 3, 4, 2, 2, WILD" />
              </div>
              <div class="form-group">
                <label>Duel Die 2 (6 faces)</label>
                <input v-model="die2DuelInput" placeholder="4, 4, 3, 1, 1, WILD" />
              </div>
              <div class="form-group">
                <label>Duel Die 3 (6 faces)</label>
                <input v-model="die3DuelInput" placeholder="2, 3, 3, 2, 3, WILD" />
              </div>
            </template>
          </div>

          <!-- Starting Bonus -->
          <div style="border: 1px solid rgba(40, 160, 80, 0.3); background: rgba(40, 160, 80, 0.05); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
              <input v-model="form.hasStartingBonus" type="checkbox" />
              <span style="color: #5ab87a; font-weight: 700;">Starting Bonus</span>
            </label>
            <template v-if="form.hasStartingBonus">
              <div class="form-group">
                <label>Extra Dice (0 = none)</label>
                <input v-model.number="form.bonusExtraDice" type="number" min="0" max="3" />
              </div>
              <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                  <input v-model="form.bonusRandomItem" type="checkbox" />
                  <span>Starts with a Random Item</span>
                </label>
              </div>
              <div class="form-group">
                <label>Stat Boosts</label>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                  <div v-for="stat in ['wealth', 'influence', 'security', 'religion', 'food', 'happiness']" :key="stat" style="display: flex; align-items: center; gap: 4px;">
                    <span style="font-size: 0.8rem; color: var(--text-secondary); min-width: 65px;">{{ stat.charAt(0).toUpperCase() + stat.slice(1) }}</span>
                    <input
                      type="number"
                      :value="form.bonusStatBoosts[stat] || 0"
                      min="-10"
                      max="10"
                      style="width: 56px;"
                      @input="setStatBoost(stat, $event)"
                    />
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div class="form-group">
            <label>Addon</label>
            <select v-model="form.addon_id">
              <option :value="undefined">Base Game</option>
              <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <div class="form-group">
            <label style="color: var(--accent-gold); font-weight: 600;">Availability</label>
            <div style="display: flex; gap: 16px; margin-top: 4px;">
              <label><input v-model="form.available_cooperative" type="checkbox" /> Co-op</label>
              <label><input v-model="form.available_duel" type="checkbox" /> Duel</label>
            </div>
          </div>

          <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px;">
              <input v-model="form.is_available" type="checkbox" />
              <span :style="form.is_available ? '' : 'color: #d05040; font-weight: 600;'">
                {{ form.is_available ? 'Available (shown to all players)' : 'Unavailable (hidden unless owned)' }}
              </span>
            </label>
          </div>

          <!-- Level Options (edit mode only) -->
          <div v-if="editing" class="level-options-section">
            <div class="lo-header" @click="showLevelOptions = !showLevelOptions">
              <span class="lo-title">Level Options ({{ charLevelOptions.length }})</span>
              <button type="button" class="balance-toggle">{{ showLevelOptions ? 'Hide' : 'Show' }}</button>
            </div>

            <div v-if="showLevelOptions" class="lo-body">
              <div v-if="charLevelOptions.length === 0" class="lo-empty">No level options for this character.</div>
              <table v-else class="admin-table lo-table">
                <thead>
                  <tr>
                    <th>Lvl</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Max</th>
                    <th>Active</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="opt in charLevelOptions" :key="opt.id">
                    <td>{{ opt.available_at_level }}</td>
                    <td>{{ opt.name }}</td>
                    <td><span class="type-badge">{{ opt.type }}</span></td>
                    <td>{{ opt.max_selections || '&infin;' }}</td>
                    <td><span :class="opt.is_active ? 'status-active' : 'status-inactive'">{{ opt.is_active ? 'Yes' : 'No' }}</span></td>
                    <td class="lo-actions">
                      <button type="button" class="btn-lo-edit" @click="openEditLo(opt)">Edit</button>
                      <button type="button" class="btn-lo-delete" @click="deleteLo(opt)">Del</button>
                    </td>
                  </tr>
                </tbody>
              </table>

              <button type="button" class="btn-lo-add" @click="openCreateLo">+ Add Level Option</button>

              <!-- Inline level option form -->
              <div v-if="showLoForm" class="lo-form">
                <h4 class="lo-form-title">{{ editingLo ? 'Edit Level Option' : 'New Level Option' }}</h4>
                <div class="lo-form-grid">
                  <div class="form-group">
                    <label>Name</label>
                    <input v-model="loForm.name" required />
                  </div>
                  <div class="form-group">
                    <label>Type</label>
                    <select v-model="loForm.type">
                      <option value="bump_dice_face">Bump Dice Face</option>
                      <option value="bump_two_dice_faces">Bump Two Dice Faces</option>
                      <option value="start_with_item">Start With Item</option>
                      <option value="extra_item_slot">Extra Item Slot</option>
                      <option value="passive_stat_bonus">Passive Stat Bonus</option>
                      <option value="add_wild">Add Wild</option>
                      <option value="card_redraw">Card Redraw</option>
                      <option value="start_with_curse">Start With Curse</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Available at Level</label>
                    <input v-model.number="loForm.available_at_level" type="number" min="1" max="7" />
                  </div>
                  <div class="form-group">
                    <label>Max Selections (0 = unlimited)</label>
                    <input v-model.number="loForm.max_selections" type="number" min="0" />
                  </div>
                  <div class="form-group">
                    <label>Sort Order</label>
                    <input v-model.number="loForm.sort_order" type="number" min="0" />
                  </div>
                  <div class="form-group">
                    <label>Icon</label>
                    <input v-model="loForm.icon" />
                  </div>
                  <div class="form-group lo-full-width">
                    <label>Description</label>
                    <input v-model="loForm.description" />
                  </div>
                  <div class="form-group">
                    <label><input v-model="loForm.is_active" type="checkbox" /> Active</label>
                  </div>

                  <!-- Type-specific config -->
                  <template v-if="loForm.type === 'passive_stat_bonus'">
                    <div class="form-group">
                      <label>Stat</label>
                      <select v-model="loConfigStat">
                        <option v-for="s in loStatOptions" :key="s" :value="s">{{ s }}</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Value</label>
                      <input v-model.number="loConfigValue" type="number" min="1" />
                    </div>
                  </template>
                  <div v-if="loForm.type === 'start_with_item'" class="form-group">
                    <label>Item ID (blank for random)</label>
                    <input v-model.number="loConfigItemId" type="number" min="1" />
                  </div>
                  <div v-if="loForm.type === 'start_with_curse'" class="form-group">
                    <label>Curse ID (blank for random)</label>
                    <input v-model.number="loConfigCurseId" type="number" min="1" />
                  </div>
                </div>

                <div v-if="loFormError" class="form-error">{{ loFormError }}</div>
                <div class="lo-form-actions">
                  <button type="button" class="btn-lo-save" @click="saveLo">{{ editingLo ? 'Update' : 'Create' }}</button>
                  <button type="button" class="btn-lo-cancel" @click="showLoForm = false">Cancel</button>
                </div>
              </div>
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
    <!-- AI Generate Modal -->
    <div v-if="showAiModal" class="modal-overlay" @click.self="showAiModal = false">
      <div class="modal-content">
        <h3>Generate Character with AI</h3>
        <div class="form-group">
          <label>Prompt (optional — describe the character you want)</label>
          <textarea v-model="aiPrompt" rows="3" placeholder="e.g. A cowardly knight who is secretly brilliant at accounting"></textarea>
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
    <!-- Media Library Modal for character image -->
    <MediaLibraryModal
      :visible="showMediaLibrary"
      :select-mode="true"
      @close="showMediaLibrary = false"
      @select="onMediaSelected"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, useTemplateRef } from "vue";
import axios, { isAxiosError } from "axios";
import { useToast } from "../../stores/toast";
import AdminSearchInput from "./AdminSearchInput.vue";
import SortableHeader from "./SortableHeader.vue";
import MediaLibraryModal from "./MediaLibraryModal.vue";

type DieFace = number | "WILD";
type Die = DieFace[];
type StatBoosts = Record<string, number>;

interface StartingBonus {
  extra_dice?: number;
  random_item?: boolean;
  stat_boosts?: StatBoosts;
}

interface UnlockInfo {
  id: number;
  method: string;
  value: number;
}

interface Character {
  id: number;
  name: string;
  description: string;
  wild_value: number;
  wild_ability: string;
  wild_ability_description?: string;
  dice: Die[];
  dice_duel?: Die[];
  wild_value_duel?: number;
  wild_ability_duel?: string;
  wild_ability_description_duel?: string;
  starting_bonus?: StartingBonus;
  addon_id?: number;
  available_cooperative?: boolean;
  available_duel?: boolean;
  is_available?: boolean;
  image_url?: string;
  unlock_info?: UnlockInfo[];
}

interface Addon {
  id: number;
  name: string;
}

interface CharacterForm {
  name: string;
  description: string;
  wild_value: number;
  wild_ability: string;
  wild_ability_description: string;
  addon_id: number | undefined;
  available_cooperative: boolean;
  available_duel: boolean;
  is_available: boolean;
  useDuelDice: boolean;
  wild_value_duel: number;
  wild_ability_duel: string;
  wild_ability_description_duel: string;
  hasStartingBonus: boolean;
  bonusExtraDice: number;
  bonusRandomItem: boolean;
  bonusStatBoosts: StatBoosts;
}

interface LevelOptionConfig {
  stat?: string;
  value?: number;
  item_id?: number;
  curse_id?: number;
  random?: boolean;
}

interface LevelOption {
  id: number;
  character_id: number;
  name: string;
  type: string;
  available_at_level: number;
  is_active: boolean;
  max_selections: number;
  sort_order: number;
  description?: string;
  icon?: string;
  config?: LevelOptionConfig;
}

interface LevelOptionForm {
  name: string;
  type: string;
  available_at_level: number;
  is_active: boolean;
  max_selections: number;
  sort_order: number;
  description: string;
  icon: string;
}

interface AiCharacterResponse {
  name?: string;
  description?: string;
  wild_value?: number;
  wild_ability?: string;
  wild_ability_description?: string;
  dice?: Die[];
}

interface MediaItem {
  id: number;
}

interface ImportResult {
  created: number;
  updated: number;
  errors: string[];
}

type DiceRules = Record<number, number>;

interface CharacterStat {
  name: string;
  dieAvgs: number[];
  totalAvg: number;
  wildValue: number;
}

interface CharacterBalanceStats {
  sorted: CharacterStat[];
  overallAvg: number;
  highest: CharacterStat;
  lowest: CharacterStat;
  wildDist: Record<number, number>;
  avgWild: number;
}

function defaultForm(): CharacterForm {
  return {
    name: "",
    description: "",
    wild_value: 3,
    wild_ability: "",
    wild_ability_description: "",
    addon_id: undefined,
    available_cooperative: true,
    available_duel: true,
    is_available: true,
    useDuelDice: false,
    wild_value_duel: 3,
    wild_ability_duel: "",
    wild_ability_description_duel: "",
    hasStartingBonus: false,
    bonusExtraDice: 0,
    bonusRandomItem: false,
    bonusStatBoosts: {},
  };
}

function defaultLevelOptionForm(): LevelOptionForm {
  return {
    name: "",
    type: "bump_dice_face",
    available_at_level: 1,
    is_active: true,
    max_selections: 0,
    sort_order: 0,
    description: "",
    icon: "",
  };
}

function errorMessage(error: unknown, fallback: string): string {
  if (isAxiosError<{ message?: string; error?: string }>(error)) {
    return error.response?.data?.message ?? error.response?.data?.error ?? error.message;
  }
  return fallback;
}

const LO_STAT_OPTIONS = ["wealth", "influence", "security", "religion", "food", "happiness"];

const toast = useToast();

const characters = ref<Character[]>([]);
const addons = ref<Addon[]>([]);
const loading = ref(true);
const searchQuery = ref("");
const sortField = ref("id");
const sortDirection = ref<"asc" | "desc">("asc");
const showModal = ref(false);
const editing = ref<Character | undefined>(undefined);
const saving = ref(false);
const formError = ref("");
const form = reactive<CharacterForm>(defaultForm());
const showMediaLibrary = ref(false);
const die1Input = ref("");
const die2Input = ref("");
const die3Input = ref("");
const die1DuelInput = ref("");
const die2DuelInput = ref("");
const die3DuelInput = ref("");
const diceRules = reactive<DiceRules>({ 1: 3, 2: 3, 3: 3, 4: 3, 5: 3, 6: 3 });
const rulesSaved = ref(false);
const imageUploaded = ref(false);
const showAiModal = ref(false);
const aiPrompt = ref("");
const aiGenerating = ref(false);
const aiError = ref("");
const importResult = ref<ImportResult | undefined>(undefined);
const showBalanceStats = ref(false);
// Level options
const showLevelOptions = ref(false);
const charLevelOptions = ref<LevelOption[]>([]);
const showLoForm = ref(false);
const editingLo = ref<number | undefined>(undefined);
const loFormError = ref("");
const loForm = reactive<LevelOptionForm>(defaultLevelOptionForm());
const loConfigStat = ref("wealth");
const loConfigValue = ref(1);
const loConfigItemId = ref<number | undefined>(undefined);
const loConfigCurseId = ref<number | undefined>(undefined);

const csvInput = useTemplateRef<HTMLInputElement>("csvInput");

const rulesSavedTimeout = ref<ReturnType<typeof setTimeout> | undefined>(undefined);
const imageUploadedTimeout = ref<ReturnType<typeof setTimeout> | undefined>(undefined);

const loStatOptions = computed<string[]>(() => LO_STAT_OPTIONS);

function sortValue(character: Character, field: string): string | number {
  if (field === "id") {
    return character.id;
  }
  if (field === "name") {
    return character.name;
  }
  if (field === "wild_value") {
    return character.wild_value;
  }
  return "";
}

const filteredCharacters = computed<Character[]>(() => {
  let list = characters.value;
  const query = searchQuery.value.toLowerCase().trim();
  if (query) {
    list = list.filter((character) =>
      character.name.toLowerCase().includes(query)
      || (character.description || "").toLowerCase().includes(query)
      || (character.wild_ability || "").toLowerCase().includes(query),
    );
  }
  const field = sortField.value;
  const direction = sortDirection.value === "asc" ? 1 : -1;
  return list.toSorted((a, b) => {
    const av = sortValue(a, field);
    const bv = sortValue(b, field);
    if (typeof av === "number" && typeof bv === "number") {
      return (av - bv) * direction;
    }
    return String(av).localeCompare(String(bv)) * direction;
  });
});

const characterBalanceStats = computed<CharacterBalanceStats | undefined>(() => {
  if (characters.value.length === 0) {
    return undefined;
  }
  const charStats: CharacterStat[] = characters.value.map((character) => {
    const dieAvgs = (character.dice || []).map((die) => {
      if (!die || die.length === 0) {
        return 0;
      }
      const values = die.map((face) => (face === "WILD" ? character.wild_value : Number(face)));
      let sum = 0;
      for (const value of values) {
        sum += value;
      }
      return sum / values.length;
    });
    let totalAvg = 0;
    for (const value of dieAvgs) {
      totalAvg += value;
    }
    return { name: character.name, dieAvgs, totalAvg, wildValue: character.wild_value };
  });
  const sorted = charStats.toSorted((a, b) => b.totalAvg - a.totalAvg);
  let totalAvgSum = 0;
  for (const stat of charStats) {
    totalAvgSum += stat.totalAvg;
  }
  const overallAvg = totalAvgSum / charStats.length;
  const highest = sorted[0];
  const lowest = sorted.at(-1) ?? highest;
  const wildDistribution: Record<number, number> = {};
  let wildSum = 0;
  for (const character of characters.value) {
    const wildValue = character.wild_value;
    wildDistribution[wildValue] = (wildDistribution[wildValue] || 0) + 1;
    wildSum += wildValue;
  }
  const avgWild = wildSum / characters.value.length;
  return { sorted, overallAvg, highest, lowest, wildDist: wildDistribution, avgWild };
});

function toggleSort(field: string): void {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
  } else {
    sortField.value = field;
    sortDirection.value = "asc";
  }
}

async function fetch(): Promise<void> {
  loading.value = true;
  const response = await axios.get<Character[]>("/api/admin/characters");
  characters.value = response.data;
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

async function fetchRules(): Promise<void> {
  try {
    const response = await axios.get<{ dice_per_player_count?: DiceRules }>("/api/admin/rules");
    if (response.data.dice_per_player_count) {
      Object.assign(diceRules, response.data.dice_per_player_count);
    }
  } catch {
    // use defaults
  }
}

async function updateDiceRule(count: number, event: Event): Promise<void> {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) {
    return;
  }
  const parsed = Math.trunc(Number(target.value));
  const number_ = Math.max(1, Math.min(3, parsed === 0 || Number.isNaN(parsed) ? 3 : parsed));
  diceRules[count] = number_;
  try {
    await axios.put("/api/admin/rules/dice_per_player_count", {
      value: { ...diceRules },
    });
    rulesSaved.value = true;
    if (rulesSavedTimeout.value) {
      clearTimeout(rulesSavedTimeout.value);
    }
    rulesSavedTimeout.value = setTimeout(() => {
      rulesSaved.value = false;
    }, 1500);
  } catch {
    // silently fail
  }
}

function truncate(value: string, length: number): string {
  return value.length > length ? `${value.slice(0, Math.max(0, length))}...` : value;
}

function parseDie(input: string): string[] {
  return input.split(",").map((face) => face.trim()).filter(Boolean);
}

function processDie(die: string[]): Die {
  return die.map((face) => (face === "WILD" ? "WILD" : Number(face)));
}

function openCreate(): void {
  editing.value = undefined;
  Object.assign(form, defaultForm());
  die1Input.value = "";
  die2Input.value = "";
  die3Input.value = "";
  die1DuelInput.value = "";
  die2DuelInput.value = "";
  die3DuelInput.value = "";
  formError.value = "";
  imageUploaded.value = false;
  showLevelOptions.value = false;
  charLevelOptions.value = [];
  showLoForm.value = false;
  showModal.value = true;
}

function openEdit(character: Character): void {
  editing.value = character;
  imageUploaded.value = false;
  const hasDuelDice = character.dice_duel != undefined || character.wild_value_duel != undefined;
  const bonus = character.starting_bonus || {};
  const hasBonus = Boolean(bonus.extra_dice || bonus.random_item || (bonus.stat_boosts && Object.keys(bonus.stat_boosts).length > 0));
  Object.assign(form, {
    name: character.name,
    description: character.description,
    wild_value: character.wild_value,
    wild_ability: character.wild_ability,
    wild_ability_description: character.wild_ability_description || "",
    addon_id: character.addon_id || undefined,
    available_cooperative: character.available_cooperative ?? true,
    available_duel: character.available_duel ?? true,
    is_available: character.is_available ?? true,
    useDuelDice: hasDuelDice,
    wild_value_duel: character.wild_value_duel ?? character.wild_value,
    wild_ability_duel: character.wild_ability_duel ?? character.wild_ability ?? "",
    wild_ability_description_duel: character.wild_ability_description_duel ?? character.wild_ability_description ?? "",
    hasStartingBonus: hasBonus,
    bonusExtraDice: bonus.extra_dice || 0,
    bonusRandomItem: Boolean(bonus.random_item),
    bonusStatBoosts: { ...bonus.stat_boosts },
  });
  die1Input.value = character.dice[0]?.join(", ") || "";
  die2Input.value = character.dice[1]?.join(", ") || "";
  die3Input.value = character.dice[2]?.join(", ") || "";
  die1DuelInput.value = hasDuelDice ? (character.dice_duel?.[0]?.join(", ") || "") : "";
  die2DuelInput.value = hasDuelDice ? (character.dice_duel?.[1]?.join(", ") || "") : "";
  die3DuelInput.value = hasDuelDice ? (character.dice_duel?.[2]?.join(", ") || "") : "";
  formError.value = "";
  showLevelOptions.value = false;
  showLoForm.value = false;
  showModal.value = true;
  void fetchLevelOptions(character.id);
}

async function save(): Promise<void> {
  formError.value = "";
  const die1 = parseDie(die1Input.value);
  const die2 = parseDie(die2Input.value);
  const die3 = parseDie(die3Input.value);

  if (die1.length !== 6 || die2.length !== 6 || die3.length !== 6) {
    formError.value = "Each die must have exactly 6 faces.";
    return;
  }

  // Validate faces are numbers or WILD
  for (const face of [...die1, ...die2, ...die3]) {
    if (face !== "WILD" && Number.isNaN(Number(face))) {
      formError.value = `Invalid face "${face}". Use numbers (1-5) or WILD.`;
      return;
    }
  }

  let diceDuel: Die[] | undefined;
  let wildValueDuel: number | undefined;
  let wildAbilityDuel: string | undefined;
  let wildAbilityDescDuel: string | undefined;
  if (form.useDuelDice) {
    const dDie1 = parseDie(die1DuelInput.value);
    const dDie2 = parseDie(die2DuelInput.value);
    const dDie3 = parseDie(die3DuelInput.value);
    if (dDie1.length === 6 && dDie2.length === 6 && dDie3.length === 6) {
      diceDuel = [processDie(dDie1), processDie(dDie2), processDie(dDie3)];
    }
    wildValueDuel = form.wild_value_duel || undefined;
    wildAbilityDuel = form.wild_ability_duel || undefined;
    wildAbilityDescDuel = form.wild_ability_description_duel || undefined;
  }

  // Build starting_bonus
  let startingBonus: StartingBonus | undefined;
  if (form.hasStartingBonus) {
    const bonus: StartingBonus = {};
    if (form.bonusExtraDice > 0) {
      bonus.extra_dice = form.bonusExtraDice;
    }
    if (form.bonusRandomItem) {
      bonus.random_item = true;
    }
    const boosts: StatBoosts = {};
    for (const [stat, value] of Object.entries(form.bonusStatBoosts)) {
      if (value && value !== 0) {
        boosts[stat] = Number(value);
      }
    }
    if (Object.keys(boosts).length > 0) {
      bonus.stat_boosts = boosts;
    }
    startingBonus = Object.keys(bonus).length > 0 ? bonus : undefined;
  }

  const payload = {
    name: form.name,
    description: form.description,
    wild_value: form.wild_value,
    wild_ability: form.wild_ability,
    wild_ability_description: form.wild_ability_description,
    is_available: form.is_available,
    available_cooperative: form.available_cooperative,
    available_duel: form.available_duel,
    addon_id: form.addon_id || undefined,
    dice: [processDie(die1), processDie(die2), processDie(die3)],
    dice_duel: diceDuel,
    wild_value_duel: wildValueDuel,
    wild_ability_duel: wildAbilityDuel,
    wild_ability_description_duel: wildAbilityDescDuel,
    starting_bonus: startingBonus,
  };

  saving.value = true;
  try {
    const current = editing.value;
    if (current) {
      await axios.put(`/api/admin/characters/${current.id}`, payload);
    } else {
      await axios.post("/api/admin/characters", payload);
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
    const response = await axios.post<AiCharacterResponse>("/api/admin/ai/generate-character", {
      prompt: aiPrompt.value || undefined,
    });
    const data = response.data;
    showAiModal.value = false;
    aiPrompt.value = "";
    // Open create modal pre-filled with AI data
    editing.value = undefined;
    Object.assign(form, defaultForm(), {
      name: data.name || "",
      description: data.description || "",
      wild_value: data.wild_value || 3,
      wild_ability: data.wild_ability || "",
      wild_ability_description: data.wild_ability_description || "",
      addon_id: undefined,
    });
    die1Input.value = (data.dice?.[0] || []).join(", ");
    die2Input.value = (data.dice?.[1] || []).join(", ");
    die3Input.value = (data.dice?.[2] || []).join(", ");
    formError.value = "";
    showModal.value = true;
  } catch (error) {
    aiError.value = errorMessage(error, "AI generation failed");
  }
  aiGenerating.value = false;
}

function exportCsv(): void {
  window.location.assign("/api/admin/characters/export-csv");
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
    const response = await axios.post<ImportResult>("/api/admin/characters/import-csv", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    importResult.value = response.data;
    await fetch();
  } catch (error) {
    importResult.value = { created: 0, updated: 0, errors: [errorMessage(error, "Import failed")] };
  }
  target.value = "";
}

function setStatBoost(stat: string, event: Event): void {
  const target = event.target;
  if (!(target instanceof HTMLInputElement)) {
    return;
  }
  const parsed = Math.trunc(Number(target.value));
  const number_ = Number.isNaN(parsed) ? 0 : parsed;
  form.bonusStatBoosts = { ...form.bonusStatBoosts, [stat]: number_ };
}

async function onMediaSelected(mediaItem: MediaItem | undefined): Promise<void> {
  showMediaLibrary.value = false;
  const current = editing.value;
  if (!current || !mediaItem) {
    return;
  }
  try {
    const response = await axios.post<{ image_url: string }>(`/api/admin/characters/${current.id}/image-from-media`, {
      media_id: mediaItem.id,
    });
    current.image_url = response.data.image_url;
    imageUploaded.value = true;
    if (imageUploadedTimeout.value) {
      clearTimeout(imageUploadedTimeout.value);
    }
    imageUploadedTimeout.value = setTimeout(() => {
      imageUploaded.value = false;
    }, 2000);
    await fetch();
  } catch (error) {
    formError.value = `Failed to update image: ${errorMessage(error, "Update failed")}`;
  }
}

async function confirmDelete(character: Character): Promise<void> {
  if (!confirm(`Delete character "${character.name}"? This cannot be undone.`)) {
    return;
  }
  try {
    await axios.delete(`/api/admin/characters/${character.id}`);
    await fetch();
  } catch (error) {
    toast.error(`Delete failed: ${errorMessage(error, "Delete failed")}`);
  }
}

// Level option methods
async function fetchLevelOptions(characterId: number): Promise<void> {
  try {
    const response = await axios.get<LevelOption[]>("/api/admin/character-level-options");
    charLevelOptions.value = response.data.filter((option) => option.character_id === characterId);
  } catch {
    charLevelOptions.value = [];
  }
}

function openCreateLo(): void {
  editingLo.value = undefined;
  Object.assign(loForm, defaultLevelOptionForm());
  loConfigStat.value = "wealth";
  loConfigValue.value = 1;
  loConfigItemId.value = undefined;
  loConfigCurseId.value = undefined;
  loFormError.value = "";
  showLoForm.value = true;
}

function openEditLo(option: LevelOption): void {
  editingLo.value = option.id;
  Object.assign(loForm, {
    name: option.name,
    type: option.type,
    available_at_level: option.available_at_level,
    is_active: option.is_active,
    max_selections: option.max_selections,
    sort_order: option.sort_order,
    description: option.description || "",
    icon: option.icon || "",
  });
  if (option.type === "passive_stat_bonus" && option.config) {
    loConfigStat.value = option.config.stat || "wealth";
    loConfigValue.value = option.config.value || 1;
  }
  if (option.type === "start_with_item" && option.config) {
    loConfigItemId.value = option.config.item_id || undefined;
  }
  if (option.type === "start_with_curse" && option.config) {
    loConfigCurseId.value = option.config.curse_id || undefined;
  }
  loFormError.value = "";
  showLoForm.value = true;
}

function buildLoConfig(): LevelOptionConfig | undefined {
  if (loForm.type === "passive_stat_bonus") {
    return { stat: loConfigStat.value, value: loConfigValue.value };
  }
  if (loForm.type === "start_with_item") {
    return loConfigItemId.value ? { item_id: loConfigItemId.value } : { random: true };
  }
  if (loForm.type === "start_with_curse") {
    return loConfigCurseId.value ? { curse_id: loConfigCurseId.value } : { random: true };
  }
  return undefined;
}

async function saveLo(): Promise<void> {
  loFormError.value = "";
  const current = editing.value;
  if (!current) {
    return;
  }
  const data = { ...loForm, config: buildLoConfig(), character_id: current.id };
  try {
    const currentLo = editingLo.value;
    if (currentLo === undefined) {
      await axios.post("/api/admin/character-level-options", data);
    } else {
      await axios.put(`/api/admin/character-level-options/${currentLo}`, data);
    }
    showLoForm.value = false;
    await fetchLevelOptions(current.id);
  } catch (error) {
    loFormError.value = errorMessage(error, "Error saving");
  }
}

async function deleteLo(option: LevelOption): Promise<void> {
  const current = editing.value;
  if (!current) {
    return;
  }
  if (!confirm(`Delete "${option.name}"?`)) {
    return;
  }
  try {
    await axios.delete(`/api/admin/character-level-options/${option.id}`);
    await fetchLevelOptions(current.id);
  } catch (error) {
    loFormError.value = errorMessage(error, "Error deleting");
  }
}

onMounted(async () => {
  await Promise.all([fetch(), fetchRules(), fetchAddons()]);
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

/* Rules panel */
.rules-panel {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 8px;
  padding: 18px 22px;
  margin-bottom: 24px;
}

.rules-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.1rem;
  margin-bottom: 4px;
}

.rules-desc {
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-style: italic;
  margin-bottom: 14px;
}

.rules-grid {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.rule-cell {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 6px;
  padding: 8px 12px;
}

.rule-label {
  color: var(--text-secondary);
  font-size: 0.85rem;
  white-space: nowrap;
}

.rule-input {
  width: 48px;
  background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--accent-gold);
  padding: 4px 6px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
  text-align: center;
}

.rule-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.rule-dice-text {
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.rules-saved {
  color: var(--accent-green, #4a8a3a);
  font-size: 0.85rem;
  margin-top: 8px;
}

.loading {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}

.table-wrap {
  overflow-x: auto;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.admin-table th,
.admin-table td {
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid rgba(184, 148, 46, 0.2);
}

.admin-table th {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.admin-table tbody tr:hover {
  background: rgba(212, 168, 67, 0.05);
}

.name-col {
  color: var(--text-bright);
  font-weight: 600;
}

.unavailable-tag {
  font-size: 0.65rem;
  padding: 1px 6px;
  border-radius: 3px;
  background: rgba(160, 48, 32, 0.15);
  border: 1px solid rgba(160, 48, 32, 0.3);
  color: #d05040;
  font-weight: 600;
  margin-left: 6px;
}

.unlock-tags {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  margin-top: 3px;
}

.unlock-tag {
  font-size: 0.65rem;
  padding: 1px 6px;
  border-radius: 3px;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(138, 106, 46, 0.2);
  color: var(--accent-gold);
  font-weight: 400;
  white-space: nowrap;
}

.desc-col {
  color: var(--text-secondary);
  max-width: 200px;
}

.dice-col {
  font-size: 0.8rem;
  color: var(--text-secondary);
  white-space: nowrap;
}

.image-col {
  width: 44px;
}

.char-thumb {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-gold);
}

.actions-col {
  white-space: nowrap;
  display: flex;
  gap: 6px;
}

.actions-col button {
  padding: 5px 12px;
  font-size: 0.8rem;
}

/* Modal styles */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.modal-content {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  padding: 28px;
  width: 90%;
  max-width: 550px;
  max-height: 85vh;
  overflow-y: auto;
}

.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 18px;
  font-size: 1.3rem;
}

.form-group {
  margin-bottom: 14px;
}

.form-group label {
  display: block;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 5px;
}

.form-group input[type="text"],
.form-group input:not([type]),
.form-group input[type="number"],
.form-group textarea,
.form-group select {
  width: 100%;
  background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright);
  padding: 8px 12px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
}

.form-group textarea {
  resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.form-error {
  color: var(--accent-red);
  font-size: 0.9rem;
  margin-bottom: 10px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 18px;
}

.image-upload-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.image-preview {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-gold);
}

.upload-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  background: linear-gradient(180deg, var(--wood-light), var(--wood-dark));
  color: var(--accent-gold);
  border: 2px solid var(--border-gold);
  padding: 6px 14px;
  font-size: 0.85rem;
  font-family: 'Cinzel', serif;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  letter-spacing: 1px;
}

.upload-btn:hover {
  background: linear-gradient(180deg, #4a3a24, var(--wood-light));
  box-shadow: 0 0 15px rgba(212, 168, 67, 0.25);
}

.hidden-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
  width: 100%;
  height: 100%;
}

.upload-status {
  font-size: 0.8rem;
  color: var(--accent-gold);
  font-style: italic;
}

.upload-success {
  font-size: 0.8rem;
  color: var(--accent-green, #4a8a3a);
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
  margin-bottom: 24px;
}

.balance-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
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

.balance-wild-dist {
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.balance-dist-label {
  color: var(--text-secondary);
  font-size: 0.85rem;
}

.balance-dist-badge {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 0.8rem;
  color: var(--accent-gold);
}

.balance-table {
  font-size: 0.85rem;
}

.balance-total {
  color: var(--accent-gold);
  font-weight: 700;
}

/* Level Options section */
.level-options-section {
  border: 1px solid rgba(138, 106, 46, 0.35);
  background: rgba(0, 0, 0, 0.15);
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 14px;
}

.lo-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

.lo-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
}

.lo-body {
  margin-top: 10px;
}

.lo-empty {
  color: var(--text-secondary);
  font-size: 0.82rem;
  font-style: italic;
  margin-bottom: 8px;
}

.lo-table {
  font-size: 0.78rem;
  margin-bottom: 8px;
}

.lo-table th,
.lo-table td {
  padding: 5px 8px;
}

.lo-actions {
  white-space: nowrap;
  display: flex;
  gap: 4px;
}

.type-badge {
  background: rgba(212, 168, 67, 0.1);
  border: 1px solid rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 0.68rem;
}

.status-active { color: #5ab87a; }
.status-inactive { color: #e07070; }

.btn-lo-edit,
.btn-lo-delete {
  padding: 2px 8px;
  font-size: 0.7rem;
  border-radius: 3px;
  cursor: pointer;
}

.btn-lo-edit {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
}

.btn-lo-delete {
  background: rgba(224, 112, 112, 0.15);
  border: 1px solid rgba(224, 112, 112, 0.3);
  color: #e07070;
}

.btn-lo-add {
  display: block;
  width: 100%;
  padding: 6px;
  background: rgba(90, 184, 122, 0.1);
  border: 1px dashed rgba(90, 184, 122, 0.4);
  color: #5ab87a;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.8rem;
  margin-top: 6px;
  transition: background 0.2s;
}

.btn-lo-add:hover {
  background: rgba(90, 184, 122, 0.2);
}

.lo-form {
  margin-top: 10px;
  padding: 12px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
}

.lo-form-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.85rem;
  margin: 0 0 10px;
}

.lo-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.lo-full-width {
  grid-column: span 2;
}

.lo-form-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}

.btn-lo-save {
  padding: 6px 16px;
  background: linear-gradient(180deg, var(--wood-light, #3a2a14), var(--wood-medium, #2a1a0a));
  border: 1px solid var(--accent-gold);
  color: var(--accent-gold);
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-size: 0.78rem;
}

.btn-lo-cancel {
  padding: 6px 16px;
  background: transparent;
  border: 1px solid var(--border-gold);
  color: var(--text-secondary);
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.78rem;
}
</style>
