import { ref } from 'vue'
import { http } from '@/api/http'
import type { Contact, Paginated, Ref, Stage } from '@/types'

/** Liste di supporto per select e filtri (caricate su richiesta, in cache per la sessione). */
const stages = ref<Stage[]>([])
const users = ref<Ref[]>([])

export function useLookups() {
  async function loadStages(force = false) {
    if (!stages.value.length || force) {
      stages.value = (await http.get<{ data: Stage[] }>('/stages')).data.data
    }
    return stages.value
  }

  async function loadUsers() {
    if (!users.value.length) {
      users.value = (await http.get<{ data: Ref[] }>('/users/options')).data.data
    }
    return users.value
  }

  async function companyContacts(companyId: number) {
    return (await http.get<Paginated<Contact>>('/contacts', { params: { company_id: companyId } })).data.data
  }

  return { stages, users, loadStages, loadUsers, companyContacts }
}
