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
          <!-- Success Message -->
          <div v-if="uploadSuccess" class="alert alert-success upload-success">
            ✓ Document uploaded successfully!
          </div>
          
          <!-- Show upload component only if no documents exist -->
          <ChunkedFileUpload
            v-if="!order.documents || order.documents.length === 0"
            target-type="order_document"
            :target-id="order.id"
            button-text="Upload Document"
            @upload-complete="handleUploadComplete"
            @upload-error="handleUploadError"
          />

          <!-- Uploaded Documents List -->
          <div v-if="order.documents && order.documents.length > 0" class="documents-list">
            <h4 class="documents-title">Uploaded Documents ({{ order.documents.length }})</h4>
            <div class="documents-grid">
              <div v-for="doc in order.documents" :key="doc.id" class="document-item">
                <div class="document-icon">
                  📄
                </div>
                <div class="document-info">
                  <a :href="doc.url" target="_blank" class="document-name" :title="doc.original_name">
                    {{ doc.original_name }}
                  </a>
                  <div class="document-meta">
                    <span class="document-size">{{ formatFileSize(doc.file_size) }}</span>
                    <span class="document-date">{{ formatDate(doc.created_at) }}</span>
                  </div>
                </div>
                <div class="document-actions">
                  <a :href="doc.url" download class="btn btn-sm btn-primary">
                    Download
                  </a>
                  <button @click.prevent.stop="deleteDocument(doc.id)" class="btn btn-sm btn-danger" title="Delete document">
                    🗑️
                  </button>
                </div>
              </div>
            </div>
          </div>
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
const uploadSuccess = ref(false)

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

function handleUploadComplete() {
  uploadSuccess.value = true
  loadOrder()
  
  // Auto-dismiss success message after 5 seconds
  setTimeout(() => {
    uploadSuccess.value = false
  }, 5000)
}

function handleUploadError(error: Error) {
  console.error('Upload error:', error)
  alert('Failed to upload file: ' + error.message)
}

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

async function deleteDocument(documentId: number) {
  // Use native browser confirmation
  const confirmed = window.confirm('Are you sure you want to delete this document? This action cannot be undone.')
  
  if (!confirmed) {
    return
  }

  try {
    const token = localStorage.getItem('auth_token')
    
    const response = await fetch(`/api/orders/${order.value?.id}/documents/${documentId}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    if (!response.ok) {
      const error = await response.json()
      throw new Error(error.message || 'Failed to delete document')
    }
    
    // Reload order to refresh documents list
    await loadOrder()
  } catch (err: any) {
    console.error('Delete error:', err)
    alert('Failed to delete document: ' + (err.message || 'Unknown error'))
  }
}

onMounted(() => {
  loadOrder()
  
  // Initialize Lucide icons
  if (window.lucide) {
    window.lucide.createIcons()
  }
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

.upload-success {
  margin-bottom: 1rem;
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Documents List */
.documents-list {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid var(--color-gray-200);
}

.documents-title {
  margin: 0 0 1rem;
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-gray-700);
}

.documents-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.document-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-md);
  transition: all 0.2s;
}

.document-item:hover {
  background: white;
  box-shadow: var(--shadow-sm);
}

.document-icon {
  font-size: 2rem;
  flex-shrink: 0;
}

.document-info {
  flex: 1;
  min-width: 0;
}

.document-name {
  display: block;
  font-weight: 500;
  color: var(--color-gray-900);
  text-decoration: none;
  margin-bottom: 0.25rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.document-name:hover {
  color: var(--color-primary);
  text-decoration: underline;
}

.document-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.8125rem;
  color: var(--color-gray-500);
}

.document-size::after {
  content: '•';
  margin-left: 1rem;
}

.document-actions {
  display: flex;
  gap: 0.5rem;
  flex-shrink: 0;
}

.document-actions .icon-sm {
  width: 16px;
  height: 16px;
}

@media (max-width: 768px) {
  .status-header {
    flex-direction: column;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .document-item {
    flex-wrap: wrap;
  }

  .document-actions {
    width: 100%;
  }
}
</style>
