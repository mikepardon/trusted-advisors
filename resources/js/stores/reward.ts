import { reactive } from "vue";

export type RewardTier = "common" | "rare" | "epic" | "legendary";

export interface Reward {
  id: number;
  amount: number;
  tier: RewardTier;
  title: string;
  icon: string;
}

interface RewardState {
  current: Reward | undefined;
  queue: Reward[];
}

// Module-scoped shared state so any reward moment (chest, daily reward, tier claim)
// can trigger the celebratory reveal; rewards queue and play one at a time.
const state = reactive<RewardState>({
  current: undefined,
  queue: [],
});

const sequence = { next: 1 };

// Bigger payouts feel rarer, so lift the tier (and the fanfare) with the amount.
function tierForAmount(amount: number): RewardTier {
  if (amount >= 55) {
    return "legendary";
  }
  if (amount >= 45) {
    return "epic";
  }
  if (amount >= 33) {
    return "rare";
  }
  return "common";
}

function reveal(reward: {
  amount: number;
  tier?: RewardTier;
  title?: string;
  icon?: string;
}): void {
  const item: Reward = {
    id: sequence.next++,
    amount: reward.amount,
    tier: reward.tier ?? tierForAmount(reward.amount),
    title: reward.title ?? "Reward",
    icon: reward.icon ?? "🪙",
  };

  if (state.current === undefined) {
    state.current = item;
  } else {
    state.queue.push(item);
  }
}

// Collect the current reward and advance to the next queued one (if any).
function dismiss(): void {
  state.current = state.queue.shift() ?? undefined;
}

export function useReward() {
  return { state, reveal, dismiss };
}
