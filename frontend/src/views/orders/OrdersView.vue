<template>
  <PageContainer 
    title="Orders" 
    subtitle="Manage customer orders and track their status"
  >
    <template #actions>
      <button @click="$router.push('/orders/create')" class="btn btn-primary">
        <span>➕</span>
        Create Order
      </button>
    </template>

    <template #filters>
      <div class="filters-grid">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search by order number or customer..."
          class="form-input"
          @input="debouncedSearch"
        />
        <select v-model="statusFilter" @change="() => fetchOrders()" class="form-select">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="confirmed">Confirmed</option>
          <option value="processing">Processing</option>
          <option value="dispatched">Dispatched</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
    </template>

    <LoadingSpinner v-if="loading" message="Loading orders..." />

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <EmptyState
      v-else-if="orders.length === 0"
      icon="📋"
      title="No orders found"
      message="Start by creating your first order."
    >
      <template #action>
        <button @click="$router.push('/orders/create')" class="btn btn-primary">
          Create Your First Order
        </button>
      </template>
    </EmptyState>

    <div v-else>
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Items</th>
              <th>Total</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td>
                <router-link :to="`/orders/${order.id}`" class="order-number">
                  {{ order.order_number }}
                </router-link>
              </td>
              <td>
                <div class="customer-info">
                  <div class="customer-name">{{ order.customer?.name }}</div>
                  <div class="customer-email">{{ order.customer?.email }}</div>
                </div>
              </td>
              <td>{{ order.items_count }} item{{ order.items_count !== 1 ? 's' : '' }}</td>
              <td class="order-total">${{ order.total }}</td>
              <td>
                <span :class="['badge', getStatusBadgeClass(order.status)]">
                  {{ order.status_label }}
                </span>
              </td>
              <td class="order-date">{{ formatDate(order.created_at) }}</td>
              <td class="actions-cell">
                <button 
                  @click="viewOrder(order.id)" 
                  class="btn btn-sm btn-secondary"
                  title="View details"
                >
                  View
                </button>
                <button 
                  v-if="order.is_editable"
                  @click="editOrder(order.id)" 
                  class="btn btn-sm btn-secondary"
                  title="Edit order"
                >
                  Edit
                </button>
                <button 
                  v-if="order.is_editable && authStore.isAdmin"
                  @click="deleteOrder(order.id)" 
                  class="btn btn-sm btn-danger"
                  title="Delete order"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
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
          <span class="pagination-total">({{ pagination.total }} total)</span>
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
import { useAuthStore } from '@/stores/auth'
import { ordersAPI } from '@/api/orders'
import type { Order, PaginationMeta, OrderStatus } from '@/types/models'
import PageContainer from '@/components/layout/PageContainer.vue'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'
import EmptyState from '@/components/common/EmptyState.vue'

const router = useRouter()
const authStore = useAuthStore()

const orders = ref<Order[]>([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const statusFilter = ref('')
const pagination = ref<PaginationMeta | null>(null)

let searchTimeout: number

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchOrders()
  }, 500)
}

async function fetchOrders(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const params: any = { page, per_page: 15 }
    if (searchQuery.value) params.search = searchQuery.value
    if (statusFilter.value) params.status = statusFilter.value

    const response = await ordersAPI.getAll(params)
    orders.value = response.data.data
    pagination.value = response.data.meta
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load orders'
  } finally {
    loading.value = false
  }
}

function changePage(page: number) {
  fetchOrders(page)
}

function viewOrder(id: number) {
  router.push(`/orders/${id}`)
}

function editOrder(id: number) {
  router.push(`/orders/${id}/edit`)
}

async function deleteOrder(id: number) {
  if (!confirm('Are you sure you want to delete this order?')) return

  try {
    await ordersAPI.delete(id)
    fetchOrders()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete order')
  }
}

function getStatusBadgeClass(status: OrderStatus): string {
  const classes: Record<OrderStatus, string> = {
    draft: 'badge-warning',
    confirmed: 'badge-info',
    processing: 'badge-info',
    dispatched: 'badge-info',
    delivered: 'badge-success',
    cancelled: 'badge-danger',
  }
  return classes[status] || 'badge-info'
}

function formatDate(dateString: string): string {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  })
}

onMounted(() => {
  fetchOrders()
})
</script>

<style scoped>
.filters-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 1rem;
}

.table-container {
  background: white;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 2rem;
}

.order-number {
  font-weight: 600;
  color: var(--color-primary);
  text-decoration: none;
}

.order-number:hover {
  text-decoration: underline;
}

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.customer-name {
  font-weight: 500;
  color: var(--color-gray-900);
}

.customer-email {
  font-size: 0.875rem;
  color: var(--color-gray-500);
}

.order-total {
  font-weight: 600;
  color: var(--color-success);
  font-size: 1.0625rem;
}

.order-date {
  color: var(--color-gray-600);
  font-size: 0.9375rem;
}

.actions-cell {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem 0;
}

.pagination-info {
  color: var(--color-gray-700);
  font-weight: 500;
}

.pagination-total {
  color: var(--color-gray-500);
  font-weight: 400;
  font-size: 0.9375rem;
}
</style>
