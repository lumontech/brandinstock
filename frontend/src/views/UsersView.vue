<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppModal from '@/components/AppModal.vue'
import { errorMessage, http } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/utils/format'
import type { Role, User } from '@/types'

const auth = useAuthStore()
const users = ref<User[]>([])
const error = ref('')
const modal = ref(false)
const form = reactive({ name: '', email: '', role: 'sales' as Role, password: '', password_confirmation: '' })
const roles: Record<Role, string> = { admin: 'Amministratore', manager: 'Responsabile vendite', sales: 'Venditore' }

async function load() {
  users.value = (await http.get<{ data: User[] }>('/users')).data.data
}

async function create() {
  error.value = ''
  try {
    await http.post('/users', form)
    Object.assign(form, { name: '', email: '', role: 'sales', password: '', password_confirmation: '' })
    modal.value = false
    await load()
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function update(user: User, patch: Partial<User> & { reset_two_factor?: boolean }) {
  error.value = ''
  try {
    await http.put(`/users/${user.id}`, patch)
    await load()
  } catch (e) {
    error.value = errorMessage(e)
    await load()
  }
}

function resetTwoFactor(user: User) {
  if (window.confirm(`Reimpostare la 2FA di ${user.name}? Dovrà configurarla di nuovo.`)) {
    update(user, { reset_two_factor: true })
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Utenti</h1>
      <button class="btn btn-primary" @click="modal = true">+ Nuovo utente</button>
    </div>
    <div v-if="error && !modal" class="alert alert-error" style="margin-bottom: 12px">{{ error }}</div>

    <div class="card">
      <table class="table">
        <thead><tr><th>Nome</th><th>Ruolo</th><th>2FA</th><th>Ultimo accesso</th><th>Stato</th><th /></tr></thead>
        <tbody>
          <tr v-for="u in users" :key="u.id">
            <td><strong>{{ u.name }}</strong><div class="muted small">{{ u.email }}</div></td>
            <td>
              <select class="input" :value="u.role" :disabled="u.id === auth.user?.id" aria-label="Ruolo" @change="update(u, { role: ($event.target as HTMLSelectElement).value as Role })">
                <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
              </select>
            </td>
            <td><span class="badge" :class="u.two_factor_enabled ? 'badge-success' : ''">{{ u.two_factor_enabled ? 'Attiva' : 'Non attiva' }}</span></td>
            <td class="small">{{ formatDateTime(u.last_login_at) }}</td>
            <td><span class="badge" :class="u.is_active ? 'badge-success' : 'badge-danger'">{{ u.is_active ? 'Attivo' : 'Disattivato' }}</span></td>
            <td class="toolbar">
              <template v-if="u.id !== auth.user?.id">
                <button class="btn btn-sm" @click="update(u, { is_active: !u.is_active })">{{ u.is_active ? 'Disattiva' : 'Riattiva' }}</button>
                <button v-if="u.two_factor_enabled" class="btn btn-sm" @click="resetTwoFactor(u)">Reset 2FA</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal v-if="modal" title="Nuovo utente" @close="modal = false">
      <form class="stack" @submit.prevent="create">
        <div v-if="error" class="alert alert-error">{{ error }}</div>
        <div class="field"><label for="u-name">Nome</label><input id="u-name" v-model="form.name" class="input" required /></div>
        <div class="field"><label for="u-email">Email</label><input id="u-email" v-model="form.email" class="input" type="email" required /></div>
        <div class="field">
          <label for="u-role">Ruolo</label>
          <select id="u-role" v-model="form.role" class="input">
            <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="field"><label for="u-pwd">Password iniziale</label><input id="u-pwd" v-model="form.password" class="input" type="password" autocomplete="new-password" minlength="12" required /></div>
        <div class="field"><label for="u-pwd2">Conferma password</label><input id="u-pwd2" v-model="form.password_confirmation" class="input" type="password" autocomplete="new-password" required /></div>
        <p class="muted small">Comunica la password iniziale su un canale diverso dall'email e chiedi all'utente di cambiarla al primo accesso.</p>
        <div class="toolbar" style="justify-content: flex-end">
          <button type="button" class="btn" @click="modal = false">Annulla</button>
          <button type="submit" class="btn btn-primary">Crea utente</button>
        </div>
      </form>
    </AppModal>
  </div>
</template>
