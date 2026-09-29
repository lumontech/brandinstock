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

export const dealSources: Record<string, string> = {
  sito: 'Sito web',
  fiera: 'Fiera',
  passaparola: 'Passaparola',
  social: 'Social',
  cold_call: 'Cold call',
  email: 'Email',
  cliente_esistente: 'Cliente esistente',
  altro: 'Altro',
}

/** Da ISO a valore per <input type="datetime-local">. */
export function toLocalInput(value: string | null | undefined): string {
  if (!value) return ''
  const d = new Date(value)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
