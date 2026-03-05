<template>
  <teleport to="body">
    <transition-group name="toast-slide" tag="div" class="toast-container">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="toast-item"
        :class="'toast-' + toast.type"
        @click="dismiss(toast.id)"
      >
        <span class="toast-icon">
          <template v-if="toast.type === 'success'">&#10003;</template>
          <template v-else-if="toast.type === 'error'">&#10007;</template>
          <template v-else>&#9432;</template>
        </span>
        <span class="toast-message">{{ toast.message }}</span>
      </div>
    </transition-group>
  </teleport>
</template>

<script>
import { useToast } from '../stores/toast';

export default {
  name: 'ToastContainer',
  setup() {
    const { state, dismiss } = useToast();
    return { toasts: state.toasts, dismiss };
  },
};
</script>

<style>
.toast-container {
  position: fixed;
  top: 16px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 10000;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  pointer-events: none;
  width: 90%;
  max-width: 420px;
}

.toast-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: 8px;
  font-family: 'Crimson Text', Georgia, serif;
  font-size: 0.95rem;
  cursor: pointer;
  pointer-events: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  width: 100%;
}

.toast-icon {
  font-size: 1.1rem;
  flex-shrink: 0;
  line-height: 1;
}

.toast-message {
  flex: 1;
  line-height: 1.3;
}

.toast-success {
  background: linear-gradient(180deg, #1a2e14, #142410);
  border: 1px solid rgba(74, 138, 58, 0.5);
  color: #8ed070;
}

.toast-error {
  background: linear-gradient(180deg, #2e1a14, #241410);
  border: 1px solid rgba(160, 48, 32, 0.5);
  color: #e08070;
}

.toast-info {
  background: linear-gradient(180deg, #1a1f2e, #141824);
  border: 1px solid rgba(67, 120, 200, 0.5);
  color: #90b8e0;
}

.toast-slide-enter-active {
  transition: all 0.3s ease;
}

.toast-slide-leave-active {
  transition: all 0.3s ease;
}

.toast-slide-enter-from {
  opacity: 0;
  transform: translateY(-20px);
}

.toast-slide-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
