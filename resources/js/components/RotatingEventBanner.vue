<template>
  <div v-if="events.length > 0" class="event-banners">
    <div
      v-for="event in events"
      :key="event.id"
      class="event-banner"
      :style="bannerStyle(event)"
      @click="$router.push('/events/' + event.id)"
    >
      <div class="event-banner-left">
        <span class="event-icon">&#9889;</span>
        <div class="event-info">
          <span class="event-name">{{ event.name }}</span>
          <span class="event-countdown">{{ timeLeft(event) }}</span>
        </div>
      </div>
      <button class="event-play-btn" :style="event.theme_color ? { borderColor: event.theme_color, color: event.theme_color } : {}" @click.stop="$router.push('/events/' + event.id)">Play Now</button>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'RotatingEventBanner',
  data() {
    return {
      events: [],
      timer: null,
    };
  },
  async mounted() {
    try {
      const res = await axios.get('/api/rotating-events');
      this.events = res.data;
    } catch {}

    this.timer = setInterval(() => this.$forceUpdate(), 60000);
  },
  beforeUnmount() {
    if (this.timer) clearInterval(this.timer);
  },
  methods: {
    bannerStyle(event) {
      if (!event.theme_color) return {};
      return {
        borderColor: event.theme_color,
        background: `rgba(${this.hexToRgb(event.theme_color)}, 0.1)`,
      };
    },
    hexToRgb(hex) {
      const h = hex.replace('#', '');
      const r = parseInt(h.substring(0, 2), 16);
      const g = parseInt(h.substring(2, 4), 16);
      const b = parseInt(h.substring(4, 6), 16);
      return `${r}, ${g}, ${b}`;
    },
    timeLeft(event) {
      const end = new Date(event.ends_at);
      const now = new Date();
      const diff = end - now;
      if (diff <= 0) return 'Ended';
      const hours = Math.floor(diff / 3600000);
      const days = Math.floor(hours / 24);
      if (days > 0) return `${days}d ${hours % 24}h left`;
      const mins = Math.floor((diff % 3600000) / 60000);
      return `${hours}h ${mins}m left`;
    },
  },
};
</script>

<style scoped>
.event-banners {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 10px;
}

.event-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  background: rgba(13, 10, 6, 0.65);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 2px solid rgba(138, 58, 185, 0.4);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.event-banner:hover {
  border-color: rgba(138, 58, 185, 0.7);
  box-shadow: 0 0 12px rgba(138, 58, 185, 0.2);
}

.event-banner-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.event-icon {
  font-size: 1.4rem;
}

.event-info {
  display: flex;
  flex-direction: column;
}

.event-name {
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.95rem;
  font-weight: 700;
}

.event-countdown {
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-style: italic;
}

.event-play-btn {
  padding: 6px 14px;
  font-size: 0.8rem;
  font-family: 'Cinzel', serif;
  font-weight: 700;
  background: rgba(138, 58, 185, 0.2);
  border: 1px solid rgba(138, 58, 185, 0.5);
  border-radius: 6px;
  color: #c890e0;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.event-play-btn:hover {
  background: rgba(138, 58, 185, 0.35);
  color: #e0b0f0;
}
</style>
