<template>
  <div v-if="visible" :class="['media-library-wrapper', { inline }]">
    <div v-if="!inline" class="modal-backdrop" @click="$emit('close')"></div>
    <div class="media-library-content" :class="{ 'inline-content': inline }">
      <div class="ml-header">
        <h3>Media Library</h3>
        <button v-if="!inline" class="btn-close" @click="$emit('close')">&times;</button>
      </div>

      <!-- Tab bar -->
      <div class="tab-bar">
        <button :class="['tab-btn', { active: activeTab === 'upload' }]" @click="activeTab = 'upload'">Upload Files</button>
        <button :class="['tab-btn', { active: activeTab === 'library' }]" @click="activeTab = 'library'">Media Library</button>
      </div>

      <!-- Upload tab -->
      <div v-show="activeTab === 'upload'" class="tab-content upload-tab">
        <div ref="uppyDashboard"></div>
      </div>

      <!-- Library tab -->
      <div v-show="activeTab === 'library'" class="tab-content library-tab">
        <div class="library-layout">
          <div class="library-main">
            <!-- Filters -->
            <div class="filters-row">
              <input
                v-model="searchQuery"
                type="text"
                class="filter-input"
                placeholder="Search by name..."
                @input="debouncedSearch"
              />
              <select v-model="filterTag" class="filter-select" @change="loadItems(1)">
                <option value="">All tags</option>
                <option v-for="tag in availableTags" :key="tag" :value="tag">{{ tag }}</option>
              </select>
            </div>

            <!-- Image grid -->
            <div class="image-grid">
              <div
                v-for="item in items"
                :key="item.id"
                :class="['grid-item', { selected: selectedItem?.id === item.id }]"
                @click="selectedItem = item"
              >
                <div class="thumb-wrap">
                  <img :src="item.url" :alt="item.display_name" class="thumb-img" />
                  <div v-if="selectedItem?.id === item.id" class="check-overlay">&#10003;</div>
                </div>
                <span class="item-name">{{ item.display_name }}</span>
              </div>
            </div>

            <!-- Pagination -->
            <div v-if="totalItems > 0" class="pagination-info">
              Showing {{ items.length }} of {{ totalItems }}
            </div>
            <button v-if="hasMorePages" class="btn-load-more" @click="loadMore" :disabled="loadingMore">
              {{ loadingMore ? 'Loading...' : 'Load More' }}
            </button>
          </div>

          <!-- Detail panel -->
          <div v-if="selectedItem" class="detail-panel">
            <img :src="selectedItem.url" :alt="selectedItem.display_name" class="detail-preview" />
            <div class="detail-fields">
              <label class="detail-label">Original filename</label>
              <div class="detail-value readonly">{{ selectedItem.original_filename }}</div>

              <label class="detail-label">Display name</label>
              <input v-model="editName" class="detail-input" />

              <label class="detail-label">Uploaded</label>
              <div class="detail-value readonly">{{ formatDate(selectedItem.created_at) }}</div>

              <label class="detail-label">Size</label>
              <div class="detail-value readonly">{{ selectedItem.formatted_size }}</div>

              <label class="detail-label">Tags (comma-separated)</label>
              <input v-model="editTagsStr" class="detail-input" placeholder="e.g. background, character" />

              <button class="btn-save" @click="saveChanges" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
              <button class="btn-delete" @click="confirmDelete">Delete</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="ml-footer">
        <button v-if="selectMode" class="btn-primary" :disabled="!selectedItem" @click="emitSelect">Select</button>
        <button class="btn-secondary" @click="$emit('close')">{{ selectMode ? 'Cancel' : 'Close' }}</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { markRaw } from 'vue';
import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import XHRUpload from '@uppy/xhr-upload';
import '@uppy/core/css/style.min.css';
import '@uppy/dashboard/css/style.min.css';
import { useToast } from '../../stores/toast';

