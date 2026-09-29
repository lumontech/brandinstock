<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppModal from './AppModal.vue'
import ActivityList from './ActivityList.vue'
import BillingFormModal from './BillingFormModal.vue'
import CompanyFormModal from './CompanyFormModal.vue'
import ContactFormModal from './ContactFormModal.vue'
import DealFormModal from './DealFormModal.vue'
import { errorMessage, http } from '@/api/http'
import { companyTypes, formatDate, leadSources, money, segments } from '@/utils/format'
import type { Company, Contact } from '@/types'

/** Scheda rapida del cliente in un popup: tutti i dettagli senza lasciare la griglia. */

const props = defineProps<{ companyId: number }>()
const emit = defineEmits<{ close: []; changed: [] }>()
const router = useRouter()

const company = ref<Company | null>(null)
const error = ref('')
const editing = ref(false)
const editingBilling = ref(false)
const confirmStatus = ref(false)
const creatingDeal = ref(false)
const contactModal = ref<{ contact: Contact | null } | null>(null)
const tab = ref<'details' | 'billing' | 'contacts' | 'deals' | 'activities'>('details')

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

async function setStatus(status: 'lead' | 'customer') {
  if (!company.value) return
  try {
    company.value = (await http.put<{ data: Company }>(`/companies/${company.value.id}`, { status })).data.data
    confirmStatus.value = false
    await load()
    emit('changed')
    if (status === 'customer') tab.value = 'billing'
  } catch (e) {
    error.value = errorMessage(e)
  }
}

function openDeal(id: number) {
  emit('close')
  router.push({ name: 'deal', params: { id } })
}

onMounted(load)
</script>

