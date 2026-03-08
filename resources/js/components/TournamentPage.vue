<template>
  <div class="tournament-page">
    <button class="back-btn" @click="$router.push('/')">&#8592; Back</button>

    <h1 class="page-title">Tournaments</h1>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="['tab', { active: tab === 'open' }]" @click="tab = 'open'">Open</button>
      <button :class="['tab', { active: tab === 'mine' }]" @click="tab = 'mine'; fetchMine()">My Tournaments</button>
    </div>

    <!-- Create button (premium only) -->
    <div v-if="auth.state.user?.is_premium && !showCreate" class="create-section">
      <button class="create-btn" @click="showCreate = true">+ Create Tournament</button>
    </div>

    <!-- Create form -->
    <div v-if="showCreate" class="card-panel create-form">
      <h2 class="section-title">Create Tournament</h2>
      <div class="form-group">
        <label>Name</label>
        <input v-model="form.name" type="text" class="form-input" placeholder="Tournament name..." maxlength="100" />
      </div>
      <div class="form-group">
        <label>Max Players</label>
        <div class="btn-row">
          <button v-for="n in [4, 8, 16]" :key="n" :class="['choice-btn', { selected: form.max_players === n }]" @click="form.max_players = n">{{ n }}</button>
        </div>
      </div>
      <div class="form-group">
        <label>Game Type</label>
        <div class="btn-row">
          <button :class="['choice-btn', { selected: form.game_type === 'duel' }]" @click="form.game_type = 'duel'">Duel</button>
          <button :class="['choice-btn', { selected: form.game_type === 'cooperative' }]" @click="form.game_type = 'cooperative'">Cooperative</button>
        </div>
      </div>
      <div class="form-group">
        <label>
          <input type="checkbox" v-model="form.is_private" /> Private (password required)
        </label>
        <input v-if="form.is_private" v-model="form.password" type="text" class="form-input" placeholder="Lobby password..." />
      </div>
      <div class="form-actions">
        <button class="cancel-btn" @click="showCreate = false">Cancel</button>
        <button class="btn-primary" :disabled="!form.name.trim() || creating" @click="createTournament">
          {{ creating ? 'Creating...' : 'Create' }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-text">Loading...</div>

    <!-- Open tournaments -->
    <div v-else-if="tab === 'open'">
      <div v-if="openTournaments.length === 0" class="empty-text">No open tournaments.</div>
      <div v-for="t in openTournaments" :key="t.id" class="tournament-card" @click="viewTournament(t.id)">
        <div class="tc-header">
          <span class="tc-name">{{ t.name }}</span>
          <span v-if="t.is_private" class="tc-lock">&#128274;</span>
        </div>
        <div class="tc-info">
          <span>{{ t.creator?.name }}</span>
          <span>{{ t.participants_count }}/{{ t.max_players }} players</span>
          <span class="tc-type">{{ t.game_type }}</span>
        </div>
        <button class="join-btn" @click.stop="joinTournament(t)">Join</button>
      </div>
    </div>

    <!-- My tournaments -->
    <div v-else-if="tab === 'mine'">
      <div v-if="myTournaments.length === 0" class="empty-text">You haven't joined any tournaments.</div>
      <div v-for="t in myTournaments" :key="t.id" class="tournament-card" @click="viewTournament(t.id)">
        <div class="tc-header">
          <span class="tc-name">{{ t.name }}</span>
          <span :class="['tc-status', 'status-' + t.status]">{{ t.status }}</span>
        </div>
        <div class="tc-info">
          <span>{{ t.creator?.name }}</span>
          <span>{{ t.participants_count }}/{{ t.max_players }} players</span>
        </div>
      </div>
    </div>

    <!-- Tournament detail modal -->
    <div v-if="detail" class="modal-overlay" @click.self="detail = null">
      <div class="detail-modal">
        <h2 class="section-title">{{ detail.name }}</h2>
        <div class="detail-meta">
          <span>Host: {{ detail.creator?.name }}</span>
          <span>Status: {{ detail.status }}</span>
          <span>Type: {{ detail.game_type }}</span>
        </div>

        <!-- Participants -->
        <div class="detail-section">
          <h3>Participants</h3>
          <div class="participant-list">
            <div v-for="p in detail.participants" :key="p.id" class="participant-row">
              <span>{{ p.user?.name }}</span>
              <span v-if="p.seed" class="seed-badge">#{{ p.seed }}</span>
              <span v-if="p.final_placement" class="placement-badge">{{ ordinal(p.final_placement) }}</span>
            </div>
          </div>
        </div>

        <!-- Bracket -->
        <div v-if="detail.matches && detail.matches.length" class="detail-section">
          <h3>Bracket</h3>
          <div v-for="round in bracketRounds" :key="round" class="bracket-round">
            <h4>Round {{ round }}</h4>
            <div v-for="m in matchesForRound(round)" :key="m.id" class="match-row">
              <div :class="['match-player', { winner: m.winner_id === m.player1_id }]">
                {{ m.player1?.name || 'BYE' }}
              </div>
              <span class="match-vs">vs</span>
              <div :class="['match-player', { winner: m.winner_id === m.player2_id }]">
                {{ m.player2?.name || 'BYE' }}
              </div>
              <button v-if="m.game && m.status === 'in_progress' && isMyMatch(m)" class="play-btn" @click="$router.push('/game/' + m.game.id); detail = null">
                Play
              </button>
              <span v-else-if="m.status === 'completed'" class="match-done">&#10003;</span>
            </div>
          </div>
        </div>

        <!-- Start button (creator only, setup status) -->
        <button
          v-if="detail.status === 'setup' && detail.creator_id === auth.state.user?.id"
          class="btn-primary start-btn"
          :disabled="starting"
          @click="startTournament"
        >
          {{ starting ? 'Starting...' : 'Start Tournament' }}
        </button>

        <button class="cancel-btn close-btn" @click="detail = null">Close</button>
      </div>
    </div>

    <!-- Password modal -->
    <div v-if="passwordModal" class="modal-overlay" @click.self="passwordModal = null">
      <div class="modal-box">
        <h3>Enter Password</h3>
        <input v-model="joinPassword" type="text" class="form-input" placeholder="Password..." @keyup.enter="doJoin" />
        <div class="form-actions">
          <button class="cancel-btn" @click="passwordModal = null">Cancel</button>
          <button class="btn-primary" @click="doJoin">Join</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { useAuth } from '../stores/auth';
import { useToast } from '../stores/toast';

export default {
  name: 'TournamentPage',
  setup() {
    const auth = useAuth();
    const toast = useToast();
    return { auth, toast };
  },
  data() {
    return {
      tab: 'open',
      loading: true,
      openTournaments: [],
      myTournaments: [],
      showCreate: false,
      creating: false,
      starting: false,
      form: {
        name: '',
        max_players: 4,
        game_type: 'duel',
        is_private: false,
        password: '',
      },
      detail: null,
      passwordModal: null,
      joinPassword: '',
    };
  },
  computed: {
    bracketRounds() {
      if (!this.detail?.matches) return [];
      const rounds = [...new Set(this.detail.matches.map(m => m.bracket_round))];
      return rounds.sort((a, b) => a - b);
    },
  },
  async mounted() {
    await this.fetchOpen();
  },
  methods: {
    async fetchOpen() {
      this.loading = true;
      try {
        const res = await axios.get('/api/tournaments');
        this.openTournaments = res.data;
      } catch {}
      this.loading = false;
    },
    async fetchMine() {
      this.loading = true;
      try {
        const res = await axios.get('/api/tournaments/mine');
        this.myTournaments = res.data;
      } catch {}
      this.loading = false;
    },
    async viewTournament(id) {
      try {
        const res = await axios.get(`/api/tournaments/${id}`);
        this.detail = res.data;
      } catch (e) {
        this.toast.error('Failed to load tournament.');
      }
    },
    matchesForRound(round) {
      return (this.detail?.matches || []).filter(m => m.bracket_round === round);
    },
    isMyMatch(match) {
      const uid = this.auth.state.user?.id;
      return match.player1_id === uid || match.player2_id === uid;
    },
    async createTournament() {
      this.creating = true;
      try {
        const payload = { ...this.form };
        if (!payload.is_private) delete payload.password;
        const res = await axios.post('/api/tournaments', payload);
        this.showCreate = false;
        this.detail = res.data;
        await this.fetchOpen();
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to create tournament.');
      }
      this.creating = false;
    },
    joinTournament(tournament) {
      if (tournament.is_private) {
        this.passwordModal = tournament;
        this.joinPassword = '';
      } else {
        this.doJoinDirect(tournament.id);
      }
    },
    async doJoin() {
      const id = this.passwordModal?.id;
      if (!id) return;
      try {
        await axios.post(`/api/tournaments/${id}/join`, { password: this.joinPassword });
        this.passwordModal = null;
        this.joinPassword = '';
        await this.fetchOpen();
        this.viewTournament(id);
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to join.');
      }
    },
    async doJoinDirect(id) {
      try {
        await axios.post(`/api/tournaments/${id}/join`);
        await this.fetchOpen();
        this.viewTournament(id);
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to join.');
      }
    },
    async startTournament() {
      if (!this.detail) return;
      this.starting = true;
      try {
        const res = await axios.post(`/api/tournaments/${this.detail.id}/start`);
        this.detail = res.data;
        await this.fetchOpen();
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to start tournament.');
      }
      this.starting = false;
    },
    ordinal(n) {
      const s = ['th', 'st', 'nd', 'rd'];
      const v = n % 100;
      return n + (s[(v - 20) % 10] || s[v] || s[0]);
    },
  },
};
</script>

<style scoped>
.tournament-page {
  max-width: 600px;
  margin: 0 auto;
  padding: 0 16px 40px;
}

.back-btn {
  background: none;
  border: 1px solid rgba(138, 106, 46, 0.4);
  color: var(--text-secondary);
  font-size: 0.9rem;
  padding: 8px 16px;
  cursor: pointer;
  margin-bottom: 16px;
  border-radius: 6px;
  letter-spacing: 0;
}

.back-btn:hover {
  color: var(--text-bright);
  border-color: var(--border-gold);
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.5rem;
  text-align: center;
  margin-bottom: 16px;
}

.tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 16px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 10px;
  padding: 4px;
}

