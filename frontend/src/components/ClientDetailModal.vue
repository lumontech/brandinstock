<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppModal from './AppModal.vue'
import ActivityList from './ActivityList.vue'
import CompanyFormModal from './CompanyFormModal.vue'
import ContactFormModal from './ContactFormModal.vue'
import DealFormModal from './DealFormModal.vue'
import { errorMessage, http } from '@/api/http'
import { companyTypes, formatDate, money, segments } from '@/utils/format'
import type { Company, Contact } from '@/types'

/** Scheda rapida del cliente in un popup: tutti i dettagli senza lasciare la griglia. */

const props = defineProps<{ companyId: number }>()
const emit = defineEmits<{ close: []; changed: [] }>()
const router = useRouter()

const company = ref<Company | null>(null)
const error = ref('')
const editing = ref(false)
const creatingDeal = ref(false)
const contactModal = ref<{ contact: Contact | null } | null>(null)
const tab = ref<'details' | 'contacts' | 'deals' | 'activities'>('details')

async function load() {
  try {
    company.value = (await http.get<{ data: Company }>(`/companies/${props.companyId}`)).data.data
  } catch (e) {
    error.value = errorMessage(e)
  }
}

function changed() {
  emit('changed')
  load()
}

const openDeals = computed(() => (company.value?.deals ?? []).filter((d) => !d.stage?.is_won && !d.stage?.is_lost))
const openValue = computed(() => openDeals.value.reduce((t, d) => t + d.value, 0))
const wonValue = computed(() => (company.value?.deals ?? []).filter((d) => d.stage?.is_won).reduce((t, d) => t + d.value, 0))

function openDeal(id: number) {
  emit('close')
  router.push({ name: 'deal', params: { id } })
}

onMounted(load)
</script>

