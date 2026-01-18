<template>
  <PageContainer 
    title="Products" 
    subtitle="Manage your product inventory"
  >
    <template #actions>
      <button v-if="authStore.isAdmin" @click="showCreateForm = true" class="btn btn-primary">
        <span>➕</span>
        Add Product
      </button>
    </template>

    <template #filters>
      <div class="filters-grid">
        <div class="filter-item">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search products..."
            class="form-input"
            @input="debouncedSearch"
          />
        </div>
        <div class="filter-item">
          <select v-model="statusFilter" @change="() => fetchProducts()" class="form-select">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </template>

    <LoadingSpinner v-if="loading" message="Loading products..." />

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <EmptyState
      v-else-if="products.length === 0"
      icon="📦"
      title="No products found"
      message="Get started by adding your first product to the inventory."
    >
      <template #action>
        <button v-if="authStore.isAdmin" @click="showCreateForm = true" class="btn btn-primary">
          Add Your First Product
        </button>
      </template>
    </EmptyState>

    <div v-else>
      <div class="products-grid">
        <div v-for="product in products" :key="product.id" class="product-card card">
          <img 
            v-if="product.image" 
            :src="product.image" 
            :alt="product.name" 
            class="product-image" 
          />
          <div v-else class="product-image-placeholder">
            <span>📦</span>
          </div>
          
          <div class="card-body">
            <h3 class="product-name">{{ product.name }}</h3>
            <p class="product-description">{{ product.description || 'No description' }}</p>
            
            <div class="product-meta">
              <div class="product-price">${{ product.price }}</div>
              <div class="product-stock">
                <span class="stock-label">Stock:</span>
                <span :class="['stock-value', { 'low-stock': product.stock < 10 }]">
                  {{ product.stock }}
                </span>
              </div>
              <span :class="['badge', product.status === 'active' ? 'badge-success' : 'badge-danger']">
                {{ product.status }}
              </span>
            </div>
          </div>

          <div class="card-footer">
            <button @click="editProduct(product)" class="btn btn-sm btn-secondary">
              Edit
            </button>
            <button 
              v-if="authStore.isAdmin" 
              @click="deleteProduct(product.id)" 
              class="btn btn-sm btn-danger"
            >
              Delete
            </button>
          </div>
        </div>
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

    <!-- Create/Edit Modal -->
    <div v-if="showCreateForm || editingProduct" class="modal-overlay" @click.self="closeForm">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editingProduct ? 'Edit Product' : 'Create Product' }}</h2>
          <button @click="closeForm" class="modal-close">×</button>
        </div>

        <form @submit.prevent="submitForm" class="modal-body">
          <div class="form-group">
            <label class="form-label form-label-required">Name</label>
            <input v-model="form.name" type="text" class="form-input" required />
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea v-model="form.description" rows="3" class="form-textarea"></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label form-label-required">Price</label>
              <input v-model="form.price" type="number" step="0.01" class="form-input" required />
            </div>

            <div class="form-group">
              <label class="form-label form-label-required">Stock</label>
              <input v-model="form.stock" type="number" class="form-input" required />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label form-label-required">Status</label>
            <select v-model="form.status" class="form-select" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Product Image</label>
            <input 
              type="file" 
              accept="image/*" 
              @change="handleImageChange" 
              class="form-input"
            />
            <p class="form-hint">Maximum file size: 2MB</p>
            <img 
              v-if="imagePreview" 
              :src="imagePreview" 
              alt="Preview" 
              class="image-preview" 
            />
          </div>

          <div v-if="formError" class="alert alert-error">
            {{ formError }}
          </div>

          <div class="modal-footer">
            <button type="button" @click="closeForm" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" :disabled="submitting" class="btn btn-primary">
              {{ submitting ? 'Saving...' : 'Save Product' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </PageContainer>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { productsAPI } from '@/api/products'
import type { Product, ProductFormData, PaginationMeta } from '@/types/models'
import PageContainer from '@/components/layout/PageContainer.vue'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'
import EmptyState from '@/components/common/EmptyState.vue'

const authStore = useAuthStore()

const products = ref<Product[]>([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const statusFilter = ref('')
const pagination = ref<PaginationMeta | null>(null)

const showCreateForm = ref(false)
const editingProduct = ref<Product | null>(null)
const form = ref<ProductFormData>({
  name: '',
  description: '',
  price: '',
  stock: '',
  status: 'active',
  image: null,
})
const imagePreview = ref('')
const formError = ref('')
const submitting = ref(false)

let searchTimeout: number

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchProducts()
  }, 500)
}

async function fetchProducts(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const params: any = { page, per_page: 12 }
    if (searchQuery.value) params.search = searchQuery.value
    if (statusFilter.value) params.status = statusFilter.value

    const response = await productsAPI.getAll(params)
    products.value = response.data.data
    pagination.value = response.data.meta
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load products'
  } finally {
    loading.value = false
  }
}

function changePage(page: number) {
  fetchProducts(page)
}

function editProduct(product: Product) {
  editingProduct.value = product
  form.value = {
    name: product.name,
    description: product.description || '',
    price: product.price,
    stock: product.stock,
    status: product.status,
    image: null,
  }
  imagePreview.value = product.image || ''
}

function handleImageChange(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    form.value.image = file
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

async function submitForm() {
  submitting.value = true
  formError.value = ''

  try {
    if (editingProduct.value) {
      await productsAPI.update(editingProduct.value.id, form.value)
    } else {
      await productsAPI.create(form.value)
    }
    closeForm()
    fetchProducts()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Failed to save product'
  } finally {
    submitting.value = false
  }
}

async function deleteProduct(id: number) {
  if (!confirm('Are you sure you want to delete this product?')) return

  try {
    await productsAPI.delete(id)
    fetchProducts()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete product')
  }
}

function closeForm() {
  showCreateForm.value = false
  editingProduct.value = null
  form.value = {
    name: '',
    description: '',
    price: '',
    stock: '',
    status: 'active',
    image: null,
  }
  imagePreview.value = ''
  formError.value = ''
}

onMounted(() => {
  fetchProducts()
})
</script>

<style scoped>
.filters-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 1rem;
}

.filter-item {
  min-width: 0;
}

.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.product-card {
  display: flex;
  flex-direction: column;
  transition: box-shadow 0.15s;
}

.product-card:hover {
  box-shadow: var(--shadow-md);
}

.product-image {
  width: 100%;
  height: 200px;
  object-fit: cover;
}

.product-image-placeholder {
  width: 100%;
  height: 200px;
  background: var(--color-gray-100);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  opacity: 0.3;
}

.product-name {
  margin: 0 0 0.5rem;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-gray-900);
}

.product-description {
  color: var(--color-gray-600);
  font-size: 0.9375rem;
  margin: 0 0 1rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.8em;
}

.product-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
}

.product-price {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-success);
}

.product-stock {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.9375rem;
}

.stock-label {
  color: var(--color-gray-500);
}

.stock-value {
  font-weight: 600;
  color: var(--color-gray-700);
}

.stock-value.low-stock {
  color: var(--color-danger);
}

.card-footer {
  display: flex;
  gap: 0.5rem;
  margin-top: auto;
}

.card-footer .btn {
  flex: 1;
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

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-content {
  background: white;
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--color-gray-200);
}

.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--color-gray-400);
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  transition: all 0.15s;
}

.modal-close:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-600);
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
}

.modal-footer {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-end;
  padding-top: 1.5rem;
  margin-top: auto;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.image-preview {
  margin-top: 1rem;
  max-width: 200px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-gray-200);
}
</style>
