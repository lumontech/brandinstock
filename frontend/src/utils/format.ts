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

/** Stato del lead: gli stessi del campo "Stato" di Airtable, nello stesso ordine. */
export const leadStatuses: Record<string, string> = {
  nuovo: 'Nuova Leads',
  qualificato: 'Qualificato',
  prospect: 'Prospect',
  cliente: 'Cliente',
  non_interessato: 'Non interessato',
  in_attesa: 'In attesa',
  email_inviata: 'Inviata email',
  appuntamento: 'Da prendere appuntamento',
  da_richiamare: 'Da richiamare',
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

/** Sigle di provincia → capoluogo (le stesse del server, per scegliere la città da un elenco). */
export const italianProvinces: Record<string, string> = {
  "AG": "Agrigento",
  "AL": "Alessandria",
  "AN": "Ancona",
  "AO": "Aosta",
  "AR": "Arezzo",
  "AP": "Ascoli Piceno",
  "AT": "Asti",
  "AV": "Avellino",
  "BA": "Bari",
  "BT": "Barletta",
  "BL": "Belluno",
  "BN": "Benevento",
  "BG": "Bergamo",
  "BI": "Biella",
  "BO": "Bologna",
  "BZ": "Bolzano",
  "BS": "Brescia",
  "BR": "Brindisi",
  "CA": "Cagliari",
  "CL": "Caltanissetta",
  "CB": "Campobasso",
  "CE": "Caserta",
  "CT": "Catania",
  "CZ": "Catanzaro",
  "CH": "Chieti",
  "CO": "Como",
  "CS": "Cosenza",
  "CR": "Cremona",
  "KR": "Crotone",
  "CN": "Cuneo",
  "EN": "Enna",
  "FM": "Fermo",
  "FE": "Ferrara",
  "FI": "Firenze",
  "FG": "Foggia",
  "FC": "Forlì",
  "FR": "Frosinone",
  "GE": "Genova",
  "GO": "Gorizia",
  "GR": "Grosseto",
  "IM": "Imperia",
  "IS": "Isernia",
  "AQ": "L'Aquila",
  "SP": "La Spezia",
  "LT": "Latina",
  "LE": "Lecce",
  "LC": "Lecco",
  "LI": "Livorno",
  "LO": "Lodi",
  "LU": "Lucca",
  "MC": "Macerata",
  "MN": "Mantova",
  "MS": "Massa",
  "MT": "Matera",
  "ME": "Messina",
  "MI": "Milano",
  "MO": "Modena",
  "MB": "Monza",
  "NA": "Napoli",
  "NO": "Novara",
  "NU": "Nuoro",
  "OR": "Oristano",
  "PD": "Padova",
  "PA": "Palermo",
  "PR": "Parma",
  "PV": "Pavia",
  "PG": "Perugia",
  "PU": "Pesaro",
  "PE": "Pescara",
  "PC": "Piacenza",
  "PI": "Pisa",
  "PT": "Pistoia",
  "PN": "Pordenone",
  "PZ": "Potenza",
  "PO": "Prato",
  "RG": "Ragusa",
  "RA": "Ravenna",
  "RC": "Reggio Calabria",
  "RE": "Reggio Emilia",
  "RI": "Rieti",
  "RN": "Rimini",
  "RM": "Roma",
  "RO": "Rovigo",
  "SA": "Salerno",
  "SS": "Sassari",
  "SV": "Savona",
  "SI": "Siena",
  "SR": "Siracusa",
  "SO": "Sondrio",
  "SU": "Carbonia",
  "TA": "Taranto",
  "TE": "Teramo",
  "TR": "Terni",
  "TO": "Torino",
  "TP": "Trapani",
  "TN": "Trento",
  "TV": "Treviso",
  "TS": "Trieste",
  "UD": "Udine",
  "VA": "Varese",
  "VE": "Venezia",
  "VB": "Verbania",
  "VC": "Vercelli",
  "VR": "Verona",
  "VV": "Vibo Valentia",
  "VI": "Vicenza",
  "VT": "Viterbo"
}
export const italianCities = Object.values(italianProvinces).sort((a, b) => a.localeCompare(b, "it"))
