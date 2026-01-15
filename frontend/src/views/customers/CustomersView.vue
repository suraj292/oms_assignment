<template>
  <PageContainer 
    title="Customers" 
    subtitle="Manage your customer database"
  >
    <template #actions>
      <button v-if="authStore.isAdmin" @click="showCreateForm = true" class="btn btn-primary">
        <span>➕</span>
        Add Customer
      </button>
    </template>

    <template #filters>
      <div class="filters-grid">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search customers by name, email, or phone..."
          class="form-input"
          @input="debouncedSearch"
        />
      </div>
    </template>

    <LoadingSpinner v-if="loading" message="Loading customers..." />

    <div v-else-if="error" class="alert alert-error">
      {{ error }}
    </div>

    <EmptyState
      v-else-if="customers.length === 0"
      icon="👥"
      title="No customers found"
      message="Start building your customer base by adding your first customer."
    >
      <template #action>
        <button v-if="authStore.isAdmin" @click="showCreateForm = true" class="btn btn-primary">
          Add Your First Customer
        </button>
      </template>
    </EmptyState>

    <div v-else>
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Address</th>
              <th v-if="authStore.isAdmin" class="actions-column">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="customer in customers" :key="customer.id">
              <td>
                <div class="customer-name">{{ customer.name }}</div>
              </td>
              <td>
                <a :href="`mailto:${customer.email}`" class="customer-email">
                  {{ customer.email }}
                </a>
              </td>
              <td>
                <span v-if="customer.phone" class="customer-phone">
                  {{ customer.phone }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span v-if="customer.address" class="customer-address">
                  {{ customer.address }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td v-if="authStore.isAdmin" class="actions-cell">
                <button @click="editCustomer(customer)" class="btn btn-sm btn-secondary">
                  Edit
                </button>
                <button @click="deleteCustomer(customer.id)" class="btn btn-sm btn-danger">
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

    <!-- Create/Edit Modal -->
    <div v-if="showCreateForm || editingCustomer" class="modal-overlay" @click.self="closeForm">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editingCustomer ? 'Edit Customer' : 'Create Customer' }}</h2>
          <button @click="closeForm" class="modal-close">×</button>
        </div>

        <form @submit.prevent="submitForm" class="modal-body">
          <div class="form-group">
            <label class="form-label form-label-required">Name</label>
            <input v-model="form.name" type="text" class="form-input" required />
          </div>

          <div class="form-group">
            <label class="form-label form-label-required">Email</label>
            <input v-model="form.email" type="email" class="form-input" required />
            <p class="form-hint">We'll use this to send order confirmations</p>
          </div>

          <div class="form-group">
            <label class="form-label">Phone</label>
            <input v-model="form.phone" type="tel" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Address</label>
            <textarea v-model="form.address" rows="3" class="form-textarea"></textarea>
          </div>

          <div v-if="formError" class="alert alert-error">
            {{ formError }}
          </div>

          <div class="modal-footer">
            <button type="button" @click="closeForm" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" :disabled="submitting" class="btn btn-primary">
              {{ submitting ? 'Saving...' : 'Save Customer' }}
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
import { customersAPI } from '@/api/customers'
import type { Customer, CustomerFormData, PaginationMeta } from '@/types/models'
import PageContainer from '@/components/layout/PageContainer.vue'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'
import EmptyState from '@/components/common/EmptyState.vue'

const authStore = useAuthStore()

const customers = ref<Customer[]>([])
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const pagination = ref<PaginationMeta | null>(null)

const showCreateForm = ref(false)
const editingCustomer = ref<Customer | null>(null)
const form = ref<CustomerFormData>({
  name: '',
  email: '',
  phone: '',
  address: '',
})
const formError = ref('')
const submitting = ref(false)

let searchTimeout: number

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchCustomers()
  }, 500)
}

async function fetchCustomers(page = 1) {
  loading.value = true
  error.value = ''

  try {
    const params: any = { page, per_page: 15 }
    if (searchQuery.value) params.search = searchQuery.value

    const response = await customersAPI.getAll(params)
    customers.value = response.data.data
    pagination.value = response.data.meta
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load customers'
  } finally {
    loading.value = false
  }
}

function changePage(page: number) {
  fetchCustomers(page)
}

function editCustomer(customer: Customer) {
  editingCustomer.value = customer
  form.value = {
    name: customer.name,
    email: customer.email,
    phone: customer.phone || '',
    address: customer.address || '',
  }
}

async function submitForm() {
  submitting.value = true
  formError.value = ''

  try {
    if (editingCustomer.value) {
      await customersAPI.update(editingCustomer.value.id, form.value)
    } else {
      await customersAPI.create(form.value)
    }
    closeForm()
    fetchCustomers()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Failed to save customer'
  } finally {
    submitting.value = false
  }
}

async function deleteCustomer(id: number) {
  if (!confirm('Are you sure you want to delete this customer?')) return

  try {
    await customersAPI.delete(id)
    fetchCustomers()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to delete customer')
  }
}

function closeForm() {
  showCreateForm.value = false
  editingCustomer.value = null
  form.value = {
    name: '',
    email: '',
    phone: '',
    address: '',
  }
  formError.value = ''
}

onMounted(() => {
  fetchCustomers()
})
</script>

<style scoped>
.filters-grid {
  display: grid;
  grid-template-columns: 1fr;
}

.table-container {
  background: white;
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: 2rem;
}

.customer-name {
  font-weight: 500;
  color: var(--color-gray-900);
}

.customer-email {
  color: var(--color-primary);
  text-decoration: none;
}

.customer-email:hover {
  text-decoration: underline;
}

.customer-phone,
.customer-address {
  color: var(--color-gray-600);
}

.customer-address {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  max-width: 300px;
}

.text-muted {
  color: var(--color-gray-400);
}

.actions-column {
  width: 180px;
  text-align: right;
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

/* Modal Styles */
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
</style>
