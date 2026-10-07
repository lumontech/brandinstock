<script setup lang="ts">
import { computed, ref } from 'vue'
import Papa from 'papaparse'
import AppModal from './AppModal.vue'
import { errorMessage, http } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { segments } from '@/utils/format'

/**
 * Importazione clienti da CSV (es. esportato da Airtable): il file viene letto nel browser,
 * le colonne vengono abbinate ai campi del CRM, poi si fa una prova e infine l'importazione.
 */

const props = defineProps<{ segment?: string; status?: 'lead' | 'customer' }>()
const emit = defineEmits<{ close: []; imported: [] }>()
const auth = useAuthStore()

interface Field { key: string; label: string; aliases: string[]; group: 'cliente' | 'fatturazione' | 'referente'; managersOnly?: boolean }
const FIELDS: Field[] = [
  { key: 'name', label: 'Nome / Ragione sociale *', group: 'cliente', aliases: ['nome', 'name', 'ragione sociale', 'azienda', 'cliente', 'company', 'denominazione', 'negozio'] },
  { key: 'segment', label: 'Categoria (B2B, B2C, Franchising)', group: 'cliente', aliases: ['categoria', 'segmento', 'segment', 'tipo cliente', 'canale', 'b2b b2c'] },
  { key: 'source', label: 'Provenienza lead', group: 'cliente', aliases: ['provenienza', 'provenienza lead', 'provenienza leads', 'fonte', 'origine', 'origine lead', 'lead source', 'source', 'canale di acquisizione', 'come ci ha conosciuto'] },
  { key: 'type', label: 'Tipologia (boutique, outlet…)', group: 'cliente', aliases: ['tipologia', 'tipo', 'type', 'tipo negozio'] },
  { key: 'vat_number', label: 'Partita IVA', group: 'cliente', aliases: ['partita iva', 'p iva', 'piva', 'p.iva', 'vat', 'vat number', 'iva'] },
  { key: 'tax_code', label: 'Codice fiscale', group: 'cliente', aliases: ['codice fiscale', 'cf', 'c f', 'fiscal code', 'tax code'] },
  { key: 'email', label: 'Email', group: 'cliente', aliases: ['email', 'e-mail', 'mail', 'email azienda'] },
  { key: 'phone', label: 'Telefono', group: 'cliente', aliases: ['telefono', 'tel', 'phone', 'cellulare', 'numero', 'numero di telefono', 'numero telefono', 'cellulare referente'] },
  { key: 'address', label: 'Indirizzo', group: 'cliente', aliases: ['indirizzo', 'address', 'via', 'sede'] },
  { key: 'city', label: 'Città', group: 'cliente', aliases: ['citta', 'città', 'city', 'comune', 'localita'] },
  { key: 'province', label: 'Provincia', group: 'cliente', aliases: ['provincia', 'prov', 'province'] },
  { key: 'country', label: 'Paese', group: 'cliente', aliases: ['paese', 'nazione', 'country'] },
  { key: 'website', label: 'Sito web', group: 'cliente', aliases: ['sito', 'sito web', 'website', 'web', 'url'] },
  { key: 'notes', label: 'Note', group: 'cliente', aliases: ['note', 'notes', 'descrizione', 'commenti'] },
  { key: 'owner_email', label: 'Email del venditore assegnato', group: 'cliente', aliases: ['venditore', 'commerciale', 'agente', 'owner', 'assegnato a', 'email venditore'], managersOnly: true },
  { key: 'billing_name', label: 'Intestazione fattura', group: 'fatturazione', aliases: ['intestazione', 'intestazione fattura', 'ragione sociale fatturazione', 'denominazione fiscale'] },
  { key: 'billing_address', label: 'Indirizzo sede legale', group: 'fatturazione', aliases: ['sede legale', 'indirizzo sede legale', 'indirizzo fatturazione'] },
  { key: 'billing_zip', label: 'CAP', group: 'fatturazione', aliases: ['cap', 'codice postale', 'zip', 'postal code'] },
  { key: 'billing_city', label: 'Città sede legale', group: 'fatturazione', aliases: ['citta sede legale', 'comune sede legale', 'citta fatturazione'] },
  { key: 'billing_province', label: 'Provincia sede legale', group: 'fatturazione', aliases: ['provincia sede legale', 'provincia fatturazione'] },
  { key: 'sdi_code', label: 'Codice SDI', group: 'fatturazione', aliases: ['sdi', 'codice sdi', 'codice destinatario', 'codice univoco', 'cod destinatario'] },
  { key: 'pec', label: 'PEC', group: 'fatturazione', aliases: ['pec', 'email pec', 'posta certificata'] },
  { key: 'iban', label: 'IBAN', group: 'fatturazione', aliases: ['iban', 'coordinate bancarie'] },
  { key: 'payment_terms', label: 'Condizioni di pagamento', group: 'fatturazione', aliases: ['pagamento', 'condizioni di pagamento', 'modalita di pagamento', 'termini di pagamento'] },
  { key: 'contact_name', label: 'Referente (nome e cognome)', group: 'referente', aliases: ['referente', 'contatto', 'contact', 'persona di riferimento', 'nome referente'] },
  { key: 'contact_first_name', label: 'Referente: nome', group: 'referente', aliases: ['nome referente', 'first name', 'nome contatto'] },
  { key: 'contact_last_name', label: 'Referente: cognome', group: 'referente', aliases: ['cognome', 'cognome referente', 'last name', 'cognome contatto'] },
  { key: 'contact_job_title', label: 'Referente: ruolo', group: 'referente', aliases: ['ruolo', 'qualifica', 'job title', 'posizione'] },
  { key: 'contact_email', label: 'Referente: email', group: 'referente', aliases: ['email referente', 'email contatto', 'contact email'] },
  { key: 'contact_phone', label: 'Referente: telefono', group: 'referente', aliases: ['telefono referente', 'cellulare referente', 'contact phone'] },
]
// Per i leads solo i dati principali: referenti e fatturazione servono solo ai clienti.
const fields = computed(() => FIELDS.filter((f) => (!f.managersOnly || auth.seesEverything) && (props.status === 'customer' || f.group === 'cliente')))
const groupOrder: Field['group'][] = props.status === 'customer' ? ['cliente', 'fatturazione', 'referente'] : ['cliente']

