<template>
  <transition name="hint-pop">
    <div v-if="show" class="hint-bubble" @click="dismiss">
      <span class="hint-icon">&#128161;</span>
      <span class="hint-text"><slot /></span>
      <button class="hint-dismiss">&times;</button>
    </div>
  </transition>
</template>

<script>
import { shouldShowHint, markHintSeen } from '../hints';

export default {
  name: 'HintBubble',
  props: {
    hintId: { type: String, required: true },
  },
  data() {
    return {
      show: false,
    };
  },
  mounted() {
    if (shouldShowHint(this.hintId)) {
      // Small delay so it appears after page renders
      setTimeout(() => { this.show = true; }, 600);
    }
  },
  methods: {
    dismiss() {
      this.show = false;
      markHintSeen(this.hintId);
    },
  },
};
</script>

<style scoped>
.hint-bubble {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  background: linear-gradient(135deg, rgba(212, 168, 67, 0.15), rgba(212, 168, 67, 0.06));
  border: 1px solid rgba(212, 168, 67, 0.35);
  border-radius: 8px;
  padding: 10px 32px 10px 14px;
  margin: 8px 0;
  cursor: pointer;
  animation: hintGlow 2s ease-in-out infinite alternate;
}

@keyframes hintGlow {
  from { box-shadow: 0 0 4px rgba(212, 168, 67, 0.1); }
  to { box-shadow: 0 0 12px rgba(212, 168, 67, 0.2); }
}

.hint-icon {
  font-size: 1rem;
  flex-shrink: 0;
  line-height: 1.4;
}

.hint-text {
  font-size: 0.85rem;
  color: var(--text-primary, #e8d5b7);
  line-height: 1.4;
  flex: 1;
}

.hint-dismiss {
  position: absolute;
  top: 0;
  right: 0;
  background: none !important;
  border: none !important;
  border-radius: 0;
  box-shadow: none !important;
  color: var(--text-secondary, #a08a6a);
  font-size: 1.4rem;
  cursor: pointer;
  padding: 4px 8px;
  line-height: 1;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.hint-dismiss:hover {
  color: var(--accent-gold, #d4a843);
  transform: none;
  box-shadow: none;
  background: none;
}

.hint-pop-enter-active {
  transition: opacity 0.35s ease, transform 0.35s ease;
}

.hint-pop-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.hint-pop-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}

.hint-pop-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
