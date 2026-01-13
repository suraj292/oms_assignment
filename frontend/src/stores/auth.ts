import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'Admin')
  const isStaff = computed(() => user.value?.role === 'Staff')

  function setToken(newToken: string) {
    token.value = newToken
    localStorage.setItem('auth_token', newToken)
  }

  function clearToken() {
    token.value = null
    localStorage.removeItem('auth_token')
  }

  function setUser(userData: User) {
    user.value = userData
  }

  function logout() {
    clearToken()
    user.value = null
  }

  return {
    token,
    user,
    isAuthenticated,
    isAdmin,
    isStaff,
    setToken,
    clearToken,
    setUser,
    logout,
  }
})
