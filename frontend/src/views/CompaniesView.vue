<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage, http } from '@/api/http'
import BulkEditModal from '@/components/BulkEditModal.vue'
import ClientDetailModal from '@/components/ClientDetailModal.vue'
import CompanyFormModal from '@/components/CompanyFormModal.vue'
import ImportClientsModal from '@/components/ImportClientsModal.vue'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { companyTypes, formatDate, leadSources, money, segments } from '@/utils/format'
import type { Company, Paginated, Segment } from '@/types'

/**
 * Vista a griglia di Leads e Clienti, in stile foglio di calcolo: modifica diretta nelle celle,
 * ordinamento, filtri, raggruppamento e scelta delle colonne. Le colonne visibili di default
 * cambiano: per i lead contano contatto e provenienza, per i clienti i dati di fatturazione.
 */

const props = defineProps<{ mode: 'lead' | 'customer' }>()
const isCustomers = props.mode === 'customer'

type Editor = 'text' | 'email' | 'url' | 'segment' | 'type' | 'owner' | 'source'
interface Column {
  key: string
  label: string
  width: number
  sort?: string
  editor?: Editor
  align?: 'right'
  /** In quale vista la colonna è visibile di default. */
  show: ('lead' | 'customer')[]
  /** Colonna disponibile solo per i clienti (fatturazione, referenti, vinto). */
  customerOnly?: boolean
}

const L = ['lead'] as ('lead' | 'customer')[]
const C = ['customer'] as ('lead' | 'customer')[]
const LC = ['lead', 'customer'] as ('lead' | 'customer')[]
const ALL_COLUMNS: Column[] = [
  { key: 'name', label: isCustomers ? 'Cliente' : 'Lead', width: 240, sort: 'name', editor: 'text', show: LC },
  { key: 'segment', label: 'Categoria', width: 120, sort: 'segment', editor: 'segment', show: LC },
  { key: 'source', label: 'Provenienza', width: 170, sort: 'source', editor: 'source', show: L },
  { key: 'email', label: 'Email', width: 210, editor: 'email', show: L },
  { key: 'phone', label: 'Telefono', width: 150, editor: 'text', show: L },
  { key: 'type', label: 'Tipologia', width: 130, sort: 'type', editor: 'type', show: L },
  { key: 'city', label: 'Città', width: 140, sort: 'city', editor: 'text', show: L },
  { key: 'vat_number', label: 'Partita IVA', width: 150, sort: 'vat_number', editor: 'text', show: C },
  { key: 'billing_complete', label: 'Fatturazione', width: 120, show: C, customerOnly: true },
  { key: 'sdi_code', label: 'Codice SDI', width: 110, sort: 'sdi_code', editor: 'text', show: C, customerOnly: true },
  { key: 'pec', label: 'PEC', width: 200, editor: 'email', show: [], customerOnly: true },
  { key: 'billing_city', label: 'Sede (città)', width: 140, sort: 'billing_city', editor: 'text', show: C, customerOnly: true },
  { key: 'payment_terms', label: 'Pagamento', width: 190, sort: 'payment_terms', editor: 'text', show: C, customerOnly: true },
  { key: 'province', label: 'Prov.', width: 70, sort: 'province', editor: 'text', show: [] },
  { key: 'tax_code', label: 'Codice fiscale', width: 170, editor: 'text', show: [] },
  { key: 'website', label: 'Sito web', width: 190, editor: 'url', show: [] },
  { key: 'owner', label: 'Venditore', width: 150, sort: 'owner', editor: 'owner', show: LC },
  { key: 'contacts_count', label: 'Referenti', width: 95, sort: 'contacts_count', align: 'right', show: [], customerOnly: true },
  { key: 'deals_count', label: 'Opportunità', width: 110, sort: 'deals_count', align: 'right', show: L },
  { key: 'open_deals_value', label: 'Valore aperto', width: 130, sort: 'open_deals_value', align: 'right', show: LC },
  { key: 'won_value', label: 'Vinto', width: 120, sort: 'won_value', align: 'right', show: C, customerOnly: true },
  { key: 'last_activity_at', label: 'Ultima attività', width: 130, sort: 'last_activity_at', show: L },
  { key: 'converted_at', label: 'Cliente dal', width: 120, sort: 'converted_at', show: C, customerOnly: true },
  { key: 'created_at', label: 'Inserito il', width: 120, sort: 'created_at', show: [] },
]

