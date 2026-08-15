import { reactive } from "vue";
import axios from "axios";

export interface LeagueResultTier {
  tier: number;
  name: string;
  color: string;
}

export interface LeagueResultStatus {
  id: number;
  week_start: string | undefined;
  rank: number;
  total: number;
  coins_earned: number;
  promoted: boolean;
  demoted: boolean;
  tier_before: LeagueResultTier;
  tier_after: LeagueResultTier;
}

interface LeagueResultResponse {
  result: LeagueResultStatus | null;
}

interface LeagueResultState {
  status: LeagueResultStatus | undefined;
  loaded: boolean;
}

// Module-scoped shared state: App.vue owns the overview modal shown once on login
// after the weekly league reset.
const state = reactive<LeagueResultState>({
  status: undefined,
  loaded: false,
});

async function fetchStatus(): Promise<void> {
  try {
    const response = await axios.get<LeagueResultResponse>("/api/league/last-result");
    // The API returns null when nothing is pending; keep null out of our own state.
    state.status = response.data.result ?? undefined;
  } catch {
    // Non-critical — no overview is shown if the status can't be fetched.
  } finally {
    state.loaded = true;
  }
}

// Dismiss locally right away, then tell the server so it isn't shown again.
async function markSeen(): Promise<void> {
  const wasPending = state.status !== undefined;
  state.status = undefined;

  if (!wasPending) {
    return;
  }

  try {
    await axios.post("/api/league/last-result/seen");
  } catch {
    // Non-critical — the server will simply re-serve it on the next load if this failed.
  }
}

export function useLeagueResult() {
  return { state, fetchStatus, markSeen };
}