<template>
  <AppModal :title="company?.name ?? 'Cliente'" wide @close="emit('close')">
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-else-if="!company" class="empty">Caricamento…</div>

    <div v-else class="stack">
      <div class="summary">
        <div class="tags">
          <span class="pill" :class="`seg-${company.segment}`">{{ segments[company.segment] }}</span>
          <span v-if="company.type" class="badge">{{ companyTypes[company.type] }}</span>
          <span v-if="company.city" class="muted">{{ company.city }}{{ company.province ? ` (${company.province})` : '' }}</span>
          <span class="muted">· {{ company.owner?.name }}</span>
        </div>
        <div class="toolbar">
          <button class="btn btn-sm" @click="editing = true">Modifica</button>
          <button class="btn btn-sm" @click="creatingDeal = true">+ Opportunità</button>
          <button class="btn btn-sm" @click="emit('close'); router.push({ name: 'company', params: { id: company.id } })">Apri scheda completa</button>
        </div>
      </div>

      <div class="figures">
        <div><span class="muted small">Pipeline aperta</span><strong>{{ money(openValue) }}</strong><span class="muted small">{{ openDeals.length }} opportunità</span></div>
        <div><span class="muted small">Vinto</span><strong>{{ money(wonValue) }}</strong></div>
        <div><span class="muted small">Referenti</span><strong>{{ company.contacts?.length ?? 0 }}</strong></div>
        <div><span class="muted small">Attività</span><strong>{{ company.activities?.length ?? 0 }}</strong></div>
      </div>

      <nav class="tabs" aria-label="Sezioni">
        <button class="tab" :class="{ active: tab === 'details' }" @click="tab = 'details'">Anagrafica</button>
        <button class="tab" :class="{ active: tab === 'contacts' }" @click="tab = 'contacts'">Referenti ({{ company.contacts?.length ?? 0 }})</button>
        <button class="tab" :class="{ active: tab === 'deals' }" @click="tab = 'deals'">Opportunità ({{ company.deals?.length ?? 0 }})</button>
        <button class="tab" :class="{ active: tab === 'activities' }" @click="tab = 'activities'">Attività</button>
      </nav>

      <dl v-if="tab === 'details'" class="details">
        <dt>{{ company.segment === 'b2c' ? 'Nome e cognome' : 'Ragione sociale' }}</dt><dd>{{ company.name }}</dd>
        <dt>Categoria</dt><dd>{{ segments[company.segment] }}</dd>
        <dt>Tipologia</dt><dd>{{ company.type ? companyTypes[company.type] : '—' }}</dd>
        <dt>Partita IVA</dt><dd>{{ company.vat_number || '—' }}</dd>
        <dt>Codice fiscale</dt><dd>{{ company.tax_code || '—' }}</dd>
        <dt>Indirizzo</dt><dd>{{ company.address || '—' }}</dd>
        <dt>Città</dt><dd>{{ company.city || '—' }} {{ company.province ? `(${company.province})` : '' }} {{ company.country }}</dd>
        <dt>Email</dt><dd><a v-if="company.email" :href="`mailto:${company.email}`">{{ company.email }}</a><span v-else>—</span></dd>
        <dt>Telefono</dt><dd><a v-if="company.phone" :href="`tel:${company.phone}`">{{ company.phone }}</a><span v-else>—</span></dd>
        <dt>Sito web</dt><dd><a v-if="company.website" :href="company.website" target="_blank" rel="noopener noreferrer">{{ company.website }}</a><span v-else>—</span></dd>
        <dt>Venditore</dt><dd>{{ company.owner?.name }}</dd>
        <dt>Cliente dal</dt><dd>{{ formatDate(company.created_at) }}</dd>
        <dt>Note</dt><dd>{{ company.notes || '—' }}</dd>
      </dl>

      <div v-else-if="tab === 'contacts'" class="stack">
        <div><button class="btn btn-sm" @click="contactModal = { contact: null }">+ Referente</button></div>
        <div v-if="!company.contacts?.length" class="empty">Nessun referente.</div>
        <table v-else class="table">
          <thead><tr><th>Nome</th><th>Ruolo</th><th>Email</th><th>Telefono</th></tr></thead>
          <tbody>
            <tr v-for="c in company.contacts" :key="c.id" class="clickable" @click="contactModal = { contact: c }">
              <td><strong>{{ c.full_name }}</strong></td>
              <td>{{ c.job_title || '—' }}</td>
              <td>{{ c.email || '—' }}</td>
              <td>{{ c.phone || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="tab === 'deals'" class="stack">
        <div><button class="btn btn-sm" @click="creatingDeal = true">+ Opportunità</button></div>
        <div v-if="!company.deals?.length" class="empty">Nessuna opportunità.</div>
        <table v-else class="table">
          <thead><tr><th>Opportunità</th><th>Fase</th><th class="num">Valore</th><th>Chiusura prevista</th></tr></thead>
          <tbody>
            <tr v-for="d in company.deals" :key="d.id" class="clickable" @click="openDeal(d.id)">
              <td><strong>{{ d.title }}</strong><div class="muted small">{{ d.brand }}</div></td>
              <td><span class="badge" :style="{ background: d.stage?.color, color: '#fff' }">{{ d.stage?.name }}</span></td>
              <td class="num">{{ money(d.value) }}</td>
              <td>{{ formatDate(d.expected_close_date) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <ActivityList v-else :activities="company.activities ?? []" :company-id="company.id" show-context @changed="changed" />
    </div>

    <CompanyFormModal v-if="editing && company" :company="company" @close="editing = false" @saved="editing = false; changed()" />
    <DealFormModal v-if="creatingDeal && company" :company-id="company.id" @close="creatingDeal = false" @saved="creatingDeal = false; tab = 'deals'; changed()" />
    <ContactFormModal v-if="contactModal && company" :company-id="company.id" :contact="contactModal.contact" @close="contactModal = null" @saved="contactModal = null; changed()" />
  </AppModal>
</template>

<style scoped>
.summary { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.tags { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.figures { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; }
.figures div { display: flex; flex-direction: column; gap: 2px; background: #f6f7f9; border-radius: 8px; padding: 10px 12px; }
.figures strong { font-size: 1.1rem; font-variant-numeric: tabular-nums; }
.tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--border); overflow-x: auto; }
.tab { border: none; background: none; padding: 8px 12px; font: inherit; cursor: pointer; color: var(--muted); border-bottom: 2px solid transparent; white-space: nowrap; }
.tab.active { color: var(--text); border-bottom-color: var(--text); font-weight: 600; }
.table .num { text-align: right; font-variant-numeric: tabular-nums; }
.pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.seg-b2b { background: #dbeafe; color: #1e40af; }
.seg-b2c { background: #dcfce7; color: #166534; }
.seg-franchising { background: #ede9fe; color: #5b21b6; }
</style>
