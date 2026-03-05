import { reactive } from 'vue';

const state = reactive({
  toasts: [],
  nextId: 1,
});

export function useToast() {
  function show(message, type = 'info', duration = 4000) {
    const id = state.nextId++;
    state.toasts.push({ id, message, type });
    if (duration > 0) {
      setTimeout(() => dismiss(id), duration);
    }
  }

  function success(message, duration = 4000) {
    show(message, 'success', duration);
  }

  function error(message, duration = 5000) {
    show(message, 'error', duration);
  }

  function dismiss(id) {
    const idx = state.toasts.findIndex(t => t.id === id);
    if (idx !== -1) state.toasts.splice(idx, 1);
  }

  return { state, show, success, error, dismiss };
}
