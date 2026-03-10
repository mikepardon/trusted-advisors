<template>
  <div class="collection-page">
    <h2 class="page-title">Collection</h2>

    <div class="collection-tabs-wrap">
      <div class="collection-tabs">
        <button
          v-for="tab in collectionTabs"
          :key="tab.key"
          class="collection-tab"
          :class="{ active: collectionTab === tab.key }"
          @click="collectionTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Advisors Tab -->
    <div v-if="collectionTab === 'advisors'">
      <AdvisorManagement
        :advisors="myAdvisors"
        :loading="advisorsLoading"
        @select="openAdvisorDetail"
        @level-up="openLevelUp"
      />
    </div>

    <!-- Dice Tab -->
    <div v-if="collectionTab === 'dice'">
      <div v-if="diceLoading" class="loading-text">Loading dice...</div>
      <div v-else-if="!myDice.length" class="loading-text">No dice themes available yet.</div>
      <div v-else class="dice-grid">
        <div
          v-for="d in myDice"
          :key="d.id"
          class="dice-card"
        >
          <div class="dice-preview-wrap">
            <img v-if="d.preview_image" :src="d.preview_image" :alt="d.name" class="dice-preview-img" />
            <div v-else class="dice-preview-placeholder">No preview</div>
          </div>
          <span class="dice-card-name">{{ d.name }}</span>
          <p v-if="d.description" class="dice-card-desc">{{ d.description }}</p>
          <div class="dice-card-actions">
            <button
              class="btn-dice-activate"
              :class="{ 'btn-dice-active': d.is_active_selection }"
              :disabled="activatingDice && !d.is_active_selection"
              @click="!d.is_active_selection && activateDice(d)"
            >{{ d.is_active_selection ? 'Active' : 'Use' }}</button>
            <button class="btn-dice-test" @click="testDice(d)">Try</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Styles Tab -->
    <div v-if="collectionTab === 'styles'">
      <div v-if="stylesLoading" class="loading-text">Loading styles...</div>
      <div v-else-if="!myStyles.length" class="loading-text">No kingdom styles available yet.</div>
      <div v-else class="ks-grid">
        <div
          v-for="s in myStyles"
          :key="s.id"
          class="ks-card"
          :data-kingdom-style="s.slug"
          :data-ks-anim="s.css_vars?.border_anim || 'none'"
          :style="ksCardStyle(s)"
        >
          <div class="ks-preview">
            <div class="ks-preview-bar ks-bar-safe"></div>
            <div class="ks-preview-bar ks-bar-caution"></div>
            <div class="ks-preview-bar ks-bar-safe" style="width: 60%"></div>
          </div>
          <span class="ks-card-name">{{ s.name }}</span>
          <div class="ks-card-actions">
            <button
              class="btn-ks-activate"
              :class="{ 'btn-ks-active': s.is_active_selection }"
              :disabled="activatingStyle && !s.is_active_selection"
              @click="!s.is_active_selection && activateStyle(s)"
            >{{ s.is_active_selection ? 'Active' : 'Use' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Advisor Detail Modal -->
    <AdvisorDetailModal
      v-if="selectedAdvisor"
      :advisor="selectedAdvisor"
      @close="selectedAdvisor = null"
      @level-up="openLevelUp"
      @updated="reloadAdvisors"
    />

    <!-- Level Up Modal -->
    <LevelUpChoice
      v-if="levelUpAdvisor"
      :advisor="levelUpAdvisor"
      @close="levelUpAdvisor = null"
      @chosen="onUpgradeChosen"
    />

    <!-- Persistent dice canvas -->
    <canvas ref="diceCanvas" class="dice-canvas-persistent"></canvas>
  </div>
</template>

<script>
import axios from 'axios';
import { createDddiceInstance, isDddiceAvailable } from '../dddiceService';
import { useToast } from '../stores/toast';
import AdvisorManagement from './AdvisorManagement.vue';
import AdvisorDetailModal from './AdvisorDetailModal.vue';
import LevelUpChoice from './LevelUpChoice.vue';
import '../styles/kingdom-styles.css';

export default {
  name: 'CollectionPage',
  components: { AdvisorManagement, AdvisorDetailModal, LevelUpChoice },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      myAdvisors: [],
      advisorsLoading: true,
      selectedAdvisor: null,
      levelUpAdvisor: null,
      myDice: [],
      diceLoading: true,
      myStyles: [],
      stylesLoading: true,
      collectionTab: 'advisors',
      activatingDice: false,
      activatingStyle: false,
      diceInstance: null,
    };
  },
  computed: {
    collectionTabs() {
      const tabs = [];
      const advisorCount = this.myAdvisors.length;
      if (advisorCount > 0 || this.advisorsLoading) {
        tabs.push({ key: 'advisors', icon: '\u{1F9D9}', label: 'Advisors', count: advisorCount });
      }
      const diceCount = this.myDice.length;
      if (diceCount > 0 || this.diceLoading) {
        tabs.push({ key: 'dice', icon: '\u{1F3B2}', label: 'Dice', count: diceCount });
      }
      const styleCount = this.myStyles.length;
      if (styleCount > 0 || this.stylesLoading) {
        tabs.push({ key: 'styles', icon: '\u{1F3F0}', label: 'Styles', count: styleCount });
      }
      return tabs;
    },
  },
  async mounted() {
    const [advisorRes, diceRes, ksRes] = await Promise.allSettled([
      axios.get('/api/my-advisors'),
      axios.get('/api/my-dice'),
      axios.get('/api/my-kingdom-styles'),
    ]);
    this.myAdvisors = advisorRes.status === 'fulfilled' ? advisorRes.value.data : [];
    this.advisorsLoading = false;
    this.myDice = diceRes.status === 'fulfilled' ? diceRes.value.data : [];
    this.diceLoading = false;
    this.myStyles = ksRes.status === 'fulfilled' ? ksRes.value.data : [];
    this.stylesLoading = false;
    this.initDiceCanvas();
  },
  methods: {
    ksCardStyle(style) {
      const s = {};
      if (style.css_vars) {
        const cv = style.css_vars;
        if (cv.border_color) s['--ks-border-color'] = cv.border_color;
        if (cv.border_glow) s['--ks-border-glow'] = cv.border_glow;
        if (cv.border_color_rgb) s['--ks-border-color-rgb'] = cv.border_color_rgb;
        if (cv.bg_tint) s['--ks-bg-tint'] = cv.bg_tint;
        if (cv.bg_color) s['--ks-bg-color'] = cv.bg_color;
        if (cv.name_accent) s['--ks-name-accent'] = cv.name_accent;
        if (cv.total_accent) s['--ks-total-accent'] = cv.total_accent;
        if (cv.bar_safe) s['--ks-bar-safe'] = cv.bar_safe;
        if (cv.bar_caution) s['--ks-bar-caution'] = cv.bar_caution;
      }
      if (style.background_image_url) {
        s.backgroundImage = `linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url(${style.background_image_url})`;
        s.backgroundSize = 'cover';
        s.backgroundPosition = 'center';
      }
      return s;
    },
    async activateStyle(style) {
      this.activatingStyle = true;
      try {
        const res = await axios.post(`/api/my-kingdom-styles/${style.id}/activate`);
        this.myStyles = res.data;
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to activate kingdom style.');
      }
      this.activatingStyle = false;
    },
    openAdvisorDetail(advisor) {
      this.selectedAdvisor = advisor;
    },
    openLevelUp(advisor) {
      this.selectedAdvisor = null;
      this.levelUpAdvisor = advisor;
    },
    async onUpgradeChosen() {
      this.levelUpAdvisor = null;
      await this.reloadAdvisors();
    },
    async reloadAdvisors() {
      try {
        const res = await axios.get('/api/my-advisors');
        this.myAdvisors = res.data;
        // If we had a selected advisor, refresh it
        if (this.selectedAdvisor) {
          this.selectedAdvisor = this.myAdvisors.find(a => a.id === this.selectedAdvisor.id) || null;
        }
      } catch (e) {
        // silent
      }
    },
    async activateDice(dice) {
      this.activatingDice = true;
      try {
        const res = await axios.post(`/api/my-dice/${dice.id}/activate`);
        this.myDice = res.data;
      } catch (e) {
        this.toast.error(e.response?.data?.error || 'Failed to activate dice theme.');
      }
      this.activatingDice = false;
    },
    async initDiceCanvas() {
      if (!isDddiceAvailable()) return;
      const canvas = this.$refs.diceCanvas;
      if (!canvas) return;
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
      this.diceInstance = createDddiceInstance();
      await this.diceInstance.init(canvas);
      this._onResize = () => {
        if (this.$refs.diceCanvas && this.diceInstance) {
          this.$refs.diceCanvas.width = window.innerWidth;
          this.$refs.diceCanvas.height = window.innerHeight;
          this.diceInstance.resize(window.innerWidth, window.innerHeight);
        }
      };
      window.addEventListener('resize', this._onResize);
    },
    async testDice(dice) {
      if (!this.diceInstance?.isReady()) return;
      const slug = dice.slug;
      this.diceInstance.roll([
        { theme: slug, value: Math.ceil(Math.random() * 6) },
        { theme: slug, value: Math.ceil(Math.random() * 6) },
        { theme: slug, value: Math.ceil(Math.random() * 6) },
      ]);
    },
  },
  beforeUnmount() {
    if (this.diceInstance) {
      this.diceInstance.destroy();
      this.diceInstance = null;
    }
    if (this._onResize) {
      window.removeEventListener('resize', this._onResize);
    }
  },
};
</script>

