<template>
  <div class="dashboard">
    <h2 class="page-title">Game Content Dashboard</h2>

    <div class="stats-grid" v-if="!loading">
      <router-link to="/admin/characters" class="stat-card">
        <div class="stat-count">{{ counts.characters }}</div>
        <div class="stat-label">Characters</div>
      </router-link>
      <router-link to="/admin/cards" class="stat-card">
        <div class="stat-count">{{ counts.cards }}</div>
        <div class="stat-label">Cards</div>
      </router-link>
      <router-link to="/admin/events" class="stat-card">
        <div class="stat-count">{{ counts.events }}</div>
        <div class="stat-label">Events</div>
      </router-link>
      <router-link to="/admin/items" class="stat-card">
        <div class="stat-count">{{ counts.items }}</div>
        <div class="stat-label">Items</div>
      </router-link>
    </div>
    <p v-else class="loading">Loading counts...</p>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminDashboard',
  data() {
    return {
      loading: true,
      counts: { characters: 0, cards: 0, events: 0, items: 0 },
    };
  },
  async mounted() {
    try {
      const [chars, cards, events, items] = await Promise.all([
        axios.get('/api/admin/characters'),
        axios.get('/api/admin/cards'),
        axios.get('/api/admin/events'),
        axios.get('/api/admin/items'),
      ]);
      this.counts = {
        characters: chars.data.length,
        cards: cards.data.length,
        events: events.data.length,
        items: items.data.length,
      };
    } catch (e) {
      console.error('Failed to load counts', e);
    }
    this.loading = false;
  },
};
</script>

<style scoped>
.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  text-align: center;
  margin-bottom: 30px;
  font-size: 1.8rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.stat-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 24px;
  text-align: center;
  text-decoration: none;
  transition: all 0.2s;
}

.stat-card:hover {
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.2);
  transform: translateY(-2px);
}

.stat-count {
  font-family: 'Cinzel', serif;
  font-size: 3rem;
  color: var(--accent-gold);
  font-weight: 900;
}

.stat-label {
  font-family: 'Cinzel', serif;
  color: var(--text-secondary);
  font-size: 1rem;
  margin-top: 5px;
}

.loading {
  text-align: center;
  color: var(--text-secondary);
}
</style>
