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
