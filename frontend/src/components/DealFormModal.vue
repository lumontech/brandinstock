<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import AppModal from './AppModal.vue'
import { errorMessage, http } from '@/api/http'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { dealSources } from '@/utils/format'
import type { Company, Contact, Deal } from '@/types'

const props = defineProps<{ deal?: Deal | null; companyId?: number; stageId?: number }>()
const emit = defineEmits<{ close: []; saved: [deal: Deal] }>()

const auth = useAuthStore()
const { stages, users, loadStages, loadUsers, searchCompanies, companyContacts } = useLookups()

const form = reactive({
  title: props.deal?.title ?? '',
  company_id: props.deal?.company_id ?? props.companyId ?? null as number | null,
  contact_id: props.deal?.contact_id ?? null as number | null,
  pipeline_stage_id: props.deal?.pipeline_stage_id ?? props.stageId ?? null as number | null,
  value: props.deal?.value ?? 0,
  brand: props.deal?.brand ?? '',
  product_category: props.deal?.product_category ?? '',
  quantity: props.deal?.quantity ?? null as number | null,
  expected_close_date: props.deal?.expected_close_date ?? '',
  source: props.deal?.source ?? '',
  notes: props.deal?.notes ?? '',
  owner_id: props.deal?.owner?.id ?? null as number | null,
})

const companies = ref<Company[]>([])
const contacts = ref<Contact[]>([])
const companyQuery = ref(props.deal?.company?.name ?? '')
const error = ref('')
const saving = ref(false)

onMounted(async () => {
  await loadStages()
  if (auth.seesEverything) await loadUsers()
  companies.value = await searchCompanies('')
  if (form.company_id) contacts.value = await companyContacts(form.company_id)
})

let timer: number | undefined
watch(companyQuery, (q) => {
  clearTimeout(timer)
  timer = window.setTimeout(async () => (companies.value = await searchCompanies(q)), 250)
})

watch(() => form.company_id, async (id, old) => {
  if (old !== undefined && id !== old) form.contact_id = null
  contacts.value = id ? await companyContacts(id) : []
})

async function submit() {
  saving.value = true
  error.value = ''
  const payload = { ...form, expected_close_date: form.expected_close_date || null, source: form.source || null }
  try {
    const { data } = props.deal
      ? await http.put<{ data: Deal }>(`/deals/${props.deal.id}`, payload)
      : await http.post<{ data: Deal }>('/deals', payload)
    emit('saved', data.data)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :title="deal ? 'Modifica opportunità' : 'Nuova opportunità'" wide @close="emit('close')">
    <form class="stack" @submit.prevent="submit">
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <div class="form-grid">
        <div class="field full">
          <label for="deal-title">Titolo *</label>
          <input id="deal-title" v-model="form.title" class="input" required maxlength="255" placeholder="Es. Stock Guess PE25 – 1.200 capi" />
        </div>
        <div class="field">
          <label for="deal-company-search">Azienda *</label>
          <input id="deal-company-search" v-model="companyQuery" class="input" placeholder="Cerca azienda…" />
          <select v-model="form.company_id" class="input" required aria-label="Azienda">
            <option :value="null" disabled>Seleziona…</option>
            <option v-if="deal?.company && !companies.some((c) => c.id === deal?.company_id)" :value="deal.company_id">{{ deal.company.name }}</option>
            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="field">
          <label for="deal-contact">Referente</label>
          <select id="deal-contact" v-model="form.contact_id" class="input" :disabled="!form.company_id">
            <option :value="null">—</option>
            <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.full_name }}</option>
          </select>
        </div>
        <div class="field">
          <label for="deal-value">Valore (€)</label>
          <input id="deal-value" v-model.number="form.value" class="input" type="number" min="0" step="0.01" />
        </div>
        <div class="field">
          <label for="deal-stage">Fase</label>
          <select id="deal-stage" v-model="form.pipeline_stage_id" class="input">
            <option :value="null">Prima fase</option>
            <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="field">
          <label for="deal-brand">Brand</label>
          <input id="deal-brand" v-model="form.brand" class="input" maxlength="100" />
        </div>
        <div class="field">
          <label for="deal-category">Categoria prodotto</label>
          <input id="deal-category" v-model="form.product_category" class="input" maxlength="100" />
        </div>
        <div class="field">
          <label for="deal-qty">Quantità (pezzi)</label>
          <input id="deal-qty" v-model.number="form.quantity" class="input" type="number" min="0" />
        </div>
        <div class="field">
          <label for="deal-close">Chiusura prevista</label>
          <input id="deal-close" v-model="form.expected_close_date" class="input" type="date" />
        </div>
        <div class="field">
          <label for="deal-source">Origine</label>
          <select id="deal-source" v-model="form.source" class="input">
            <option value="">—</option>
            <option v-for="(label, key) in dealSources" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div v-if="auth.seesEverything" class="field">
          <label for="deal-owner">Venditore</label>
          <select id="deal-owner" v-model="form.owner_id" class="input">
            <option :value="null">Io</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="field full">
          <label for="deal-notes">Note</label>
          <textarea id="deal-notes" v-model="form.notes" class="input" rows="3" maxlength="10000" />
        </div>
      </div>
      <div class="toolbar" style="justify-content: flex-end">
        <button type="button" class="btn" @click="emit('close')">Annulla</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Salvataggio…' : 'Salva' }}</button>
      </div>
    </form>
  </AppModal>
</template>
