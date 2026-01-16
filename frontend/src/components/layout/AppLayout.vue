<template>
  <div class="app-layout">
    <!-- Top Navigation Bar -->
    <header class="app-header">
      <div class="header-content">
        <div class="header-left">
          <router-link to="/" class="logo">
            <span class="logo-icon">📦</span>
            <span class="logo-text">OMS</span>
          </router-link>
          
          <nav v-if="authStore.isAuthenticated" class="main-nav">
            <router-link to="/products" class="nav-item">Products</router-link>
            <router-link to="/customers" class="nav-item">Customers</router-link>
            <router-link to="/orders" class="nav-item">Orders</router-link>
          </nav>
        </div>

        <div v-if="authStore.isAuthenticated" class="header-right">
          <NotificationBell />
          <div class="user-menu">
            <span class="user-name">{{ authStore.user?.name }}</span>
            <span class="user-role">{{ authStore.user?.role }}</span>
            <button @click="handleLogout" class="btn-logout">Logout</button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="app-main">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authAPI } from '@/api/auth'
import NotificationBell from './NotificationBell.vue'

const router = useRouter()
const authStore = useAuthStore()

async function handleLogout() {
  try {
    await authAPI.logout()
  } catch (error) {
    console.error('Logout error:', error)
  } finally {
    authStore.logout()
    router.push('/login')
  }
}
</script>

<style scoped>
.app-layout {
  min-height: 100vh;
  background: #f5f7fa;
}

.app-header {
  background: white;
  border-bottom: 1px solid #e5e7eb;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.header-content {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 64px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 2rem;
}

.logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  color: #111827;
  font-weight: 600;
  font-size: 1.25rem;
}

.logo-icon {
  font-size: 1.5rem;
}

.main-nav {
  display: flex;
  gap: 0.5rem;
}

.nav-item {
  padding: 0.5rem 1rem;
  text-decoration: none;
  color: #6b7280;
  border-radius: 6px;
  font-weight: 500;
  transition: all 0.15s;
}

.nav-item:hover {
  background: #f3f4f6;
  color: #111827;
}

.nav-item.router-link-active {
  background: #eff6ff;
  color: #2563eb;
}

.header-right {
  display: flex;
  align-items: center;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-name {
  font-weight: 500;
  color: #111827;
}

.user-role {
  padding: 0.25rem 0.75rem;
  background: #f3f4f6;
  color: #6b7280;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 500;
}

.btn-logout {
  padding: 0.5rem 1rem;
  background: white;
  border: 1px solid #e5e7eb;
  color: #6b7280;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.15s;
}

.btn-logout:hover {
  background: #f9fafb;
  border-color: #d1d5db;
  color: #111827;
}

.app-main {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem;
}
</style>
