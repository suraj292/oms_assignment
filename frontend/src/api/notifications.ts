import apiClient from './client'
import type { Notification, PaginatedResponse } from '@/types/models'

export const notificationsAPI = {
    getAll: (params?: { page?: number; per_page?: number }) =>
        apiClient.get<PaginatedResponse<Notification>>('/notifications', { params }),

    getUnreadCount: () =>
        apiClient.get<{ success: boolean; data: { count: number } }>('/notifications/unread-count'),

    markAsRead: (id: string) =>
        apiClient.patch<{ success: boolean; data: Notification; message: string }>(`/notifications/${id}/read`),

    markAllAsRead: () =>
        apiClient.post<{ success: boolean; message: string }>('/notifications/mark-all-read'),
}
