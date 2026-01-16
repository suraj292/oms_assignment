<template>
  <PageContainer 
    :title="order ? `Order ${order.order_number}` : 'Order Details'"
    :subtitle="order ? `Created ${formatDate(order.created_at)}` : ''"
  >
    <template #actions>
      <button 
        v-if="order?.is_editable"
        @click="$router.push(`/orders/${order.id}/edit`)" 
        class="btn btn-secondary"
      >
        Edit Order
      </button>
      <button @click="$router.push('/orders')" class="btn btn-secondary">
        Back to Orders
      </button>
    </template>

    <LoadingSpinner v-if="loading" message="Loading order..." />

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <div v-else-if="order" class="order-details">
      <!-- Status and Actions -->
      <div class="status-section card">
        <div class="card-body">
          <div class="status-header">
            <div>
              <h3>Order Status</h3>
              <span :class="['badge', 'badge-lg', getStatusBadgeClass(order.status)]">
                {{ order.status_label }}
              </span>
            </div>
            
            <div v-if="!order.is_final && order.allowed_next_statuses.length > 0" class="status-actions">
              <label class="form-label">Update Status:</label>
              <div class="status-buttons">
                <button
                  v-for="nextStatus in order.allowed_next_statuses"
                  :key="nextStatus.value"
                  @click="updateStatus(nextStatus.value)"
                  class="btn btn-sm btn-primary"
                  :disabled="updatingStatus"
                >
                  {{ nextStatus.label }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Customer Information -->
      <div class="info-section card">
        <div class="card-header">
          <h3>Customer Information</h3>
        </div>
        <div class="card-body">
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">Name:</span>
              <span class="info-value">{{ order.customer?.name }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Email:</span>
              <a :href="`mailto:${order.customer?.email}`" class="info-value info-link">
                {{ order.customer?.email }}
              </a>
            </div>
            <div class="info-item">
              <span class="info-label">Phone:</span>
              <span class="info-value">{{ order.customer?.phone || '—' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Address:</span>
              <span class="info-value">{{ order.customer?.address || '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Items -->
      <div class="items-section card">
        <div class="card-header">
          <h3>Order Items</h3>
        </div>
        <div class="card-body">
          <div class="table-container">
            <table class="table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Price</th>
                  <th>Quantity</th>
                  <th>Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in order.items" :key="item.id">
                  <td>{{ item.product_name }}</td>
                  <td>${{ item.price }}</td>
                  <td>{{ item.quantity }}</td>
                  <td class="item-subtotal">${{ item.subtotal }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="total-row">
                  <td colspan="3" class="total-label">Total</td>
                  <td class="total-value">${{ order.total }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="order.notes" class="notes-section card">
        <div class="card-header">
          <h3>Notes</h3>
        </div>
        <div class="card-body">
          <p class="notes-text">{{ order.notes }}</p>
        </div>
      </div>

      <!-- File Upload Section -->
      <div class="upload-section card">
        <div class="card-header">
          <h3>Upload Documents</h3>
        </div>
        <div class="card-body">
          <ChunkedFileUpload
            target-type="order_document"
            :target-id="order.id"
            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xlsx,.xls,.csv"
            button-text="Upload Document"
            @upload-complete="handleUploadComplete"
            @upload-error="handleUploadError"
          />
        </div>
      </div>
    </div>
  </PageContainer>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ordersAPI } from '@/api/orders'
import type { Order, OrderStatus } from '@/types/models'
import PageContainer from '@/components/layout/PageContainer.vue'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'
import ChunkedFileUpload from '@/components/common/ChunkedFileUpload.vue'

const route = useRoute()

const order = ref<Order | null>(null)
const loading = ref(false)
const error = ref('')
const updatingStatus = ref(false)

const orderId = Number(route.params.id)

async function loadOrder() {
  loading.value = true
  error.value = ''

  try {
    const response = await ordersAPI.getById(orderId)
    order.value = response.data.data
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load order'
  } finally {
    loading.value = false
  }
}

async function updateStatus(newStatus: OrderStatus) {
  if (!confirm(`Are you sure you want to change the status to ${newStatus}?`)) return

  updatingStatus.value = true

  try {
    const response = await ordersAPI.updateStatus(orderId, newStatus)
    order.value = response.data.data
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to update status')
  } finally {
    updatingStatus.value = false
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
    month: 'long', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function handleUploadComplete(fileUrl: string) {
  console.log('File uploaded successfully:', fileUrl)
  loadOrder()
}

function handleUploadError(error: Error) {
  console.error('Upload error:', error)
  alert('Failed to upload file: ' + error.message)
}

onMounted(() => {
  loadOrder()
})
</script>

<style scoped>
.order-details {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.status-section {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.status-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.status-header h3 {
  margin: 0 0 0.75rem;
  font-size: 1.125rem;
  font-weight: 600;
}

.badge-lg {
  font-size: 1rem;
  padding: 0.5rem 1rem;
}

.status-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.status-actions .form-label {
  margin: 0;
  font-size: 0.875rem;
}

.status-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.info-section h3,
.items-section h3,
.notes-section h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.info-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-gray-500);
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.info-value {
  font-size: 1rem;
  color: var(--color-gray-900);
}

.info-link {
  color: var(--color-primary);
  text-decoration: none;
}

.info-link:hover {
  text-decoration: underline;
}

.table-container {
  overflow-x: auto;
}

.item-subtotal {
  font-weight: 500;
}

.total-row {
  font-weight: 600;
  background: var(--color-gray-50);
}

.total-label {
  text-align: right;
  font-size: 1.0625rem;
}

.total-value {
  font-size: 1.25rem;
  color: var(--color-success);
}

.notes-text {
  margin: 0;
  color: var(--color-gray-700);
  line-height: 1.6;
  white-space: pre-wrap;
}

@media (max-width: 768px) {
  .status-header {
    flex-direction: column;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
