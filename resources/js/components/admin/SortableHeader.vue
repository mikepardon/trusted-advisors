<template>
  <th class="sortable-header" @click="$emit('sort', field)">
    <span class="header-label">{{ label }}</span>
    <span class="sort-arrow" v-if="currentSort === field">
      {{ currentDir === 'asc' ? '\u25B2' : '\u25BC' }}
    </span>
    <span class="sort-arrow sort-inactive" v-else>&#8693;</span>
  </th>
</template>

<script>
export default {
  name: 'SortableHeader',
  props: {
    label: { type: String, required: true },
    field: { type: String, required: true },
    currentSort: { type: String, default: '' },
    currentDir: { type: String, default: 'asc' },
  },
  emits: ['sort'],
};
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
