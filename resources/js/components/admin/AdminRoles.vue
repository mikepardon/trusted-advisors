<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">Admin Roles</h2>
    </div>

    <div class="list-panel">
      <div v-for="admin in admins" :key="admin.id" class="list-row">
        <div class="list-info">
          <div class="list-top">
            <strong>{{ admin.name }}</strong>
            <span class="email-label">{{ admin.email }}</span>
          </div>
        </div>
        <div class="role-control">
          <select :value="admin.admin_role" :disabled="saving === admin.id" @change="updateRole(admin, $event.target.value)">
            <option value="super_admin">Super Admin</option>
            <option value="content_admin">Content Admin</option>
            <option value="moderator">Moderator</option>
            <option value="analyst">Analyst</option>
          </select>
        </div>
      </div>
      <div v-if="admins.length === 0 && !loading" class="empty">No admin users found.</div>
      <div v-if="loading" class="empty">Loading...</div>
    </div>

    <div v-if="error" class="form-error">{{ error }}</div>
    <div v-if="success" class="form-success">{{ success }}</div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import axios, { isAxiosError } from "axios";

interface Admin {
  id: number;
  name: string;
  email: string;
  admin_role: string;
}

const admins = ref<Admin[]>([]);
const loading = ref(true);
const saving = ref<number>();
const error = ref("");
const success = ref("");

async function load(): Promise<void> {
  loading.value = true;
  try {
    const response = await axios.get<Admin[]>("/api/admin/roles");
    admins.value = response.data;
  } catch {
    error.value = "Failed to load admin users";
  }
  loading.value = false;
}

async function updateRole(admin: Admin, newRole: string): Promise<void> {
  saving.value = admin.id;
  error.value = "";
  success.value = "";
  try {
    await axios.put(`/api/admin/users/${admin.id}/role`, { admin_role: newRole });
    admin.admin_role = newRole;
    success.value = `Updated ${admin.name} to ${newRole.replace("_", " ")}`;
    setTimeout(() => {
      success.value = "";
    }, 3000);
  } catch (error_) {
    const message = isAxiosError<{ message?: string }>(error_)
      ? error_.response?.data?.message
      : undefined;
    error.value = message ?? "Failed to update role";
    setTimeout(() => {
      error.value = "";
    }, 5000);
  }
  saving.value = undefined;
}

onMounted(async () => {
  await load();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-title { font-family: 'Cinzel', serif; color: var(--accent-gold); font-size: 1.5rem; }
.list-panel { display: flex; flex-direction: column; gap: 6px; }
.list-row { display: flex; justify-content: space-between; align-items: center; background: var(--bg-secondary); border: 1px solid rgba(138, 106, 46, 0.2); border-radius: 6px; padding: 10px 12px; }
.list-info strong { color: var(--accent-gold); }
.list-top { display: flex; align-items: center; gap: 8px; }
.email-label { font-size: 0.8rem; color: var(--text-secondary); }
.role-control select { background: var(--bg-primary); border: 1px solid rgba(138, 106, 46, 0.3); color: var(--text-bright); padding: 5px 8px; border-radius: 4px; font-family: inherit; }
.empty { text-align: center; color: var(--text-secondary); font-style: italic; padding: 20px; }
.form-error { color: var(--accent-red, #e74c3c); font-size: 0.9rem; margin-top: 12px; padding: 8px 12px; background: rgba(231, 76, 60, 0.1); border-radius: 4px; }
.form-success { color: #2ecc71; font-size: 0.9rem; margin-top: 12px; padding: 8px 12px; background: rgba(46, 204, 113, 0.1); border-radius: 4px; }
</style>
