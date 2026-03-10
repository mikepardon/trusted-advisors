<template>
  <span v-if="resolved.type === 'emoji'" class="app-icon app-icon--emoji" :class="sizeClass">{{ resolved.value }}</span>
  <img v-else-if="resolved.type === 'image'" :src="resolved.value" alt="" class="app-icon app-icon--image" :class="sizeClass" />
</template>

<script>
import { useIcons } from '../stores/icons';

export default {
  name: 'AppIcon',
  props: {
    iconKey: { type: String, default: null },
    type: { type: String, default: null },
    value: { type: String, default: null },
    size: { type: String, default: 'md' },
  },
  setup(props) {
    const { getIcon } = useIcons();
    return { getIcon };
  },
  computed: {
    resolved() {
      if (this.type && this.value) {
        return { type: this.type, value: this.value };
      }
      if (this.iconKey) {
        return this.getIcon(this.iconKey);
      }
      return { type: 'emoji', value: '?' };
    },
    sizeClass() {
      return `app-icon--${this.size}`;
    },
  },
};
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
