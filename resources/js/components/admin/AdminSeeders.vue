<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Seeders</h2>
      <button
        class="btn-primary"
        :disabled="running || pendingCount === 0"
        @click="runAllPending"
      >
        {{ running ? "Running…" : `Run All Pending (${pendingCount})` }}
      </button>
    </div>

    <p class="seeder-hint">
      Seeders aren't tracked like migrations, so status is inferred from the data.
      "Pending" means the seeder's expected content is missing or out of date — run it
      after pulling changes. Running a seeder is idempotent (uses updateOrCreate).
    </p>

    <div v-if="loading" class="loading">Loading…</div>

    <div v-else class="list-panel">
      <div v-for="seeder in seeders" :key="seeder.class" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ seeder.label }}</strong>
            <span
              class="status-badge"
              :class="seeder.pending ? 'badge-inactive' : 'badge-active'"
            >
              {{ seeder.pending ? "Pending" : "Applied" }}
            </span>
          </div>
          <div class="list-desc">{{ seeder.description }}</div>
          <div class="list-desc dim">{{ seeder.class }}</div>
        </div>
        <div class="list-actions">
          <button
            class="btn-sm"
            :class="{ 'btn-primary': seeder.pending }"
            :disabled="running"
            @click="runSeeder(seeder.class)"
          >
            {{ runningClass === seeder.class ? "…" : "Run" }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="lastOutput" class="seeder-output">
      <div class="seeder-output-head">{{ lastMessage }}</div>
      <pre v-if="lastOutput">{{ lastOutput }}</pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { computed, onMounted, ref } from "vue";
import { useToast } from "../../stores/toast";

interface SeederInfo {
  class: string;
  label: string;
  description: string;
  pending: boolean;
}

const toast = useToast();
const loading = ref(true);
const running = ref(false);
const runningClass = ref<string | undefined>(undefined);
const seeders = ref<SeederInfo[]>([]);
const lastMessage = ref<string | undefined>(undefined);
const lastOutput = ref<string | undefined>(undefined);

const pendingCount = computed(() => seeders.value.filter((seeder) => seeder.pending).length);

async function load(): Promise<void> {
  loading.value = true;
  try {
    const { data } = await axios.get<{ seeders: SeederInfo[] }>("/api/admin/seeders");
    seeders.value = data.seeders;
  } catch {
    toast.error("Could not load seeders.");
  } finally {
    loading.value = false;
  }
}

async function runSeeder(seederClass: string): Promise<void> {
  running.value = true;
  runningClass.value = seederClass;
  try {
    const { data } = await axios.post<{ message: string; output: string }>(
      "/api/admin/seeders/run",
      { class: seederClass },
    );
    lastMessage.value = data.message;
    lastOutput.value = data.output;
    toast.success(data.message);
    await load();
  } catch {
    toast.error(`Failed to run ${seederClass}.`);
  } finally {
    running.value = false;
    runningClass.value = undefined;
  }
}

async function runAllPending(): Promise<void> {
  const pending = seeders.value.filter((seeder) => seeder.pending).map((seeder) => seeder.class);
  for (const seederClass of pending) {
    await runSeeder(seederClass);
  }
}

onMounted(load);
</script>

<style scoped>
.seeder-hint {
  color: #b0a88f;
  font-size: 0.85rem;
  margin: 0 0 16px;
  line-height: 1.5;
  max-width: 720px;
}

.list-desc.dim {
  opacity: 0.5;
  font-size: 0.72rem;
  font-family: monospace;
}

.seeder-output {
  margin-top: 20px;
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(212, 168, 67, 0.25);
  border-radius: 8px;
  padding: 12px 14px;
}

.seeder-output-head {
  color: #6abf50;
  font-weight: 700;
  margin-bottom: 6px;
}

.seeder-output pre {
  margin: 0;
  color: #b0a88f;
  font-size: 0.78rem;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
