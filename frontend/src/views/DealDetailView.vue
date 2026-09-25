<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage, http } from '@/api/http'
import ActivityList from '@/components/ActivityList.vue'
import DealFormModal from '@/components/DealFormModal.vue'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { dealSources, formatDate, money } from '@/utils/format'
import type { Deal } from '@/types'

const props = defineProps<{ id: string }>()
const auth = useAuthStore()
const router = useRouter()
const { stages, loadStages } = useLookups()

const deal = ref<Deal | null>(null)
const editing = ref(false)
const error = ref('')

async function load() {
  try {
    deal.value = (await http.get<{ data: Deal }>(`/deals/${props.id}`)).data.data
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function moveTo(stageId: number) {
  if (!deal.value) return
  const stage = stages.value.find((s) => s.id === stageId)
  const lost_reason = stage?.is_lost ? (window.prompt('Motivo della perdita (facoltativo):') ?? undefined) : undefined
  try {
    await http.patch(`/deals/${deal.value.id}/move`, { pipeline_stage_id: stageId, lost_reason })
    await load()
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function remove() {
  if (!deal.value || !window.confirm('Eliminare definitivamente questa opportunità?')) return
  try {
    await http.delete(`/deals/${deal.value.id}`)
    await router.push({ name: 'pipeline' })
  } catch (e) {
    error.value = errorMessage(e)
  }
}

onMounted(() => Promise.all([load(), loadStages()]))
</script>

<template>
  <div>
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <template v-if="deal">
      <div class="page-header">
        <div>
          <RouterLink :to="{ name: 'pipeline' }" class="muted small">← Pipeline</RouterLink>
          <h1>{{ deal.title }}</h1>
          <div class="muted">
            <RouterLink :to="{ name: 'company', params: { id: deal.company_id } }">{{ deal.company?.name }}</RouterLink>
            <span v-if="deal.contact"> · {{ deal.contact.name }}</span>
          </div>
        </div>
        <div class="toolbar">
          <button class="btn" @click="editing = true">Modifica</button>
          <button v-if="auth.seesEverything" class="btn btn-danger" @click="remove">Elimina</button>
        </div>
      </div>

      <div class="stages card">
        <button
          v-for="s in stages"
          :key="s.id"
          class="stage"
          :class="{ active: s.id === deal.pipeline_stage_id }"
          :style="s.id === deal.pipeline_stage_id ? { background: s.color, borderColor: s.color } : {}"
          @click="moveTo(s.id)"
        >
          {{ s.name }}
        </button>
      </div>

      <div class="grid-2" style="margin-top: 16px">
        <div class="card">
          <h2>Dettagli</h2>
          <dl class="details">
            <dt>Valore</dt><dd><strong>{{ money(deal.value) }}</strong></dd>
            <dt>Brand</dt><dd>{{ deal.brand || '—' }}</dd>
            <dt>Categoria</dt><dd>{{ deal.product_category || '—' }}</dd>
            <dt>Quantità</dt><dd>{{ deal.quantity?.toLocaleString('it-IT') ?? '—' }}</dd>
            <dt>Chiusura prevista</dt><dd>{{ formatDate(deal.expected_close_date) }}</dd>
            <dt>Origine</dt><dd>{{ deal.source ? dealSources[deal.source] : '—' }}</dd>
            <dt>Venditore</dt><dd>{{ deal.owner?.name }}</dd>
            <template v-if="deal.lost_reason"><dt>Motivo perdita</dt><dd>{{ deal.lost_reason }}</dd></template>
            <dt>Note</dt><dd>{{ deal.notes || '—' }}</dd>
          </dl>
        </div>
        <div class="card">
          <h2>Attività</h2>
          <ActivityList :activities="deal.activities ?? []" :deal-id="deal.id" @changed="load" />
        </div>
      </div>

      <DealFormModal v-if="editing" :deal="deal" @close="editing = false" @saved="editing = false; load()" />
    </template>
  </div>
</template>

<style scoped>
.stages { display: flex; gap: 6px; flex-wrap: wrap; padding: 10px; }
.stage { flex: 1; min-width: 110px; border: 1px solid var(--border); background: #f8f9fb; padding: 8px; border-radius: 6px; cursor: pointer; font: inherit; }
.stage.active { color: #fff; font-weight: 600; }
</style>
