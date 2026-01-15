<template>
  <PageContainer 
    title="Order Management System" 
    subtitle="Welcome back! Here's what you can do today."
  >
    <div class="dashboard-grid">
      <div class="welcome-card card">
        <div class="card-body">
          <div class="user-info-section">
            <div class="user-avatar">
              {{ authStore.user?.name?.charAt(0).toUpperCase() }}
            </div>
            <div>
              <h2 class="user-greeting">Hello, {{ authStore.user?.name }}!</h2>
              <p class="user-role-text">
                You're logged in as 
                <span :class="['role-badge', authStore.isAdmin ? 'badge-info' : 'badge-success']">
                  {{ authStore.user?.role }}
                </span>
              </p>
            </div>
          </div>

          <div v-if="authStore.isAdmin" class="permissions-info">
            <p class="permissions-title">✓ Admin Permissions</p>
            <ul class="permissions-list">
              <li>Create, edit, and delete products</li>
              <li>Manage customer database</li>
              <li>Full access to all features</li>
            </ul>
          </div>
          <div v-else class="permissions-info">
            <p class="permissions-title">✓ Staff Permissions</p>
            <ul class="permissions-list">
              <li>View products and customers</li>
              <li>Process orders</li>
              <li>Limited editing capabilities</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="quick-actions-card card">
        <div class="card-header">
          <h3>Quick Actions</h3>
        </div>
        <div class="card-body">
          <div class="action-links">
            <router-link to="/products" class="action-link">
              <span class="action-icon">📦</span>
              <div>
                <div class="action-title">Products</div>
                <div class="action-description">Manage inventory</div>
              </div>
            </router-link>

            <router-link to="/customers" class="action-link">
              <span class="action-icon">👥</span>
              <div>
                <div class="action-title">Customers</div>
                <div class="action-description">Customer database</div>
              </div>
            </router-link>

            <router-link to="/orders" class="action-link">
              <span class="action-icon">📋</span>
              <div>
                <div class="action-title">Orders</div>
                <div class="action-description">Manage orders</div>
              </div>
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </PageContainer>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import PageContainer from '@/components/layout/PageContainer.vue'

const authStore = useAuthStore()
</script>

<style scoped>
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

@media (max-width: 768px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}

.welcome-card {
  grid-column: span 2;
}

@media (max-width: 768px) {
  .welcome-card {
    grid-column: span 1;
  }
}

.user-info-section {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.user-avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary), #3b82f6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 600;
}

.user-greeting {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-gray-900);
}

.user-role-text {
  margin: 0;
  color: var(--color-gray-600);
  font-size: 0.9375rem;
}

.role-badge {
  margin-left: 0.5rem;
}

.permissions-info {
  background: var(--color-gray-50);
  padding: 1.25rem;
  border-radius: var(--radius-md);
}

.permissions-title {
  margin: 0 0 0.75rem;
  font-weight: 600;
  color: var(--color-gray-900);
}

.permissions-list {
  margin: 0;
  padding-left: 1.25rem;
  color: var(--color-gray-600);
}

.permissions-list li {
  margin-bottom: 0.375rem;
}

.quick-actions-card h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.action-links {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.action-link {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--color-gray-50);
  border-radius: var(--radius-md);
  text-decoration: none;
  transition: all 0.15s;
  border: 1px solid transparent;
}

.action-link:hover {
  background: white;
  border-color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}

.action-icon {
  font-size: 2rem;
}

.action-title {
  font-weight: 600;
  color: var(--color-gray-900);
  margin-bottom: 0.125rem;
}

.action-description {
  font-size: 0.875rem;
  color: var(--color-gray-500);
}
</style>