type Step = 'file' | 'map' | 'done'
const step = ref<Step>('file')
const fileName = ref('')
const headers = ref<string[]>([])
const data = ref<Record<string, string>[]>([])
const mapping = ref<Record<string, string>>({})
const defaultSegment = ref(props.segment || 'b2b')
const duplicates = ref<'skip' | 'update'>('skip')
const error = ref('')
const busy = ref(false)
const progress = ref(0)
// Le colonne non abbinate finiscono nelle note, così non si perde nessuna informazione.
const extraToNotes = ref(true)
const airtableLink = ref('')
const airtableToken = ref('')

interface Result { created: number; updated: number; skipped: number; contacts_created: number; errors: { row: number; messages: string[] }[] }
const check = ref<Result | null>(null)
const final = ref<Result | null>(null)

const norm = (s: string) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim()

function guessMapping() {
  const used = new Set<string>()
  const result: Record<string, string> = {}
  for (const field of fields.value) {
    const aliases = field.aliases.map(norm)
    const header = headers.value.find((h) => !used.has(h) && aliases.includes(norm(h)))
    if (header) {
      result[field.key] = header
      used.add(header)
    }
  }
  mapping.value = result
}

function onFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  error.value = ''
  if (file.size > 20 * 1024 * 1024) {
    error.value = 'Il file supera i 20 MB: dividilo in più parti.'
    return
  }
  fileName.value = file.name
  Papa.parse<Record<string, string>>(file, {
    header: true,
    skipEmptyLines: 'greedy',
    transformHeader: (h) => h.replace(/^﻿/, '').trim(),
    complete: (res) => {
      headers.value = (res.meta.fields ?? []).filter(Boolean)
      data.value = res.data
      if (!headers.value.length || !data.value.length) {
        error.value = 'Il file non contiene righe leggibili. Esporta la vista da Airtable in formato CSV.'
        return
      }
      guessMapping()
      check.value = null
      step.value = 'map'
    },
    error: () => (error.value = 'Impossibile leggere il file. Assicurati che sia un CSV.'),
  })
}

