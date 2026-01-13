import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const user = ref<any>(null)

  function setToken(newToken: string) {
    token.value = newToken
    localStorage.setItem('auth_token', newToken)
  }

  function clearToken() {
    token.value = null
    localStorage.removeItem('auth_token')
  }

  function setUser(userData: any) {
    user.value = userData
  }

  function logout() {
    clearToken()
    user.value = null
  }

  return {
    token,
    user,
    setToken,
    clearToken,
    setUser,
    logout,
  }
})
