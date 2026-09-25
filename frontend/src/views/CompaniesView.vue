<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { http } from '@/api/http'
import CompanyFormModal from '@/components/CompanyFormModal.vue'
import { useAuthStore } from '@/stores/auth'
import { companyTypes } from '@/utils/format'
import type { Company, Paginated } from '@/types'

const auth = useAuthStore()
const router = useRouter()
const result = ref<Paginated<Company> | null>(null)
const q = ref('')
const type = ref('')
const page = ref(1)
const creating = ref(false)

async function load() {
  result.value = (await http.get<Paginated<Company>>('/companies', { params: { q: q.value || undefined, type: type.value || undefined, page: page.value } })).data
}

onMounted(load)
let timer: number | undefined
watch([q, type], () => {
  page.value = 1
  clearTimeout(timer)
  timer = window.setTimeout(load, 250)
})
watch(page, load)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Aziende</h1>
      <div class="toolbar">
        <input v-model="q" class="input" type="search" placeholder="Nome, P.IVA, città…" aria-label="Cerca" style="width: 240px" />
        <select v-model="type" class="input" style="width: 160px" aria-label="Tipologia">
          <option value="">Tutte le tipologie</option>
          <option v-for="(label, key) in companyTypes" :key="key" :value="key">{{ label }}</option>
        </select>
        <button class="btn btn-primary" @click="creating = true">+ Nuova azienda</button>
      </div>
    </div>

    <div class="card">
      <table v-if="result?.data.length" class="table">
        <thead>
          <tr><th>Azienda</th><th>Tipologia</th><th>Città</th><th>Opportunità</th><th v-if="auth.seesEverything">Venditore</th></tr>
        </thead>
        <tbody>
          <tr v-for="c in result.data" :key="c.id" class="clickable" @click="router.push({ name: 'company', params: { id: c.id } })">
            <td><strong>{{ c.name }}</strong><div class="muted small">{{ c.vat_number }}</div></td>
            <td>{{ c.type ? companyTypes[c.type] : '—' }}</td>
            <td>{{ c.city || '—' }}</td>
            <td>{{ c.deals_count }}</td>
            <td v-if="auth.seesEverything">{{ c.owner?.name }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="empty">Nessuna azienda trovata.</div>

      <div v-if="result && result.meta.last_page > 1" class="toolbar" style="justify-content: flex-end; margin-top: 12px">
        <button class="btn btn-sm" :disabled="page <= 1" @click="page--">‹</button>
        <span class="muted small">{{ result.meta.current_page }} / {{ result.meta.last_page }}</span>
        <button class="btn btn-sm" :disabled="page >= result.meta.last_page" @click="page++">›</button>
      </div>
    </div>

    <CompanyFormModal v-if="creating" @close="creating = false" @saved="(c) => router.push({ name: 'company', params: { id: c.id } })" />
  </div>
</template>
