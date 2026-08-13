import { reactive } from 'vue';

type ToastType = 'info' | 'success' | 'error';

interface Toast {
  id: number;
  message: string;
  type: ToastType;
}

interface ToastState {
  toasts: Toast[];
  nextId: number;
}

const state = reactive<ToastState>({
  toasts: [],
  nextId: 1,
});

export function useToast() {
  function dismiss(id: number): void {
    const index = state.toasts.findIndex(toast => toast.id === id);
    if (index !== -1) state.toasts.splice(index, 1);
  }

  function show(message: string, type: ToastType = 'info', duration = 4000): void {
    const id = state.nextId++;
    state.toasts.push({ id, message, type });
    if (duration > 0) {
      setTimeout(() => dismiss(id), duration);
    }
  }

  function success(message: string, duration = 4000): void {
    show(message, 'success', duration);
  }

  function error(message: string, duration = 5000): void {
    show(message, 'error', duration);
  }

  return { state, show, success, error, dismiss };
}
