export type Role = 'admin' | 'manager' | 'sales'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  role_label: string
  is_active: boolean
  two_factor_enabled: boolean
  two_factor_required: boolean
  last_login_at: string | null
}

export interface Ref { id: number; name: string }

export interface Stage {
  id: number
  name: string
  position: number
  probability: number
  color: string
  is_won: boolean
  is_lost: boolean
}

export interface Deal {
  id: number
  title: string
  value: number
  currency: string
  brand: string | null
  product_category: string | null
  quantity: number | null
  expected_close_date: string | null
  source: string | null
  lost_reason: string | null
  notes: string | null
  pipeline_stage_id: number
  company_id: number
  contact_id: number | null
  stage?: Stage
  company?: Ref
  contact?: Ref | null
  owner?: Ref
  activities?: Activity[]
  closed_at: string | null
  updated_at: string
}

export interface PipelineColumn extends Stage {
  total_value: number
  deals: Deal[]
}

export interface Contact {
  id: number
  company_id: number | null
  company?: Ref
  first_name: string
  last_name: string | null
  full_name: string
  job_title: string | null
  email: string | null
  phone: string | null
  notes: string | null
  marketing_consent: boolean
  owner?: Ref
}

export type Segment = 'b2b' | 'b2c' | 'franchising'

export interface Company {
  id: number
  name: string
  segment: Segment
  status: 'lead' | 'customer'
  source: string | null
  lead_status?: string | null
  contact_person?: string | null
  converted_at?: string | null
  vat_number: string | null
  tax_code: string | null
  type: string | null
  city: string | null
  province: string | null
  country: string
  address: string | null
  email: string | null
  phone: string | null
  website: string | null
  notes: string | null
  billing_name: string | null
  billing_address: string | null
  billing_zip: string | null
  billing_city: string | null
  billing_province: string | null
  billing_country: string | null
  sdi_code: string | null
  pec: string | null
  iban: string | null
  payment_terms: string | null
  billing_notes: string | null
  billing_complete: boolean
  won_value?: number
  owner?: Ref
  deals_count?: number
  contacts_count?: number
  open_deals_value?: number
  last_activity_at?: string | null
  created_at?: string
  contacts?: Contact[]
  deals?: Deal[]
  activities?: Activity[]
}

export type ActivityType = 'call' | 'email' | 'meeting' | 'task' | 'note'

export interface Activity {
  id: number
  type: ActivityType
  subject: string
  description: string | null
  deal_id: number | null
  company_id: number | null
  deal?: { id: number; title: string } | null
  company?: Ref | null
  user?: Ref
  due_at: string | null
  completed_at: string | null
  is_overdue: boolean
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}
