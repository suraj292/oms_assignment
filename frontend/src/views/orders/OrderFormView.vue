<template>
  <PageContainer 
    :title="isEditing ? 'Edit Order' : 'Create Order'" 
    :subtitle="isEditing ? `Order ${order?.order_number}` : 'Create a new customer order'"
  >
    <LoadingSpinner v-if="loadingOrder" message="Loading order..." />

    <div v-else-if="loadError" class="alert alert-error">
      {{ loadError }}
    </div>

    <form v-else @submit.prevent="submitForm" class="order-form">
      <div class="form-section card">
        <div class="card-header">
          <h3>Order Information</h3>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label form-label-required">Customer</label>
            <select 
              v-model="form.customer_id" 
              class="form-select" 
              required
              :disabled="!canEdit"
            >
              <option value="">Select a customer</option>
              <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                {{ customer.name }} ({{ customer.email }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea 
              v-model="form.notes" 
              rows="3" 
              class="form-textarea"
              placeholder="Add any special instructions or notes..."
              :disabled="!canEdit"
            ></textarea>
          </div>
        </div>
      </div>

      <div class="form-section card">
        <div class="card-header">
          <h3>Order Items</h3>
        </div>
        <div class="card-body">
          <div v-for="(item, index) in form.items" :key="index" class="order-item">
            <div class="item-fields">
              <div class="form-group">
                <label class="form-label form-label-required">Product</label>
                <select 
                  v-model="item.product_id" 
                  class="form-select" 
                  required
                  :disabled="!canEdit"
                  @change="updateItemPrice(index)"
                >
                  <option value="">Select a product</option>
                  <option v-for="product in products" :key="product.id" :value="product.id">
                    {{ product.name }} - ${{ product.price }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label form-label-required">Quantity</label>
                <input 
                  v-model.number="item.quantity" 
                  type="number" 
                  min="1" 
                  class="form-input" 
                  required
                  :disabled="!canEdit"
                  @input="calculateItemSubtotal(index)"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Price</label>
                <input 
                  :value="item.price || '0.00'" 
                  type="text" 
                  class="form-input" 
                  disabled
                />
              </div>

              <div class="form-group">
                <label class="form-label">Subtotal</label>
                <input 
                  :value="item.subtotal || '0.00'" 
                  type="text" 
                  class="form-input" 
                  disabled
                />
              </div>

              <div class="item-actions">
                <button 
                  v-if="canEdit && form.items.length > 1"
                  type="button" 
                  @click="removeItem(index)" 
                  class="btn btn-sm btn-danger"
                  title="Remove item"
                >
                  ×
                </button>
              </div>
            </div>
          </div>

          <button 
            v-if="canEdit"
            type="button" 
            @click="addItem" 
            class="btn btn-secondary"
          >
            + Add Item
          </button>
        </div>
      </div>

      <div class="order-summary card">
        <div class="card-body">
          <div class="summary-row">
            <span class="summary-label">Total Items:</span>
            <span class="summary-value">{{ form.items.length }}</span>
          </div>
          <div class="summary-row total-row">
            <span class="summary-label">Order Total:</span>
            <span class="summary-value">${{ orderTotal }}</span>
          </div>
        </div>
      </div>

      <div v-if="formError" class="alert alert-error">
        {{ formError }}
      </div>

      <div class="form-actions">
        <button type="button" @click="$router.back()" class="btn btn-secondary">
          Cancel
        </button>
        <button 
          v-if="canEdit"
          type="submit" 
          :disabled="submitting" 
          class="btn btn-primary"
        >
          {{ submitting ? 'Saving...' : (isEditing ? 'Update Order' : 'Create Order') }}
        </button>
      </div>
    </form>
  </PageContainer>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ordersAPI } from '@/api/orders'
import { customersAPI } from '@/api/customers'
import { productsAPI } from '@/api/products'
import type { Order, Customer, Product } from '@/types/models'
import PageContainer from '@/components/layout/PageContainer.vue'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'

const router = useRouter()
const route = useRoute()

const isEditing = computed(() => !!route.params.id)
const orderId = computed(() => Number(route.params.id))

const order = ref<Order | null>(null)
const customers = ref<Customer[]>([])
const products = ref<Product[]>([])

const loadingOrder = ref(false)
const loadError = ref('')
const formError = ref('')
const submitting = ref(false)

const form = ref({
  customer_id: '' as number | string,
  notes: '',
  items: [
    {
      product_id: '' as number | string,
      quantity: 1 as number | string,
      price: '0.00',
      subtotal: '0.00',
    }
  ]
})

const canEdit = computed(() => {
  if (!isEditing.value) return true
  return order.value?.is_editable ?? false
})

const orderTotal = computed(() => {
  return form.value.items
    .reduce((sum, item) => sum + parseFloat(item.subtotal || '0'), 0)
    .toFixed(2)
})

async function loadOrder() {
  if (!isEditing.value) return

  loadingOrder.value = true
  loadError.value = ''

  try {
    const response = await ordersAPI.getById(orderId.value)
    order.value = response.data.data


    form.value.customer_id = order.value.customer_id
    form.value.notes = order.value.notes || ''
    form.value.items = order.value.items.map(item => ({
      product_id: item.product_id,
      quantity: item.quantity,
      price: item.price,
      subtotal: item.subtotal,
    }))
  } catch (err: any) {
    loadError.value = err.response?.data?.message || 'Failed to load order'
  } finally {
    loadingOrder.value = false
  }
}

async function loadCustomers() {
  try {
    const response = await customersAPI.getAll({ per_page: 1000 })
    customers.value = response.data.data
  } catch (err) {
    console.error('Failed to load customers:', err)
  }
}

async function loadProducts() {
  try {
    const response = await productsAPI.getAll({ per_page: 1000, status: 'active' })
    products.value = response.data.data
  } catch (err) {
    console.error('Failed to load products:', err)
  }
}

function addItem() {
  form.value.items.push({
    product_id: '',
    quantity: 1,
    price: '0.00',
    subtotal: '0.00',
  })
}

function removeItem(index: number) {
  form.value.items.splice(index, 1)
}

function updateItemPrice(index: number) {
  const item = form.value.items[index]
  if (!item) return
  
  const product = products.value.find(p => p.id === Number(item.product_id))
  
  if (product) {
    item.price = product.price
    calculateItemSubtotal(index)
  }
}

function calculateItemSubtotal(index: number) {
  const item = form.value.items[index]
  if (!item) return
  
  const price = parseFloat(item.price || '0')
  const quantity = Number(item.quantity) || 0
  item.subtotal = (price * quantity).toFixed(2)
}

async function submitForm() {
  submitting.value = true
  formError.value = ''

  try {
    const data = {
      customer_id: Number(form.value.customer_id),
      notes: form.value.notes || undefined,
      items: form.value.items.map(item => ({
        product_id: Number(item.product_id),
        quantity: Number(item.quantity),
      }))
    }

    if (isEditing.value) {
      await ordersAPI.update(orderId.value, data)
    } else {
      await ordersAPI.create(data)
    }

    router.push('/orders')
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Failed to save order'
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await Promise.all([
    loadCustomers(),
    loadProducts(),
    loadOrder(),
  ])
})
</script>

<style scoped>
.order-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-section h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.order-item {
  padding: 1rem;
  background: var(--color-gray-50);
  border-radius: var(--radius-md);
  margin-bottom: 1rem;
}

.item-fields {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr auto;
  gap: 1rem;
  align-items: end;
}

.item-actions {
  display: flex;
  align-items: flex-end;
  padding-bottom: 0.5rem;
}

.order-summary {
  background: var(--color-gray-50);
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
}

.summary-row.total-row {
  border-top: 2px solid var(--color-gray-300);
  margin-top: 0.5rem;
  padding-top: 1rem;
}

.summary-label {
  font-weight: 500;
  color: var(--color-gray-700);
}

.summary-value {
  font-weight: 600;
  color: var(--color-gray-900);
}

.total-row .summary-value {
  font-size: 1.25rem;
  color: var(--color-success);
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  padding-top: 1rem;
}

@media (max-width: 768px) {
  .item-fields {
    grid-template-columns: 1fr;
  }
}
</style>
