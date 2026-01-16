<template>
  <PageContainer title="Notifications" subtitle="View all your notifications">
    <template #actions>
      <button 
        v-if="notifications.length > 0 && unreadCount > 0"
        @click="markAllAsRead" 
        class="btn btn-secondary"
        :disabled="markingAllRead"
      >
        {{ markingAllRead ? 'Marking...' : 'Mark All as Read' }}
      </button>
    </template>

    <LoadingSpinner v-if="loading" message="Loading notifications..." />

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <EmptyState
      v-else-if="notifications.length === 0"
      icon="🔔"
      title="No notifications"
      message="You don't have any notifications yet."
    />

    <div v-else class="notifications-container">
      <div
        v-for="notification in notifications"
        :key="notification.id"
        :class="['notification-card', { unread: !notification.read_at }]"
        @click="handleNotificationClick(notification)"
      >
        <div class="notification-icon">
          {{ getNotificationIcon(notification.data.type) }}
        </div>
        <div class="notification-content">
          <p class="notification-message">{{ notification.data.message }}</p>
          <div class="notification-meta">
            <span class="notification-time">{{ formatDate(notification.created_at) }}</span>
            <span v-if="!notification.read_at" class="unread-indicator">New</span>
          </div>
        </div>
        <button
          v-if="!notification.read_at"
          @click.stop="markAsRead(notification.id)"
          class="mark-read-btn"
          title="Mark as read"
        >
          ✓
        </button>
      </div>

      <div v-if="pagination" class="pagination">
        <button
          @click="changePage(pagination.current_page - 1)"
          :disabled="pagination.current_page === 1"
          class="btn btn-secondary"
        >
          ← Previous
        </button>
        <span class="pagination-info">
          Page {{ pagination.current_page }} of {{ pagination.last_page }}
        </span>
        <button
          @click="changePage(pagination.current_page + 1)"
          :disabled="pagination.current_page === pagination.last_page"
          class="btn btn-secondary"
        >
          Next →
        </button>
      </div>
    </div>
  </PageContainer>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { notificationsAPI } from '@/api/notifications'
import type { Notification, PaginationMeta } from '@/types/models'
import PageContainer from '@/components/layout/PageContainer.vue'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'
import EmptyState from '@/components/common/EmptyState.vue'

const router = useRouter()

const notifications = ref<Notification[]>([])
const loading = ref(false)
const error = ref('')
const pagination = ref<PaginationMeta | null>(null)
const unreadCount = ref(0)
const markingAllRead = ref(false)

async function fetchNotifications(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const response = await notificationsAPI.getAll({ page, per_page: 20 })
    notifications.value = response.data.data
    pagination.value = response.data.meta
    
    // Count unread
    unreadCount.value = notifications.value.filter(n => !n.read_at).length
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load notifications'
  } finally {
    loading.value = false
  }
}

function changePage(page: number) {
  fetchNotifications(page)
}

async function handleNotificationClick(notification: Notification) {
  if (!notification.read_at) {
    await markAsRead(notification.id)
  }

  // Navigate to order if it's an order notification
  if (notification.data.order_id) {
    router.push(`/orders/${notification.data.order_id}`)
  }
}

async function markAsRead(id: string) {
  try {
    await notificationsAPI.markAsRead(id)
    
    // Update local state
    const notification = notifications.value.find(n => n.id === id)
    if (notification) {
      notification.read_at = new Date().toISOString()
      unreadCount.value = notifications.value.filter(n => !n.read_at).length
    }
  } catch (err) {
    console.error('Failed to mark as read:', err)
  }
}

async function markAllAsRead() {
  markingAllRead.value = true
  try {
    await notificationsAPI.markAllAsRead()
    
    // Update local state
    notifications.value.forEach(n => {
      if (!n.read_at) {
        n.read_at = new Date().toISOString()
      }
    })
    unreadCount.value = 0
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to mark all as read')
  } finally {
    markingAllRead.value = false
  }
}

function getNotificationIcon(type: string): string {
  const icons: Record<string, string> = {
    order_created: '📦',
    order_status_changed: '🔄',
  }
  return icons[type] || '📢'
}

function formatDate(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`
  if (hours < 24) return `${hours} hour${hours !== 1 ? 's' : ''} ago`
  if (days < 7) return `${days} day${days !== 1 ? 's' : ''} ago`
  
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  })
}

onMounted(() => {
  fetchNotifications()
})
</script>

<style scoped>
.notifications-container {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.notification-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1.25rem;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all 0.15s;
}

.notification-card:hover {
  border-color: var(--color-gray-300);
  box-shadow: var(--shadow-sm);
}

.notification-card.unread {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.notification-card.unread:hover {
  background: #dbeafe;
}

.notification-icon {
  font-size: 1.75rem;
  flex-shrink: 0;
}

.notification-content {
  flex: 1;
  min-width: 0;
}

.notification-message {
  margin: 0 0 0.5rem;
  color: var(--color-gray-900);
  font-size: 0.9375rem;
  line-height: 1.5;
}

.notification-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.notification-time {
  font-size: 0.8125rem;
  color: var(--color-gray-500);
}

.unread-indicator {
  display: inline-block;
  padding: 0.125rem 0.5rem;
  background: var(--color-primary);
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 12px;
}

.mark-read-btn {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  background: white;
  border: 1px solid var(--color-gray-300);
  border-radius: 50%;
  color: var(--color-gray-600);
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.15s;
}

.mark-read-btn:hover {
  background: var(--color-success);
  border-color: var(--color-success);
  color: white;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1.5rem;
  padding: 2rem 0 1rem;
}

.pagination-info {
  color: var(--color-gray-700);
  font-weight: 500;
}
</style>
