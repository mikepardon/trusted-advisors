import { isNativeApp } from "webtonative";
import { trigger as triggerNativeHaptic } from "webtonative/Haptics";

export type HapticStyle =
  | "light"
  | "medium"
  | "heavy"
  | "success"
  | "warning"
  | "error"
  | "selection";

// Web Vibration API fallback for the browser/dev experience; native builds
// route through the WebToNative haptics bridge for real device feedback.
const vibrationPatterns: Record<HapticStyle, number | number[]> = {
  light: 10,
  medium: 20,
  heavy: 40,
  success: [15, 40, 20],
  warning: [30, 40, 30],
  error: [55, 30, 60],
  selection: 8,
};

/**
 * Fire a short haptic pulse. No-ops silently where haptics are unavailable, so
 * it is always safe to call for UI feedback.
 */
export function haptic(style: HapticStyle = "light"): void {
  if (isNativeApp) {
    triggerNativeHaptic({ effect: style });
    return;
  }

  if (typeof navigator.vibrate === "function") {
    navigator.vibrate(vibrationPatterns[style]);
  }
}
