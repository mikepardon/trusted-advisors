<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Kingdom Styles</h2>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else-if="!styles.length" class="empty-state">
      <p>No kingdom styles found. Run the KingdomStyleSeeder to create presets.</p>
    </div>

    <div v-else class="style-grid">
      <div v-for="style in styles" :key="style.id" class="style-card">
        <div class="style-info">
          <div class="style-name">{{ style.name }}</div>
          <div class="style-slug">{{ style.slug }}</div>
          <div class="style-desc" v-if="style.description">{{ style.description }}</div>
          <div class="style-badges">
            <span v-if="style.is_active" class="badge badge-active">Active</span>
            <span v-else class="badge badge-inactive">Inactive</span>
            <span v-if="style.is_default_unlocked" class="badge badge-default">Default</span>
            <span v-if="style.is_default" class="badge badge-default">Is Default</span>
          </div>
        </div>
        <!-- Mini color swatch preview -->
        <div class="style-swatch" :style="swatchStyle(style)">
          <div class="swatch-bar swatch-bar-safe" :style="{ background: style.css_vars?.bar_safe || '#27ae60' }"></div>
          <div class="swatch-bar swatch-bar-caution" :style="{ background: style.css_vars?.bar_caution || '#d4a843' }"></div>
        </div>
        <button class="btn-edit" @click="openEdit(style)">Edit</button>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="editingStyle" class="modal-overlay" @click.self="editingStyle = null">
      <div class="modal-content modal-wide">
        <h3>Edit Kingdom Style: {{ editingStyle.name }}</h3>

        <!-- Name & Description -->
        <div class="form-group">
          <label>Display Name</label>
          <input v-model="editForm.name" class="form-input" />
        </div>
        <div class="form-group">
          <label>Description</label>
          <input v-model="editForm.description" class="form-input" />
        </div>

        <!-- Toggles -->
        <div class="form-row">
          <div class="form-group">
            <label class="toggle-label">
              <input type="checkbox" v-model="editForm.is_active" />
              <span>Active</span>
            </label>
          </div>
          <div class="form-group">
            <label class="toggle-label">
              <input type="checkbox" v-model="editForm.is_default_unlocked" />
              <span>Default Unlocked</span>
            </label>
          </div>
        </div>

        <!-- Color Pickers -->
        <h4 class="section-label">Colors</h4>
        <div class="color-grid">
          <div v-for="cv in colorVars" :key="cv.key" class="color-item">
            <label>{{ cv.label }}</label>
            <div class="color-input-row">
              <input type="color" :value="editForm.css_vars[cv.key] || cv.fallback" @input="editForm.css_vars[cv.key] = $event.target.value" />
              <input type="text" class="form-input color-hex" :value="editForm.css_vars[cv.key] || cv.fallback" @input="editForm.css_vars[cv.key] = $event.target.value" />
            </div>
          </div>
        </div>

        <!-- Border Animation -->
        <h4 class="section-label">Border Animation</h4>
        <div class="form-group">
          <label>Animation Type</label>
          <select v-model="editForm.css_vars.border_anim" class="form-input">
            <option value="none">None (static)</option>
            <option value="pulse">Pulse (glow intensity cycles)</option>
            <option value="breathe">Breathe (glow + border fades in/out)</option>
            <option value="flicker">Flicker (rapid candlelight glow)</option>
            <option value="shimmer">Shimmer (rotating border color gradient)</option>
            <option value="pride">Pride (rainbow border rotation)</option>
            <option value="fire">Fire (red/orange/yellow rotation)</option>
            <option value="ocean">Ocean (blue/teal/cyan rotation)</option>
          </select>
        </div>

        <!-- Background Image -->
        <h4 class="section-label">Background Image</h4>
        <div class="bg-image-section">
          <div v-if="editingStyle.background_image_url" class="bg-image-preview">
            <img :src="editingStyle.background_image_url" alt="Background" />
          </div>
          <div v-else class="bg-image-placeholder">No background image</div>
          <div class="bg-image-actions">
            <input ref="bgImageInput" type="file" accept="image/*" style="display:none" @change="uploadBgImage" />
            <button class="btn-upload" :disabled="uploadingImage" @click="$refs.bgImageInput.click()">
              {{ uploadingImage ? 'Uploading...' : 'Upload Image' }}
            </button>
            <button v-if="editingStyle.background_image_url" class="btn-remove-img" :disabled="removingImage" @click="removeBgImage">
              {{ removingImage ? 'Removing...' : 'Remove' }}
            </button>
          </div>
          <div v-if="uploadError" class="form-error">{{ uploadError }}</div>
        </div>

        <!-- Live Preview -->
        <h4 class="section-label">Preview</h4>
        <div class="live-preview" :style="previewStyle" :data-ks-anim="editForm.css_vars.border_anim || 'none'" data-kingdom-style="preview">
          <div class="preview-name" :style="{ color: editForm.css_vars.name_accent || 'var(--accent-gold)' }">Kingdom Name</div>
          <div class="preview-bars">
            <div class="preview-bar" :style="{ background: editForm.css_vars.bar_safe || '#27ae60', width: '80%' }"></div>
            <div class="preview-bar" :style="{ background: editForm.css_vars.bar_caution || '#d4a843', width: '40%' }"></div>
            <div class="preview-bar" :style="{ background: editForm.css_vars.bar_safe || '#27ae60', width: '60%' }"></div>
          </div>
          <div class="preview-total" :style="{ color: editForm.css_vars.total_accent || 'var(--accent-gold)' }">48</div>
        </div>

        <div v-if="editError" class="form-error">{{ editError }}</div>
        <div class="modal-actions">
          <button class="btn-primary" :disabled="editSaving" @click="saveEdit">
            {{ editSaving ? 'Saving...' : 'Save' }}
          </button>
          <button type="button" @click="editingStyle = null">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import '../../styles/kingdom-styles.css';

