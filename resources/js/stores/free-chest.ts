import { reactive } from "vue";
import axios from "axios";
import { useAuth } from "./auth";

interface FreeChestStatusResponse {
  available: boolean;
  next_available_at: string | null;
  reward_min: number;
  reward_max: number;
}

interface FreeChestClaimResponse {
  coins: number;
  new_coins: number;
  next_available_at: string;
}

interface FreeChestState {
  available: boolean;
  // ISO string kept raw (not a luxon object) so reactive() doesn't proxy it.
  nextAvailableAtIso: string | undefined;
  loaded: boolean;
}

const state = reactive<FreeChestState>({
  available: false,
  nextAvailableAtIso: undefined,
  loaded: false,
});

function applyNext(next: string | null | undefined): void {
  state.nextAvailableAtIso = next ?? undefined;
}

async function fetchStatus(): Promise<void> {
  try {
    const response = await axios.get<FreeChestStatusResponse>("/api/free-chest");
    state.available = response.data.available;
    applyNext(response.data.next_available_at);
  } catch {
    // Non-critical — the chest button hides if status can't load.
  } finally {
    state.loaded = true;
  }
}

async function claim(): Promise<number | undefined> {
  const auth = useAuth();
  try {
    const response = await axios.post<FreeChestClaimResponse>("/api/free-chest/claim");
    auth.updateUserStats({ coins: response.data.new_coins });
    state.available = false;
    applyNext(response.data.next_available_at);
    return response.data.coins;
  } catch {
    // Race or still on cooldown — resync so the timer reflects the server.
    await fetchStatus();
    return undefined;
  }
}

// Called by the countdown tick once the cooldown elapses.
function markAvailable(): void {
  state.available = true;
  state.nextAvailableAtIso = undefined;
}

export function useFreeChest() {
  return { state, fetchStatus, claim, markAvailable };
}
