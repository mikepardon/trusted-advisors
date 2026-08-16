import { reactive } from "vue";
import axios from "axios";

export interface DailyRewardEntry {
  type: "coins" | "xp";
  amount: number;
}

export interface DailyRewardStatus {
  available: boolean;
  streak: number;
  day: number;
  ladder: DailyRewardEntry[];
  reward: DailyRewardEntry;
}

interface DailyRewardState {
  status: DailyRewardStatus | undefined;
  modalOpen: boolean;
  loaded: boolean;
}

// Module-scoped shared state: the home hero surfaces the claimable reward while
// App.vue owns the claim modal — both read the same status.
const state = reactive<DailyRewardState>({
  status: undefined,
  modalOpen: false,
  loaded: false,
});

async function fetchStatus(): Promise<void> {
  try {
    const response = await axios.get<DailyRewardStatus>("/api/daily-reward");
    state.status = response.data;
  } catch {
    // Non-critical — the hero panel simply hides if status can't be fetched.
  } finally {
    state.loaded = true;
  }
}

function openModal(): void {
  state.modalOpen = true;
}

function closeModal(): void {
  state.modalOpen = false;
}

// Reflect a successful claim locally so the hero panel drops to its rested
// state without a refetch; the server remains the source of truth on next load.
function markClaimed(streak: number): void {
  if (!state.status) {
  	return;
  }

  state.status.available = false;
  state.status.streak = streak;
}

export function useDailyReward() {
  return { state, fetchStatus, openModal, closeModal, markClaimed };
}
