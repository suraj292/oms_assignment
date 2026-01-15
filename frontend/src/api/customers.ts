import apiClient from './client'
import type { Customer, CustomerFormData, PaginatedResponse } from '@/types/models'

export const customersAPI = {
    getAll: (params?: { page?: number; per_page?: number; search?: string }) =>
        apiClient.get<PaginatedResponse<Customer>>('/customers', { params }),

    getById: (id: number) =>
        apiClient.get<{ success: boolean; data: Customer }>(`/customers/${id}`),

    create: (data: CustomerFormData) =>
        apiClient.post<{ success: boolean; data: Customer; message: string }>('/customers', data),

    update: (id: number, data: Partial<CustomerFormData>) =>
        apiClient.put<{ success: boolean; data: Customer; message: string }>(`/customers/${id}`, data),

    delete: (id: number) =>
        apiClient.delete<{ success: boolean; message: string }>(`/customers/${id}`),
}