.tab {
  flex: 1;
  padding: 8px 14px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.2s;
}

.tab.active {
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
}

.section-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.2rem;
  margin-bottom: 12px;
  text-align: center;
}

.create-section {
  margin-bottom: 16px;
  text-align: center;
}

.create-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  padding: 10px 24px;
  border-radius: 8px;
  border: 2px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  cursor: pointer;
  font-weight: 700;
}

.create-btn:hover {
  background: rgba(212, 168, 67, 0.3);
}

.create-form {
  margin-bottom: 16px;
}

.form-group {
  margin-bottom: 14px;
}

.form-group label {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.85rem;
  margin-bottom: 6px;
}

.form-input {
  width: 100%;
  background: rgba(0, 0, 0, 0.3);
  border: 2px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-primary);
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 1rem;
  padding: 8px 12px;
  outline: none;
  box-sizing: border-box;
  margin-top: 4px;
}

.form-input:focus {
  border-color: var(--accent-gold);
}

.btn-row {
  display: flex;
  gap: 8px;
}

.choice-btn {
  flex: 1;
  padding: 8px 12px;
  border-radius: 6px;
  border: 2px solid rgba(138, 106, 46, 0.3);
  background: rgba(0, 0, 0, 0.2);
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
}

.choice-btn.selected {
  border-color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
}