<style scoped>
.collection-page {
  max-width: 800px;
  margin: 0 auto;
}

.page-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.6rem;
  text-align: center;
  margin-bottom: 20px;
}

.loading-text {
  text-align: center;
  color: var(--text-secondary);
  font-style: italic;
  padding: 40px;
}

/* Collection Tabs */
.collection-tabs-wrap {
  margin-bottom: 16px;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}

.collection-tabs-wrap::-webkit-scrollbar {
  display: none;
}

.collection-tabs {
  display: flex;
  gap: 6px;
  justify-content: center;
  min-width: min-content;
}

.collection-tab {
  padding: 6px 16px;
  font-size: 0.8rem;
  white-space: nowrap;
}

.collection-tab.active {
  background: var(--accent-gold);
  border-color: var(--accent-gold);
  color: black;
  box-shadow: 0 4px 0 #7a5a14, inset 0 1px 0 rgba(255,255,255,0.2);
}


/* Advisors Grid */
.char-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 12px;
}

.char-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.char-card:hover {
  border-color: var(--accent-gold);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.2);
}

.char-img-wrap {
  position: relative;
  width: 64px;
  height: 64px;
}

.char-img {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-gold);
}

.char-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.8rem;
  text-align: center;
  font-weight: 700;
}

/* Dice Grid */
.dice-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 10px;
}

