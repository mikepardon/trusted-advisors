<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Decision Cards</h2>
      <div class="header-buttons">
        <button class="btn-csv" @click="exportCsv">Export CSV</button>
        <button class="btn-csv" @click="triggerImport">Import CSV</button>
        <input type="file" ref="csvInput" accept=".csv" style="display:none" @change="handleImportFile" />
        <button class="btn-primary" @click="openCreate">+ New Card</button>
        <button class="btn-ai" @click="showAiModal = true">Generate with AI</button>
      </div>
    </div>

    <div v-if="importResult" class="import-result" :class="importResult.errors.length ? 'import-warn' : 'import-ok'">
      CSV Import: {{ importResult.created }} created, {{ importResult.updated }} updated.
      <span v-if="importResult.errors.length"> {{ importResult.errors.length }} error(s).</span>
      <div v-for="(err, i) in importResult.errors" :key="i" class="import-error-line">{{ err }}</div>
      <button class="import-dismiss" @click="importResult = null">Dismiss</button>
    </div>

    <!-- Balance Stats Panel -->
    <div class="balance-panel">
      <div class="balance-header" @click="showBalanceStats = !showBalanceStats">
        <h3 class="balance-title">Balance Stats</h3>
        <button type="button" class="balance-toggle">{{ showBalanceStats ? 'Hide' : 'Show' }}</button>
      </div>
      <div v-if="showBalanceStats && cardBalanceStats" class="balance-body">
        <div class="balance-summary">
          <div class="balance-stat-card">
            <span class="balance-stat-label">Total Cards</span>
            <span class="balance-stat-value">{{ cardBalanceStats.count }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg Difficulty</span>
            <span class="balance-stat-value">{{ cardBalanceStats.avgDiff.toFixed(1) }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-easy">Easy (&le;5)</span>
            <span class="balance-stat-value">{{ cardBalanceStats.diffDist.easy }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-medium">Medium (6-8)</span>
            <span class="balance-stat-value">{{ cardBalanceStats.diffDist.medium }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label balance-hard">Hard (9+)</span>
            <span class="balance-stat-value">{{ cardBalanceStats.diffDist.hard }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg +Stats/Card</span>
            <span class="balance-stat-value balance-pos">{{ cardBalanceStats.avgPosPerCard.toFixed(2) }}</span>
          </div>
          <div class="balance-stat-card">
            <span class="balance-stat-label">Avg -Stats/Card</span>
            <span class="balance-stat-value balance-neg">{{ cardBalanceStats.avgNegPerCard.toFixed(2) }}</span>
          </div>
        </div>
        <div class="balance-section-row">
          <div class="balance-section">
            <h4 class="balance-section-title">Category Distribution</h4>
            <div class="balance-dist-row">
              <span v-for="(count, cat) in cardBalanceStats.catDist" :key="cat" class="balance-dist-badge">
                {{ cat }}: {{ count }}
              </span>
            </div>
          </div>
          <div class="balance-section">
            <h4 class="balance-section-title">Special Effects</h4>
            <div class="balance-dist-row">
              <span v-for="(count, key) in cardBalanceStats.specialCounts" :key="key" class="balance-dist-badge" :class="count > 0 ? '' : 'balance-dist-zero'">
                {{ key.replace(/_/g, ' ') }}: {{ count }}
              </span>
            </div>
          </div>
        </div>
        <table class="admin-table balance-table">
          <thead>
            <tr>
              <th>Stat</th>
              <th>Total +</th>
              <th>Total -</th>
              <th>Net</th>
              <th>Avg +/Card</th>
              <th>Avg -/Card</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(data, stat) in cardBalanceStats.perStat" :key="stat">
              <td class="name-col" style="text-transform: capitalize;">{{ stat }}</td>
              <td class="balance-pos">{{ data.totalPos }}</td>
              <td class="balance-neg">{{ data.totalNeg }}</td>
              <td :class="data.net > 0 ? 'balance-pos' : data.net < 0 ? 'balance-neg' : ''">{{ data.net > 0 ? '+' : '' }}{{ data.net }}</td>
              <td class="balance-pos">{{ data.avgPos.toFixed(2) }}</td>
              <td class="balance-neg">{{ data.avgNeg.toFixed(2) }}</td>
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
            <SortableHeader label="#" field="sort_order" :currentSort="sortField" :currentDir="sortDir" @sort="toggleSort" />
            <SortableHeader label="Title" field="title" :currentSort="sortField" :currentDir="sortDir" @sort="toggleSort" />
            <SortableHeader label="Difficulty" field="difficulty" :currentSort="sortField" :currentDir="sortDir" @sort="toggleSort" />
            <SortableHeader label="Category" field="category" :currentSort="sortField" :currentDir="sortDir" @sort="toggleSort" />
            <th>Description</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="card in filteredCards" :key="card.id">
            <td>{{ card.sort_order }}</td>
            <td class="name-col">{{ card.title }}</td>
            <td>
              <span class="diff-badge" :class="diffClass(card.difficulty)">{{ card.difficulty }}</span>
            </td>
            <td>{{ card.category || '-' }}</td>
            <td class="desc-col">{{ truncate(card.description, 60) }}</td>
            <td class="actions-col">
              <button @click="openEdit(card)">Edit</button>
              <button class="btn-danger" @click="confirmDelete(card)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Card Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content modal-wide">
        <h3>{{ editing ? 'Edit Card' : 'New Card' }}</h3>
        <form @submit.prevent="save">
          <div class="form-row">
            <div class="form-group">
              <label>Title</label>
              <input v-model="form.title" required />
            </div>
            <div class="form-group form-small">
              <label>Sort Order</label>
              <input v-model.number="form.sort_order" type="number" required />
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="3" required></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Difficulty (integer, 3-12)</label>
              <input v-model.number="form.difficulty" type="number" min="1" max="20" required />
            </div>
            <div class="form-group">
              <label>Category</label>
              <select v-model="form.category">
                <option value="">None</option>
                <option value="military">Military</option>
                <option value="political">Political</option>
                <option value="economic">Economic</option>
                <option value="religious">Religious</option>
                <option value="social">Social</option>
                <option value="crisis">Crisis</option>
              </select>
            </div>
          </div>

          <!-- Positive Effects -->
          <div class="effects-section effects-positive">
            <h4 class="effects-title">Positive Outcome (Success)</h4>
            <div class="form-group">
              <label>Outcome Text</label>
              <textarea v-model="form.positive_flavor" rows="2" placeholder="What happens when the advisors succeed..."></textarea>
            </div>
            <div class="stat-grid">
              <div v-for="stat in stats" :key="'pos-' + stat.key" class="stat-cell">
                <span class="stat-icon" :title="stat.label">{{ stat.icon }}</span>
                <input
                  type="number"
                  :value="form.positive[stat.key] || ''"
                  @input="setEffect('positive', stat.key, $event.target.value)"
                  class="stat-input"
                  :placeholder="0"
                />
              </div>
            </div>
            <div class="variant-rules">
              <label class="variant-check">
                <input type="checkbox" v-model="form.positiveRecoverDie" />
                <span class="variant-label">Recover a lost die</span>
              </label>
              <label class="variant-check">
                <input type="checkbox" v-model="form.positiveDrawItem" />
                <span class="variant-label">Draw an item</span>
              </label>
              <label class="variant-check">
                <input type="checkbox" v-model="form.positiveRemoveCurse" />
                <span class="variant-label">Remove a curse</span>
              </label>
            </div>
            <div class="form-group" style="margin-top: 8px;">
              <label>Bonus Score (on success)</label>
              <input type="number" v-model.number="form.positiveBonusScore" placeholder="0" style="width: 80px;" />
            </div>
          </div>

          <!-- Negative Effects -->
          <div class="effects-section effects-negative">
            <h4 class="effects-title">Negative Outcome (Failure)</h4>
            <div class="form-group">
              <label>Outcome Text</label>
              <textarea v-model="form.negative_flavor" rows="2" placeholder="What happens when the advisors fail..."></textarea>
            </div>
            <div class="stat-grid">
              <div v-for="stat in stats" :key="'neg-' + stat.key" class="stat-cell">
                <span class="stat-icon" :title="stat.label">{{ stat.icon }}</span>
                <input
                  type="number"
                  :value="form.negative[stat.key] || ''"
                  @input="setEffect('negative', stat.key, $event.target.value)"
                  class="stat-input"
                  :placeholder="0"
                />
              </div>
            </div>
            <div class="variant-rules">
              <label class="variant-check">
                <input type="checkbox" v-model="form.negativeLoseDie" />
                <span class="variant-label">Lose a die</span>
              </label>
              <label class="variant-check">
                <input type="checkbox" v-model="form.negativeDiscardItem" />
                <span class="variant-label">Discard an item</span>
              </label>
              <label class="variant-check">
                <input type="checkbox" v-model="form.negativeDrawItem" />
                <span class="variant-label">Draw cursed item</span>
              </label>
            </div>
            <div class="form-group" style="margin-top: 8px;">
              <label>Bonus Score Penalty (on failure)</label>
              <input type="number" v-model.number="form.negativeBonusScore" placeholder="0" style="width: 80px;" />
            </div>
          </div>

          <!-- Availability -->
          <div class="availability-section">
            <h4 class="effects-title" style="color: var(--accent-gold);">Availability</h4>
            <div class="variant-rules">
              <label class="variant-check">
                <input type="checkbox" v-model="form.available_cooperative" />
                <span class="variant-label">Available in Co-op</span>
              </label>
              <label class="variant-check">
                <input type="checkbox" v-model="form.available_duel" />
                <span class="variant-label">Available in Duel</span>
              </label>
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
        <h3>Generate Card with AI</h3>
        <div class="form-group">
          <label>Category (optional)</label>
          <select v-model="aiCategory">
            <option value="">Any</option>
            <option value="military">Military</option>
            <option value="political">Political</option>
            <option value="economic">Economic</option>
            <option value="religious">Religious</option>
            <option value="social">Social</option>
          </select>
        </div>
        <div class="form-group">
          <label>Prompt (optional — describe the card you want)</label>
          <textarea v-model="aiPrompt" rows="3" placeholder="e.g. A situation involving a suspiciously generous merchant"></textarea>
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
  </div>
</template>

<script>
import axios from 'axios';
import { useToast } from '../../stores/toast';
import AdminSearchInput from './AdminSearchInput.vue';
import SortableHeader from './SortableHeader.vue';

export default {
  name: 'AdminCards',
  components: { AdminSearchInput, SortableHeader },
  setup() { return { toast: useToast() }; },
  data() {
    return {
      cards: [],
      loading: true,
      searchQuery: '',
      sortField: 'sort_order',
      sortDir: 'asc',
      showModal: false,
      editing: null,
      saving: false,
      formError: '',
      showAiModal: false,
      aiPrompt: '',
      aiCategory: '',
      aiGenerating: false,
      aiError: '',
      importResult: null,
      showBalanceStats: false,
      stats: [
        { key: 'wealth', label: 'Wealth', icon: '\u{1FA99}' },
        { key: 'influence', label: 'Influence', icon: '\u{1F3DB}' },
        { key: 'security', label: 'Security', icon: '\u{1F6E1}' },
        { key: 'religion', label: 'Religion', icon: '\u{1F54C}' },
        { key: 'food', label: 'Food', icon: '\u{1F33E}' },
        { key: 'happiness', label: 'Happiness', icon: '\u{1F3AD}' },
      ],
      form: {
        title: '',
        description: '',
        sort_order: 1,
        difficulty: 6,
        category: '',
        positive: {},
        negative: {},
        positive_flavor: '',
        negative_flavor: '',
        positiveRecoverDie: false,
        positiveDrawItem: false,
        positiveRemoveCurse: false,
        negativeLoseDie: false,
        negativeDiscardItem: false,
        negativeDrawItem: false,
        positiveBonusScore: 0,
        negativeBonusScore: 0,
        available_cooperative: true,
        available_duel: true,
      },
    };
  },
  computed: {
    filteredCards() {
      let list = this.cards;
      const q = this.searchQuery.toLowerCase().trim();
      if (q) {
        list = list.filter(c =>
          (c.title || '').toLowerCase().includes(q) ||
          (c.description || '').toLowerCase().includes(q) ||
          (c.category || '').toLowerCase().includes(q) ||
          String(c.difficulty).includes(q)
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
    cardBalanceStats() {
      if (!this.cards.length) return null;
      const statKeys = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
      const specialKeys = ['draw_item', 'recover_die', 'lose_die', 'discard_item', 'remove_curse'];

      // Difficulty
      const avgDiff = this.cards.reduce((s, c) => s + (c.difficulty || 0), 0) / this.cards.length;
      const diffDist = { easy: 0, medium: 0, hard: 0 };
      this.cards.forEach(c => {
        if (c.difficulty <= 5) diffDist.easy++;
        else if (c.difficulty <= 8) diffDist.medium++;
        else diffDist.hard++;
      });

      // Category distribution
      const catDist = {};
      this.cards.forEach(c => {
        const cat = c.category || 'none';
        catDist[cat] = (catDist[cat] || 0) + 1;
      });

      // Per-stat totals
      const perStat = {};
      statKeys.forEach(key => {
        let totalPos = 0, totalNeg = 0;
        this.cards.forEach(c => {
          totalPos += (c.positive_effects && c.positive_effects[key]) || 0;
          totalNeg += (c.negative_effects && c.negative_effects[key]) || 0;
        });
        perStat[key] = {
          totalPos,
          totalNeg,
          net: totalPos + totalNeg,
          avgPos: totalPos / this.cards.length,
          avgNeg: totalNeg / this.cards.length,
        };
      });

      // Special effect counts
      const specialCounts = {};
      specialKeys.forEach(key => {
        let count = 0;
        this.cards.forEach(c => {
          if ((c.positive_effects && c.positive_effects[key]) || (c.negative_effects && c.negative_effects[key])) count++;
        });
        specialCounts[key] = count;
      });

      // Avg positive/negative per card
      let totalPosSum = 0, totalNegSum = 0;
      this.cards.forEach(c => {
        statKeys.forEach(key => {
          totalPosSum += (c.positive_effects && c.positive_effects[key]) || 0;
          totalNegSum += (c.negative_effects && c.negative_effects[key]) || 0;
        });
      });

      return {
        count: this.cards.length,
        avgDiff: avgDiff,
        diffDist,
        catDist,
        perStat,
        specialCounts,
        avgPosPerCard: totalPosSum / this.cards.length,
        avgNegPerCard: totalNegSum / this.cards.length,
      };
    },
  },
  async mounted() {
    await this.fetch();
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
      const res = await axios.get('/api/admin/cards');
      this.cards = res.data;
      this.loading = false;
    },
    truncate(str, len) {
      return str && str.length > len ? str.substring(0, len) + '...' : str || '';
    },
    diffClass(difficulty) {
      if (difficulty <= 5) return 'diff-easy';
      if (difficulty <= 8) return 'diff-medium';
      return 'diff-hard';
    },
    setEffect(side, key, value) {
      const num = value === '' ? null : parseInt(value);
      if (num === null || num === 0 || isNaN(num)) {
        delete this.form[side][key];
      } else {
        this.form[side][key] = num;
      }
    },
    effectsToObj(effects, form, side) {
      const obj = {};
      for (const [key, val] of Object.entries(effects)) {
        if (val && val !== 0) obj[key] = val;
      }
      if (side === 'positive') {
        if (form.positiveRecoverDie) obj['recover_die'] = 1;
        if (form.positiveDrawItem) obj['draw_item'] = 1;
        if (form.positiveRemoveCurse) obj['remove_curse'] = 1;
        if (form.positiveBonusScore) obj['bonus_score'] = form.positiveBonusScore;
      }
      if (side === 'negative') {
        if (form.negativeLoseDie) obj['lose_die'] = 1;
        if (form.negativeDiscardItem) obj['discard_item'] = 1;
        if (form.negativeDrawItem) obj['draw_item'] = 1;
        if (form.negativeBonusScore) obj['bonus_score'] = form.negativeBonusScore;
      }
      return obj;
    },
    objToForm(effects) {
      const obj = {};
      let recoverDie = false;
      let loseDie = false;
      let drawItem = false;
      let discardItem = false;
      let removeCurse = false;
      let bonusScore = 0;
      for (const [key, val] of Object.entries(effects || {})) {
        if (key === 'recover_die') recoverDie = true;
        else if (key === 'lose_die') loseDie = true;
        else if (key === 'draw_item') drawItem = true;
        else if (key === 'discard_item') discardItem = true;
        else if (key === 'remove_curse') removeCurse = true;
        else if (key === 'bonus_score') bonusScore = val;
        else obj[key] = val;
      }
      return { stats: obj, recoverDie, loseDie, drawItem, discardItem, removeCurse, bonusScore };
    },

    openCreate() {
      this.editing = null;
      const maxSort = this.cards.reduce((m, c) => Math.max(m, c.sort_order), 0);
      this.form = {
        title: '',
        description: '',
        sort_order: maxSort + 1,
        difficulty: 6,
        category: '',
        positive: {},
        negative: {},
        positive_flavor: '',
        negative_flavor: '',
        positiveRecoverDie: false,
        positiveDrawItem: false,
        positiveRemoveCurse: false,
        negativeLoseDie: false,
        negativeDiscardItem: false,
        negativeDrawItem: false,
        positiveBonusScore: 0,
        negativeBonusScore: 0,
        available_cooperative: true,
        available_duel: true,
      };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(card) {
      this.editing = card;
      const pos = this.objToForm(card.positive_effects);
      const neg = this.objToForm(card.negative_effects);
      this.form = {
        title: card.title,
        description: card.description,
        sort_order: card.sort_order,
        difficulty: card.difficulty,
        category: card.category || '',
        positive: { ...pos.stats },
        negative: { ...neg.stats },
        positive_flavor: card.positive_flavor || '',
        negative_flavor: card.negative_flavor || '',
        positiveRecoverDie: pos.recoverDie,
        positiveDrawItem: pos.drawItem,
        positiveRemoveCurse: pos.removeCurse,
        negativeLoseDie: neg.loseDie,
        negativeDiscardItem: neg.discardItem,
        negativeDrawItem: neg.drawItem,
        positiveBonusScore: pos.bonusScore || 0,
        negativeBonusScore: neg.bonusScore || 0,
        available_cooperative: card.available_cooperative ?? true,
        available_duel: card.available_duel ?? true,
      };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';

      const positive_effects = this.effectsToObj(this.form.positive, this.form, 'positive');
      const negative_effects = this.effectsToObj(this.form.negative, this.form, 'negative');

      const payload = {
        title: this.form.title,
        description: this.form.description,
        sort_order: this.form.sort_order,
        difficulty: this.form.difficulty,
        category: this.form.category || null,
        positive_effects,
        negative_effects,
        positive_flavor: this.form.positive_flavor || null,
        negative_flavor: this.form.negative_flavor || null,
        available_cooperative: this.form.available_cooperative,
        available_duel: this.form.available_duel,
      };

      this.saving = true;
      try {
        if (this.editing) {
          await axios.put(`/api/admin/cards/${this.editing.id}`, payload);
        } else {
          await axios.post('/api/admin/cards', payload);
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
        const res = await axios.post('/api/admin/ai/generate-card', {
          prompt: this.aiPrompt || undefined,
          category: this.aiCategory || undefined,
        });
        const data = res.data;
        this.showAiModal = false;
        this.aiPrompt = '';
        this.aiCategory = '';
        // Open create modal pre-filled with AI data
        const maxSort = this.cards.reduce((m, c) => Math.max(m, c.sort_order), 0);
        const posData = this.objToForm(data.positive_effects);
        const negData = this.objToForm(data.negative_effects);
        this.editing = null;
        this.form = {
          title: data.title || '',
          description: data.description || '',
          sort_order: maxSort + 1,
          difficulty: data.difficulty || 6,
          category: data.category || '',
          positive: { ...posData.stats },
          negative: { ...negData.stats },
          positive_flavor: data.positive_flavor || '',
          negative_flavor: data.negative_flavor || '',
          positiveRecoverDie: posData.recoverDie,
          positiveDrawItem: posData.drawItem,
          positiveRemoveCurse: posData.removeCurse,
          negativeLoseDie: negData.loseDie,
          negativeDiscardItem: negData.discardItem,
          negativeDrawItem: negData.drawItem,
        };
        this.formError = '';
        this.showModal = true;
      } catch (e) {
        this.aiError = e.response?.data?.message || e.response?.data?.error || 'AI generation failed';
      }
      this.aiGenerating = false;
    },
    exportCsv() {
      window.location.href = '/api/admin/cards/export-csv';
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
        const res = await axios.post('/api/admin/cards/import-csv', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.importResult = res.data;
        await this.fetch();
      } catch (e) {
        this.importResult = { created: 0, updated: 0, errors: [e.response?.data?.message || 'Import failed'] };
      }
      event.target.value = '';
    },
    async confirmDelete(card) {
      if (!confirm(`Delete card "${card.title}"?`)) return;
      try {
        await axios.delete(`/api/admin/cards/${card.id}`);
        await this.fetch();
      } catch (e) {
        this.toast.error('Delete failed: ' + (e.response?.data?.message || e.message));
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

.loading { text-align: center; color: var(--text-secondary); padding: 40px; }

.table-wrap { overflow-x: auto; }

.admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.admin-table th, .admin-table td {
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

.admin-table tbody tr:hover { background: rgba(212, 168, 67, 0.05); }

.name-col { color: var(--text-bright); font-weight: 600; white-space: nowrap; }
.desc-col { color: var(--text-secondary); max-width: 250px; }

.diff-badge {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 1px;
  padding: 2px 10px;
  border-radius: 4px;
}

.diff-easy { background: rgba(39, 174, 96, 0.2); color: #27ae60; }
.diff-medium { background: rgba(212, 168, 67, 0.2); color: #d4a843; }
.diff-hard { background: rgba(192, 57, 43, 0.2); color: #c0392b; }

.actions-col {
  white-space: nowrap;
  display: flex;
  gap: 6px;
}

.actions-col button { padding: 5px 12px; font-size: 0.8rem; }

/* Modal */
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

.modal-wide { max-width: 700px; }

.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 18px;
  font-size: 1.3rem;
}

.form-group { margin-bottom: 14px; flex: 1; }
.form-small { max-width: 120px; }

.form-row {
  display: flex;
  gap: 14px;
}

.form-group label {
  display: block;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 5px;
}

.form-group input:not([type="checkbox"]),
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

.form-group textarea { resize: vertical; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }

/* Effects sections */
.effects-section {
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
}

.effects-positive {
  border-color: rgba(74, 138, 58, 0.4);
  background: rgba(74, 138, 58, 0.04);
}

.effects-negative {
  border-color: rgba(160, 48, 32, 0.4);
  background: rgba(160, 48, 32, 0.04);
}

.effects-title {
  font-family: 'Cinzel', serif;
  font-size: 1rem;
  margin-bottom: 12px;
}

.effects-positive .effects-title { color: #6abf50; }
.effects-negative .effects-title { color: #d05040; }

.stat-grid {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 10px;
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

.stat-icon {
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

/* Variant rules */
.variant-rules {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.variant-check {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

.variant-check input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--accent-gold);
  cursor: pointer;
}

.variant-label {
  color: var(--text-secondary);
  font-size: 0.85rem;
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
  border: 1px solid var(--border-gold);
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

.balance-easy { color: #27ae60; }
.balance-medium { color: #d4a843; }
.balance-hard { color: #c0392b; }
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

.balance-dist-zero {
  opacity: 0.4;
}

.balance-table {
  font-size: 0.85rem;
}
</style>
