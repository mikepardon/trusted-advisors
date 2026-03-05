<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Unlockables</h2>
      <button class="btn-primary" @click="openCreate">+ New Unlockable</button>
    </div>

    <!-- List -->
    <div class="list-panel">
      <div v-for="u in unlockables" :key="u.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ u.entity_name }}</strong>
            <span class="type-badge">{{ u.type }}</span>
            <span class="method-badge">{{ u.unlock_method }}</span>
          </div>
          <div class="list-sub">{{ u.unlock_label }}</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm" @click="openEdit(u)">Edit</button>
          <button class="btn-sm btn-danger" @click="deleteItem(u)">Del</button>
        </div>
      </div>
      <div v-if="unlockables.length === 0" class="empty">No unlockables defined.</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>{{ editing ? 'Edit Unlockable' : 'New Unlockable' }}</h3>
        <form @submit.prevent="save">
          <div class="form-grid">
            <div class="form-group">
              <label>Type</label>
              <select v-model="form.type" @change="form.entity_id = ''">
                <option value="character">Character</option>
                <option value="item">Item</option>
                <option value="dice_theme">Dice Theme</option>
                <option value="kingdom_style">Kingdom Style</option>
              </select>
            </div>
            <div class="form-group">
              <label>{{ { character: 'Character', dice_theme: 'Dice Theme', kingdom_style: 'Kingdom Style', item: 'Item' }[form.type] || 'Item' }}</label>
              <select v-model.number="form.entity_id" required>
                <option value="" disabled>Select...</option>
                <option v-for="e in entityOptions" :key="e.id" :value="e.id">{{ e.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Unlock Method</label>
              <select v-model="form.unlock_method" @change="form.unlock_value = form.unlock_method === 'level' || form.unlock_method === 'coins' ? 1 : ''">
                <option value="level">Level</option>
                <option value="achievement">Achievement</option>
                <option value="coins">Coins</option>
              </select>
            </div>
            <div class="form-group">
              <label>{{ form.unlock_method === 'level' ? 'Required Level' : form.unlock_method === 'coins' ? 'Price (Coins)' : 'Achievement' }}</label>
              <input v-if="form.unlock_method === 'level' || form.unlock_method === 'coins'" v-model.number="form.unlock_value" type="number" required min="1" />
              <select v-else v-model.number="form.unlock_value" required>
                <option value="" disabled>Select achievement...</option>
                <option v-for="a in achievements" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-section-label">Cash Pricing (optional)</div>
          <div class="form-grid">
            <div class="form-group">
              <label>Cash Price (cents)</label>
              <input v-model.number="form.cash_price_cents" type="number" min="0" placeholder="e.g. 299" />
            </div>
            <div class="form-group">
              <label>Stripe Price ID</label>
              <input v-model="form.stripe_price_id" type="text" placeholder="price_..." />
            </div>
            <div class="form-group">
              <label>Apple Product ID</label>
              <input v-model="form.apple_product_id" type="text" placeholder="com.app.item" />
            </div>
            <div class="form-group">
              <label>Google Product ID</label>
              <input v-model="form.google_product_id" type="text" placeholder="com.app.item" />
            </div>
          </div>
          <div v-if="formError" class="form-error">{{ formError }}</div>
          <div class="modal-actions">
            <button type="submit" class="btn-primary">{{ editing ? 'Update' : 'Create' }}</button>
            <button type="button" @click="showModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminUnlockables',
  data() {
    return {
      unlockables: [],
      characters: [],
      items: [],
      diceThemes: [],
      kingdomStyles: [],
      achievements: [],
      showModal: false,
      editing: null,
      formError: '',
      form: { type: 'character', entity_id: '', unlock_method: 'level', unlock_value: 1, cash_price_cents: null, stripe_price_id: '', apple_product_id: '', google_product_id: '' },
    };
  },
  computed: {
    entityOptions() {
      if (this.form.type === 'character') return this.characters;
      if (this.form.type === 'dice_theme') return this.diceThemes;
      if (this.form.type === 'kingdom_style') return this.kingdomStyles;
      return this.items;
    },
  },
  async mounted() { this.load(); },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/unlockables');
      this.unlockables = res.data.unlockables;
      this.characters = res.data.characters;
      this.items = res.data.items;
      this.diceThemes = res.data.dice_themes || [];
      this.kingdomStyles = res.data.kingdom_styles || [];
      this.achievements = res.data.achievements;
    },
    openCreate() {
      this.editing = null;
      this.form = { type: 'character', entity_id: '', unlock_method: 'level', unlock_value: 1, cash_price_cents: null, stripe_price_id: '', apple_product_id: '', google_product_id: '' };
      this.formError = '';
      this.showModal = true;
    },
    openEdit(u) {
      this.editing = u.id;
      this.form = { type: u.type, entity_id: u.entity_id, unlock_method: u.unlock_method, unlock_value: u.unlock_value, cash_price_cents: u.cash_price_cents, stripe_price_id: u.stripe_price_id || '', apple_product_id: u.apple_product_id || '', google_product_id: u.google_product_id || '' };
      this.formError = '';
      this.showModal = true;
    },
    async save() {
      this.formError = '';
      try {
        if (this.editing) {
          await axios.put(`/api/admin/unlockables/${this.editing}`, this.form);
        } else {
          await axios.post('/api/admin/unlockables', this.form);
        }
        this.showModal = false;
        this.load();
      } catch (e) {
        this.formError = e.response?.data?.error || e.response?.data?.message || 'Error';
      }
    },
    async deleteItem(u) {
      if (!confirm('Delete this unlockable?')) return;
      await axios.delete(`/api/admin/unlockables/${u.id}`);
      this.load();
    },
  },
};
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 8px; }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.type-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; text-transform: uppercase; }
.method-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); text-transform: uppercase; }
.list-actions { display: flex; gap: 4px; }
.btn-sm { background: rgba(212, 168, 67, 0.15); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--accent-gold); padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: rgba(160, 48, 32, 0.15); color: #d05040; border-color: rgba(160, 48, 32, 0.3); }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 500px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-group { margin-bottom: 0; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent-gold); }
.form-section-label { grid-column: 1 / -1; font-family: 'Cinzel', serif; font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(138, 106, 46, 0.15); }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
</style>
