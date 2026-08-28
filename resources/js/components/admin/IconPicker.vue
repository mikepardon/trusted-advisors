<template>
  <div class="icon-picker">
    <div class="icon-preview">
      <AppIcon
        v-if="iconType === 'image' && modelValue"
        type="image"
        :value="modelValue"
        size="lg"
      />
      <span v-else class="preview-emoji">{{ modelValue || '?' }}</span>
    </div>

    <div class="picker-controls">
      <div class="type-toggle">
        <button
          :class="['toggle-btn', { active: iconType === 'emoji' }]"
          @click="$emit('update:iconType', 'emoji')"
        >Emoji</button>
        <button
          :class="['toggle-btn', { active: iconType === 'image' }]"
          @click="$emit('update:iconType', 'image')"
        >Image</button>
      </div>

      <div v-if="iconType === 'emoji'" class="emoji-section">
        <input
          :value="modelValue"
          class="emoji-input"
          placeholder="Paste emoji..."
          @input="$emit('update:modelValue', $event.target.value)"
        />
        <div class="emoji-grid">
          <button
            v-for="e in commonEmojis"
            :key="e"
            class="emoji-btn"
            :class="{ selected: modelValue === e }"
            @click="$emit('update:modelValue', e)"
          >{{ e }}</button>
        </div>
      </div>

      <div v-else class="image-section">
        <div v-if="modelValue" class="image-preview-row">
          <img :src="modelValue" alt="" class="image-thumb" />
          <button class="btn-change" @click="showMedia = true">Change</button>
        </div>
        <button v-else class="btn-choose" @click="showMedia = true">Choose from Media Library</button>
      </div>
    </div>

    <MediaLibraryModal
      v-if="showMedia"
      :visible="showMedia"
      :select-mode="true"
      @select="onMediaSelect"
      @close="showMedia = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import AppIcon from "../AppIcon.vue";
import MediaLibraryModal from "./MediaLibraryModal.vue";

const { modelValue = "", iconType = "emoji" } = defineProps<{
  modelValue?: string;
  iconType?: string;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: string];
  "update:iconType": [value: string];
}>();

interface MediaItem {
  url: string;
}

const showMedia = ref(false);
const commonEmojis = [
  "\u{1F6D2}", "\u{1F0CF}", "\u{2694}", "\u{1F465}", "\u{1F464}",
  "\u{1FA99}", "\u{1F3DB}", "\u{1F6E1}", "\u{1F54C}", "\u{1F33E}",
  "\u{1F3AD}", "\u{1F3C6}", "\u{1F9E9}",
  "\u{1F451}", "\u{1F525}", "\u{26A1}", "\u{2B50}", "\u{1F48E}",
  "\u{1F4DC}", "\u{2B06}", "\u{1F4D6}", "\u{1F9D9}", "\u{1F3F0}",
  "\u{1F4C5}", "\u{1F91D}", "\u{1F30D}", "\u{1F4AA}", "\u{2728}",
];

function onMediaSelect(item: MediaItem): void {
  emit("update:modelValue", item.url);
  emit("update:iconType", "image");
  showMedia.value = false;
}
</script>

<style scoped>
.icon-picker {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.icon-preview {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #faf7ef;
  border: 1px solid var(--admin-line-strong, #d2c7ac);
  border-radius: 8px;
  flex-shrink: 0;
}
.preview-emoji {
  font-size: 1.8rem;
  line-height: 1;
}
.picker-controls {
  flex: 1;
  min-width: 0;
}
.type-toggle {
  display: flex;
  gap: 4px;
  margin-bottom: 8px;
}
.toggle-btn {
  padding: 4px 12px !important;
  font-size: 0.75rem !important;
  border-radius: 4px !important;
  background: #ffffff !important;
  border: 1px solid var(--admin-line-strong, #d2c7ac) !important;
  color: var(--admin-mute, #7a7365) !important;
  cursor: pointer;
}
.toggle-btn.active {
  background: rgba(168, 121, 44, 0.12) !important;
  border-color: var(--admin-brass, #a8792c) !important;
  color: var(--admin-brass, #a8792c) !important;
}
.emoji-input {
  width: 100%;
  padding: 6px 10px;
  background: #ffffff;
  border: 1px solid var(--admin-line-strong, #d2c7ac);
  border-radius: 4px;
  color: var(--admin-ink, #23201a);
  font-size: 1rem;
  margin-bottom: 8px;
}
.emoji-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.emoji-btn {
  width: 32px !important;
  height: 32px !important;
  padding: 0 !important;
  display: flex !important;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem !important;
  background: #faf7ef !important;
  border: 1px solid transparent !important;
  border-radius: 4px !important;
  cursor: pointer;
}
.emoji-btn:hover {
  border-color: var(--admin-brass, #a8792c) !important;
}
.emoji-btn.selected {
  border-color: var(--admin-brass, #a8792c) !important;
  background: rgba(168, 121, 44, 0.12) !important;
}
.image-section .image-preview-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.image-thumb {
  width: 40px;
  height: 40px;
  object-fit: contain;
  border-radius: 4px;
  border: 1px solid var(--admin-line-strong, #d2c7ac);
}
.btn-choose,
.btn-change {
  font-size: 0.8rem !important;
  padding: 6px 14px !important;
}
</style>
