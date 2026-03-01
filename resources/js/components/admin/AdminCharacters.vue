<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Characters</h2>
      <button class="btn-primary" @click="openCreate">+ New Character</button>
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
              <label class="upload-btn">
                Upload Image
                <input type="file" accept="image/*" class="hidden-input" @change="uploadImage" />
              </label>
              <span v-if="imageUploading" class="upload-status">Uploading...</span>
              <span v-if="imageUploaded" class="upload-success">Uploaded!</span>
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
  </div>
</template>

<script>
import axios from 'axios';
import AdminSearchInput from './AdminSearchInput.vue';
import SortableHeader from './SortableHeader.vue';

export default {
  name: 'AdminCharacters',
  components: { AdminSearchInput, SortableHeader },
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
      form: { name: '', description: '', wild_value: 3, wild_ability: '', wild_ability_description: '', addon_id: null },
      die1Input: '',
      die2Input: '',
      die3Input: '',
      diceRules: { 1: 3, 2: 3, 3: 3, 4: 3, 5: 3, 6: 3 },
      rulesSaved: false,
      imageUploading: false,
      imageUploaded: false,
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
      this.form = { name: '', description: '', wild_value: 3, wild_ability: '', wild_ability_description: '', addon_id: null };
      this.die1Input = '';
      this.die2Input = '';
      this.die3Input = '';
      this.formError = '';
      this.imageUploading = false;
      this.imageUploaded = false;
      this.showModal = true;
    },
    openEdit(c) {
      this.editing = c;
      this.imageUploading = false;
      this.imageUploaded = false;
      this.form = {
        name: c.name,
        description: c.description,
        wild_value: c.wild_value,
        wild_ability: c.wild_ability,
        wild_ability_description: c.wild_ability_description || '',
        addon_id: c.addon_id || null,
      };
      this.die1Input = c.dice[0]?.join(', ') || '';
      this.die2Input = c.dice[1]?.join(', ') || '';
      this.die3Input = c.dice[2]?.join(', ') || '';
      this.formError = '';
      this.showModal = true;
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

      const payload = {
        ...this.form,
        addon_id: this.form.addon_id || null,
        dice: [processDie(die1), processDie(die2), processDie(die3)],
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
    async uploadImage(event) {
      const file = event.target.files[0];
      if (!file || !this.editing) return;

      this.imageUploading = true;
      this.imageUploaded = false;

      const formData = new FormData();
      formData.append('image', file);

      try {
        const res = await axios.post(`/api/admin/characters/${this.editing.id}/image`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        this.editing.image_url = res.data.image_url;
        this.imageUploaded = true;
        setTimeout(() => { this.imageUploaded = false; }, 2000);
        await this.fetch();
      } catch (e) {
        this.formError = 'Image upload failed. Check S3 config.';
      }
      this.imageUploading = false;
      event.target.value = '';
    },
    async confirmDelete(c) {
      if (!confirm(`Delete character "${c.name}"? This cannot be undone.`)) return;
      try {
        await axios.delete(`/api/admin/characters/${c.id}`);
        await this.fetch();
      } catch (e) {
        alert('Delete failed: ' + (e.response?.data?.message || e.message));
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

/* Rules panel */
.rules-panel {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid var(--border-gold);
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
</style>
