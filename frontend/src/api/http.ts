import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'

/**
 * Autenticazione con cookie di sessione httpOnly (Laravel Sanctum SPA):
 * nessun token viene mai salvato in localStorage/sessionStorage, quindi un
 * eventuale XSS non può esfiltrare credenziali. Il token CSRF viaggia nel
 * cookie XSRF-TOKEN e viene rimandato da axios nell'header X-XSRF-TOKEN.
 */
export const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 20000,
})

export function csrf() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

http.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ code?: string }>) => {
    const status = error.response?.status
    const config = error.config as RetriableConfig | undefined

    // Token CSRF scaduto: rigenera il cookie e ripeti una sola volta.
    if (status === 419 && config && !config._csrfRetried) {
      config._csrfRetried = true
      await csrf()
      return http(config)
    }

    // Sessione scaduta durante l'uso: torna al login. Se l'utente non era
    // autenticato ci pensa già la guardia del router (evita loop di redirect).
    if (status === 401) {
      const auth = useAuthStore()
      const wasLoggedIn = !!auth.user
      auth.clear()
      if (wasLoggedIn && router.currentRoute.value.name !== 'login') {
        void router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      }
    }

    if (status === 403 && error.response?.data?.code === 'two_factor_setup_required') {
      await router.push({ name: 'security' })
    }

    return Promise.reject(error)
  },
)

/** Estrae un messaggio leggibile da un errore API (validazione Laravel inclusa). */
export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    if (data?.errors) {
      return Object.values(data.errors).flat().join(' ')
    }
    if (error.response?.status === 429) return 'Troppi tentativi, riprova tra un minuto.'
    if (data?.message) return data.message
  }
  return 'Si è verificato un errore imprevisto.'
}
