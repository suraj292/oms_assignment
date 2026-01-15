import apiClient from './client'
import type { Product, ProductFormData, PaginatedResponse } from '@/types/models'

export const productsAPI = {
    getAll: (params?: { page?: number; per_page?: number; search?: string; status?: string }) =>
        apiClient.get<PaginatedResponse<Product>>('/products', { params }),

    getById: (id: number) =>
        apiClient.get<{ success: boolean; data: Product }>(`/products/${id}`),

    create: (data: ProductFormData) => {
        const formData = new FormData()
        formData.append('name', data.name)
        formData.append('price', data.price.toString())
        formData.append('stock', data.stock.toString())
        formData.append('status', data.status)
        if (data.description) formData.append('description', data.description)
        if (data.image) formData.append('image', data.image)

        return apiClient.post<{ success: boolean; data: Product; message: string }>(
            '/products',
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            }
        )
    },

    update: (id: number, data: Partial<ProductFormData>) => {
        const formData = new FormData()
        if (data.name) formData.append('name', data.name)
        if (data.price !== undefined) formData.append('price', data.price.toString())
        if (data.stock !== undefined) formData.append('stock', data.stock.toString())
        if (data.status) formData.append('status', data.status)
        if (data.description !== undefined) formData.append('description', data.description)
        if (data.image) formData.append('image', data.image)

        return apiClient.post<{ success: boolean; data: Product; message: string }>(
            `/products/${id}?_method=PUT`,
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            }
        )
    },

    delete: (id: number) =>
        apiClient.delete<{ success: boolean; message: string }>(`/products/${id}`),
}
