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
            <button v-if="hasMorePages" class="btn-load-more" :disabled="loadingMore" @click="loadMore">
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
              <input v-model="editTagsString" class="detail-input" placeholder="e.g. background, character" />

              <button class="btn-save" :disabled="saving" @click="saveChanges">
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

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from "vue";
import axios from "axios";
import { markRaw } from "vue";
import Uppy from "@uppy/core";
import Dashboard from "@uppy/dashboard";
import XHRUpload from "@uppy/xhr-upload";
import "@uppy/core/css/style.min.css";
import "@uppy/dashboard/css/style.min.css";
import { useToast } from "../../stores/toast";

interface MediaItem {
  id: number;
  url: string;
  display_name: string;
  original_filename: string;
  created_at: string;
  formatted_size: string;
  tags?: string[];
}

interface MediaListResponse {
  data: MediaItem[];
  current_page: number;
  last_page: number;
  total: number;
}

interface MediaListParameters {
  page: number;
  search?: string;
  tag?: string;
}

const { visible = false, selectMode = true, inline = false } = defineProps<{
  visible?: boolean;
  selectMode?: boolean;
  inline?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  select: [item: MediaItem];
}>();

const toast = useToast();

const activeTab = ref<"upload" | "library">("library");
const items = ref<MediaItem[]>([]);
const totalItems = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const searchQuery = ref("");
const filterTag = ref("");
const availableTags = ref<string[]>([]);
const selectedItem = ref<MediaItem | undefined>(undefined);
const editName = ref("");
const editTagsString = ref("");
const saving = ref(false);
const loadingMore = ref(false);
const uppy = ref<Uppy | undefined>(undefined);
const searchTimeout = ref<ReturnType<typeof setTimeout> | undefined>(undefined);
const uppyDashboard = useTemplateRef<HTMLDivElement>("uppyDashboard");

const hasMorePages = computed<boolean>(() => currentPage.value < lastPage.value);

function initUppy(): void {
  if (uppy.value) {
    return;
  }

  const xsrfToken = document.cookie
    .split("; ")
    .find((row) => row.startsWith("XSRF-TOKEN="))
    ?.split("=", 2)[1];

  const instance = markRaw(new Uppy({
    restrictions: {
      maxFileSize: 10 * 1024 * 1024,
      allowedFileTypes: ["image/*"],
    },
  }));

  instance.use(Dashboard, {
    inline: true,
    target: uppyDashboard.value ?? undefined,
    theme: "dark",
    width: "100%",
    height: 350,
    proudlyDisplayPoweredByUppy: false,
  });

  instance.use(XHRUpload, {
    endpoint: "/api/admin/media-library",
    fieldName: "file",
    headers: {
      "X-XSRF-TOKEN": xsrfToken ? decodeURIComponent(xsrfToken) : "",
    },
  });

  instance.on("complete", (result) => {
    const uploaded = result.successful?.length ?? 0;
    if (uploaded > 0) {
      toast.success(`Uploaded ${uploaded} file(s)`);
      activeTab.value = "library";
      loadItems(1);
      loadTags();
      instance.cancelAll();
    }
  });

  uppy.value = instance;
}

function destroyUppy(): void {
  if (!uppy.value) {
  	return;
  }

  uppy.value.destroy();
  uppy.value = undefined;
}

async function loadItems(page = 1): Promise<void> {
  try {
    const parameters: MediaListParameters = { page };
    if (searchQuery.value) {
      parameters.search = searchQuery.value;
    }
    if (filterTag.value) {
      parameters.tag = filterTag.value;
    }

    const response = await axios.get<MediaListResponse>("/api/admin/media-library", { params: parameters });

    if (page === 1) {
      items.value = response.data.data;
    } else {
      items.value.push(...response.data.data);
    }

    currentPage.value = response.data.current_page;
    lastPage.value = response.data.last_page;
    totalItems.value = response.data.total;
  } catch {
    toast.error("Failed to load media library");
  }
}

async function loadTags(): Promise<void> {
  try {
    const response = await axios.get<string[]>("/api/admin/media-library-tags");
    availableTags.value = response.data;
  } catch {
    // ignore
  }
}

async function loadMore(): Promise<void> {
  if (loadingMore.value || !hasMorePages.value) {
    return;
  }
  loadingMore.value = true;
  try {
    await loadItems(currentPage.value + 1);
  } finally {
    loadingMore.value = false;
  }
}

function debouncedSearch(): void {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }
  searchTimeout.value = setTimeout(() => {
    selectedItem.value = undefined;
    loadItems(1);
  }, 300);
}

async function saveChanges(): Promise<void> {
  const current = selectedItem.value;
  if (!current) {
    return;
  }
  saving.value = true;
  try {
    const tags = editTagsString.value
      .split(",")
      .map((tag) => tag.trim())
      .filter(Boolean);

    const response = await axios.put<MediaItem>(`/api/admin/media-library/${current.id}`, {
      display_name: editName.value,
      tags,
    });

    const index = items.value.findIndex((item) => item.id === current.id);
    if (index !== -1) {
      items.value[index] = response.data;
    }
    selectedItem.value = response.data;
    loadTags();
    toast.success("Saved");
  } catch {
    toast.error("Failed to save");
  }
  saving.value = false;
}

async function confirmDelete(): Promise<void> {
  const current = selectedItem.value;
  if (!current) {
    return;
  }
  if (!confirm("Delete this image permanently?")) {
    return;
  }
  try {
    await axios.delete(`/api/admin/media-library/${current.id}`);
    items.value = items.value.filter((item) => item.id !== current.id);
    totalItems.value--;
    selectedItem.value = undefined;
    loadTags();
    toast.success("Deleted");
  } catch {
    toast.error("Failed to delete");
  }
}

function emitSelect(): void {
  if (selectedItem.value) {
    emit("select", selectedItem.value);
  }
}

function formatDate(dateString: string): string {
  if (!dateString) {
    return "";
  }
  return new Date(dateString).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

watch(() => visible, (value) => {
  if (value) {
    loadItems(1);
    loadTags();
    nextTick(() => initUppy());
  } else {
    destroyUppy();
  }
});

watch(selectedItem, (item) => {
  if (!item) {
  	return;
  }

  editName.value = item.display_name;
  editTagsString.value = (item.tags || []).join(", ");
});

onMounted(() => {
  if (!visible) {
  	return;
  }

  loadItems(1);
  loadTags();
  nextTick(() => initUppy());
});

onBeforeUnmount(() => {
  destroyUppy();
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }
});
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
  border: 2px solid var(--border-gold);
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
  border: 2px solid var(--border-gold);
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
  border: 2px solid var(--border-gold);
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
  border: 2px solid var(--border-gold);
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
  border: 2px solid var(--border-gold);
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
  border: 2px solid var(--border-gold);
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
  border: 2px solid var(--border-gold);
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
