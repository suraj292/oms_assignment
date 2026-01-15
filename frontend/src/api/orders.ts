import apiClient from './client'
import type { Order, OrderFormData, PaginatedResponse, OrderStatus } from '@/types/models'

export const ordersAPI = {
    getAll: (params?: { page?: number; per_page?: number; search?: string; status?: string; customer_id?: number }) =>
        apiClient.get<PaginatedResponse<Order>>('/orders', { params }),

    getById: (id: number) =>
        apiClient.get<{ success: boolean; data: Order }>(`/orders/${id}`),

    create: (data: OrderFormData) =>
        apiClient.post<{ success: boolean; data: Order; message: string }>('/orders', data),

    update: (id: number, data: Partial<OrderFormData>) =>
        apiClient.put<{ success: boolean; data: Order; message: string }>(`/orders/${id}`, data),

    updateStatus: (id: number, status: OrderStatus) =>
        apiClient.patch<{ success: boolean; data: Order; message: string }>(`/orders/${id}/status`, { status }),

    delete: (id: number) =>
        apiClient.delete<{ success: boolean; message: string }>(`/orders/${id}`),
}
