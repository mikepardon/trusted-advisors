<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Admin Gifts</h2>
      <button class="btn-primary" @click="showModal = true">Send Gift</button>
    </div>

    <!-- Past gifts -->
    <div class="list-panel">
      <div v-for="g in gifts" :key="g.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ g.title }}</strong>
            <span class="date-badge">{{ formatDate(g.created_at) }}</span>
          </div>
          <div class="list-sub">
            {{ g.note || 'No note' }} &mdash;
            <span v-if="g.reward_xp">{{ g.reward_xp }} XP</span>
            <span v-if="g.reward_coins"> {{ g.reward_coins }} coins</span>
            <span v-if="g.reward_character"> {{ g.reward_character?.name }}</span>
            <span v-if="g.reward_dice_theme"> {{ g.reward_dice_theme?.name }} (dice)</span>
            <span v-if="g.reward_kingdom_style"> {{ g.reward_kingdom_style?.name }} (kingdom)</span>
            &mdash; Sent to {{ g.recipient_count }} users
            <span v-if="g.creator"> by {{ g.creator.name }}</span>
          </div>
        </div>
      </div>
      <div v-if="gifts.length === 0" class="empty">No gifts sent yet.</div>
    </div>

    <!-- Send Gift Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <h3>Send Gift to All Users</h3>
        <form @submit.prevent="confirmSend">
          <div class="form-group">
            <label>Title</label>
            <input v-model="form.title" required placeholder="e.g. Holiday Gift" />
          </div>
          <div class="form-group">
            <label>Note</label>
            <textarea v-model="form.note" rows="2" placeholder="Optional message"></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Reward XP</label>
              <input v-model.number="form.reward_xp" type="number" min="0" />
            </div>
            <div class="form-group">
              <label>Reward Coins</label>
              <input v-model.number="form.reward_coins" type="number" min="0" />
            </div>
          </div>
          <div class="form-group">
            <label>Reward Character</label>
            <select v-model="form.reward_character_id">
              <option :value="null">None</option>
              <option v-for="c in characters" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Reward Dice Theme</label>
            <select v-model="form.reward_dice_theme_id">
              <option :value="null">None</option>
              <option v-for="d in diceThemes" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label>Reward Kingdom Style</label>
            <select v-model="form.reward_kingdom_style_id">
              <option :value="null">None</option>
              <option v-for="ks in kingdomStyles" :key="ks.id" :value="ks.id">{{ ks.name }}</option>
            </select>
          </div>
          <div v-if="formError" class="form-error">{{ formError }}</div>

          <!-- Confirmation step -->
          <div v-if="showConfirm" class="confirm-box">
            <p>Are you sure you want to send this gift to all users?</p>
            <div class="modal-actions">
              <button type="button" class="btn-primary" :disabled="sending" @click="send">
                {{ sending ? 'Sending...' : 'Confirm Send' }}
              </button>
              <button type="button" @click="showConfirm = false">Back</button>
            </div>
          </div>
          <div v-else class="modal-actions">
            <button type="submit" class="btn-primary">Review & Send</button>
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
  name: 'AdminGifts',
  data() {
    return {
      gifts: [],
      characters: [],
      diceThemes: [],
      kingdomStyles: [],
      showModal: false,
      showConfirm: false,
      sending: false,
      formError: '',
      form: { title: '', note: '', reward_xp: 0, reward_coins: 0, reward_character_id: null, reward_dice_theme_id: null, reward_kingdom_style_id: null },
    };
  },
  async mounted() {
    await Promise.all([this.load(), this.fetchCharacters(), this.fetchDiceThemes(), this.fetchKingdomStyles()]);
  },
  methods: {
    async load() {
      const res = await axios.get('/api/admin/gifts');
      this.gifts = res.data;
    },
    async fetchCharacters() {
      try {
        const res = await axios.get('/api/characters');
        this.characters = res.data;
      } catch {}
    },
    async fetchDiceThemes() {
      try {
        const res = await axios.get('/api/admin/dice-themes');
        this.diceThemes = res.data;
      } catch {}
    },
    async fetchKingdomStyles() {
      try {
        const res = await axios.get('/api/admin/kingdom-styles');
        this.kingdomStyles = res.data;
      } catch {}
    },
    confirmSend() {
      this.formError = '';
      if (!this.form.title) {
        this.formError = 'Title is required.';
        return;
      }
      if (this.form.reward_xp === 0 && this.form.reward_coins === 0 && !this.form.reward_character_id && !this.form.reward_dice_theme_id && !this.form.reward_kingdom_style_id) {
        this.formError = 'At least one reward must be specified.';
        return;
      }
      this.showConfirm = true;
    },
    async send() {
      this.sending = true;
      this.formError = '';
      try {
        await axios.post('/api/admin/gifts', this.form);
        this.showModal = false;
        this.showConfirm = false;
        this.form = { title: '', note: '', reward_xp: 0, reward_coins: 0, reward_character_id: null, reward_dice_theme_id: null, reward_kingdom_style_id: null };
        this.load();
      } catch (e) {
        this.formError = e.response?.data?.error || 'Failed to send gift';
      }
      this.sending = false;
    },
    formatDate(d) {
      if (!d) return '';
      return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
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
.list-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.list-sub { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }
.date-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 3px; background: rgba(100, 100, 160, 0.2); color: #a0a0d0; }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 200; }
.modal-content { background: var(--bg-secondary); border: 2px solid var(--border-gold); border-radius: 10px; padding: 28px; width: 90%; max-width: 500px; max-height: 85vh; overflow-y: auto; }
.modal-content h3 { font-family: 'Cinzel', serif; color: var(--accent-gold); margin-bottom: 18px; font-size: 1.3rem; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 3px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 6px 10px; border-radius: 4px; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--accent-gold); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.form-error { color: var(--accent-red); font-size: 0.9rem; margin: 10px 0; }
.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.confirm-box { margin-top: 16px; padding: 14px; border: 1px solid rgba(212, 168, 67, 0.3); border-radius: 6px; background: rgba(212, 168, 67, 0.05); }
.confirm-box p { margin-bottom: 10px; color: var(--text-bright); font-size: 0.95rem; }
</style>
