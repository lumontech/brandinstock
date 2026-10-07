const currency = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
const date = new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', year: 'numeric' })
const dateTime = new Intl.DateTimeFormat('it-IT', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

export const money = (value: number | null | undefined) => currency.format(value ?? 0)
export const formatDate = (value: string | null | undefined) => (value ? date.format(new Date(value)) : '—')
export const formatDateTime = (value: string | null | undefined) => (value ? dateTime.format(new Date(value)) : '—')

export const activityLabels: Record<string, string> = {
  call: 'Chiamata',
  email: 'Email',
  meeting: 'Incontro',
  task: 'Attività',
  note: 'Nota',
}

export const segments: Record<string, string> = {
  b2b: 'B2B',
  b2c: 'B2C',
  franchising: 'Franchising',
}

export const companyTypes: Record<string, string> = {
  boutique: 'Boutique',
  outlet: 'Outlet',
  grossista: 'Grossista',
  ecommerce: 'E-commerce',
  catena: 'Catena retail',
  altro: 'Altro',
}

/** Provenienza dei lead (stesso elenco per clienti e opportunità). */
export const leadSources: Record<string, string> = {
  sito: 'Sito web',
  google: 'Google / Ads',
  social: 'Social (Facebook, Instagram)',
  linkedin: 'LinkedIn',
  fiera: 'Fiera / Evento',
  passaparola: 'Passaparola',
  email: 'Email / Newsletter',
  cold_call: 'Cold call',
  whatsapp: 'WhatsApp',
  agente: 'Agente / Segnalatore',
  cliente_esistente: 'Cliente esistente',
  altro: 'Altro',
}
export const dealSources = leadSources

/** Stato di lavorazione dei lead (in ordine di avanzamento). */
export const leadStatuses: Record<string, string> = {
  nuovo: 'Nuovo',
  da_richiamare: 'Da richiamare',
  email_inviata: 'Email inviata',
  appuntamento: 'Da fissare appuntamento',
  in_attesa: 'In attesa',
  qualificato: 'Qualificato',
  prospect: 'Prospect',
  non_interessato: 'Non interessato',
}

export const paymentTermsOptions = [
  'Bonifico anticipato',
  'Bonifico 30 gg data fattura',
  'Bonifico 60 gg data fattura',
  'Bonifico 30 gg fine mese',
  'Ri.Ba. 30 gg',
  'Ri.Ba. 60 gg',
  'Contrassegno',
  'Carta di credito',
]

/** Da ISO a valore per <input type="datetime-local">. */
export function toLocalInput(value: string | null | undefined): string {
  if (!value) return ''
  const d = new Date(value)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
