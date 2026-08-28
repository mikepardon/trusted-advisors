<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Scheduled Jobs</h2>
    </div>

    <p class="schedule-hint">
      These console commands run automatically on the server schedule shown below. Use
      "Run now" to trigger one manually — handy when the server cron isn't running locally,
      or when a job needs re-running out of band. Each command is idempotent.
    </p>

    <div v-if="loading" class="loading">Loading…</div>

    <div v-else class="list-panel">
      <div v-for="job in jobs" :key="job.key" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ job.label }}</strong>
            <span class="status-badge badge-schedule">{{ job.schedule }}</span>
          </div>
          <div class="list-desc">{{ job.description }}</div>
          <div class="list-desc dim">{{ job.command }}</div>
        </div>
        <div class="list-actions">
          <button class="btn-sm btn-primary" :disabled="running" @click="runJob(job.key)">
            {{ runningKey === job.key ? "…" : "Run now" }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="lastMessage" class="schedule-output">
      <div class="schedule-output-head">{{ lastMessage }}</div>
      <pre v-if="lastOutput">{{ lastOutput }}</pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { onMounted, ref } from "vue";
import { useToast } from "../../stores/toast";

interface ScheduledJob {
  key: string;
  label: string;
  schedule: string;
  description: string;
  command: string;
}

const toast = useToast();
const loading = ref(true);
const running = ref(false);
const runningKey = ref<string | undefined>(undefined);
const jobs = ref<ScheduledJob[]>([]);
const lastMessage = ref<string | undefined>(undefined);
const lastOutput = ref<string | undefined>(undefined);

async function load(): Promise<void> {
  loading.value = true;
  try {
    const { data } = await axios.get<{ jobs: ScheduledJob[] }>("/api/admin/schedule");
    jobs.value = data.jobs;
  } catch {
    toast.error("Could not load scheduled jobs.");
  } finally {
    loading.value = false;
  }
}

async function runJob(key: string): Promise<void> {
  running.value = true;
  runningKey.value = key;
  try {
    const { data } = await axios.post<{ message: string; output: string }>(
      "/api/admin/schedule/run",
      { key },
    );
    lastMessage.value = data.message;
    lastOutput.value = data.output;
    toast.success(data.message);
  } catch {
    toast.error("Failed to run the job.");
  } finally {
    running.value = false;
    runningKey.value = undefined;
  }
}

onMounted(load);
</script>

<style scoped>
.schedule-hint {
  color: #b0a88f;
  font-size: 0.85rem;
  margin: 0 0 16px;
  line-height: 1.5;
  max-width: 720px;
}

.badge-schedule {
  background: rgba(100, 100, 160, 0.2);
  color: #a0a0d0;
}

.list-desc.dim {
  opacity: 0.5;
  font-size: 0.72rem;
  font-family: monospace;
}

.schedule-output {
  margin-top: 20px;
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(212, 168, 67, 0.25);
  border-radius: 8px;
  padding: 12px 14px;
}

.schedule-output-head {
  color: #6abf50;
  font-weight: 700;
  margin-bottom: 6px;
}

.schedule-output pre {
  margin: 0;
  color: #b0a88f;
  font-size: 0.78rem;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
