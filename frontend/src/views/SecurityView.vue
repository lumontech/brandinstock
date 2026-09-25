<script setup lang="ts">
import { reactive, ref } from 'vue'
import QRCode from 'qrcode'
import { errorMessage, http } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// --- Cambio password ---
const pwd = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwdMsg = ref<{ ok: boolean; text: string } | null>(null)

async function changePassword() {
  pwdMsg.value = null
  try {
    await http.put('/auth/password', pwd)
    Object.assign(pwd, { current_password: '', password: '', password_confirmation: '' })
    pwdMsg.value = { ok: true, text: 'Password aggiornata. Le altre sessioni sono state chiuse.' }
  } catch (e) {
    pwdMsg.value = { ok: false, text: errorMessage(e) }
  }
}

// --- 2FA ---
const setup = ref<{ secret: string; qr: string } | null>(null)
const confirmPassword = ref('')
const code = ref('')
const recoveryCodes = ref<string[]>([])
const tfaError = ref('')

async function startSetup() {
  tfaError.value = ''
  try {
    const { data } = await http.post<{ secret: string; otpauth_url: string }>('/auth/two-factor', { password: confirmPassword.value })
    // Il QR viene generato localmente: il segreto non viene mai inviato a servizi esterni.
    setup.value = { secret: data.secret, qr: await QRCode.toDataURL(data.otpauth_url, { width: 200, margin: 1 }) }
    confirmPassword.value = ''
  } catch (e) {
    tfaError.value = errorMessage(e)
  }
}

async function confirmSetup() {
  tfaError.value = ''
  try {
    const { data } = await http.post<{ recovery_codes: string[] }>('/auth/two-factor/confirm', { code: code.value })
    recoveryCodes.value = data.recovery_codes
    setup.value = null
    code.value = ''
    await auth.fetchUser()
  } catch (e) {
    tfaError.value = errorMessage(e)
  }
}

async function disable() {
  tfaError.value = ''
  try {
    await http.delete('/auth/two-factor', { data: { password: confirmPassword.value } })
    confirmPassword.value = ''
    await auth.fetchUser()
  } catch (e) {
    tfaError.value = errorMessage(e)
  }
}
</script>

<template>
  <div>
    <div class="page-header"><h1>Sicurezza account</h1></div>

    <div v-if="auth.needsTwoFactorSetup" class="alert alert-info" style="margin-bottom: 16px">
      Per il ruolo <strong>{{ auth.user?.role_label }}</strong> l'autenticazione a due fattori è obbligatoria. Attivala per accedere al CRM.
    </div>

    <div class="grid-2">
      <div class="card stack">
        <h2>Autenticazione a due fattori (2FA)</h2>
        <div v-if="tfaError" class="alert alert-error">{{ tfaError }}</div>

        <template v-if="recoveryCodes.length">
          <div class="alert alert-success">2FA attivata. Salva questi codici di recupero in un luogo sicuro: vengono mostrati solo ora.</div>
          <pre class="codes">{{ recoveryCodes.join('\n') }}</pre>
          <button class="btn" @click="recoveryCodes = []">Li ho salvati</button>
        </template>

        <template v-else-if="setup">
          <p>Scansiona il QR con Google Authenticator, Microsoft Authenticator, 1Password o simili, poi inserisci il codice.</p>
          <img :src="setup.qr" alt="QR code per la 2FA" width="200" height="200" />
          <p class="muted small">Chiave manuale: <code>{{ setup.secret }}</code></p>
          <form class="toolbar" @submit.prevent="confirmSetup">
            <input v-model="code" class="input" style="width: 140px" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" aria-label="Codice" required />
            <button class="btn btn-primary" type="submit">Conferma</button>
          </form>
        </template>

        <template v-else-if="auth.user?.two_factor_enabled">
          <p><span class="badge badge-success">Attiva</span> Il tuo account è protetto dalla 2FA.</p>
          <form v-if="!auth.user.two_factor_required" class="toolbar" @submit.prevent="disable">
            <input v-model="confirmPassword" class="input" type="password" placeholder="Password attuale" autocomplete="current-password" aria-label="Password" required style="width: 200px" />
            <button class="btn btn-danger" type="submit">Disattiva 2FA</button>
          </form>
        </template>

        <form v-else class="stack" @submit.prevent="startSetup">
          <p class="muted">Aggiunge un codice temporaneo dal telefono oltre alla password.</p>
          <div class="toolbar">
            <input v-model="confirmPassword" class="input" type="password" placeholder="Password attuale" autocomplete="current-password" aria-label="Password" required style="width: 200px" />
            <button class="btn btn-primary" type="submit">Attiva 2FA</button>
          </div>
        </form>
      </div>

      <form class="card stack" @submit.prevent="changePassword">
        <h2>Cambia password</h2>
        <div v-if="pwdMsg" class="alert" :class="pwdMsg.ok ? 'alert-success' : 'alert-error'">{{ pwdMsg.text }}</div>
        <div class="field"><label for="cur">Password attuale</label><input id="cur" v-model="pwd.current_password" class="input" type="password" autocomplete="current-password" required /></div>
        <div class="field"><label for="new">Nuova password</label><input id="new" v-model="pwd.password" class="input" type="password" autocomplete="new-password" minlength="12" required /></div>
        <div class="field"><label for="conf">Conferma nuova password</label><input id="conf" v-model="pwd.password_confirmation" class="input" type="password" autocomplete="new-password" required /></div>
        <p class="muted small">Almeno 12 caratteri, con maiuscole, minuscole e numeri.</p>
        <button class="btn btn-primary" type="submit">Aggiorna password</button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.codes { background: #f3f4f6; padding: 12px; border-radius: 8px; font-size: 14px; }
</style>