export default {
  name: 'AdminKingdomStyles',
  data() {
    return {
      styles: [],
      loading: true,
      editingStyle: null,
      editForm: {
        name: '',
        description: '',
        is_active: true,
        is_default_unlocked: false,
        css_vars: {},
      },
      editSaving: false,
      editError: '',
      uploadingImage: false,
      removingImage: false,
      uploadError: '',
      colorVars: [
        { key: 'border_color', label: 'Border Color', fallback: '#c9a227' },
        { key: 'bg_tint', label: 'Background Tint', fallback: '#1a1a0a' },
        { key: 'bg_color', label: 'Background Color', fallback: '#1a1a0a' },
        { key: 'name_accent', label: 'Name Accent', fallback: '#e8c468' },
        { key: 'total_accent', label: 'Total Accent', fallback: '#e8c468' },
        { key: 'bar_safe', label: 'Bar Safe', fallback: '#27ae60' },
        { key: 'bar_caution', label: 'Bar Caution', fallback: '#d4a843' },
        { key: 'stat_color', label: 'Stat Value', fallback: '#e8e0d0' },
        { key: 'text_color', label: 'Text / Labels', fallback: '#a09080' },
      ],
    };
  },
  computed: {
    previewStyle() {
      const cv = this.editForm.css_vars;
      const borderColor = cv.border_color || 'transparent';
      const bgTint = cv.bg_tint || 'transparent';
      const bgColor = cv.bg_color || 'transparent';
      const anim = cv.border_anim || 'none';
      // Only use static glow if no animation is active
      const glow = (anim === 'none' && borderColor !== 'transparent')
        ? `0 0 12px ${borderColor}66`
        : 'none';
      // Compute RGB for animation CSS vars
      let borderRgb = '0, 0, 0';
      if (borderColor !== 'transparent' && borderColor.startsWith('#')) {
        const r = parseInt(borderColor.slice(1, 3), 16);
        const g = parseInt(borderColor.slice(3, 5), 16);
        const b = parseInt(borderColor.slice(5, 7), 16);
        borderRgb = `${r}, ${g}, ${b}`;
      }
      return {
        border: `2px solid ${borderColor}`,
        boxShadow: glow,
        backgroundColor: bgColor !== 'transparent' ? bgColor : bgTint,
        backgroundImage: this.editingStyle?.background_image_url
          ? `linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url(${this.editingStyle.background_image_url})`
          : 'none',
        backgroundSize: 'cover',
        backgroundPosition: 'center',
        '--ks-border-color-rgb': borderRgb,
      };
    },
  },
  async mounted() {
    await this.fetch();
  },
  methods: {
    async fetch() {
      this.loading = true;
      try {
        const res = await axios.get('/api/admin/kingdom-styles');
        this.styles = res.data;
      } catch {
        // ignore
      }
      this.loading = false;
    },
    swatchStyle(style) {
      const cv = style.css_vars || {};
      const borderColor = cv.border_color || 'transparent';
      const glow = borderColor !== 'transparent'
        ? `0 0 8px ${borderColor}66`
        : 'none';
      return {
        border: `2px solid ${borderColor}`,
        boxShadow: glow,
        backgroundColor: cv.bg_tint || 'transparent',
      };
    },
    openEdit(style) {
      this.editingStyle = { ...style };
      const cssVars = { ...(style.css_vars || {}) };
      if (!cssVars.border_anim) cssVars.border_anim = 'none';
      this.editForm = {
        name: style.name || '',
        description: style.description || '',
        is_active: style.is_active ?? true,
        is_default_unlocked: style.is_default_unlocked ?? false,
        css_vars: cssVars,
      };
      this.editError = '';
      this.uploadError = '';
    },
    async saveEdit() {
      this.editSaving = true;
      this.editError = '';
      try {
        // Build the css_vars with generated glow string
        const cv = { ...this.editForm.css_vars };
        if (cv.border_color && cv.border_color !== 'transparent') {
          // Auto-generate border_glow from border_color
          const hex = cv.border_color;
          const r = parseInt(hex.slice(1, 3), 16);
          const g = parseInt(hex.slice(3, 5), 16);
          const b = parseInt(hex.slice(5, 7), 16);
          cv.border_glow = `0 0 12px rgba(${r}, ${g}, ${b}, 0.4)`;
          cv.border_color_rgb = `${r}, ${g}, ${b}`;
        } else {
          cv.border_glow = 'none';
          cv.border_color_rgb = '0, 0, 0';
        }

        const payload = {
          name: this.editForm.name,
          description: this.editForm.description,
          is_active: this.editForm.is_active,
          is_default_unlocked: this.editForm.is_default_unlocked,
          css_vars: cv,
        };

        const res = await axios.put(`/api/admin/kingdom-styles/${this.editingStyle.id}`, payload);
        const idx = this.styles.findIndex(s => s.id === this.editingStyle.id);
        if (idx !== -1) {
          this.styles[idx] = { ...this.styles[idx], ...res.data };
        }
        this.editingStyle = null;
      } catch (e) {
        this.editError = e.response?.data?.message || 'Save failed';
      }
      this.editSaving = false;
    },
    async removeBgImage() {
      this.removingImage = true;
      this.uploadError = '';
      try {
        const res = await axios.delete(`/api/admin/kingdom-styles/${this.editingStyle.id}/image`);
        this.editingStyle = { ...this.editingStyle, ...res.data };
        const idx = this.styles.findIndex(s => s.id === this.editingStyle.id);
        if (idx !== -1) {
          this.styles[idx] = { ...this.styles[idx], ...res.data };
        }
      } catch (e) {
        this.uploadError = e.response?.data?.error || 'Remove failed';
      }
      this.removingImage = false;
    },
    async uploadBgImage(event) {
      const file = event.target.files?.[0];
      if (!file) return;
      this.uploadingImage = true;
      this.uploadError = '';
      try {
        const formData = new FormData();
        formData.append('image', file);
        const res = await axios.post(`/api/admin/kingdom-styles/${this.editingStyle.id}/image`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        // Update the editing style with new image URL
        this.editingStyle = { ...this.editingStyle, ...res.data };
        // Also update in the list
        const idx = this.styles.findIndex(s => s.id === this.editingStyle.id);
        if (idx !== -1) {
          this.styles[idx] = { ...this.styles[idx], ...res.data };
        }
      } catch (e) {
        this.uploadError = e.response?.data?.error || 'Upload failed';
      }
      this.uploadingImage = false;
      // Reset the file input
      if (this.$refs.bgImageInput) {
        this.$refs.bgImageInput.value = '';
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

.loading {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}

.empty-state {
  text-align: center;
  color: var(--text-secondary);
  padding: 60px 20px;
  font-size: 0.95rem;
}

.style-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}

.style-card {
  background: var(--bg-secondary);
  border: 1px solid rgba(138, 106, 46, 0.25);
  border-radius: 8px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  transition: border-color 0.2s;
}

.style-card:hover {
  border-color: var(--accent-gold);
}

.style-info {
  flex: 1;
}

.style-name {
  color: var(--text-bright);
  font-weight: 600;
  font-size: 1rem;
  margin-bottom: 2px;
}

.style-slug {
  color: var(--text-secondary);
  font-size: 0.75rem;
  opacity: 0.7;
  margin-bottom: 4px;
}

.style-desc {
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-style: italic;
  margin-bottom: 8px;
}

.style-badges {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}

.badge {
  font-size: 0.65rem;
  padding: 1px 6px;
  border-radius: 3px;
  font-weight: 600;
}

.badge-active {
  background: rgba(39, 174, 96, 0.15);
  border: 1px solid rgba(39, 174, 96, 0.4);
  color: #5ab87a;
}

.badge-inactive {
  background: rgba(160, 48, 32, 0.15);
  border: 1px solid rgba(160, 48, 32, 0.3);
  color: #d05040;
}

.badge-default {
  background: rgba(67, 160, 212, 0.15);
  border: 1px solid rgba(67, 160, 212, 0.3);
  color: #60b8e0;
}

/* Color swatch preview on card */
.style-swatch {
  border-radius: 4px;
  padding: 6px;
  margin-bottom: 8px;
  display: flex;
  gap: 4px;
}

.swatch-bar {
  height: 6px;
  border-radius: 3px;
  flex: 1;
}

.btn-edit {
  padding: 5px 12px;
  font-size: 0.8rem;
  background: rgba(212, 168, 67, 0.12);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--accent-gold);
  border-radius: 4px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: all 0.2s;
  align-self: flex-start;
}

.btn-edit:hover {
  background: rgba(212, 168, 67, 0.25);
  border-color: var(--accent-gold);
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  z-index: 200;
  overflow-y: auto;
  padding: 40px 20px;
}

.modal-content {
  background: var(--bg-secondary);
  border: 2px solid var(--border-gold);
  border-radius: 10px;
  padding: 28px;
  width: 90%;
  max-width: 420px;
}

.modal-wide {
  max-width: 560px;
}

.modal-content h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin-bottom: 18px;
  font-size: 1.1rem;
}

.section-label {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  font-size: 0.9rem;
  margin: 18px 0 10px;
  border-bottom: 1px solid rgba(138, 106, 46, 0.2);
  padding-bottom: 4px;
}

.form-group {
  margin-bottom: 14px;
}

.form-group > label:not(.toggle-label) {
  display: block;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-bottom: 5px;
}

.form-input {
  width: 100%;
  background: var(--bg-primary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright);
  padding: 8px 12px;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
}

.form-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.form-row {
  display: flex;
  gap: 20px;
}

.toggle-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: var(--text-bright);
  font-size: 0.95rem;
}

.toggle-label input[type="checkbox"] {
  margin-top: 0;
}

/* Color pickers grid */
.color-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
}

