<template>
  <span v-if="resolved.type === 'emoji'" class="app-icon app-icon--emoji" :class="sizeClass">{{ resolved.value }}</span>
  <img v-else-if="resolved.type === 'image'" :src="resolved.value" alt="" class="app-icon app-icon--image" :class="sizeClass" />
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useIcons } from "../stores/icons";

interface ResolvedIcon {
  type: string;
  value: string;
}

const {
  iconKey = undefined,
  type = undefined,
  value = undefined,
  size = "md",
} = defineProps<{
  iconKey?: string;
  type?: string;
  value?: string;
  size?: string;
}>();

const { getIcon } = useIcons();

const resolved = computed<ResolvedIcon>(() => {
  if (type && value) {
    return { type, value };
  }
  if (iconKey) {
    return getIcon(iconKey);
  }
  return { type: "emoji", value: "?" };
});

const sizeClass = computed(() => `app-icon--${size}`);
</script>

<style scoped>
.app-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: middle;
  line-height: 1;
}
.app-icon--emoji {
  font-family: 'Apple Color Emoji', 'Segoe UI Emoji', 'Noto Color Emoji', sans-serif;
}
.app-icon--image {
  object-fit: contain;
}
.app-icon--sm { font-size: 0.9em; width: 0.9em; height: 0.9em; }
.app-icon--md { font-size: 1em; width: 1.5em; height: 1.5em; }
.app-icon--lg { font-size: 1.5em; width: 1.5em; height: 1.5em; }
</style>
