<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Settings</h2>
    </div>

    <div v-if="loading" class="loading">Loading…</div>

    <div v-else class="list-panel">
      <div class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>Anthropic API Key</strong>
            <span
              class="status-badge"
              :class="anthropic.set ? 'badge-active' : 'badge-inactive'"
            >
              {{ anthropic.set ? "Set" : "Not set" }}
            </span>
          </div>
          <div class="list-desc">
            Used by the admin AI content generators (characters, cards, events). An admin
            key set here takes effect immediately and overrides the server environment key.
          </div>
          <div v-if="anthropic.set" class="list-desc dim">
            Current: {{ anthropic.masked }}
          </div>
          <div v-else-if="anthropic.env_fallback" class="list-desc dim">
            Falling back to the ANTHROPIC_API_KEY environment variable.
          </div>
          <div v-else class="list-desc dim warn">
            No key configured — AI generation will fail until one is set.
          </div>

          <form class="key-form" @submit.prevent="save">
            <input
              v-model="newKey"
              type="password"
              class="key-input"
              placeholder="sk-ant-…"
              autocomplete="off"
              spellcheck="false"
            />
            <button class="btn-primary" type="submit" :disabled="saving || newKey.trim() === ''">
              {{ saving ? "Saving…" : "Save" }}
            </button>
            <button
              v-if="anthropic.set"
              class="btn-sm btn-danger"
              type="button"
              :disabled="saving"
              @click="clear"
            >
              Clear
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import axios from "axios";
import { onMounted, reactive, ref } from "vue";
import { useToast } from "../../stores/toast";

interface AnthropicSetting {
  set: boolean;
  masked: string;
  env_fallback: boolean;
}

const toast = useToast();
const loading = ref(true);
const saving = ref(false);
const newKey = ref("");
const anthropic = reactive<AnthropicSetting>({
  set: false,
  masked: "",
  env_fallback: false,
});

function applyAnthropic(next: AnthropicSetting): void {
  anthropic.set = next.set;
  anthropic.masked = next.masked;
  anthropic.env_fallback = next.env_fallback;
}

async function load(): Promise<void> {
  loading.value = true;
  try {
    const { data } = await axios.get<{ anthropic: AnthropicSetting }>("/api/admin/settings");
    applyAnthropic(data.anthropic);
  } catch {
    toast.error("Could not load settings.");
  } finally {
    loading.value = false;
  }
}

async function save(): Promise<void> {
  if (newKey.value.trim() === "") {
    return;
  }
  saving.value = true;
  try {
    const { data } = await axios.put<{ message: string; anthropic: AnthropicSetting }>(
      "/api/admin/settings/anthropic-key",
      { api_key: newKey.value.trim() },
    );
    applyAnthropic(data.anthropic);
    newKey.value = "";
    toast.success(data.message);
  } catch {
    toast.error("Failed to save the key.");
  } finally {
    saving.value = false;
  }
}

async function clear(): Promise<void> {
  if (!window.confirm("Clear the stored Anthropic API key?")) {
    return;
  }
  saving.value = true;
  try {
    const { data } = await axios.delete<{ message: string; anthropic: AnthropicSetting }>(
      "/api/admin/settings/anthropic-key",
    );
    applyAnthropic(data.anthropic);
    toast.success(data.message);
  } catch {
    toast.error("Failed to clear the key.");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.list-desc.dim {
  opacity: 0.55;
  font-size: 0.75rem;
}

.list-desc.warn {
  color: #d98a5a;
  opacity: 0.9;
}

.key-form {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-top: 14px;
  flex-wrap: wrap;
}

.key-input {
  flex: 1;
  min-width: 220px;
  padding: 9px 12px;
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(212, 168, 67, 0.3);
  border-radius: 6px;
  color: var(--text-bright);
  font-family: monospace;
  font-size: 0.85rem;
}

.key-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.btn-danger {
  color: #e08a6f;
  border-color: rgba(224, 138, 111, 0.4);
}
</style>
