<template>
  <div class="search-wrap">
    <span class="search-icon">&#128269;</span>
    <input
      type="text"
      class="search-input"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      placeholder="Search..."
    />
    <button
      v-if="modelValue"
      class="search-clear"
      @click="$emit('update:modelValue', '')"
      type="button"
    >&times;</button>
  </div>
</template>

<script>
export default {
  name: 'AdminSearchInput',
  props: {
    modelValue: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
};
</script>

<style scoped>
.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
  margin-bottom: 16px;
  max-width: 360px;
}

.search-icon {
  position: absolute;
  left: 10px;
  font-size: 0.9rem;
  pointer-events: none;
  opacity: 0.6;
}

.search-input {
  width: 100%;
  background: var(--bg-secondary);
  border: 1px solid rgba(184, 148, 46, 0.3);
  color: var(--text-bright);
  padding: 8px 32px 8px 34px;
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.92rem;
  transition: border-color 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: var(--accent-gold);
}

.search-input::placeholder {
  color: var(--text-secondary);
  opacity: 0.6;
}

.search-clear {
  position: absolute;
  right: 6px;
  background: none;
  border: none;
  color: var(--text-secondary);
  font-size: 1.2rem;
  cursor: pointer;
  padding: 2px 6px;
  line-height: 1;
}

.search-clear:hover {
  color: var(--text-bright);
}
</style>