const unmapped = computed(() => {
  const used = new Set(Object.values(mapping.value).filter(Boolean))
  return headers.value.filter((h) => !used.has(h))
})

function buildRow(source: Record<string, string>) {
  const row: Record<string, string | null> = {}
  for (const [key, header] of Object.entries(mapping.value)) {
    if (header) row[key] = source[header] ?? null
  }
  if (extraToNotes.value) {
    const extra = unmapped.value
      .map((h) => [h, String(source[h] ?? '').trim()])
      .filter(([, v]) => v)
      .map(([h, v]) => `${h}: ${v}`)
    if (extra.length) row.notes = [row.notes, ...extra].filter(Boolean).join('\n')
  }
  // Senza nome ma con email: uso l'email come nome, così il contatto non va perso.
  if (!row.name?.trim() && row.email) row.name = row.email
  if (!row.segment) row.segment = defaultSegment.value
  return row
}

/** Legge la tabella direttamente da Airtable (tramite il server del CRM) invece che da un file. */
async function loadAirtable() {
  error.value = ''
  const ids = airtableLink.value.match(/(app[A-Za-z0-9]{14})\/(tbl[A-Za-z0-9]{14})(?:\/(viw[A-Za-z0-9]{14}))?/)
  if (!ids) {
    error.value = "Incolla il link della tabella di Airtable, come appare nella barra del browser (contiene 'app…/tbl…')."
    return
  }
  busy.value = true
  try {
    const { data: res } = await http.post<{ headers: string[]; rows: Record<string, string>[] }>(
      '/airtable/records',
      { token: airtableToken.value.trim(), base_id: ids[1], table_id: ids[2], view_id: ids[3] ?? null },
      { timeout: 180000 },
    )
    if (!res.rows.length) {
      error.value = 'La tabella di Airtable non contiene record.'
      return
    }
    headers.value = res.headers
    data.value = res.rows
    fileName.value = 'Airtable'
    airtableToken.value = ''
    guessMapping()
    check.value = null
    step.value = 'map'
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

const preview = computed(() => data.value.slice(0, 5).map(buildRow))
const mappedFields = computed(() => fields.value.filter((f) => mapping.value[f.key]))

const CHUNK = 200
async function run(dryRun: boolean): Promise<Result | null> {
  if (!mapping.value.name) {
    error.value = 'Scegli quale colonna contiene il nome del cliente.'
    return null
  }
  busy.value = true
  error.value = ''
  progress.value = 0
  const total: Result = { created: 0, updated: 0, skipped: 0, contacts_created: 0, errors: [] }
  let failedChunks = 0
  for (let start = 0; start < data.value.length; start += CHUNK) {
    const rows = data.value.slice(start, start + CHUNK).map(buildRow)
    try {
      const { data: res } = await http.post<Result>(
        '/companies/import',
        { rows, duplicates: duplicates.value, dry_run: dryRun, status: props.status ?? 'lead' },
        { timeout: 120000 },
      )
      total.created += res.created
      total.updated += res.updated
      total.skipped += res.skipped
      total.contacts_created += res.contacts_created
      // Numero di riga come appare nel file (la riga 1 è l'intestazione).
      total.errors.push(...res.errors.map((e) => ({ ...e, row: start + e.row + 2 })))
    } catch (e) {
      // Un blocco non riuscito non ferma gli altri: le sue righe finiscono tra gli errori.
      failedChunks++
      const status = (e as { response?: { status?: number } }).response?.status
      const reason = status && status >= 500 ? 'errore del server' : errorMessage(e)
      total.errors.push({ row: start + 2, messages: [`Righe ${start + 2}-${start + rows.length + 1} non elaborate (${reason}).`] })
    }
    progress.value = Math.min(100, Math.round(((start + rows.length) / data.value.length) * 100))
  }
  busy.value = false
  if (failedChunks && failedChunks * CHUNK >= data.value.length) {
    error.value = "Il server non è riuscito a elaborare il file. Riprova tra qualche minuto; se l'errore resta, mandami il file senza dati sensibili."
    return null
  }
  return total
}

async function verify() {
  check.value = await run(true)
}

async function importAll() {
  const res = await run(false)
  if (res) {
    final.value = res
    step.value = 'done'
    emit('imported')
  }
}
</script>

<template>
  <AppModal :title="props.status === 'customer' ? 'Importa clienti da CSV' : 'Importa leads da CSV'" wide @close="emit('close')">
    <div class="stack">
      <div v-if="error" class="alert alert-error" role="alert">{{ error }}</div>

      <!-- 1. Scelta del file -->
      <template v-if="step === 'file'">
        <div class="alert alert-info">
          In Airtable apri la tabella, clicca sul nome della vista → <strong>Scarica CSV</strong>, poi scegli qui il file.
          Nel passaggio successivo potrai abbinare le colonne e fare una prova prima di importare.
        </div>
        <label class="drop">
          <input type="file" accept=".csv,text/csv" @change="onFile" />
          <span><strong>Scegli il file CSV</strong><br /><span class="muted small">oppure trascinalo qui</span></span>
        </label>

        <form class="airtable stack" @submit.prevent="loadAirtable">
          <h3 class="map-title">Oppure leggi direttamente da Airtable</h3>
          <div class="field">
            <label for="at-link">Link della tabella (copialo dalla barra del browser)</label>
            <input id="at-link" v-model="airtableLink" class="input" placeholder="https://airtable.com/app…/tbl…/viw…" required />
          </div>
          <div class="field">
            <label for="at-token">Token di accesso Airtable</label>
            <input id="at-token" v-model="airtableToken" class="input" type="password" autocomplete="off" placeholder="pat…" required />
            <span class="muted small">
              Crealo su airtable.com/create/tokens con il permesso <strong>data.records:read</strong> e accesso alla base.
              Viene usato solo per questa lettura e non viene salvato.
            </span>
          </div>
          <div class="toolbar" style="justify-content: flex-end">
            <button type="submit" class="btn btn-primary" :disabled="busy">{{ busy ? 'Lettura da Airtable…' : 'Leggi da Airtable' }}</button>
          </div>
        </form>
      </template>

      <!-- 2. Abbinamento colonne, prova e importazione -->
      <template v-else-if="step === 'map'">
        <p class="muted">
          <strong>{{ fileName }}</strong>: {{ data.length }} righe, {{ headers.length }} colonne.
          Abbina le colonne del file ai campi del CRM (quelle riconosciute sono già selezionate).
        </p>

        <div class="map-grid">
          <template v-for="group in groupOrder" :key="group">
            <h3 class="map-title">{{ { cliente: 'Dati principali', fatturazione: 'Fatturazione (facoltativo)', referente: 'Referente (facoltativo)' }[group] }}</h3>
            <div v-for="f in fields.filter((x) => x.group === group)" :key="f.key" class="map-row">
              <label :for="`map-${f.key}`">{{ f.label }}</label>
              <select :id="`map-${f.key}`" v-model="mapping[f.key]" class="input" @change="check = null">
                <option :value="undefined">— non importare —</option>
                <option v-for="h in headers" :key="h" :value="h">{{ h }}</option>
              </select>
            </div>
          </template>
        </div>

        <div class="form-grid">
          <div class="field">
            <label for="imp-seg">Categoria se la colonna è vuota</label>
            <select id="imp-seg" v-model="defaultSegment" class="input" @change="check = null">
              <option v-for="(label, key) in segments" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div class="field">
            <label for="imp-dup">Clienti già presenti (stessa P.IVA o stesso nome)</label>
            <select id="imp-dup" v-model="duplicates" class="input" @change="check = null">
              <option value="skip">Salta, non modificarli</option>
              <option value="update">Aggiorna con i dati del file</option>
            </select>
          </div>
        </div>

        <label v-if="unmapped.length" class="checkbox">
          <input v-model="extraToNotes" type="checkbox" @change="check = null" />
          Aggiungi alle note le colonne non abbinate ({{ unmapped.join(', ') }})
        </label>

        <div v-if="mappedFields.length" class="preview">
          <h3 class="map-title">Anteprima delle prime righe</h3>
          <div class="preview-scroll">
            <table class="table">
              <thead><tr><th v-for="f in mappedFields" :key="f.key">{{ f.label.replace(' *', '') }}</th></tr></thead>
              <tbody>
                <tr v-for="(row, i) in preview" :key="i">
                  <td v-for="f in mappedFields" :key="f.key">{{ row[f.key] ?? '' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="check" class="alert" :class="check.errors.length ? 'alert-info' : 'alert-success'">
          <strong>Risultato della prova</strong> (nessun dato è stato salvato):
          {{ check.created }} nuovi {{ props.status === 'customer' ? 'clienti' : 'leads' }}, {{ check.updated }} aggiornati, {{ check.skipped }} già presenti saltati,
          {{ check.contacts_created }} referenti.
          <span v-if="check.errors.length"> {{ check.errors.length }} righe con errori non verranno importate.</span>
        </div>
        <ul v-if="check?.errors.length" class="errors small">
          <li v-for="e in check.errors.slice(0, 50)" :key="e.row">Riga {{ e.row }}: {{ e.messages.join(' ') }}</li>
          <li v-if="check.errors.length > 50" class="muted">…e altre {{ check.errors.length - 50 }} righe.</li>
        </ul>

        <div v-if="busy" class="progress" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
          <div :style="{ width: progress + '%' }" />
        </div>

        <div class="toolbar actions">
          <button class="btn" :disabled="busy" @click="step = 'file'">Cambia file</button>
          <span class="spacer" />
          <button class="btn" :disabled="busy" @click="verify">Prova (senza salvare)</button>
          <button class="btn btn-primary" :disabled="busy" @click="importAll">
            Importa {{ data.length }} righe
          </button>
        </div>
      </template>

      <!-- 3. Fine -->
      <template v-else>
        <div class="alert alert-success">
          Importazione completata: {{ final?.created }} nuovi {{ props.status === 'customer' ? 'clienti' : 'leads' }}, {{ final?.updated }} aggiornati,
          {{ final?.skipped }} saltati, {{ final?.contacts_created }} referenti creati.
        </div>
        <ul v-if="final?.errors.length" class="errors small">
          <li v-for="e in final.errors.slice(0, 50)" :key="e.row">Riga {{ e.row }} non importata: {{ e.messages.join(' ') }}</li>
        </ul>
        <div class="toolbar actions">
          <span class="spacer" />
          <button class="btn btn-primary" @click="emit('close')">Chiudi</button>
        </div>
      </template>
    </div>
  </AppModal>
</template>

<style scoped>
.drop { display: flex; align-items: center; justify-content: center; text-align: center; min-height: 140px; border: 2px dashed #c7cdd6; border-radius: var(--radius); cursor: pointer; position: relative; padding: 16px; }
.airtable { border-top: 1px solid var(--border); padding-top: 8px; }
.drop:hover { background: #f8f9fb; }
.drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.map-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; }
.map-title { grid-column: 1 / -1; font-size: 13px; margin: 8px 0 0; }
.map-row { display: flex; flex-direction: column; gap: 4px; }
.map-row label { font-size: 12px; color: var(--muted); font-weight: 600; }
.preview-scroll { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; }
.preview .table { font-size: 12px; white-space: nowrap; }
.errors { max-height: 160px; overflow-y: auto; margin: 0; padding-left: 18px; color: #991b1b; }
.progress { height: 6px; background: #eef0f3; border-radius: 3px; overflow: hidden; }
.progress div { height: 100%; background: #111827; transition: width 0.2s; }
.actions { justify-content: flex-end; }
.spacer { flex: 1; }
@media (max-width: 720px) { .map-grid { grid-template-columns: 1fr; } }
</style>
