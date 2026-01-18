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

    <LoadingSpinner v-if="isLoadingOrders" message="Loading orders..." />

    <div v-else-if="errorMessage" class="alert alert-error">
      {{ errorMessage }}
    </div>

    <EmptyState
      v-else-if="ordersList.length === 0"
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
            <tr v-for="order in ordersList" :key="order.id">
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

      <div v-if="paginationData" class="pagination">
        <button
          @click="changePage(paginationData.current_page - 1)"
          :disabled="paginationData.current_page === 1"
          class="btn btn-secondary"
        >
          ← Previous
        </button>
        <span class="pagination-info">
          Page {{ paginationData.current_page }} of {{ paginationData.last_page }}
          <span class="pagination-total">({{ paginationData.total }} total)</span>
        </span>
        <button
          @click="changePage(paginationData.current_page + 1)"
          :disabled="paginationData.current_page === paginationData.last_page"
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

const ordersList = ref<Order[]>([])
const isLoadingOrders = ref(false)
const errorMessage = ref('')
const searchQuery = ref('')
const statusFilter = ref('')
const paginationData = ref<PaginationMeta | null>(null)

let searchDebounceTimer: number

const debouncedSearch = () => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    fetchOrders()
  }, 500)
}

async function fetchOrders(page = 1) {
  isLoadingOrders.value = true
  errorMessage.value = ''

  try {
    const queryParams: any = { page, per_page: 15 }
    if (searchQuery.value) queryParams.search = searchQuery.value
    if (statusFilter.value) queryParams.status = statusFilter.value

    const apiResponse = await ordersAPI.getAll(queryParams)
    ordersList.value = apiResponse.data.data
    paginationData.value = apiResponse.data.meta
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Failed to load orders'
  } finally {
    isLoadingOrders.value = false
  }
}

function changePage(pageNumber: number) {
  fetchOrders(pageNumber)
}

function viewOrder(orderId: number) {
  router.push(`/orders/${orderId}`)
}

function editOrder(orderId: number) {
  router.push(`/orders/${orderId}/edit`)
}

async function deleteOrder(orderId: number) {
  // simple confirmation for now, might want a nicer modal later
  if (!confirm('Are you sure you want to delete this order?')) return

  try {
    await ordersAPI.delete(orderId)
    fetchOrders()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete order')
  }
}

// badge colors for different order statuses
function getStatusBadgeClass(status: OrderStatus): string {
  const statusColors: Record<OrderStatus, string> = {
    draft: 'badge-warning',
    confirmed: 'badge-info',
    processing: 'badge-info',
    dispatched: 'badge-info',
    delivered: 'badge-success',
    cancelled: 'badge-danger',
  }
  return statusColors[status] || 'badge-info'
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
