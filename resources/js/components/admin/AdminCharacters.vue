<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Characters</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input type="file" ref="csvInput" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Character</button>
        <button class="btn-ai" @click="showAiModal = true">Generate with AI</button>
      </div>
    </div>

    <div v-if="importResult" class="import-result" :class="importResult.errors.length ? 'import-warn' : 'import-ok'">
      CSV Import: {{ importResult.created }} created, {{ importResult.updated }} updated.
      <span v-if="importResult.errors.length"> {{ importResult.errors.length }} error(s).</span>
      <div v-for="(err, i) in importResult.errors" :key="i" class="import-error-line">{{ err }}</div>
      <button class="import-dismiss" @click="importResult = null">Dismiss</button>
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
            @change="updateDiceRule(n, $event.target.value)"
            class="rule-input"
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
            <SortableHeader label="ID" field="id" :currentSort="sortField" :currentDir="sortDir" @sort="toggleSort" />
            <th>Image</th>
            <SortableHeader label="Name" field="name" :currentSort="sortField" :currentDir="sortDir" @sort="toggleSort" />
            <th>Description</th>
            <SortableHeader label="Wild" field="wild_value" :currentSort="sortField" :currentDir="sortDir" @sort="toggleSort" />
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
                <div v-if="c.unlock_info && c.unlock_info.length" class="unlock-tags">
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
              <input type="checkbox" v-model="form.useDuelDice" />
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
              <input type="checkbox" v-model="form.hasStartingBonus" />
              <span style="color: #5ab87a; font-weight: 700;">Starting Bonus</span>
            </label>
            <template v-if="form.hasStartingBonus">
              <div class="form-group">
                <label>Extra Dice (0 = none)</label>
                <input v-model.number="form.bonusExtraDice" type="number" min="0" max="3" />
              </div>
              <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px;">
                  <input type="checkbox" v-model="form.bonusRandomItem" />
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
                      @input="setStatBoost(stat, $event.target.value)"
                      min="-10"
                      max="10"
                      style="width: 56px;"
                    />
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div class="form-group">
            <label>Addon</label>
            <select v-model="form.addon_id">
              <option :value="null">Base Game</option>
              <option v-for="a in addons" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <div class="form-group">
            <label style="color: var(--accent-gold); font-weight: 600;">Availability</label>
            <div style="display: flex; gap: 16px; margin-top: 4px;">
              <label><input type="checkbox" v-model="form.available_cooperative" /> Co-op</label>
              <label><input type="checkbox" v-model="form.available_duel" /> Duel</label>
            </div>
          </div>

          <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px;">
              <input type="checkbox" v-model="form.is_available" />
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
              <div v-if="!charLevelOptions.length" class="lo-empty">No level options for this character.</div>
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
                    <label><input type="checkbox" v-model="loForm.is_active" /> Active</label>
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
      :selectMode="true"
      @close="showMediaLibrary = false"
      @select="onMediaSelected"
    />
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';
import AdminSearchInput from './AdminSearchInput.vue';
import SortableHeader from './SortableHeader.vue';
import MediaLibraryModal from './MediaLibraryModal.vue';