.color-item label {
  display: block;
  color: var(--text-secondary);
  font-size: 0.75rem;
  margin-bottom: 4px;
}

.color-input-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.color-input-row input[type="color"] {
  width: 32px;
  height: 32px;
  border: 1px solid rgba(138, 106, 46, 0.3);
  border-radius: 4px;
  cursor: pointer;
  background: none;
  padding: 1px;
}

.color-hex {
  width: 90px !important;
  font-size: 0.8rem !important;
  padding: 4px 6px !important;
  font-family: monospace !important;
}

/* Background image section */
.bg-image-section {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.bg-image-preview {
  width: 80px;
  height: 60px;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid rgba(138, 106, 46, 0.3);
}

.bg-image-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.bg-image-placeholder {
  width: 80px;
  height: 60px;
  border-radius: 4px;
  border: 1px dashed rgba(138, 106, 46, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  color: var(--text-secondary);
  text-align: center;
}

.btn-upload {
  padding: 5px 14px;
  font-size: 0.8rem;
  background: rgba(67, 160, 212, 0.12);
  border: 1px solid rgba(67, 160, 212, 0.3);
  color: #60b8e0;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-upload:hover:not(:disabled) {
  background: rgba(67, 160, 212, 0.25);
}

.btn-upload:disabled {
  opacity: 0.5;
  cursor: default;
}

.btn-remove-img {
  padding: 5px 14px;
  font-size: 0.8rem;
  background: rgba(160, 48, 32, 0.12);
  border: 1px solid rgba(160, 48, 32, 0.3);
  color: #d05040;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-remove-img:hover:not(:disabled) {
  background: rgba(160, 48, 32, 0.25);
}

.btn-remove-img:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Live preview */
.live-preview {
  border-radius: 6px;
  padding: 10px;
  transition: all 0.3s;
}

.preview-name {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  text-align: center;
  margin-bottom: 6px;
}

.preview-bars {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.preview-bar {
  height: 6px;
  border-radius: 3px;
}

.preview-total {
  text-align: center;
  font-weight: 700;
  font-size: 0.9rem;
  margin-top: 6px;
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
</style>
