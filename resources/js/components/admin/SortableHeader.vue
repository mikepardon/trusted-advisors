<template>
  <th class="sortable-header" @click="$emit('sort', field)">
    <span class="header-label">{{ label }}</span>
    <span v-if="currentSort === field" class="sort-arrow">
      {{ currentDir === 'asc' ? '\u25B2' : '\u25BC' }}
    </span>
    <span v-else class="sort-arrow sort-inactive">&#8693;</span>
  </th>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string;
    field: string;
    currentSort?: string;
    currentDir?: string;
  }>(),
  {
    currentSort: "",
    currentDir: "asc",
  },
);

defineEmits<{ sort: [field: string] }>();
</script>

<style scoped>
.sortable-header {
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}

.sortable-header:hover {
  color: var(--text-bright);
}

.header-label {
  margin-right: 4px;
}

.sort-arrow {
  font-size: 0.7rem;
  color: var(--accent-gold);
}

.sort-inactive {
  opacity: 0.3;
  font-size: 0.8rem;
}
</style>