export default {
  name: 'AdminCharacters',
  components: { AdminSearchInput, SortableHeader, MediaLibraryModal },
  setup() { return { toast: useToast() }; },
  data() {
    return {
      characters: [],
      addons: [],
      loading: true,
      searchQuery: '',
      sortField: 'id',
      sortDir: 'asc',
      showModal: false,
      editing: null,
      saving: false,
      formError: '',
      form: { name: '', description: '', wild_value: 3, wild_ability: '', wild_ability_description: '', addon_id: null, available_cooperative: true, available_duel: true, is_available: true, useDuelDice: false, wild_value_duel: 3, wild_ability_duel: '', wild_ability_description_duel: '', hasStartingBonus: false, bonusExtraDice: 0, bonusRandomItem: false, bonusStatBoosts: {} },
      showMediaLibrary: false,
      die1Input: '',
      die2Input: '',
      die3Input: '',
      die1DuelInput: '',
      die2DuelInput: '',
      die3DuelInput: '',
      diceRules: { 1: 3, 2: 3, 3: 3, 4: 3, 5: 3, 6: 3 },
      rulesSaved: false,
      imageUploaded: false,
      showAiModal: false,
      aiPrompt: '',
      aiGenerating: false,
      aiError: '',
      importResult: null,
      showBalanceStats: false,
      // Level options
      showLevelOptions: false,
      charLevelOptions: [],
      showLoForm: false,
      editingLo: null,
      loFormError: '',
      loForm: { name: '', type: 'bump_dice_face', available_at_level: 1, is_active: true, max_selections: 0, sort_order: 0, description: '', icon: '' },
      loConfigStat: 'wealth',
      loConfigValue: 1,
      loConfigItemId: null,
      loConfigCurseId: null,
    };
  },
  computed: {
    filteredCharacters() {
      let list = this.characters;
      const q = this.searchQuery.toLowerCase().trim();
      if (q) {
        list = list.filter(c =>
          c.name.toLowerCase().includes(q) ||
          (c.description || '').toLowerCase().includes(q) ||
          (c.wild_ability || '').toLowerCase().includes(q)
        );
      }
      const field = this.sortField;
      const dir = this.sortDir === 'asc' ? 1 : -1;
      return [...list].sort((a, b) => {
        const av = a[field] ?? '';
        const bv = b[field] ?? '';
        if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
        return String(av).localeCompare(String(bv)) * dir;
      });
    },
    loStatOptions() {
      return ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
    },
    characterBalanceStats() {
      if (!this.characters.length) return null;
      const charStats = this.characters.map(c => {
        const dieAvgs = (c.dice || []).map(die => {
          if (!die || !die.length) return 0;
          const values = die.map(f => f === 'WILD' ? c.wild_value : Number(f));
          return values.reduce((s, v) => s + v, 0) / values.length;
        });
        const totalAvg = dieAvgs.reduce((s, v) => s + v, 0);
        return { name: c.name, dieAvgs, totalAvg, wildValue: c.wild_value };
      });
      const sorted = [...charStats].sort((a, b) => b.totalAvg - a.totalAvg);
      const overallAvg = charStats.reduce((s, c) => s + c.totalAvg, 0) / charStats.length;
      const highest = sorted[0];
      const lowest = sorted[sorted.length - 1];
      const wildDist = {};
      this.characters.forEach(c => {
        const wv = c.wild_value;
        wildDist[wv] = (wildDist[wv] || 0) + 1;
      });
      const avgWild = this.characters.reduce((s, c) => s + c.wild_value, 0) / this.characters.length;
      return { sorted, overallAvg, highest, lowest, wildDist, avgWild };
    },
  },
  async mounted() {
    await Promise.all([this.fetch(), this.fetchRules(), this.fetchAddons()]);
  },
  methods: {
    toggleSort(field) {
      if (this.sortField === field) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortField = field;
        this.sortDir = 'asc';
      }
    },
    async fetch() {
      this.loading = true;
      const res = await axios.get('/api/admin/characters');
      this.characters = res.data;
      this.loading = false;
    },
    async fetchAddons() {
      try {
        const res = await axios.get('/api/admin/addons');
        this.addons = res.data;
      } catch { /* ignore */ }
    },
    async fetchRules() {
      try {
        const res = await axios.get('/api/admin/rules');
        if (res.data.dice_per_player_count) {
          this.diceRules = { ...this.diceRules, ...res.data.dice_per_player_count };
        }
      } catch {
        // use defaults
      }
    },
    async updateDiceRule(count, value) {
      const num = Math.max(1, Math.min(3, parseInt(value) || 3));
      this.diceRules[count] = num;
      try {
        await axios.put('/api/admin/rules/dice_per_player_count', {
          value: { ...this.diceRules },
        });
        this.rulesSaved = true;
        setTimeout(() => { this.rulesSaved = false; }, 1500);
      } catch {
        // silently fail
      }
    },
    truncate(str, len) {
      return str.length > len ? str.substring(0, len) + '...' : str;
    },
    parseDie(input) {
      return input.split(',').map(f => f.trim()).filter(Boolean);
    },
    openCreate() {
      this.editing = null;
      this.form = { name: '', description: '', wild_value: 3, wild_ability: '', wild_ability_description: '', addon_id: null, available_cooperative: true, available_duel: true, is_available: true, useDuelDice: false, wild_value_duel: 3, wild_ability_duel: '', wild_ability_description_duel: '', hasStartingBonus: false, bonusExtraDice: 0, bonusRandomItem: false, bonusStatBoosts: {} };
      this.die1Input = '';
      this.die2Input = '';
      this.die3Input = '';
      this.die1DuelInput = '';
      this.die2DuelInput = '';
      this.die3DuelInput = '';
      this.formError = '';
      this.imageUploaded = false;
      this.showLevelOptions = false;
      this.charLevelOptions = [];
      this.showLoForm = false;
      this.showModal = true;
    },
    openEdit(c) {
      this.editing = c;
      this.imageUploaded = false;
      const hasDuelDice = c.dice_duel != null || c.wild_value_duel != null;
      const bonus = c.starting_bonus || {};
      const hasBonus = !!(bonus.extra_dice || bonus.random_item || (bonus.stat_boosts && Object.keys(bonus.stat_boosts).length));
      this.form = {
        name: c.name,
        description: c.description,
        wild_value: c.wild_value,
        wild_ability: c.wild_ability,
        wild_ability_description: c.wild_ability_description || '',
        addon_id: c.addon_id || null,
        available_cooperative: c.available_cooperative ?? true,
        available_duel: c.available_duel ?? true,
        is_available: c.is_available ?? true,
        useDuelDice: hasDuelDice,
        wild_value_duel: c.wild_value_duel ?? c.wild_value,
        wild_ability_duel: c.wild_ability_duel ?? c.wild_ability ?? '',
        wild_ability_description_duel: c.wild_ability_description_duel ?? c.wild_ability_description ?? '',
        hasStartingBonus: hasBonus,
        bonusExtraDice: bonus.extra_dice || 0,
        bonusRandomItem: !!bonus.random_item,
        bonusStatBoosts: { ...(bonus.stat_boosts || {}) },
      };
      this.die1Input = c.dice[0]?.join(', ') || '';
      this.die2Input = c.dice[1]?.join(', ') || '';
      this.die3Input = c.dice[2]?.join(', ') || '';
      this.die1DuelInput = hasDuelDice ? (c.dice_duel?.[0]?.join(', ') || '') : '';
      this.die2DuelInput = hasDuelDice ? (c.dice_duel?.[1]?.join(', ') || '') : '';
      this.die3DuelInput = hasDuelDice ? (c.dice_duel?.[2]?.join(', ') || '') : '';
      this.formError = '';
      this.showLevelOptions = false;
      this.showLoForm = false;
      this.showModal = true;
      this.fetchLevelOptions(c.id);
    },
    async save() {
      this.formError = '';
      const die1 = this.parseDie(this.die1Input);
      const die2 = this.parseDie(this.die2Input);
      const die3 = this.parseDie(this.die3Input);

      if (die1.length !== 6 || die2.length !== 6 || die3.length !== 6) {
        this.formError = 'Each die must have exactly 6 faces.';
        return;
      }

      // Validate faces are numbers or WILD
      for (const f of [...die1, ...die2, ...die3]) {
        if (f !== 'WILD' && isNaN(Number(f))) {
          this.formError = `Invalid face "${f}". Use numbers (1-5) or WILD.`;
          return;
        }
      }

      // Convert numeric strings to numbers, keep WILD as string
      const processDie = (die) => die.map(f => f === 'WILD' ? 'WILD' : Number(f));

      let diceDuel = null;
      let wildValueDuel = null;
      let wildAbilityDuel = null;
      let wildAbilityDescDuel = null;
      if (this.form.useDuelDice) {
        const dDie1 = this.parseDie(this.die1DuelInput);
        const dDie2 = this.parseDie(this.die2DuelInput);
        const dDie3 = this.parseDie(this.die3DuelInput);
        if (dDie1.length === 6 && dDie2.length === 6 && dDie3.length === 6) {
          diceDuel = [processDie(dDie1), processDie(dDie2), processDie(dDie3)];
        }
        wildValueDuel = this.form.wild_value_duel || null;
        wildAbilityDuel = this.form.wild_ability_duel || null;
        wildAbilityDescDuel = this.form.wild_ability_description_duel || null;
      }

      // Build starting_bonus
      let startingBonus = null;
      if (this.form.hasStartingBonus) {
        startingBonus = {};
        if (this.form.bonusExtraDice > 0) startingBonus.extra_dice = this.form.bonusExtraDice;
        if (this.form.bonusRandomItem) startingBonus.random_item = true;
        const boosts = {};
        for (const [stat, val] of Object.entries(this.form.bonusStatBoosts)) {
          if (val && val !== 0) boosts[stat] = Number(val);
        }
        if (Object.keys(boosts).length) startingBonus.stat_boosts = boosts;
        if (!Object.keys(startingBonus).length) startingBonus = null;
      }

      const { useDuelDice, wild_value_duel, wild_ability_duel, wild_ability_description_duel, hasStartingBonus, bonusExtraDice, bonusRandomItem, bonusStatBoosts, ...formFields } = this.form;
      const payload = {
        ...formFields,
        addon_id: this.form.addon_id || null,
        dice: [processDie(die1), processDie(die2), processDie(die3)],
        dice_duel: diceDuel,
        wild_value_duel: wildValueDuel,
        wild_ability_duel: wildAbilityDuel,
        wild_ability_description_duel: wildAbilityDescDuel,
        starting_bonus: startingBonus,
      };

      this.saving = true;
      try {
        if (this.editing) {
          await axios.put(`/api/admin/characters/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/characters', payload);
        }
        this.showModal = false;
        await this.fetch();
      } catch (e) {
        this.formError = e.response?.data?.message || 'Save failed';
      }
      this.saving = false;
    },
    async generateWithAi() {
      this.aiError = '';
      this.aiGenerating = true;
      try {
        const res = await axios.post('/api/admin/ai/generate-character', {
          prompt: this.aiPrompt || undefined,
        });
        const data = res.data;
        this.showAiModal = false;
        this.aiPrompt = '';
        // Open create modal pre-filled with AI data
        this.editing = null;
        this.form = {
          name: data.name || '',
          description: data.description || '',
          wild_value: data.wild_value || 3,
          wild_ability: data.wild_ability || '',
          wild_ability_description: data.wild_ability_description || '',
          addon_id: null,
        };
        this.die1Input = (data.dice?.[0] || []).join(', ');
        this.die2Input = (data.dice?.[1] || []).join(', ');
        this.die3Input = (data.dice?.[2] || []).join(', ');
        this.formError = '';
        this.showModal = true;
      } catch (e) {
        this.aiError = e.response?.data?.message || e.response?.data?.error || 'AI generation failed';
      }
      this.aiGenerating = false;
    },
    exportCsv() {
      window.location.href = '/api/admin/characters/export-csv';
    },
    triggerImport() {
      this.$refs.csvInput.click();
    },
    async handleImportFile(event) {
      const file = event.target.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append('file', file);
      try {
        const res = await axios.post('/api/admin/characters/import-csv', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.importResult = res.data;
        await this.fetch();
      } catch (e) {
        this.importResult = { created: 0, updated: 0, errors: [e.response?.data?.message || 'Import failed'] };
      }
      event.target.value = '';
    },
    setStatBoost(stat, value) {
      const num = parseInt(value) || 0;
      this.form.bonusStatBoosts = { ...this.form.bonusStatBoosts, [stat]: num };
    },
    async onMediaSelected(mediaItem) {
      this.showMediaLibrary = false;
      if (!this.editing || !mediaItem) return;
      try {
        const res = await axios.post(`/api/admin/characters/${this.editing.id}/image-from-media`, {
          media_id: mediaItem.id,
        });
        this.editing.image_url = res.data.image_url;
        this.imageUploaded = true;
        setTimeout(() => { this.imageUploaded = false; }, 2000);
        await this.fetch();
      } catch (e) {
        this.formError = 'Failed to update image: ' + (e.response?.data?.message || e.message);
      }
    },
    async confirmDelete(c) {
      if (!confirm(`Delete character "${c.name}"? This cannot be undone.`)) return;
      try {
        await axios.delete(`/api/admin/characters/${c.id}`);
        await this.fetch();
      } catch (e) {
        this.toast.error('Delete failed: ' + (e.response?.data?.message || e.message));
      }
    },
    // Level option methods
    async fetchLevelOptions(characterId) {
      try {
        const res = await axios.get('/api/admin/character-level-options');
        this.charLevelOptions = res.data.filter(o => o.character_id === characterId);
      } catch { this.charLevelOptions = []; }
    },
    openCreateLo() {
      this.editingLo = null;
      this.loForm = { name: '', type: 'bump_dice_face', available_at_level: 1, is_active: true, max_selections: 0, sort_order: 0, description: '', icon: '' };
      this.loConfigStat = 'wealth';
      this.loConfigValue = 1;
      this.loConfigItemId = null;
      this.loConfigCurseId = null;
      this.loFormError = '';
      this.showLoForm = true;
    },
    openEditLo(opt) {
      this.editingLo = opt.id;
      this.loForm = {
        name: opt.name,
        type: opt.type,
        available_at_level: opt.available_at_level,
        is_active: opt.is_active,
        max_selections: opt.max_selections,
        sort_order: opt.sort_order,
        description: opt.description || '',
        icon: opt.icon || '',
      };
      if (opt.type === 'passive_stat_bonus' && opt.config) {
        this.loConfigStat = opt.config.stat || 'wealth';
        this.loConfigValue = opt.config.value || 1;
      }
      if (opt.type === 'start_with_item' && opt.config) {
        this.loConfigItemId = opt.config.item_id || null;
      }
      if (opt.type === 'start_with_curse' && opt.config) {
        this.loConfigCurseId = opt.config.curse_id || null;
      }
      this.loFormError = '';
      this.showLoForm = true;
    },
    buildLoConfig() {
      if (this.loForm.type === 'passive_stat_bonus') {
        return { stat: this.loConfigStat, value: this.loConfigValue };
      }
      if (this.loForm.type === 'start_with_item') {
        return this.loConfigItemId ? { item_id: this.loConfigItemId } : { random: true };
      }
      if (this.loForm.type === 'start_with_curse') {
        return this.loConfigCurseId ? { curse_id: this.loConfigCurseId } : { random: true };
      }
      return null;
    },
    async saveLo() {
      this.loFormError = '';
      const data = { ...this.loForm, config: this.buildLoConfig(), character_id: this.editing.id };
      try {
        if (this.editingLo) {
          await axios.put(`/api/admin/character-level-options/${this.editingLo}`, data);
        } else {
          await axios.post('/api/admin/character-level-options', data);
        }
        this.showLoForm = false;
        await this.fetchLevelOptions(this.editing.id);
      } catch (e) {
        this.loFormError = e.response?.data?.message || 'Error saving';
      }
    },
    async deleteLo(opt) {
      if (!confirm(`Delete "${opt.name}"?`)) return;
      try {
        await axios.delete(`/api/admin/character-level-options/${opt.id}`);
        await this.fetchLevelOptions(this.editing.id);
      } catch (e) {
        this.loFormError = e.response?.data?.message || 'Error deleting';
      }
    },
  },
};
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