export default {
  name: 'MediaLibraryModal',
  props: {
    visible: { type: Boolean, default: false },
    selectMode: { type: Boolean, default: true },
    inline: { type: Boolean, default: false },
  },
  emits: ['close', 'select'],
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      activeTab: 'library',
      items: [],
      totalItems: 0,
      currentPage: 1,
      lastPage: 1,
      searchQuery: '',
      filterTag: '',
      availableTags: [],
      selectedItem: null,
      editName: '',
      editTagsStr: '',
      saving: false,
      loadingMore: false,
      uppy: null,
      searchTimeout: null,
    };
  },
  computed: {
    hasMorePages() {
      return this.currentPage < this.lastPage;
    },
  },
  watch: {
    visible(val) {
      if (val) {
        this.loadItems(1);
        this.loadTags();
        this.$nextTick(() => this.initUppy());
      } else {
        this.destroyUppy();
      }
    },
    selectedItem(item) {
      if (item) {
        this.editName = item.display_name;
        this.editTagsStr = (item.tags || []).join(', ');
      }
    },
  },
  mounted() {
    if (this.visible) {
      this.loadItems(1);
      this.loadTags();
      this.$nextTick(() => this.initUppy());
    }
  },
  beforeUnmount() {
    this.destroyUppy();
    if (this.searchTimeout) clearTimeout(this.searchTimeout);
  },
  methods: {
    initUppy() {
      if (this.uppy) return;

      const xsrfToken = document.cookie
        .split('; ')
        .find(row => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

      this.uppy = markRaw(new Uppy({
        restrictions: {
          maxFileSize: 10 * 1024 * 1024,
          allowedFileTypes: ['image/*'],
        },
      }));

      this.uppy.use(Dashboard, {
        inline: true,
        target: this.$refs.uppyDashboard,
        theme: 'dark',
        width: '100%',
        height: 350,
        proudlyDisplayPoweredByUppy: false,
      });

      this.uppy.use(XHRUpload, {
        endpoint: '/api/admin/media-library',
        fieldName: 'file',
        headers: {
          'X-XSRF-TOKEN': xsrfToken ? decodeURIComponent(xsrfToken) : '',
        },
      });

      this.uppy.on('complete', (result) => {
        if (result.successful.length > 0) {
          this.toast.success(`Uploaded ${result.successful.length} file(s)`);
          this.activeTab = 'library';
          this.loadItems(1);
          this.loadTags();
          this.uppy.cancelAll();
        }
      });
    },

    destroyUppy() {
      if (this.uppy) {
        this.uppy.destroy();
        this.uppy = null;
      }
    },

    async loadItems(page = 1) {
      try {
        const params = { page };
        if (this.searchQuery) params.search = this.searchQuery;
        if (this.filterTag) params.tag = this.filterTag;

        const res = await axios.get('/api/admin/media-library', { params });

        if (page === 1) {
          this.items = res.data.data;
        } else {
          this.items.push(...res.data.data);
        }

        this.currentPage = res.data.current_page;
        this.lastPage = res.data.last_page;
        this.totalItems = res.data.total;
      } catch {
        this.toast.error('Failed to load media library');
      }
    },

    async loadTags() {
      try {
        const res = await axios.get('/api/admin/media-library-tags');
        this.availableTags = res.data;
      } catch {
        // ignore
      }
    },

    loadMore() {
      if (this.loadingMore || !this.hasMorePages) return;
      this.loadingMore = true;
      this.loadItems(this.currentPage + 1).finally(() => {
        this.loadingMore = false;
      });
    },

    debouncedSearch() {
      if (this.searchTimeout) clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.selectedItem = null;
        this.loadItems(1);
      }, 300);
    },

    async saveChanges() {
      if (!this.selectedItem) return;
      this.saving = true;
      try {
        const tags = this.editTagsStr
          .split(',')
          .map(t => t.trim())
          .filter(Boolean);

        const res = await axios.put(`/api/admin/media-library/${this.selectedItem.id}`, {
          display_name: this.editName,
          tags,
        });

        // Update item in list
        const idx = this.items.findIndex(i => i.id === this.selectedItem.id);
        if (idx !== -1) this.items[idx] = res.data;
        this.selectedItem = res.data;
        this.loadTags();
        this.toast.success('Saved');
      } catch {
        this.toast.error('Failed to save');
      }
      this.saving = false;
    },

    async confirmDelete() {
      if (!this.selectedItem) return;
      if (!confirm('Delete this image permanently?')) return;
      try {
        await axios.delete(`/api/admin/media-library/${this.selectedItem.id}`);
        this.items = this.items.filter(i => i.id !== this.selectedItem.id);
        this.totalItems--;
        this.selectedItem = null;
        this.loadTags();
        this.toast.success('Deleted');
      } catch {
        this.toast.error('Failed to delete');
      }
    },

    emitSelect() {
      if (this.selectedItem) {
        this.$emit('select', this.selectedItem);
      }
    },

    formatDate(dateStr) {
      if (!dateStr) return '';
      return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    },
  },
};
</script>

<style scoped>
.media-library-wrapper {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.media-library-wrapper.inline {
  position: static;
  z-index: auto;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
}

.media-library-content {
  position: relative;
  z-index: 1;
  background: var(--bg-primary);
  border: 1px solid var(--border-gold);
  border-radius: 10px;
  width: 960px;
  max-width: 95vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.inline-content {
  border: none;
  border-radius: 0;
  max-height: none;
  width: 100%;
}

.ml-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-gold);
}

.ml-header h3 {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold);
  margin: 0;
  font-size: 1.3rem;
}

.btn-close {
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.6rem;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
}

.btn-close:hover {
  color: var(--text-bright);
}

/* Tabs */
.tab-bar {
  display: flex;
  border-bottom: 1px solid var(--border-gold);
}

.tab-btn {
  flex: 1;
  padding: 10px 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-secondary);
  font-family: 'Cinzel', serif;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: var(--text-bright);
}

.tab-btn.active {
  color: var(--accent-gold);
  border-bottom-color: var(--accent-gold);
}

/* Tab content */
.tab-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px;
}

/* Upload tab — Uppy overrides */
.upload-tab :deep(.uppy-Dashboard-inner) {
  background: var(--bg-secondary) !important;
  border-color: var(--border-gold) !important;
}

