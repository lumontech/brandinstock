<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppModal from './AppModal.vue'
import { errorMessage, http } from '@/api/http'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { companyTypes, leadSources, leadStatuses, segments } from '@/utils/format'
import type { Company } from '@/types'

/** Creazione e modifica di un lead o cliente: in evidenza solo i campi essenziali. */
const props = defineProps<{ company?: Company | null; segment?: string; status?: 'lead' | 'customer' }>()
const emit = defineEmits<{ close: []; saved: [company: Company] }>()

const auth = useAuthStore()
const { users, loadUsers } = useLookups()
const c = props.company
const isCustomer = (c?.status ?? props.status) === 'customer'
const form = reactive({
  name: c?.name ?? '',
  segment: c?.segment ?? props.segment ?? 'b2b',
  source: c?.source ?? '',
  lead_status: c?.lead_status ?? 'nuovo',
  type: c?.type ?? '',
  city: c?.city ?? '',
  email: c?.email ?? '',
  phone: c?.phone ?? '',
  owner_id: c?.owner?.id ?? (null as number | null),
  // Altre informazioni
  vat_number: c?.vat_number ?? '',
  tax_code: c?.tax_code ?? '',
  address: c?.address ?? '',
  province: c?.province ?? '',
  country: c?.country ?? 'IT',
  website: c?.website ?? '',
  notes: c?.notes ?? '',
})
const error = ref('')
const saving = ref(false)
// Le informazioni secondarie restano chiuse, a meno che siano già compilate.
const moreOpen = ref(!!(c?.vat_number || c?.tax_code || c?.address || c?.website || c?.notes))

onMounted(() => auth.seesEverything && loadUsers())

async function submit() {
  saving.value = true
  error.value = ''
  // Stringhe vuote → null, così la validazione "nullable" lato server funziona.
  const payload: Record<string, unknown> = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
  if (!c && props.status) payload.status = props.status
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
  <AppModal :title="c ? `Modifica ${isCustomer ? 'cliente' : 'lead'}` : `Nuovo ${isCustomer ? 'cliente' : 'lead'}`" wide @close="emit('close')">
    <form class="stack" @submit.prevent="submit">
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <div class="form-grid">
        <div class="field full">
          <label for="co-name">{{ form.segment === 'b2c' ? 'Nome e cognome' : 'Ragione sociale / Nome' }} *</label>
          <input id="co-name" v-model="form.name" class="input" required maxlength="255" />
        </div>
        <div class="field">
          <label for="co-segment">Categoria</label>
          <select id="co-segment" v-model="form.segment" class="input">
            <option v-for="(label, key) in segments" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="field">
          <label for="co-source">Provenienza lead</label>
          <select id="co-source" v-model="form.source" class="input">
            <option value="">—</option>
            <option v-for="(label, key) in leadSources" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div v-if="!isCustomer" class="field">
          <label for="co-lstatus">Stato del lead</label>
          <select id="co-lstatus" v-model="form.lead_status" class="input">
            <option value="">—</option>
            <option v-for="(label, key) in leadStatuses" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="field"><label for="co-email">Email</label><input id="co-email" v-model="form.email" class="input" type="email" /></div>
        <div class="field"><label for="co-phone">Telefono</label><input id="co-phone" v-model="form.phone" class="input" maxlength="40" /></div>
        <div class="field">
          <label for="co-type">Tipologia</label>
          <select id="co-type" v-model="form.type" class="input">
            <option value="">—</option>
            <option v-for="(label, key) in companyTypes" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="field"><label for="co-city">Città</label><input id="co-city" v-model="form.city" class="input" maxlength="100" /></div>
        <div v-if="auth.seesEverything" class="field">
          <label for="co-owner">Venditore</label>
          <select id="co-owner" v-model="form.owner_id" class="input">
            <option :value="null">Io</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
      </div>

      <details class="more" :open="moreOpen" @toggle="moreOpen = ($event.target as HTMLDetailsElement).open">
        <summary>Altre informazioni <span class="muted small">(P.IVA, indirizzo, sito, note)</span></summary>
        <div class="form-grid">
          <div class="field"><label for="co-vat">Partita IVA</label><input id="co-vat" v-model="form.vat_number" class="input" maxlength="32" /></div>
          <div class="field"><label for="co-cf">Codice fiscale</label><input id="co-cf" v-model="form.tax_code" class="input" maxlength="16" /></div>
          <div class="field full"><label for="co-address">Indirizzo</label><input id="co-address" v-model="form.address" class="input" maxlength="255" /></div>
          <div class="field"><label for="co-prov">Provincia</label><input id="co-prov" v-model="form.province" class="input" maxlength="10" /></div>
          <div class="field"><label for="co-country">Paese (ISO)</label><input id="co-country" v-model="form.country" class="input" maxlength="2" /></div>
          <div class="field full"><label for="co-web">Sito web</label><input id="co-web" v-model="form.website" class="input" type="url" placeholder="https://" /></div>
          <div class="field full"><label for="co-notes">Note</label><textarea id="co-notes" v-model="form.notes" class="input" rows="3" /></div>
        </div>
      </details>
      <p v-if="isCustomer" class="muted small">I dati di fatturazione si modificano dalla scheda del cliente, sezione Fatturazione.</p>

      <div class="toolbar" style="justify-content: flex-end">
        <button type="button" class="btn" @click="emit('close')">Annulla</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">Salva</button>
      </div>
    </form>
  </AppModal>
</template>

<style scoped>
.more { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; }
.more summary { cursor: pointer; font-weight: 600; }
.more[open] summary { margin-bottom: 12px; }
</style>
