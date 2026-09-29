<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage, http } from '@/api/http'
import ActivityList from '@/components/ActivityList.vue'
import CompanyFormModal from '@/components/CompanyFormModal.vue'
import ContactFormModal from '@/components/ContactFormModal.vue'
import DealFormModal from '@/components/DealFormModal.vue'
import { useAuthStore } from '@/stores/auth'
import { companyTypes, money, segments } from '@/utils/format'
import type { Company, Contact } from '@/types'

const props = defineProps<{ id: string }>()
const auth = useAuthStore()
const router = useRouter()

const company = ref<Company | null>(null)
const error = ref('')
const editing = ref(false)
const creatingDeal = ref(false)
const contactModal = ref<{ contact: Contact | null } | null>(null)

async function load() {
  try {
    company.value = (await http.get<{ data: Company }>(`/companies/${props.id}`)).data.data
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function remove() {
  if (!company.value || !window.confirm('Eliminare questo cliente?')) return
  try {
    await http.delete(`/companies/${company.value.id}`)
    await router.push({ name: 'companies' })
  } catch (e) {
    error.value = errorMessage(e)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="error" class="alert alert-error" style="margin-bottom: 12px">{{ error }}</div>
    <template v-if="company">
      <div class="page-header">
        <div>
          <RouterLink :to="{ name: 'companies' }" class="muted small">← Clienti</RouterLink>
          <h1>{{ company.name }}</h1>
          <div class="muted">
            <span class="badge">{{ segments[company.segment] }}</span>
            {{ company.type ? companyTypes[company.type] : '' }} {{ company.city ? '· ' + company.city : '' }}
          </div>
        </div>
        <div class="toolbar">
          <button class="btn btn-primary" @click="creatingDeal = true">+ Opportunità</button>
          <button class="btn" @click="editing = true">Modifica</button>
          <button v-if="auth.seesEverything" class="btn btn-danger" @click="remove">Elimina</button>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <h2>Anagrafica</h2>
          <dl class="details">
            <dt>Categoria</dt><dd>{{ segments[company.segment] }}</dd>
            <dt>Partita IVA</dt><dd>{{ company.vat_number || '—' }}</dd>
            <dt>Codice fiscale</dt><dd>{{ company.tax_code || '—' }}</dd>
            <dt>Indirizzo</dt><dd>{{ company.address || '—' }}</dd>
            <dt>Email</dt><dd><a v-if="company.email" :href="`mailto:${company.email}`">{{ company.email }}</a><span v-else>—</span></dd>
            <dt>Telefono</dt><dd>{{ company.phone || '—' }}</dd>
            <dt>Sito</dt><dd><a v-if="company.website" :href="company.website" target="_blank" rel="noopener noreferrer">{{ company.website }}</a><span v-else>—</span></dd>
            <dt>Venditore</dt><dd>{{ company.owner?.name }}</dd>
            <dt>Note</dt><dd>{{ company.notes || '—' }}</dd>
          </dl>
        </div>

        <div class="card">
          <div class="page-header" style="margin-bottom: 8px">
            <h2 style="margin: 0">Referenti</h2>
            <button class="btn btn-sm" @click="contactModal = { contact: null }">+ Referente</button>
          </div>
          <div v-if="!company.contacts?.length" class="empty">Nessun referente.</div>
          <table v-else class="table">
            <tbody>
              <tr v-for="c in company.contacts" :key="c.id" class="clickable" @click="contactModal = { contact: c }">
                <td><strong>{{ c.full_name }}</strong><div class="muted small">{{ c.job_title }}</div></td>
                <td class="small">{{ c.email }}<div>{{ c.phone }}</div></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h2>Opportunità</h2>
          <div v-if="!company.deals?.length" class="empty">Nessuna opportunità.</div>
          <table v-else class="table">
            <tbody>
              <tr v-for="d in company.deals" :key="d.id" class="clickable" @click="router.push({ name: 'deal', params: { id: d.id } })">
                <td><strong>{{ d.title }}</strong></td>
                <td><span class="badge" :style="{ background: d.stage?.color, color: '#fff' }">{{ d.stage?.name }}</span></td>
                <td>{{ money(d.value) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h2>Attività</h2>
          <ActivityList :activities="company.activities ?? []" :company-id="company.id" show-context @changed="load" />
        </div>
      </div>

      <CompanyFormModal v-if="editing" :company="company" @close="editing = false" @saved="editing = false; load()" />
      <DealFormModal v-if="creatingDeal" :company-id="company.id" @close="creatingDeal = false" @saved="(d) => router.push({ name: 'deal', params: { id: d.id } })" />
      <ContactFormModal v-if="contactModal" :company-id="company.id" :contact="contactModal.contact" @close="contactModal = null" @saved="contactModal = null; load()" />
    </template>
  </div>
</template>