.upload-tab :deep(.uppy-Dashboard-dropFilesHereHint) {
  color: var(--text-secondary) !important;
  border-color: var(--border-gold) !important;
}

.upload-tab :deep(.uppy-Dashboard-AddFiles-title) {
  color: var(--text-bright) !important;
}

.upload-tab :deep(.uppy-Dashboard-browse) {
  color: var(--accent-gold) !important;
}

.upload-tab :deep(.uppy-StatusBar-actionBtn--upload) {
  background-color: var(--accent-gold) !important;
  color: var(--bg-primary) !important;
}

.upload-tab :deep(.uppy-DashboardContent-addMore) {
  color: var(--accent-gold) !important;
}

/* Library tab */
.library-layout {
  display: flex;
  gap: 20px;
}

.library-main {
  flex: 1;
  min-width: 0;
}

.filters-row {
  display: flex;
  gap: 10px;
  margin-bottom: 14px;
}

.filter-input,
.filter-select {
  padding: 8px 12px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-bright);
  font-size: 0.9rem;
}

.filter-input {
  flex: 1;
}

.filter-input::placeholder {
  color: var(--text-secondary);
}

.filter-select {
  min-width: 140px;
}

.filter-select option {
  background: var(--bg-secondary);
  color: var(--text-bright);
}

/* Image grid */
.image-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.grid-item {
  cursor: pointer;
  border-radius: 6px;
  overflow: hidden;
  border: 2px solid transparent;
  transition: all 0.2s;
  background: var(--bg-secondary);
}

.grid-item:hover {
  border-color: var(--text-secondary);
}

.grid-item.selected {
  border-color: var(--accent-gold);
  box-shadow: 0 0 12px rgba(212, 168, 67, 0.3);
}

.thumb-wrap {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
}

.thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.check-overlay {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 24px;
  height: 24px;
  background: var(--accent-gold);
  color: var(--bg-primary);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: bold;
}

.item-name {
  display: block;
  padding: 6px 8px;
  font-size: 0.78rem;
  color: var(--text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Pagination */
.pagination-info {
  text-align: center;
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin-top: 14px;
}

.btn-load-more {
  display: block;
  margin: 10px auto 0;
  padding: 8px 24px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--accent-gold);
  cursor: pointer;
  font-size: 0.9rem;
}

.btn-load-more:hover:not(:disabled) {
  background: rgba(212, 168, 67, 0.1);
}

.btn-load-more:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Detail panel */
.detail-panel {
  width: 240px;
  flex-shrink: 0;
  background: var(--bg-secondary);
  border: 1px solid var(--border-gold);
  border-radius: 8px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-self: flex-start;
}

.detail-preview {
  width: 100%;
  aspect-ratio: 1;
  object-fit: contain;
  border-radius: 6px;
  background: var(--bg-primary);
  border: 1px solid var(--border-gold);
}

.detail-fields {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.detail-label {
  font-size: 0.72rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 4px;
}

.detail-value.readonly {
  font-size: 0.85rem;
  color: var(--text-primary);
  word-break: break-all;
}

.detail-input {
  padding: 6px 10px;
  background: var(--bg-primary);
  border: 1px solid var(--border-gold);
  border-radius: 4px;
  color: var(--text-bright);
  font-size: 0.85rem;
}

.detail-input::placeholder {
  color: var(--text-secondary);
}

.btn-save {
  margin-top: 8px;
  padding: 8px 14px;
  background: var(--accent-gold);
  color: var(--bg-primary);
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
}

.btn-save:hover:not(:disabled) {
  filter: brightness(1.1);
}

.btn-save:disabled {
  opacity: 0.5;
  cursor: default;
}

.btn-delete {
  background: none;
  border: none;
  color: var(--accent-red);
  font-size: 0.82rem;
  cursor: pointer;
  text-align: center;
  padding: 4px;
}

.btn-delete:hover {
  text-decoration: underline;
}

/* Footer */
.ml-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--border-gold);
}

.btn-primary {
  padding: 8px 20px;
  background: var(--accent-gold);
  color: var(--bg-primary);
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
}

.btn-primary:hover:not(:disabled) {
  filter: brightness(1.1);
}

.btn-primary:disabled {
  opacity: 0.4;
  cursor: default;
}

.btn-secondary {
  padding: 8px 20px;
  background: none;
  border: 1px solid var(--border-gold);
  border-radius: 6px;
  color: var(--text-secondary);
  font-size: 0.9rem;
  cursor: pointer;
}

.btn-secondary:hover {
  color: var(--text-bright);
  border-color: var(--text-secondary);
}

@media (max-width: 768px) {
  .library-layout {
    flex-direction: column;
  }

  .detail-panel {
    width: 100%;
    flex-direction: row;
    flex-wrap: wrap;
  }

  .detail-preview {
    width: 120px;
    height: 120px;
    flex-shrink: 0;
  }

  .detail-fields {
    flex: 1;
    min-width: 0;
  }

  .image-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 480px) {
  .image-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
