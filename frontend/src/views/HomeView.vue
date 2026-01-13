<template>
  <div class="home">
    <div class="header">
      <h1>Order Management System</h1>
      <div v-if="authStore.isAuthenticated" class="user-info">
        <p>Welcome, <strong>{{ authStore.user?.name }}</strong></p>
        <p class="role-badge">Role: {{ authStore.user?.role }}</p>
        <button @click="handleLogout" class="btn-logout">Logout</button>
      </div>
    </div>

    <div v-if="authStore.isAuthenticated" class="content">
      <p>You are logged in and authenticated.</p>
      <p v-if="authStore.isAdmin" class="admin-notice">
        ✓ You have Admin access
      </p>
      <p v-else-if="authStore.isStaff" class="staff-notice">
        ✓ You have Staff access
      </p>
    </div>
    <div v-else class="content">
      <p>Please log in to access the system.</p>
      <router-link to="/login" class="btn-primary">Go to Login</router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authAPI } from '@/api/auth'

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
.home {
  padding: 2rem;
  max-width: 800px;
  margin: 0 auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

h1 {
  margin: 0;
  color: #333;
}

.user-info {
  text-align: right;
}

.user-info p {
  margin: 0.25rem 0;
}

.role-badge {
  display: inline-block;
  background: #4CAF50;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.875rem;
}

.btn-logout {
  margin-top: 0.5rem;
  padding: 0.5rem 1rem;
  background: #f44336;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.875rem;
}

.btn-logout:hover {
  background: #d32f2f;
}

.content {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.admin-notice {
  color: #4CAF50;
  font-weight: 600;
}

.staff-notice {
  color: #2196F3;
  font-weight: 600;
}

.btn-primary {
  display: inline-block;
  margin-top: 1rem;
  padding: 0.75rem 1.5rem;
  background: #4CAF50;
  color: white;
  text-decoration: none;
  border-radius: 4px;
}

.btn-primary:hover {
  background: #45a049;
}
</style>
