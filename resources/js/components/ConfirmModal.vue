<template>
  <transition name="confirm-fade">
    <div v-if="visible" class="confirm-overlay" @click.self="cancel">
      <div class="confirm-box">
        <h3 class="confirm-title">{{ title }}</h3>
        <p class="confirm-message">{{ message }}</p>
        <div class="confirm-actions">
          <button class="btn-confirm-cancel" @click="cancel">{{ cancelText }}</button>
          <button class="btn-confirm-ok" :class="dangerous ? 'btn-danger-fill' : 'btn-primary'" @click="confirm">{{ confirmText }}</button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
export default {
  name: 'ConfirmModal',
  props: {
    visible: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmText: { type: String, default: 'Confirm' },
    cancelText: { type: String, default: 'Cancel' },
    dangerous: { type: Boolean, default: false },
  },
  emits: ['confirm', 'cancel'],
  methods: {
    confirm() {
      this.$emit('confirm');
    },
    cancel() {
      this.$emit('cancel');
    },
  },
};
</script>

<style scoped>
.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(6, 4, 2, 0.88);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1200;
}

.confirm-box {
  background: linear-gradient(180deg, #2a1f14, #1a1209);
  border: 2px solid var(--border-gold, #8a6a2e);
  border-radius: 12px;
  padding: 28px 24px 22px;
  max-width: 400px;
  width: 90%;
  text-align: center;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.6), 0 0 20px rgba(212, 168, 67, 0.1);
}

.confirm-title {
  font-family: 'Cinzel', serif;
  color: var(--accent-gold, #d4a843);
  font-size: 1.15rem;
  margin-bottom: 10px;
}

.confirm-message {
  color: var(--text-secondary, #a08a6a);
  font-size: 0.95rem;
  line-height: 1.5;
  margin-bottom: 22px;
}

.confirm-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.btn-confirm-cancel {
  padding: 8px 22px;
  font-size: 0.9rem;
  background: rgba(100, 80, 60, 0.3);
  border: 1px solid rgba(138, 106, 46, 0.3);
  color: var(--text-secondary, #a08a6a);
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  transition: all 0.2s;
}

.btn-confirm-cancel:hover {
  background: rgba(100, 80, 60, 0.5);
  color: var(--text-bright, #f5e6cc);
}

.btn-confirm-ok {
  padding: 8px 22px;
  font-size: 0.9rem;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'Cinzel', serif;
  font-weight: 700;
  transition: all 0.2s;
}

.btn-danger-fill {
  background: linear-gradient(180deg, #8b2020, #6b1818);
  border: 2px solid #c0392b;
  color: #f5e6cc;
}

.btn-danger-fill:hover {
  background: linear-gradient(180deg, #a03020, #8b2020);
  box-shadow: 0 0 15px rgba(192, 57, 43, 0.3);
}

.confirm-fade-enter-active {
  transition: opacity 0.25s ease;
}

.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}

.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}
</style>
