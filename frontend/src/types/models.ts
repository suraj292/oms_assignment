export interface Product {
    id: number
    name: string
    description: string | null
    price: string
    stock: number
    status: 'active' | 'inactive'
    image: string | null
    created_at: string
    updated_at: string
}

export interface ProductFormData {
    name: string
    description?: string
    price: number | string
    stock: number | string
    status: 'active' | 'inactive'
    image?: File | null
}

export interface Customer {
    id: number
    name: string
    email: string
    phone: string | null
    address: string | null
    created_at: string
    updated_at: string
}

export interface CustomerFormData {
    name: string
    email: string
    phone?: string
    address?: string
}

export interface PaginationMeta {
    current_page: number
    from: number
    last_page: number
    per_page: number
    to: number
    total: number
}

export interface PaginatedResponse<T> {
    data: T[]
    links: {
        first: string
        last: string
        prev: string | null
        next: string | null
    }
    meta: PaginationMeta
}

export type OrderStatus = 'draft' | 'confirmed' | 'processing' | 'dispatched' | 'delivered' | 'cancelled'

export interface OrderStatusOption {
  value: OrderStatus
  label: string
}

export interface OrderItem {
  id?: number
  product_id: number
  product_name: string
  price: string
  quantity: number
  subtotal: string
}

export interface Order {
  id: number
  order_number: string
  customer_id: number
  customer?: Customer
  status: OrderStatus
  status_label: string
  total: string
  notes: string | null
  items: OrderItem[]
  items_count: number
  is_editable: boolean
  is_final: boolean
  allowed_next_statuses: OrderStatusOption[]
  created_at: string
  updated_at: string
}

export interface OrderFormData {
  customer_id: number | string
  notes?: string
  items: Array<{
    product_id: number | string
    quantity: number | string
  }>
}

// Notification types
export interface Notification {
  id: string
  type: string
  data: {
    type: 'order_created' | 'order_status_changed'
    order_id: number
    order_number: string
    message: string
    total?: string
    old_status?: string
    new_status?: string
  }
  read_at: string | null
  created_at: string
}
