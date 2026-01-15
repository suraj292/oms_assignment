<template>
  <div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title-section">
        <h1 class="page-title">{{ title }}</h1>
        <p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="page-actions">
        <slot name="actions" />
      </div>
    </div>

    <!-- Filters/Search Section -->
    <div v-if="$slots.filters" class="page-filters">
      <slot name="filters" />
    </div>

    <!-- Main Content -->
    <div class="page-content">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<style scoped>
.page-container {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.page-title-section {
  flex: 1;
}

.page-title {
  margin: 0;
  font-size: 1.875rem;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.page-subtitle {
  margin: 0.5rem 0 0;
  color: #6b7280;
  font-size: 0.9375rem;
}

.page-actions {
  display: flex;
  gap: 0.75rem;
  flex-shrink: 0;
}

.page-filters {
  background: white;
  padding: 1.25rem;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}
</style>
