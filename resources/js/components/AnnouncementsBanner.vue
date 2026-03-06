<template>
  <div v-if="announcements.length" class="announcements-banner">
    <Swiper
      v-if="announcements.length > 1"
      :modules="swiperModules"
      :autoplay="{ delay: 5000, disableOnInteraction: true }"
      :pagination="{ clickable: true }"
      :loop="true"
      :space-between="0"
      class="announcements-swiper"
    >
      <SwiperSlide v-for="a in announcements" :key="a.id">
        <div class="announcement-slide">
          <button class="dismiss-btn" @click.stop="dismiss(a)" title="Dismiss">&times;</button>
          <h3 class="announcement-title">{{ a.title }}</h3>
          <p class="announcement-desc">{{ a.description }}</p>
          <a
            v-if="a.link_url"
            :href="a.link_url"
            class="announcement-link"
            target="_blank"
            rel="noopener"
            @click.stop
          >{{ a.link_label || 'Learn more' }} &rarr;</a>
        </div>
      </SwiperSlide>
    </Swiper>

    <div v-else class="announcement-slide single">
      <button class="dismiss-btn" @click.stop="dismiss(announcements[0])" title="Dismiss">&times;</button>
      <h3 class="announcement-title">{{ announcements[0].title }}</h3>
      <p class="announcement-desc">{{ announcements[0].description }}</p>
      <a
        v-if="announcements[0].link_url"
        :href="announcements[0].link_url"
        class="announcement-link"
        target="_blank"
        rel="noopener"
        @click.stop
      >{{ announcements[0].link_label || 'Learn more' }} &rarr;</a>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Autoplay, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';

export default {
  name: 'AnnouncementsBanner',
  components: { Swiper, SwiperSlide },
  data() {
    return {
      announcements: [],
    };
  },
  computed: {
    swiperModules() {
      return [Autoplay, Pagination];
    },
  },
  async mounted() {
    try {
      const res = await axios.get('/api/announcements');
      this.announcements = res.data;
    } catch {}
  },
  methods: {
    async dismiss(announcement) {
      this.announcements = this.announcements.filter(a => a.id !== announcement.id);
      try {
        await axios.post(`/api/announcements/${announcement.id}/dismiss`);
      } catch {}
    },
  },
};
</script>

<style scoped>
.announcements-banner {
  margin-bottom: 6px;
}

.announcement-slide {
  position: relative;
  background: rgba(13, 10, 6, 0.65);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 2px solid rgba(138, 106, 46, 0.3);
  border-radius: 10px;
  padding: 12px 14px;
  padding-right: 32px;
}

.announcement-slide.single {
  /* same styling, no swiper wrapper */
}

.dismiss-btn {
  position: absolute;
  top: 6px;
  right: 8px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.3rem;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  opacity: 0.6;
  transition: opacity 0.2s, color 0.2s;
}

.dismiss-btn:hover {
  opacity: 1;
  color: var(--text-bright);
  transform: none;
  box-shadow: none;
  background: none;
}

.announcement-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.95rem;
  margin-bottom: 4px;
}

.announcement-desc {
  color: var(--text-secondary);
  font-size: 0.85rem;
  line-height: 1.4;
  margin: 0 0 4px;
}

.announcement-link {
  color: var(--accent-gold);
  font-size: 0.8rem;
  text-decoration: none;
  opacity: 0.8;
  transition: opacity 0.2s;
}

.announcement-link:hover {
  opacity: 1;
  text-decoration: underline;
}

/* Swiper pagination dots */
.announcements-banner :deep(.swiper-pagination-bullet) {
  background: rgba(212, 168, 67, 0.4);
  opacity: 1;
}

.announcements-banner :deep(.swiper-pagination-bullet-active) {
  background: var(--accent-gold);
}

.announcements-banner :deep(.swiper-pagination) {
  position: relative;
  margin-top: 6px;
}
</style>