const COLUMNS = ALL_COLUMNS.filter((c) => isCustomers || !c.customerOnly)

const GROUPS = { none: 'Nessuno', segment: 'Categoria', source: 'Provenienza', type: 'Tipologia', owner: 'Venditore', city: 'Città', billing: 'Dati di fatturazione' } as const
type GroupBy = keyof typeof GROUPS
const TABS: { key: Segment | ''; label: string }[] = [
  { key: '', label: 'Tutti' },
  { key: 'b2b', label: 'B2B' },
  { key: 'b2c', label: 'B2C' },
  { key: 'franchising', label: 'Franchising' },
]

const auth = useAuthStore()
const router = useRouter()
const { users, loadUsers } = useLookups()

// --- Preferenze della vista (solo in questo browser) ---
const PREFS_KEY = `bis.${props.mode}.grid.v2`
function readPrefs(): { hidden?: string[]; groupBy?: GroupBy; segment?: Segment | '' } {
  try {
    return JSON.parse(localStorage.getItem(PREFS_KEY) ?? '{}')
  } catch {
    return {}
  }
}
const prefs = readPrefs()
const hidden = ref(new Set<string>(prefs.hidden ?? COLUMNS.filter((c) => !c.show.includes(props.mode)).map((c) => c.key)))
const groupBy = ref<GroupBy>(prefs.groupBy && prefs.groupBy in GROUPS ? prefs.groupBy : 'none')

// --- Filtri e dati ---
const segment = ref<Segment | ''>(prefs.segment ?? '')
const q = ref('')
const type = ref('')
const source = ref('')
const ownerId = ref<number | null>(null)
const sort = reactive({ key: 'name', direction: 'asc' as 'asc' | 'desc' })
const rows = ref<Company[]>([])
const meta = ref<Paginated<Company>['meta'] | null>(null)
const loading = ref(false)
const error = ref('')
const showFields = ref(false)
const collapsed = ref(new Set<string>())
const creating = ref(false)
const selectedId = ref<number | null>(null)
const importing = ref(false)

watch([hidden, groupBy, segment], () => {
  try {
    localStorage.setItem(PREFS_KEY, JSON.stringify({ hidden: [...hidden.value], groupBy: groupBy.value, segment: segment.value }))
  } catch {
    /* preferenze non salvabili: la vista funziona comunque */
  }
}, { deep: true })

const columns = computed(() => COLUMNS.filter((c) => c.key === 'name' || !hidden.value.has(c.key)))
const gridTemplate = computed(() => `64px ${columns.value.map((c) => `${c.width}px`).join(' ')}`)

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const { data } = await http.get<Paginated<Company>>('/companies', {
      params: {
        page,
        per_page: 100,
        q: q.value || undefined,
        status: props.mode,
        segment: segment.value || undefined,
        source: source.value || undefined,
        type: type.value || undefined,
        owner_id: ownerId.value || undefined,
        sort: sort.key,
        direction: sort.direction,
      },
    })
    rows.value = page === 1 ? data.data : [...rows.value, ...data.data]
    // La selezione resta valida solo per le righe ancora visibili.
    const ids = new Set(rows.value.map((r) => r.id))
    selected.value = new Set([...selected.value].filter((id) => ids.has(id)))
    meta.value = data.meta
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (auth.seesEverything) await loadUsers()
  await load()
})

let timer: number | undefined
watch([q, type, source, ownerId, segment], () => {
  clearTimeout(timer)
  timer = window.setTimeout(() => load(), 250)
})

function toggleSort(col: Column) {
  if (!col.sort) return
  if (sort.key !== col.sort) {
    sort.key = col.sort
    sort.direction = 'asc'
  } else if (sort.direction === 'asc') {
    sort.direction = 'desc'
  } else {
    sort.key = 'name'
    sort.direction = 'asc'
  }
  load()
}

