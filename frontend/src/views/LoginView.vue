<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const remember = ref(false)
const code = ref('')
const useRecovery = ref(false)
const step = ref<'credentials' | 'two-factor'>('credentials')
const error = ref('')
const loading = ref(false)

/** Accetta solo redirect interni, per evitare open redirect verso siti esterni. */
function safeRedirect(): string {
  const target = route.query.redirect
  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/'
}

async function submitCredentials() {
  loading.value = true
  error.value = ''
  try {
    const needsCode = await auth.login(email.value, password.value, remember.value)
    password.value = ''
    if (needsCode) step.value = 'two-factor'
    else await router.replace(safeRedirect())
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

async function submitCode() {
  loading.value = true
  error.value = ''
  try {
    await auth.twoFactorChallenge(useRecovery.value ? { recovery_code: code.value } : { code: code.value })
    await router.replace(safeRedirect())
  } catch (e) {
    error.value = errorMessage(e)
    code.value = ''
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="wrap">
    <div class="card box">
      <div class="brand">Brandinstock <span>CRM</span></div>

      <form v-if="step === 'credentials'" class="stack" @submit.prevent="submitCredentials">
        <div v-if="error" class="alert alert-error" role="alert">{{ error }}</div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" v-model="email" class="input" type="email" autocomplete="username" required autofocus />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" v-model="password" class="input" type="password" autocomplete="current-password" required />
        </div>
        <label class="checkbox"><input v-model="remember" type="checkbox" /> Ricordami su questo dispositivo</label>
        <button class="btn btn-primary" type="submit" :disabled="loading">{{ loading ? 'Accesso…' : 'Accedi' }}</button>
      </form>

      <form v-else class="stack" @submit.prevent="submitCode">
        <div v-if="error" class="alert alert-error" role="alert">{{ error }}</div>
        <p class="muted">
          {{ useRecovery ? 'Inserisci uno dei codici di recupero.' : "Inserisci il codice a 6 cifre dell'app di autenticazione." }}
        </p>
        <div class="field">
          <label for="code">{{ useRecovery ? 'Codice di recupero' : 'Codice' }}</label>
          <input
            id="code"
            v-model="code"
            class="input"
            :inputmode="useRecovery ? 'text' : 'numeric'"
            autocomplete="one-time-code"
            required
            autofocus
          />
        </div>
        <button class="btn btn-primary" type="submit" :disabled="loading">Verifica</button>
        <button class="btn btn-sm" type="button" @click="useRecovery = !useRecovery">
          {{ useRecovery ? "Usa l'app di autenticazione" : 'Usa un codice di recupero' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
.box { width: 100%; max-width: 380px; padding: 28px; }
.brand { font-size: 1.4rem; font-weight: 700; margin-bottom: 20px; text-align: center; }
.brand span { color: var(--accent); }
</style>