.dice-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: linear-gradient(180deg, var(--bg-secondary), var(--bg-primary));
  border: 2px solid var(--border-gold);
  border-radius: 0;
  padding: 8px;
}

.dice-preview-wrap {
  width: 100%;
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 0;
  background: rgba(0, 0, 0, 0.2);
}

.dice-preview-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.dice-preview-placeholder {
  color: var(--text-secondary);
  font-size: 0.7rem;
  opacity: 0.5;
}

.dice-card-name {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--text-bright);
  font-size: 0.72rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: center;
  padding: 4px 4px 2px;
  max-width: 100%;
}

.dice-card-desc {
  color: var(--text-secondary);
  font-size: 0.68rem;
  font-style: italic;
  text-align: center;
  line-height: 1.3;
  padding: 0 4px 2px;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.dice-card-actions {
  display: flex;
  gap: 4px;
  padding: 2px 6px 4px;
}

.btn-dice-activate,
.btn-dice-test {
  padding: 4px 10px;
  font-size: 0.7rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: all 0.2s;
}

.btn-dice-activate {
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
}

.btn-dice-activate:hover {
  background: rgba(212, 168, 67, 0.25);
}

.btn-dice-activate.btn-dice-active {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
  cursor: default;
}

.btn-dice-activate:disabled {
  opacity: 0.5;
  cursor: default;
}

.btn-dice-test {
  background: rgba(67, 160, 212, 0.15);
  border: 1px solid rgba(67, 160, 212, 0.3);
  color: #60b8e0;
}

.btn-dice-test:hover {
  background: rgba(67, 160, 212, 0.25);
}

/* Kingdom Styles Grid */
.ks-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.ks-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px;
  border: 2px solid var(--ks-border-color, rgba(138, 106, 46, 0.2));
  border-radius: 8px;
  background-color: var(--ks-bg-color, var(--ks-bg-tint, transparent));
  box-shadow: var(--ks-border-glow, none);
  transition: border-color 0.2s, box-shadow 0.2s;
}