function toggleColumn(key: string) {
  const next = new Set(hidden.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  hidden.value = next
}

// --- Raggruppamento (sulle righe caricate) ---
function groupLabel(row: Company): string {
  switch (groupBy.value) {
    case 'segment': return segments[row.segment] ?? 'Senza categoria'
    case 'source': return row.source ? (leadSources[row.source] ?? row.source) : 'Provenienza non indicata'
    case 'billing': return row.billing_complete ? 'Dati completi' : 'Dati mancanti'
    case 'type': return row.type ? (companyTypes[row.type] ?? row.type) : 'Senza tipologia'
    case 'owner': return row.owner?.name ?? 'Senza venditore'
    case 'city': return row.city || 'Senza città'
    default: return ''
  }
}

const groups = computed(() => {
  if (groupBy.value === 'none') return [{ key: '', label: '', rows: rows.value, total: 0 }]
  const map = new Map<string, Company[]>()
  for (const row of rows.value) {
    const label = groupLabel(row)
    map.set(label, [...(map.get(label) ?? []), row])
  }
  return [...map.entries()]
    .sort(([a], [b]) => a.localeCompare(b, 'it'))
    .map(([label, list]) => ({ key: label, label, rows: list, total: list.reduce((t, r) => t + (r.open_deals_value ?? 0), 0) }))
})

function toggleGroup(key: string) {
  const next = new Set(collapsed.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  collapsed.value = next
}

// --- Visualizzazione delle celle ---
function display(row: Company, key: string): string {
  switch (key) {
    case 'segment': return segments[row.segment] ?? ''
    case 'source': return row.source ? (leadSources[row.source] ?? row.source) : ''
    case 'billing_complete': return row.billing_complete ? 'Completi' : 'Mancanti'
    case 'won_value': return row.won_value ? money(row.won_value) : ''
    case 'converted_at': return formatDate(row.converted_at)
    case 'type': return row.type ? (companyTypes[row.type] ?? row.type) : ''
    case 'owner': return row.owner?.name ?? ''
    case 'open_deals_value': return row.open_deals_value ? money(row.open_deals_value) : ''
    case 'contacts_count': return String(row.contacts_count ?? 0)
    case 'deals_count': return String(row.deals_count ?? 0)
    case 'last_activity_at': return row.last_activity_at ? formatDate(row.last_activity_at) : ''
    case 'created_at': return formatDate(row.created_at)
    default: return String((row as unknown as Record<string, unknown>)[key] ?? '')
  }
}

function canEdit(col: Column) {
  // Il nome apre la scheda del cliente (si modifica da lì).
  return !!col.editor && col.key !== 'name' && (col.editor !== 'owner' || auth.seesEverything)
}

// --- Modifica diretta ---
const editing = ref<{ id: number; key: string } | null>(null)
const draft = ref('')
const saving = ref(false)

function startEdit(row: Company, col: Column) {
  if (!canEdit(col) || saving.value) return
  editing.value = { id: row.id, key: col.key }
  draft.value = col.key === 'owner' ? String(row.owner?.id ?? '') : String((row as unknown as Record<string, unknown>)[col.key] ?? '')
  nextTick(() => {
    const el = document.querySelector<HTMLInputElement | HTMLSelectElement>('.cell-editor')
    el?.focus()
    if (el instanceof HTMLInputElement) el.select()
  })
}

function cancelEdit() {
  editing.value = null
}

async function commitEdit(row: Company) {
  const current = editing.value
  if (!current || saving.value) return
  const key = current.key
  const original = key === 'owner' ? String(row.owner?.id ?? '') : String((row as unknown as Record<string, unknown>)[key] ?? '')
  const value = draft.value.trim()
  if (value === original) {
    editing.value = null
    return
  }
  if (key === 'name' && !value) {
    error.value = 'Il nome del cliente non può essere vuoto.'
    return
  }

  saving.value = true
  const payload = key === 'owner' ? { owner_id: value ? Number(value) : null } : { [key]: value === '' ? null : value }
  try {
    const { data } = await http.put<{ data: Company }>(`/companies/${row.id}`, payload)
    // La risposta non contiene i totali calcolati: aggiorno solo i campi modificabili.
    const { contacts_count, deals_count, open_deals_value, last_activity_at, won_value } = row
    Object.assign(row, data.data, { contacts_count, deals_count, open_deals_value, last_activity_at, won_value })
    editing.value = null
    error.value = ''
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

function onKey(event: KeyboardEvent, row: Company) {
  if (event.key === 'Enter') {
    event.preventDefault()
    commitEdit(row)
  } else if (event.key === 'Escape') {
    cancelEdit()
  }
}

// --- Nuovo cliente dalla riga in fondo ---
const newName = ref('')
async function createRow() {
  const name = newName.value.trim()
  if (!name) return
  try {
    const { data } = await http.post<{ data: Company }>('/companies', { name, segment: segment.value || 'b2b', status: props.mode })
    rows.value = [{ ...data.data, contacts_count: 0, deals_count: 0, open_deals_value: 0, won_value: 0, last_activity_at: null }, ...rows.value]
    if (meta.value) meta.value.total += 1
    newName.value = ''
    error.value = ''
  } catch (e) {
    error.value = errorMessage(e)
  }
}

// --- Selezione e azioni massive ---
const selected = ref(new Set<number>())
const allSelected = computed(() => rows.value.length > 0 && rows.value.every((r) => selected.value.has(r.id)))
const bulkEditing = ref(false)
const confirmingDelete = ref(false)
const bulkBusy = ref(false)
const notice = ref('')

function toggleRow(id: number) {
  const next = new Set(selected.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selected.value = next
  confirmingDelete.value = false
}

function toggleAll() {
  selected.value = allSelected.value ? new Set() : new Set(rows.value.map((r) => r.id))
  confirmingDelete.value = false
}

function clearSelection() {
  selected.value = new Set()
  confirmingDelete.value = false
}

interface BulkResult { processed: number; skipped: number; skipped_with_deals: number; deals_deleted?: number }

// Quante delle righe selezionate hanno opportunità (verranno eliminate insieme al record).
const selectedDeals = computed(() => rows.value.filter((r) => selected.value.has(r.id)).reduce((t, r) => t + (r.deals_count ?? 0), 0))

async function runBulk(action: 'update' | 'delete', changes?: Record<string, string | number | null>) {
  bulkBusy.value = true
  error.value = ''
  notice.value = ''
  try {
    const { data } = await http.post<BulkResult>('/companies/bulk', { ids: [...selected.value], action, changes, with_deals: action === 'delete' ? true : undefined })
    const parts = [`${data.processed} ${action === 'delete' ? 'eliminati' : 'aggiornati'}`]
    if (data.deals_deleted) parts.push(`${data.deals_deleted} opportunità eliminate`)
    if (data.skipped_with_deals) parts.push(`${data.skipped_with_deals} non eliminati perché hanno opportunità collegate`)
    if (data.skipped) parts.push(`${data.skipped} non accessibili`)
    notice.value = parts.join(' · ')
    bulkEditing.value = false
    clearSelection()
    await load()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    bulkBusy.value = false
  }
}

const isEditing = (row: Company, col: Column) => editing.value?.id === row.id && editing.value.key === col.key
</script>

<template>
  <div class="clients">
    <div class="page-header">
      <div>
        <h1>{{ isCustomers ? 'Clienti' : 'Leads' }}</h1>
        <p class="muted small subtitle">
          {{ isCustomers ? 'Chi ha già acquistato: qui servono i dati di fatturazione.' : "Contatti commerciali: quando vinci un'opportunità in pipeline diventano Clienti." }}
        </p>
      </div>
      <div class="toolbar">
        <nav class="tabs" aria-label="Categoria">
          <button v-for="t in TABS" :key="t.key" class="tab" :class="{ active: segment === t.key }" @click="segment = t.key">{{ t.label }}</button>
        </nav>
        <button class="btn" @click="importing = true">Importa CSV</button>
        <button class="btn btn-primary" @click="creating = true">{{ isCustomers ? '+ Nuovo cliente' : '+ Nuovo lead' }}</button>
      </div>
    </div>

    <div class="toolbar grid-toolbar">
      <input v-model="q" class="input search" type="search" placeholder="Cerca nome, P.IVA, città…" aria-label="Cerca" />
      <select v-model="type" class="input narrow" aria-label="Tipologia">
        <option value="">Tutte le tipologie</option>
        <option v-for="(label, key) in companyTypes" :key="key" :value="key">{{ label }}</option>
      </select>
      <select v-model="source" class="input narrow" aria-label="Provenienza">
        <option value="">Tutte le provenienze</option>
        <option v-for="(label, key) in leadSources" :key="key" :value="key">{{ label }}</option>
      </select>
      <select v-if="auth.seesEverything" v-model="ownerId" class="input narrow" aria-label="Venditore">
        <option :value="null">Tutti i venditori</option>
        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <label class="group-by">
        Raggruppa
        <select v-model="groupBy" class="input narrow" aria-label="Raggruppa per">
          <template v-for="(label, key) in GROUPS" :key="key">
            <option v-if="isCustomers || key !== 'billing'" :value="key">{{ label }}</option>
          </template>
        </select>
      </label>
      <div class="fields">
        <button class="btn" :aria-expanded="showFields" @click="showFields = !showFields">Campi ({{ columns.length }})</button>
        <div v-if="showFields" class="fields-menu card" @mouseleave="showFields = false">
          <label v-for="c in COLUMNS.filter((c) => c.key !== 'name')" :key="c.key" class="checkbox">
            <input type="checkbox" :checked="!hidden.has(c.key)" @change="toggleColumn(c.key)" /> {{ c.label }}
          </label>
        </div>
      </div>
      <span class="muted small count">{{ meta?.total ?? 0 }} {{ isCustomers ? 'clienti' : 'leads' }}</span>
    </div>

    <div v-if="error" class="alert alert-error" role="alert">{{ error }}</div>
    <div v-if="notice" class="alert alert-success" role="status">{{ notice }}</div>

    <div v-if="selected.size" class="bulk-bar" role="toolbar" aria-label="Azioni sui selezionati">
      <strong>{{ selected.size }} selezionat{{ selected.size === 1 ? 'o' : 'i' }}</strong>
      <template v-if="!confirmingDelete">
        <button class="btn btn-sm" :disabled="bulkBusy" @click="bulkEditing = true">Modifica</button>
        <button v-if="auth.seesEverything" class="btn btn-sm btn-danger" :disabled="bulkBusy" @click="confirmingDelete = true">Elimina</button>
        <button class="btn btn-sm" :disabled="bulkBusy" @click="clearSelection">Annulla selezione</button>
      </template>
      <template v-else>
        <span>
          Eliminare {{ selected.size }} record?
          <template v-if="selectedDeals">Verranno eliminate anche <strong>{{ selectedDeals }} opportunità</strong> collegate, con referenti e attività.</template>
        </span>
        <button class="btn btn-sm btn-danger" :disabled="bulkBusy" @click="runBulk('delete')">Sì, elimina</button>
        <button class="btn btn-sm" :disabled="bulkBusy" @click="confirmingDelete = false">No</button>
      </template>
    </div>

    <div class="grid-wrap">
      <div class="grid" role="grid" :aria-rowcount="rows.length" :style="{ gridTemplateColumns: gridTemplate }">
        <!-- Intestazione -->
        <div class="cell head num sticky-a" role="columnheader">
          <input type="checkbox" class="pick" :checked="allSelected" :indeterminate="selected.size > 0 && !allSelected" :disabled="!rows.length" aria-label="Seleziona tutti" title="Seleziona tutti" @change="toggleAll" />
        </div>
        <div
          v-for="(col, i) in columns"
          :key="col.key"
          class="cell head"
          :class="{ sortable: !!col.sort, right: col.align === 'right', 'sticky-b': i === 0 }"
          role="columnheader"
          :aria-sort="sort.key === col.sort ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'"
          @click="toggleSort(col)"
        >
          {{ col.label }}
          <span v-if="col.sort && sort.key === col.sort" class="arrow">{{ sort.direction === 'asc' ? '▲' : '▼' }}</span>
        </div>

        <template v-for="group in groups" :key="group.key">
          <button v-if="groupBy !== 'none'" class="group-row" :style="{ gridColumn: '1 / -1' }" @click="toggleGroup(group.key)">
            <span class="caret">{{ collapsed.has(group.key) ? '▸' : '▾' }}</span>
            <strong>{{ group.label }}</strong>
            <span class="muted small">{{ group.rows.length }} · {{ money(group.total) }} aperti</span>
          </button>

          <template v-if="!collapsed.has(group.key)">
            <template v-for="(row, index) in group.rows" :key="row.id">
              <div class="cell num sticky-a" :class="{ picked: selected.has(row.id) }" role="rowheader">
                <input type="checkbox" class="pick" :checked="selected.has(row.id)" :aria-label="`Seleziona ${row.name}`" @change="toggleRow(row.id)" />
                <span class="idx">{{ index + 1 }}</span>
                <button class="open" :aria-label="`Apri ${row.name}`" title="Apri scheda" @click="router.push({ name: 'company', params: { id: row.id } })">↗</button>
              </div>
              <div
                v-for="(col, i) in columns"
                :key="col.key"
                class="cell"
                :class="{ right: col.align === 'right', editable: canEdit(col), editing: isEditing(row, col), 'sticky-b': i === 0, name: col.key === 'name' }"
                role="gridcell"
                :tabindex="canEdit(col) || col.key === 'name' ? 0 : -1"
                @click="col.key === 'name' ? (selectedId = row.id) : startEdit(row, col)"
                @keydown.enter="col.key === 'name' ? (selectedId = row.id) : !isEditing(row, col) && startEdit(row, col)"
              >
                <template v-if="isEditing(row, col)">
                  <select v-if="col.editor === 'segment'" v-model="draft" class="cell-editor" @change="commitEdit(row)" @blur="cancelEdit" @keydown="onKey($event, row)">
                    <option v-for="(label, key) in segments" :key="key" :value="key">{{ label }}</option>
                  </select>
                  <select v-else-if="col.editor === 'source'" v-model="draft" class="cell-editor" @change="commitEdit(row)" @blur="cancelEdit" @keydown="onKey($event, row)">
                    <option value="">—</option>
                    <option v-for="(label, key) in leadSources" :key="key" :value="key">{{ label }}</option>
                  </select>
                  <select v-else-if="col.editor === 'type'" v-model="draft" class="cell-editor" @change="commitEdit(row)" @blur="cancelEdit" @keydown="onKey($event, row)">
                    <option value="">—</option>
                    <option v-for="(label, key) in companyTypes" :key="key" :value="key">{{ label }}</option>
                  </select>
                  <select v-else-if="col.editor === 'owner'" v-model="draft" class="cell-editor" @change="commitEdit(row)" @blur="cancelEdit" @keydown="onKey($event, row)">
                    <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                  </select>
                  <input
                    v-else
                    v-model="draft"
                    class="cell-editor"
                    :type="col.editor === 'email' ? 'email' : col.editor === 'url' ? 'url' : 'text'"
                    @keydown="onKey($event, row)"
                    @blur="commitEdit(row)"
                  />
                </template>
                <span v-else-if="col.key === 'segment'" class="pill" :class="`seg-${row.segment}`">{{ display(row, col.key) }}</span>
                <span v-else-if="col.key === 'billing_complete'" class="pill" :class="row.billing_complete ? 'bill-ok' : 'bill-missing'">{{ display(row, col.key) }}</span>
                <span v-else class="value">{{ display(row, col.key) }}</span>
              </div>
            </template>
          </template>
        </template>

        <!-- Riga per aggiungere un cliente -->
        <div class="cell num sticky-a add-num">+</div>
        <div class="cell add sticky-b" :style="{ gridColumn: `2 / -1` }">
          <input
            v-model="newName"
            class="add-input"
            :placeholder="`${isCustomers ? 'Nuovo cliente' : 'Nuovo lead'}${segment ? ' ' + segments[segment] : ''}: scrivi il nome e premi Invio`"
            aria-label="Nome del nuovo cliente"
            @keydown.enter="createRow"
          />
        </div>
      </div>

      <div v-if="!loading && !rows.length" class="empty">
        {{ isCustomers ? "Nessun cliente con questi filtri. I lead diventano clienti quando vinci un'opportunità in Pipeline." : 'Nessun lead trovato con questi filtri.' }}
      </div>
    </div>

    <div class="footer">
      <span class="muted small">Clic sul nome per la scheda completa · clic su un'altra cella per modificarla · Invio per salvare · Esc per annullare</span>
      <button v-if="meta && meta.current_page < meta.last_page" class="btn btn-sm" :disabled="loading" @click="load(meta.current_page + 1)">
        Carica altri ({{ rows.length }} di {{ meta.total }})
      </button>
    </div>

    <BulkEditModal v-if="bulkEditing" :count="selected.size" :busy="bulkBusy" @close="bulkEditing = false" @apply="(changes) => runBulk('update', changes)" />
    <ClientDetailModal v-if="selectedId" :company-id="selectedId" @close="selectedId = null" @changed="load()" />
    <CompanyFormModal v-if="creating" :status="mode" :segment="segment || undefined" @close="creating = false" @saved="(c) => { creating = false; load(); selectedId = c.id }" />
    <ImportClientsModal v-if="importing" :status="mode" :segment="segment || undefined" @close="importing = false" @imported="load()" />
  </div>
</template>

<style scoped>
.clients { display: flex; flex-direction: column; gap: 12px; }
.clients .page-header { margin-bottom: 0; }
.tabs { display: flex; gap: 2px; background: #e9ebef; padding: 3px; border-radius: 8px; }
.tab { border: none; background: none; padding: 6px 14px; border-radius: 6px; font: inherit; cursor: pointer; color: var(--muted); font-weight: 500; }
.tab.active { background: var(--surface); color: var(--text); box-shadow: var(--shadow); }
.grid-toolbar { gap: 8px; }
.search { width: 240px; }
.narrow { width: auto; }
.group-by { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); }
.fields { position: relative; }
.fields-menu { position: absolute; top: calc(100% + 4px); left: 0; z-index: 20; display: flex; flex-direction: column; gap: 6px; min-width: 200px; padding: 12px; }
.count { margin-left: auto; }

.grid-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: auto; max-height: calc(100vh - 250px); }
.grid { display: grid; width: max-content; min-width: 100%; font-size: 13px; }
.cell { height: 36px; display: flex; align-items: center; padding: 0 10px; border-right: 1px solid #edf0f3; border-bottom: 1px solid #edf0f3; background: var(--surface); white-space: nowrap; overflow: hidden; min-width: 0; }
.cell .value { overflow: hidden; text-overflow: ellipsis; }
.cell.right { justify-content: flex-end; font-variant-numeric: tabular-nums; }
.cell.name { font-weight: 600; cursor: pointer; color: #1d4ed8; }
.cell.name:hover .value { text-decoration: underline; }
.cell.editable { cursor: text; }
.cell.editable:hover { background: #f7f9fc; }
.cell:focus-visible { outline: 2px solid #2563eb; outline-offset: -2px; }
.cell.editing { padding: 0; outline: 2px solid #2563eb; outline-offset: -2px; overflow: visible; }
.cell-editor { width: 100%; height: 100%; border: none; padding: 0 10px; font: inherit; background: #fff; outline: none; }

.head { position: sticky; top: 0; z-index: 3; background: #f6f7f9; font-weight: 600; font-size: 12px; color: #4b5563; user-select: none; }
.head.sortable { cursor: pointer; }
.head.sortable:hover { background: #eef0f4; }
.arrow { font-size: 9px; margin-left: 6px; color: #2563eb; }
.sticky-a { position: sticky; left: 0; z-index: 2; }
.sticky-b { position: sticky; left: 64px; z-index: 2; border-right: 1px solid #d9dde3; }
.head.sticky-a, .head.sticky-b { z-index: 4; }

.num { justify-content: flex-start; gap: 6px; color: #9ca3af; font-size: 11px; padding: 0 0 0 8px; }
.num .idx { min-width: 24px; text-align: center; }
.num.picked { background: #eff6ff; }
.pick { width: 15px; height: 15px; margin: 0; cursor: pointer; accent-color: #2563eb; }
.bulk-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 8px 12px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius); font-size: 13px; }
.num .open { display: none; border: 1px solid var(--border); background: #fff; border-radius: 4px; cursor: pointer; font-size: 12px; width: 24px; height: 22px; }
.num:hover .idx, .num:focus-within .idx { display: none; }
.num:hover .open, .num:focus-within .open { display: inline-block; }
.num .open:focus-visible { display: inline-block; outline: 2px solid #2563eb; }

.group-row { display: flex; align-items: center; gap: 10px; height: 34px; padding: 0 12px; background: #f1f3f7; border: none; border-bottom: 1px solid #e3e6eb; font: inherit; cursor: pointer; text-align: left; position: sticky; left: 0; }
.caret { width: 12px; color: var(--muted); }

.pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.subtitle { margin: 2px 0 0; }
.bill-ok { background: #dcfce7; color: #166534; }
.bill-missing { background: #fee2e2; color: #991b1b; }
.seg-b2b { background: #dbeafe; color: #1e40af; }
.seg-b2c { background: #dcfce7; color: #166534; }
.seg-franchising { background: #ede9fe; color: #5b21b6; }

.add-num { color: #9ca3af; font-size: 14px; }
.add { padding: 0; }
.add-input { width: 100%; height: 100%; border: none; padding: 0 10px; font: inherit; background: transparent; }
.add-input:focus { outline: 2px solid #2563eb; outline-offset: -2px; background: #fff; }

.footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
@media (max-width: 720px) {
  .search { width: 100%; }
  .count { margin-left: 0; }
}
</style>
