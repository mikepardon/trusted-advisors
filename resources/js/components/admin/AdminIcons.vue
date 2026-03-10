<template>
  <div class="admin-icons">
    <h2>App Icons</h2>
    <p class="desc">Configure icons used throughout the app. Changes take effect immediately for all users.</p>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-for="group in groupedIcons" :key="group.category" class="icon-group">
      <h3 class="group-title">{{ group.label }}</h3>
      <div class="icon-rows">
        <div v-for="icon in group.icons" :key="icon.id" class="icon-row">
          <div class="icon-meta">
            <span class="icon-label">{{ icon.label }}</span>
            <span class="icon-key">{{ icon.key }}</span>
          </div>
          <IconPicker
            :model-value="icon.icon_value"
            :icon-type="icon.icon_type"
            @update:model-value="updateIcon(icon, 'icon_value', $event)"
            @update:icon-type="updateIcon(icon, 'icon_type', $event)"
          />
          <span v-if="icon._saving" class="save-status saving">Saving...</span>
          <span v-else-if="icon._saved" class="save-status saved">Saved</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import IconPicker from './IconPicker.vue';
import { useToast } from '../../stores/toast';

const CATEGORY_LABELS = {
  navigation: 'Navigation',
  stats: 'Stats',
  ui: 'UI',
};

export default {
  name: 'AdminIcons',
  components: { IconPicker },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      icons: [],
      loading: true,
      saveTimers: {},
    };
  },
  computed: {
    groupedIcons() {
      const groups = {};
      for (const icon of this.icons) {
        if (!groups[icon.category]) {
          groups[icon.category] = {
            category: icon.category,
            label: CATEGORY_LABELS[icon.category] || icon.category,
            icons: [],
          };
        }
        groups[icon.category].icons.push(icon);
      }
      return Object.values(groups);
    },
  },
  async mounted() {
    await this.loadIcons();
  },
  methods: {
    async loadIcons() {
      this.loading = true;
      try {
        const { data } = await axios.get('/api/admin/app-icons');
        this.icons = data.map(i => ({ ...i, _saving: false, _saved: false }));
      } catch {
        this.toast.error('Failed to load icons');
      }
      this.loading = false;
    },
    updateIcon(icon, field, value) {
      icon[field] = value;
      // Debounce save
      if (this.saveTimers[icon.id]) clearTimeout(this.saveTimers[icon.id]);
      this.saveTimers[icon.id] = setTimeout(() => this.saveIcon(icon), 500);
    },
    async saveIcon(icon) {
      icon._saving = true;
      icon._saved = false;
      try {
        await axios.put(`/api/admin/app-icons/${icon.id}`, {
          icon_type: icon.icon_type,
          icon_value: icon.icon_value,
        });
        icon._saving = false;
        icon._saved = true;
        setTimeout(() => { icon._saved = false; }, 2000);
      } catch {
        icon._saving = false;
        this.toast.error(`Failed to save ${icon.label}`);
      }
    },
  },
};
</script>

<style scoped>
.admin-icons { max-width: 800px; }
.desc { color: #b8a07a; margin-bottom: 24px; font-size: 0.9rem; }
.loading { color: #b8a07a; padding: 20px; }
.icon-group { margin-bottom: 32px; }
.group-title {
  font-family: 'Cinzel', serif;
  color: #f0c050;
  font-size: 1.1rem;
  border-bottom: 1px solid #463220;
  padding-bottom: 6px;
  margin-bottom: 12px;
}
.icon-rows { display: flex; flex-direction: column; gap: 12px; }
.icon-row {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 12px;
  background: #1e160c;
  border: 1px solid #342618;
  border-radius: 8px;
}
.icon-meta {
  width: 120px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.icon-label { font-weight: 600; color: #f0e0c8; font-size: 0.9rem; }
.icon-key { font-size: 0.7rem; color: #8a7a5a; font-family: monospace; }
.save-status {
  font-size: 0.75rem;
  padding: 4px 8px;
  border-radius: 4px;
  white-space: nowrap;
  align-self: center;
}
.saving { color: #f0c050; }
.saved { color: #5cb85c; }
</style>