.ks-preview {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
  padding: 6px;
}

.ks-preview-bar {
  height: 6px;
  border-radius: 3px;
  width: 80%;
}

.ks-bar-safe {
  background: var(--ks-bar-safe, #27ae60);
}

.ks-bar-caution {
  background: var(--ks-bar-caution, #d4a843);
  width: 50%;
}

.ks-card-name {
  display: block;
  font-family: 'Cinzel', serif;
  color: var(--ks-name-accent, var(--text-bright));
  font-size: 0.72rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: center;
  padding: 4px 4px 2px;
  max-width: 100%;
}

.ks-card-actions {
  padding: 2px 6px 4px;
}

.btn-ks-activate {
  padding: 4px 10px;
  font-size: 0.7rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: all 0.2s;
  background: rgba(212, 168, 67, 0.15);
  border: 1px solid rgba(212, 168, 67, 0.3);
  color: var(--accent-gold);
}

.btn-ks-activate:hover {
  background: rgba(212, 168, 67, 0.25);
}

.btn-ks-activate.btn-ks-active {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
  cursor: default;
}

.btn-ks-activate:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Character Detail Modal */
.char-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.char-modal {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid var(--accent-gold);
  border-radius: 12px;
  padding: 24px;
  max-width: 360px;
  width: 100%;
  position: relative;
  max-height: 85vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.char-modal-close {
  position: absolute;
  top: 8px;
  right: 12px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.char-modal-close:hover {
  color: var(--accent-gold);
  transform: none;
  box-shadow: none;
}

.char-modal-portrait-wrap {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid var(--accent-gold);
  box-shadow: 0 0 20px rgba(212, 168, 67, 0.3);
  margin-bottom: 12px;
}

.char-modal-portrait {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.char-modal-name {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 1.3rem;
  margin-bottom: 6px;
  text-align: center;
}

.char-modal-desc {
  color: var(--text-secondary);
  font-style: italic;
  font-size: 0.9rem;
  text-align: center;
  margin-bottom: 14px;
  line-height: 1.5;
}

.char-modal-dice {
  width: 100%;
  margin-bottom: 12px;
}

.char-dice-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
  justify-content: center;
}

.char-dice-label {
  color: var(--text-secondary);
  font-size: 0.8rem;
  min-width: 42px;
}

.char-dice-face {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 4px;
  color: var(--text-bright);
  font-size: 0.8rem;
  font-weight: 600;
}

.char-modal-wild {
  width: 100%;
  background: rgba(212, 168, 67, 0.08);
  border-top: 1px solid rgba(212, 168, 67, 0.2);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
}

.char-wild-badge {
  display: inline-block;
  background: rgba(212, 168, 67, 0.2);
  color: var(--accent-gold);
  padding: 2px 10px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 700;
  margin-bottom: 4px;
}

.char-wild-desc {
  display: block;
  color: var(--text-secondary);
  font-size: 0.78rem;
  font-style: italic;
  line-height: 1.4;
}

.char-modal-locked {
  margin-top: 12px;
  padding: 10px;
  background: rgba(160, 48, 32, 0.1);
  border: 1px solid rgba(160, 48, 32, 0.3);
  border-radius: 6px;
  text-align: center;
  color: var(--accent-red);
  font-size: 0.85rem;
}

.char-lock-icon {
  margin-right: 6px;
}

/* Persistent dice canvas */
.dice-canvas-persistent {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 200;
}

@media (max-width: 768px) {
  .dice-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .char-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }

  .char-img-wrap {
    width: 56px;
    height: 56px;
  }

  .char-img {
    width: 56px;
    height: 56px;
  }

  .char-name {
    font-size: 0.7rem;
  }
}
</style>