<template>
  <AppModal :title="company ? `${company.status === 'customer' ? 'Cliente' : 'Lead'} · ${company.name}` : 'Caricamento'" wide @close="emit('close')">
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-else-if="!company" class="empty">Caricamento…</div>

    <div v-else class="stack">
      <div class="summary">
        <div class="tags">
          <span class="pill" :class="company.status === 'customer' ? 'st-customer' : 'st-lead'">{{ company.status === 'customer' ? 'Cliente' : 'Lead' }}</span>
          <span class="pill" :class="`seg-${company.segment}`">{{ segments[company.segment] }}</span>
          <span v-if="company.source" class="badge">{{ leadSources[company.source] }}</span>
          <span v-if="company.type" class="badge">{{ companyTypes[company.type] }}</span>
          <span v-if="company.city" class="muted">{{ company.city }}{{ company.province ? ` (${company.province})` : '' }}</span>
          <span class="muted">· {{ company.owner?.name }}</span>
        </div>
        <div class="toolbar">
          <button class="btn btn-sm" @click="editing = true">Modifica</button>
          <template v-if="!confirmStatus">
            <button v-if="company.status === 'lead'" class="btn btn-sm" @click="confirmStatus = true">Segna come cliente</button>
            <button v-else class="btn btn-sm" @click="confirmStatus = true">Riporta a lead</button>
          </template>
          <span v-else class="confirm">
            {{ company.status === 'lead' ? 'Spostarlo in Clienti?' : 'Riportarlo nei Leads?' }}
            <button class="btn btn-sm btn-primary" @click="setStatus(company.status === 'lead' ? 'customer' : 'lead')">Sì</button>
            <button class="btn btn-sm" @click="confirmStatus = false">No</button>
          </span>
          <button class="btn btn-sm" @click="creatingDeal = true">+ Opportunità</button>
          <button class="btn btn-sm" @click="emit('close'); router.push({ name: 'company', params: { id: company.id } })">Apri scheda completa</button>
        </div>
      </div>

      <div v-if="company.status === 'customer' && !company.billing_complete" class="alert alert-info billing-alert">
        <span>Mancano i dati per la fattura elettronica (intestazione, P.IVA o C.F., indirizzo, CAP, città e codice SDI o PEC).</span>
        <button class="btn btn-sm btn-primary" @click="editingBilling = true">Completa i dati</button>
      </div>

      <div class="figures">
        <div><span class="muted small">Pipeline aperta</span><strong>{{ money(openValue) }}</strong><span class="muted small">{{ openDeals.length }} opportunità</span></div>
        <div><span class="muted small">Vinto</span><strong>{{ money(wonValue) }}</strong></div>
        <div><span class="muted small">Referenti</span><strong>{{ company.contacts?.length ?? 0 }}</strong></div>
        <div><span class="muted small">Attività</span><strong>{{ company.activities?.length ?? 0 }}</strong></div>
      </div>

      <nav class="tabs" aria-label="Sezioni">
        <button class="tab" :class="{ active: tab === 'details' }" @click="tab = 'details'">Anagrafica</button>
        <button class="tab" :class="{ active: tab === 'billing' }" @click="tab = 'billing'">
          Fatturazione <span v-if="company.status === 'customer'" class="dot" :class="company.billing_complete ? 'ok' : 'missing'" />
        </button>
        <button class="tab" :class="{ active: tab === 'contacts' }" @click="tab = 'contacts'">Referenti ({{ company.contacts?.length ?? 0 }})</button>
        <button class="tab" :class="{ active: tab === 'deals' }" @click="tab = 'deals'">Opportunità ({{ company.deals?.length ?? 0 }})</button>
        <button class="tab" :class="{ active: tab === 'activities' }" @click="tab = 'activities'">Attività</button>
      </nav>

      <div v-if="tab === 'details'" class="stack">
        <dl class="details">
          <dt>{{ company.segment === 'b2c' ? 'Nome e cognome' : 'Ragione sociale' }}</dt><dd>{{ company.name }}</dd>
          <dt>Categoria</dt><dd>{{ segments[company.segment] }}</dd>
          <dt>Provenienza lead</dt><dd>{{ company.source ? leadSources[company.source] : '—' }}</dd>
          <dt>Email</dt><dd><a v-if="company.email" :href="`mailto:${company.email}`">{{ company.email }}</a><span v-else>—</span></dd>
          <dt>Telefono</dt><dd><a v-if="company.phone" :href="`tel:${company.phone}`">{{ company.phone }}</a><span v-else>—</span></dd>
          <dt>Tipologia</dt><dd>{{ company.type ? companyTypes[company.type] : '—' }}</dd>
          <dt>Città</dt><dd>{{ company.city || '—' }}</dd>
          <dt>Venditore</dt><dd>{{ company.owner?.name }}</dd>
        </dl>
        <details class="more">
          <summary>Altre informazioni</summary>
          <dl class="details">
            <dt>Partita IVA</dt><dd>{{ company.vat_number || '—' }}</dd>
            <dt>Codice fiscale</dt><dd>{{ company.tax_code || '—' }}</dd>
            <dt>Indirizzo</dt><dd>{{ company.address || '—' }} {{ company.province ? `(${company.province})` : '' }} {{ company.country }}</dd>
            <dt>Sito web</dt><dd><a v-if="company.website" :href="company.website" target="_blank" rel="noopener noreferrer">{{ company.website }}</a><span v-else>—</span></dd>
            <dt>Inserito il</dt><dd>{{ formatDate(company.created_at) }}</dd>
            <dt v-if="company.converted_at">Cliente dal</dt><dd v-if="company.converted_at">{{ formatDate(company.converted_at) }}</dd>
            <dt>Note</dt><dd>{{ company.notes || '—' }}</dd>
          </dl>
        </details>
      </div>

      <div v-else-if="tab === 'billing'" class="stack">
        <div><button class="btn btn-sm" @click="editingBilling = true">Modifica dati di fatturazione</button></div>
        <dl class="details">
          <dt>Intestazione</dt><dd>{{ company.billing_name || company.name }}</dd>
          <dt>Partita IVA</dt><dd>{{ company.vat_number || '—' }}</dd>
          <dt>Codice fiscale</dt><dd>{{ company.tax_code || '—' }}</dd>
          <dt>Sede legale</dt>
          <dd>
            <template v-if="company.billing_address">{{ company.billing_address }}<br />{{ company.billing_zip }} {{ company.billing_city }} {{ company.billing_province ? `(${company.billing_province})` : '' }} {{ company.billing_country }}</template>
            <span v-else>—</span>
          </dd>
          <dt>Codice SDI</dt><dd>{{ company.sdi_code || '—' }}</dd>
          <dt>PEC</dt><dd>{{ company.pec || '—' }}</dd>
          <dt>IBAN</dt><dd>{{ company.iban || '—' }}</dd>
          <dt>Pagamento</dt><dd>{{ company.payment_terms || '—' }}</dd>
          <dt>Note amministrazione</dt><dd>{{ company.billing_notes || '—' }}</dd>
        </dl>
      </div>

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

    <BillingFormModal v-if="editingBilling && company" :company="company" @close="editingBilling = false" @saved="editingBilling = false; changed()" />
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
.st-lead { background: #fef3c7; color: #92400e; }
.st-customer { background: #dcfce7; color: #166534; }
.confirm { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
.billing-alert { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-left: 4px; vertical-align: middle; }
.dot.ok { background: #16a34a; }
.dot.missing { background: #dc2626; }
.more { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; }
.more summary { cursor: pointer; font-weight: 600; }
.more[open] summary { margin-bottom: 10px; }
.seg-b2b { background: #dbeafe; color: #1e40af; }
.seg-b2c { background: #dcfce7; color: #166534; }
.seg-franchising { background: #ede9fe; color: #5b21b6; }
</style>
