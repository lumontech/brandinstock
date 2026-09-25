import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { csrf, http } from '@/api/http'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loaded = ref(false)

  const isAdmin = computed(() => user.value?.role === 'admin')
  const seesEverything = computed(() => user.value?.role === 'admin' || user.value?.role === 'manager')
  const needsTwoFactorSetup = computed(() => !!user.value && user.value.two_factor_required && !user.value.two_factor_enabled)

  async function fetchUser() {
    try {
      const { data } = await http.get<{ data: User }>('/auth/me')
      user.value = data.data
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
  }

  /** Restituisce true se serve il secondo fattore. */
  async function login(email: string, password: string, remember: boolean): Promise<boolean> {
    await csrf()
    const { data } = await http.post<{ two_factor: boolean; user?: User }>('/auth/login', { email, password, remember })
    if (data.two_factor) return true
    user.value = data.user ?? null
    return false
  }

  async function twoFactorChallenge(payload: { code?: string; recovery_code?: string }) {
    const { data } = await http.post<{ user: User }>('/auth/two-factor-challenge', payload)
    user.value = data.user
  }

  async function logout() {
    try {
      await http.post('/auth/logout')
    } finally {
      clear()
    }
  }

  function clear() {
    user.value = null
  }

  return { user, loaded, isAdmin, seesEverything, needsTwoFactorSetup, fetchUser, login, twoFactorChallenge, logout, clear }
})
