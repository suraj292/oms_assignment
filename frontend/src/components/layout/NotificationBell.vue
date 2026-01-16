<template>
  <div class="notification-bell">
    <button @click="toggleDropdown" class="bell-button" title="Notifications">
      <span class="bell-icon">🔔</span>
      <span v-if="unreadCount > 0" class="badge">{{ unreadCount }}</span>
    </button>

    <div v-if="showDropdown" class="dropdown" @click.stop>
      <div class="dropdown-header">
        <h3>Notifications</h3>
        <button v-if="notifications.length > 0" @click="markAllRead" class="mark-all-btn">
          Mark all read
        </button>
      </div>

      <div v-if="loading" class="dropdown-loading">
        Loading...
      </div>

      <div v-else-if="notifications.length === 0" class="dropdown-empty">
        No notifications
      </div>

      <div v-else class="notifications-list">
        <div
          v-for="notification in notifications.slice(0, 5)"
          :key="notification.id"
          :class="['notification-item', { unread: !notification.read_at }]"
          @click="handleNotificationClick(notification)"
        >
          <div class="notification-content">
            <p class="notification-message">{{ notification.data.message }}</p>
            <span class="notification-time">{{ formatTime(notification.created_at) }}</span>
          </div>
        </div>
      </div>

      <div class="dropdown-footer">
        <router-link to="/notifications" class="view-all-link" @click="showDropdown = false">
          View All Notifications
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { notificationsAPI } from '@/api/notifications'
import pusher from '@/lib/pusher'
import type { Notification } from '@/types/models'

const router = useRouter()
const authStore = useAuthStore()

const showDropdown = ref(false)
const notifications = ref<Notification[]>([])
const unreadCount = ref(0)
const loading = ref(false)

function toggleDropdown() {
  showDropdown.value = !showDropdown.value
  if (showDropdown.value) {
    fetchNotifications()
  }
}

async function fetchNotifications() {
  loading.value = true
  try {
    const response = await notificationsAPI.getAll({ per_page: 5 })
    notifications.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch notifications:', error)
  } finally {
    loading.value = false
  }
}

async function fetchUnreadCount() {
  try {
    const response = await notificationsAPI.getUnreadCount()
    unreadCount.value = response.data.data.count
  } catch (error) {
    console.error('Failed to fetch unread count:', error)
  }
}

async function handleNotificationClick(notification: Notification) {
  if (!notification.read_at) {
    await notificationsAPI.markAsRead(notification.id)
    fetchUnreadCount()
    fetchNotifications()
  }

  showDropdown.value = false

  // Navigate to order if it's an order notification
  if (notification.data.order_id) {
    router.push(`/orders/${notification.data.order_id}`)
  }
}

async function markAllRead() {
  try {
    await notificationsAPI.markAllAsRead()
    fetchUnreadCount()
    fetchNotifications()
  } catch (error) {
    console.error('Failed to mark all as read:', error)
  }
}

function formatTime(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  return `${days}d ago`
}

// Close dropdown when clicking outside
function handleClickOutside(event: MouseEvent) {
  const target = event.target as HTMLElement
  if (!target.closest('.notification-bell')) {
    showDropdown.value = false
  }
}

// Setup Pusher real-time notifications
onMounted(() => {
  // Initial fetch
  fetchUnreadCount()
  
  // Subscribe to user's notification channel
  if (authStore.user?.id) {
    const channel = pusher.subscribe(`App.Models.User.${authStore.user.id}`)
    
    // Listen for notification events
    channel.bind('Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', () => {
      // Increment unread count
      unreadCount.value++
      
      // Refresh notifications if dropdown is open
      if (showDropdown.value) {
        fetchNotifications()
      }
    })
  }
  
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  // Unsubscribe from Pusher channel
  if (authStore.user?.id) {
    pusher.unsubscribe(`App.Models.User.${authStore.user.id}`)
  }
  
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.notification-bell {
  position: relative;
}

.bell-button {
  position: relative;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.5rem;
  border-radius: var(--radius-md);
  transition: background 0.15s;
}

.bell-button:hover {
  background: var(--color-gray-100);
}

.bell-icon {
  display: block;
}

.badge {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  background: var(--color-danger);
  color: white;
  font-size: 0.625rem;
  font-weight: 600;
  padding: 0.125rem 0.375rem;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

.dropdown {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  width: 360px;
  max-height: 480px;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  z-index: 1000;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.dropdown-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--color-gray-200);
  flex-shrink: 0;
}

.dropdown-header h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.mark-all-btn {
  background: none;
  border: none;
  color: var(--color-primary);
  font-size: 0.875rem;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
}

.mark-all-btn:hover {
  text-decoration: underline;
}

.dropdown-loading,
.dropdown-empty {
  padding: 2rem;
  text-align: center;
  color: var(--color-gray-500);
}

.notifications-list {
  max-height: 360px;
  overflow-y: auto;
  flex: 1;
}

.notification-item {
  padding: 1rem;
  border-bottom: 1px solid var(--color-gray-100);
  cursor: pointer;
  transition: background 0.15s;
}

.notification-item:hover {
  background: var(--color-gray-50);
}

.notification-item.unread {
  background: #eff6ff;
}

.notification-item.unread:hover {
  background: #dbeafe;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.notification-message {
  margin: 0;
  font-size: 0.9375rem;
  color: var(--color-gray-900);
  line-height: 1.4;
}

.notification-time {
  font-size: 0.8125rem;
  color: var(--color-gray-500);
}

.dropdown-footer {
  padding: 0.75rem 1rem;
  border-top: 1px solid var(--color-gray-200);
  text-align: center;
  flex-shrink: 0;
}

.view-all-link {
  display: block;
  color: var(--color-primary);
  font-size: 0.9375rem;
  font-weight: 500;
  text-decoration: none;
}

.view-all-link:hover {
  text-decoration: underline;
}
</style>
