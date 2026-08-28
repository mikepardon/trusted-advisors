<template>
  <!-- Single dice theme rendered in the in-game dark card style, so admins see it
       roughly as players do in their collection. Mirrors the visual approach of
       ItemPreviewCard.vue (parchment card, Cinzel title, gold accents). -->
  <div class="theme-preview-card">
    <div class="theme-image-frame">
      <img
        v-if="theme.preview_image"
        :src="theme.preview_image"
        :alt="theme.name"
        class="theme-preview-image"
      />
      <div v-else class="theme-preview-placeholder">No preview image</div>
    </div>

    <h3 class="theme-preview-title">{{ theme.name || 'Unnamed Theme' }}</h3>

    <div class="theme-badge-row">
      <span v-if="theme.is_active" class="theme-badge badge-active">Active</span>
      <span v-else class="theme-badge badge-inactive">Inactive</span>
      <span v-if="theme.is_default_unlocked" class="theme-badge badge-default">Default</span>
    </div>

    <div class="theme-divider"><span class="theme-divider-ornament">&#9830;</span></div>

    <p v-if="theme.description" class="theme-preview-desc">{{ theme.description }}</p>
    <p v-else class="theme-preview-desc theme-preview-desc-empty">No description</p>

    <div class="theme-slug-chip">{{ theme.slug }}</div>
  </div>
</template>

<script setup lang="ts">
interface PreviewDiceTheme {
  slug: string;
  name: string;
  description: string | undefined;
  preview_image: string | undefined;
  is_active: boolean;
  is_default_unlocked: boolean;
}

const { theme } = defineProps<{
  theme: PreviewDiceTheme;
}>();
</script>

<style scoped>
.theme-preview-card {
  background: linear-gradient(180deg, #3a2a1a, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #6b5b3a);
  border-radius: 12px;
  padding: 20px;
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow:
    0 4px 20px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(212, 168, 67, 0.08);
}

.theme-image-frame {
  width: 100%;
  max-width: 220px;
  aspect-ratio: 1;
  background: rgba(0, 0, 0, 0.4);
  border: 1px solid rgba(138, 106, 46, 0.4);
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
}

.theme-preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.theme-preview-placeholder {
  color: var(--text-secondary, #a09080);
  font-size: 0.85rem;
  opacity: 0.6;
}

.theme-preview-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #c9a84c);
  font-size: 1.15rem;
  text-align: center;
  margin-bottom: 8px;
  line-height: 1.3;
}

.theme-badge-row {
  display: flex;
  gap: 6px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.theme-badge {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  border-radius: 3px;
  padding: 1px 8px;
  font-weight: 600;
}

.badge-active {
  color: #67c23a;
  border: 1px solid #67c23a;
}

.badge-inactive {
  color: var(--text-secondary, #a09080);
  border: 1px solid var(--text-secondary, #a09080);
}

.badge-default {
  color: #60b8e0;
  border: 1px solid #60b8e0;
}

.theme-divider {
  position: relative;
  width: 80%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border-gold, #6b5b3a), transparent);
  margin: 12px 0;
}

.theme-divider-ornament {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #2a1f14;
  color: var(--accent-gold, #c9a84c);
  padding: 0 8px;
  font-size: 0.7rem;
}

.theme-preview-desc {
  color: var(--text-primary, #e8d5b0);
  font-style: italic;
  font-size: 0.88rem;
  line-height: 1.5;
  text-align: center;
  flex: 1;
}

.theme-preview-desc-empty {
  opacity: 0.5;
}

.theme-slug-chip {
  margin-top: 12px;
  padding: 3px 12px;
  border-radius: 4px;
  font-size: 0.72rem;
  letter-spacing: 0.5px;
  background: rgba(212, 168, 67, 0.12);
  color: var(--text-secondary, #a09080);
  font-family: 'Courier New', monospace;
  word-break: break-all;
  text-align: center;
}
</style>
