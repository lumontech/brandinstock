<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppModal from './AppModal.vue'
import { errorMessage, http } from '@/api/http'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { companyTypes, segments } from '@/utils/format'
import type { Company } from '@/types'

const props = defineProps<{ company?: Company | null; segment?: string }>()
const emit = defineEmits<{ close: []; saved: [company: Company] }>()

const auth = useAuthStore()
const { users, loadUsers } = useLookups()
const c = props.company
const form = reactive({
  name: c?.name ?? '',
  segment: c?.segment ?? props.segment ?? 'b2b',
  vat_number: c?.vat_number ?? '',
  tax_code: c?.tax_code ?? '',
  type: c?.type ?? '',
  city: c?.city ?? '',
  province: c?.province ?? '',
  country: c?.country ?? 'IT',
  address: c?.address ?? '',
  email: c?.email ?? '',
  phone: c?.phone ?? '',
  website: c?.website ?? '',
  notes: c?.notes ?? '',
  owner_id: c?.owner?.id ?? null as number | null,
})
const error = ref('')
const saving = ref(false)

onMounted(() => auth.seesEverything && loadUsers())

async function submit() {
  saving.value = true
  error.value = ''
  // Stringhe vuote → null, così la validazione "nullable" lato server funziona.
  const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
  try {
    const { data } = c
      ? await http.put<{ data: Company }>(`/companies/${c.id}`, payload)
      : await http.post<{ data: Company }>('/companies', payload)
    emit('saved', data.data)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :title="company ? 'Modifica cliente' : 'Nuovo cliente'" wide @close="emit('close')">
    <form class="stack" @submit.prevent="submit">
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <div class="form-grid">
        <div class="field full">
          <label for="co-name">{{ form.segment === 'b2c' ? 'Nome e cognome' : 'Ragione sociale' }} *</label>
          <input id="co-name" v-model="form.name" class="input" required maxlength="255" />
        </div>
        <div class="field">
          <label for="co-segment">Categoria</label>
          <select id="co-segment" v-model="form.segment" class="input">
            <option v-for="(label, key) in segments" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="field"><label for="co-vat">Partita IVA</label><input id="co-vat" v-model="form.vat_number" class="input" maxlength="32" /></div>
        <div class="field"><label for="co-cf">Codice fiscale</label><input id="co-cf" v-model="form.tax_code" class="input" maxlength="16" /></div>
        <div class="field">
          <label for="co-type">Tipologia</label>
          <select id="co-type" v-model="form.type" class="input">
            <option value="">—</option>
            <option v-for="(label, key) in companyTypes" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="field"><label for="co-city">Città</label><input id="co-city" v-model="form.city" class="input" maxlength="100" /></div>
        <div class="field"><label for="co-prov">Provincia</label><input id="co-prov" v-model="form.province" class="input" maxlength="10" /></div>
        <div class="field full"><label for="co-address">Indirizzo</label><input id="co-address" v-model="form.address" class="input" maxlength="255" /></div>
        <div class="field"><label for="co-email">Email</label><input id="co-email" v-model="form.email" class="input" type="email" /></div>
        <div class="field"><label for="co-phone">Telefono</label><input id="co-phone" v-model="form.phone" class="input" maxlength="40" /></div>
        <div class="field"><label for="co-web">Sito web</label><input id="co-web" v-model="form.website" class="input" type="url" placeholder="https://" /></div>
        <div class="field"><label for="co-country">Paese (ISO)</label><input id="co-country" v-model="form.country" class="input" maxlength="2" /></div>
        <div v-if="auth.seesEverything" class="field">
          <label for="co-owner">Venditore assegnato</label>
          <select id="co-owner" v-model="form.owner_id" class="input">
            <option :value="null">Io</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="field full"><label for="co-notes">Note</label><textarea id="co-notes" v-model="form.notes" class="input" rows="3" /></div>
      </div>
      <div class="toolbar" style="justify-content: flex-end">
        <button type="button" class="btn" @click="emit('close')">Annulla</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">Salva</button>
      </div>
    </form>
  </AppModal>
</template>
