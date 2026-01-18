<template>
  <AppLayout v-if="shouldShowLayout">
    <RouterView />
  </AppLayout>
  <RouterView v-else />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from './components/layout/AppLayout.vue'

const route = useRoute()


const shouldShowLayout = computed(() => {
  return !['Login', 'Register'].includes(route.name as string)
})
</script>

<style>
#app {
  min-height: 100vh;
}
</style>