.form-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
  margin-top: 16px;
}

.cancel-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  padding: 8px 18px;
  border-radius: 6px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
}

.loading-text, .empty-text {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 30px 0;
}

.tournament-card {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: border-color 0.2s;
}

.tournament-card:hover {
  border-color: var(--accent-gold);
}

.tc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.tc-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1rem;
  font-weight: 700;
}

.tc-lock {
  font-size: 0.9rem;
}

.tc-status {
  font-size: 0.7rem;
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  font-weight: 600;
}

.status-setup { background: rgba(67, 160, 212, 0.15); color: #60b8e0; }
.status-in_progress { background: rgba(212, 168, 67, 0.15); color: var(--accent-gold); }
.status-completed { background: rgba(106, 191, 80, 0.15); color: #6abf50; }

.tc-info {
  display: flex;
  gap: 12px;
  color: var(--text-secondary);
  font-size: 0.8rem;
}

.tc-type {
  text-transform: capitalize;
}

.join-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.75rem;
  padding: 4px 14px;
  border-radius: 4px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
  color: var(--accent-gold);
  cursor: pointer;
  margin-top: 8px;
}

.join-btn:hover {
  background: rgba(212, 168, 67, 0.3);
}

/* Detail modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.detail-modal {
  background: var(--bg-secondary);
  border: 2px solid var(--accent-gold);
  border-radius: 14px;
  padding: 24px;
  max-width: 480px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-box {
  background: var(--bg-secondary);
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 20px 24px;
  max-width: 320px;
  width: 90%;
  text-align: center;
}

.modal-box h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin: 0 0 12px;
}

.detail-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  color: var(--text-secondary);
  font-size: 0.8rem;
  margin-bottom: 16px;
  justify-content: center;
}

.detail-section {
  margin-bottom: 16px;
}

.detail-section h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  margin-bottom: 8px;
}

.participant-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.participant-row {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(0, 0, 0, 0.15);
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  color: var(--text-primary);
}

.seed-badge {
  font-size: 0.7rem;
  color: var(--accent-gold);
}

.placement-badge {
  font-size: 0.7rem;
  color: #6abf50;
  font-weight: 700;
}

.bracket-round {
  margin-bottom: 12px;
}

.bracket-round h4 {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 0.8rem;
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.match-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: rgba(0, 0, 0, 0.12);
  border-radius: 6px;
  margin-bottom: 4px;
}

.match-player {
  flex: 1;
  font-size: 0.85rem;
  color: var(--text-primary);
}

.match-player.winner {
  color: var(--accent-gold);
  font-weight: 700;
}

.match-vs {
  color: var(--text-secondary);
  font-size: 0.7rem;
  text-transform: uppercase;
}

.match-done {
  color: #6abf50;
  font-size: 0.9rem;
}

.play-btn {
  font-family: 'Cinzel', serif;
  font-size: 0.7rem;
  padding: 3px 10px;
  border-radius: 4px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  cursor: pointer;
}

.start-btn {
  display: block;
  margin: 16px auto 12px;
  font-size: 1rem;
  padding: 10px 30px;
}

.close-btn {
  display: block;
  margin: 8px auto 0;
  width: 100%;
}

.card-panel {
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 1px solid rgba(138, 106, 46, 0.2);
  border-radius: 10px;
  padding: 20px;
}

.btn-primary {
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  padding: 8px 20px;
  border-radius: 6px;
  border: 1px solid var(--accent-gold);
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  cursor: pointer;
  font-weight: 600;
}

.btn-primary:hover:not(:disabled) {
  background: rgba(212, 168, 67, 0.35);
}

.btn-primary:disabled {
  opacity: 0.4;
  cursor: default;
}
</style>
