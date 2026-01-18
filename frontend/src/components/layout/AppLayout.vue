<template>
  <div class="app-wrapper">
    <!-- Fixed Sidebar -->
    <nav id="sidebar" :class="{ 'open': sidebarOpen }">
      <div class="sidebar-brand">
        <i data-lucide="box" class="brand-icon"></i>
        <span class="brand-text">Nexus OMS</span>
      </div>
      
      <div class="sidebar-nav">
        <router-link to="/" class="nav-link" @click="closeSidebarMobile">
          <i data-lucide="layout-dashboard" size="20"></i>
          <span>Dashboard</span>
        </router-link>
        <router-link to="/orders" class="nav-link" @click="closeSidebarMobile">
          <i data-lucide="shopping-cart" size="20"></i>
          <span>Orders</span>
        </router-link>
        <router-link to="/products" class="nav-link" @click="closeSidebarMobile">
          <i data-lucide="package" size="20"></i>
          <span>Products</span>
        </router-link>
        <router-link to="/customers" class="nav-link" @click="closeSidebarMobile">
          <i data-lucide="users" size="20"></i>
          <span>Customers</span>
        </router-link>
      </div>

      <!-- Sidebar Footer -->
      <div class="sidebar-footer">
        <div class="user-role-info">
          <span class="role-label">Role:</span>
          <span :class="['role-badge', authStore.isAdmin ? 'badge-admin' : 'badge-staff']">
            {{ authStore.user?.role }}
          </span>
        </div>
        <button class="btn-logout" @click="handleLogout">
          <i data-lucide="log-out" size="16"></i>
          <span>Logout</span>
        </button>
      </div>
    </nav>

    <!-- Main Content Area -->
    <div id="main-content">
      <!-- Top Header -->
      <header class="top-header">
        <!-- Mobile Menu Toggle -->
        <button class="mobile-toggle" @click="toggleSidebar">
          <i data-lucide="menu" size="24"></i>
        </button>

        <!-- Global Search -->
        <div class="search-container">
          <i data-lucide="search" size="16" class="search-icon"></i>
          <input 
            type="search" 
            class="global-search" 
            placeholder="Search orders, products..." 
          />
        </div>

        <!-- Right Actions -->
        <div class="header-actions">
          <!-- Notification Bell -->
          <NotificationBell />

          <!-- User Profile -->
          <div class="user-profile">
            <img 
              :src="`https://api.dicebear.com/7.x/avataaars/svg?seed=${authStore.user?.name}`" 
              alt="User" 
              class="user-avatar"
            />
            <div class="user-info">
              <div class="user-name">{{ authStore.user?.name }}</div>
              <div class="user-title">{{ authStore.isAdmin ? 'Administrator' : 'Staff Member' }}</div>
            </div>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="page-content fade-in">
        <slot />
      </main>
    </div>

    <!-- Mobile Overlay -->
    <div v-if="sidebarOpen" class="sidebar-overlay" @click="closeSidebarMobile"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authAPI } from '@/api/auth'
import NotificationBell from './NotificationBell.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const sidebarOpen = ref(false)

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebarMobile() {
  if (window.innerWidth < 768) {
    sidebarOpen.value = false
  }
}

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


onMounted(() => {
  if (window.lucide) {
    window.lucide.createIcons()
  }
})


watch(route, () => {
  setTimeout(() => {
    if (window.lucide) {
      window.lucide.createIcons()
    }
  }, 100)
})
</script>

<style scoped>
.app-wrapper {
  display: flex;
  min-height: 100vh;
}

/* ===== SIDEBAR ===== */
#sidebar {
  width: 260px;
  background-color: var(--sidebar-bg);
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  transition: transform 0.3s ease;
}

.sidebar-brand {
  height: 70px;
  display: flex;
  align-items: center;
  padding: 0 1.5rem;
  color: white;
  font-weight: 700;
  font-size: 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  gap: 0.75rem;
}

.brand-icon {
  color: var(--color-primary);
}

.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  overflow-y: auto;
}

.nav-link {
  color: var(--sidebar-text);
  padding: 0.85rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  transition: all 0.2s;
  border-left: 3px solid transparent;
  text-decoration: none;
  font-weight: 500;
}

.nav-link:hover {
  color: white;
  background: rgba(255, 255, 255, 0.05);
}

.nav-link.router-link-active {
  color: var(--sidebar-active);
  background: rgba(99, 102, 241, 0.1);
  border-left-color: var(--color-primary);
}

.sidebar-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-role-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  font-size: 0.875rem;
}

.role-label {
  color: var(--sidebar-text);
}

.role-badge {
  padding: 0.25rem 0.625rem;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.75rem;
}

.badge-admin {
  background: var(--color-primary);
  color: white;
}

.badge-staff {
  background: rgba(255, 255, 255, 0.1);
  color: var(--sidebar-text);
}

.btn-logout {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #fca5a5;
  border-radius: var(--radius-md);
  cursor: pointer;
  font-weight: 500;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.btn-logout:hover {
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
}

/* ===== MAIN CONTENT ===== */
#main-content {
  margin-left: 260px;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  transition: margin-left 0.3s ease;
}

/* ===== TOP HEADER ===== */
.top-header {
  height: 70px;
  background: white;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 2rem;
  position: sticky;
  top: 0;
  z-index: 900;
  gap: 1.5rem;
}

.mobile-toggle {
  display: none;
  background: none;
  border: none;
  color: var(--color-gray-700);
  cursor: pointer;
  padding: 0.5rem;
  margin-right: 0.5rem;
}

.search-container {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-gray-400);
  pointer-events: none;
}

.global-search {
  width: 100%;
  padding: 0.625rem 1rem 0.625rem 2.75rem;
  border: 1px solid var(--color-gray-200);
  border-radius: 2rem;
  background: var(--color-gray-50);
  font-size: 0.9375rem;
  transition: all 0.2s;
}

.global-search:focus {
  outline: none;
  background: white;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 2px solid var(--color-gray-200);
}

.user-info {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.user-name {
  font-weight: 600;
  font-size: 0.9375rem;
  color: var(--color-gray-900);
}

.user-title {
  font-size: 0.75rem;
  color: var(--color-gray-500);
}

/* ===== PAGE CONTENT ===== */
.page-content {
  flex: 1;
  padding: 2rem;
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
}

/* ===== MOBILE RESPONSIVE ===== */
.sidebar-overlay {
  display: none;
}

@media (max-width: 768px) {
  #sidebar {
    transform: translateX(-100%);
  }

  #sidebar.open {
    transform: translateX(0);
  }

  #main-content {
    margin-left: 0;
  }

  .mobile-toggle {
    display: block;
  }

  .search-container {
    display: none;
  }

  .user-info {
    display: none;
  }

  .sidebar-overlay {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }

  .page-content {
    padding: 1rem;
  }
}

@media (max-width: 480px) {
  .top-header {
    padding: 0 1rem;
  }
}
</style>
