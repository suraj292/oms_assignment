import apiClient from './client'
import type { LoginCredentials, RegisterData, AuthResponse, User } from '@/types/auth'

export const authAPI = {
  register: (data: RegisterData) => 
    apiClient.post<AuthResponse>('/auth/register', data),

  login: (credentials: LoginCredentials) => 
    apiClient.post<AuthResponse>('/auth/login', credentials),

  logout: () => 
    apiClient.post('/auth/logout'),

  me: () => 
    apiClient.get<{ success: boolean; data: User }>('/auth/me'),
}
