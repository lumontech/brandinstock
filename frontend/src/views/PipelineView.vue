<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage, http } from '@/api/http'
import DealFormModal from '@/components/DealFormModal.vue'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { formatDate, money } from '@/utils/format'
import type { Deal, PipelineColumn } from '@/types'

const auth = useAuthStore()
const router = useRouter()
const { users, loadUsers } = useLookups()

const columns = ref<PipelineColumn[]>([])
const ownerId = ref<number | null>(null)
const q = ref('')
const error = ref('')
const loading = ref(true)
const creatingInStage = ref<number | null | undefined>(undefined)
const dragging = ref<Deal | null>(null)
const overStage = ref<number | null>(null)

async function load() {
  loading.value = true
  try {
    const { data } = await http.get<{ data: PipelineColumn[] }>('/pipeline', { params: { owner_id: ownerId.value || undefined, q: q.value || undefined } })
    columns.value = data.data
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
watch([ownerId, q], () => {
  clearTimeout(timer)
  timer = window.setTimeout(load, 250)
})

function onDragStart(deal: Deal) {
  dragging.value = deal
}

async function onDrop(column: PipelineColumn) {
  const deal = dragging.value
  dragging.value = null
  overStage.value = null
  if (!deal || deal.pipeline_stage_id === column.id) return

  let lostReason: string | undefined
  if (column.is_lost) {
    lostReason = window.prompt('Motivo della perdita (facoltativo):') ?? undefined
  }

  // Aggiornamento ottimistico, con ripristino in caso di errore.
  const from = columns.value.find((c) => c.id === deal.pipeline_stage_id)
  if (from) {
    from.deals = from.deals.filter((d) => d.id !== deal.id)
    from.total_value -= deal.value
  }
  column.deals.unshift({ ...deal, pipeline_stage_id: column.id })
  column.total_value += deal.value

  try {
    await http.patch(`/deals/${deal.id}/move`, { pipeline_stage_id: column.id, lost_reason: lostReason })
  } catch (e) {
    error.value = errorMessage(e)
    await load()
  }
}

function onSaved(deal: Deal) {
  creatingInStage.value = undefined
  router.push({ name: 'deal', params: { id: deal.id } })
}
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Pipeline di vendita</h1>
      <div class="toolbar">
        <input v-model="q" class="input search" type="search" placeholder="Cerca opportunità, brand, azienda…" aria-label="Cerca" />
        <select v-if="auth.seesEverything" v-model="ownerId" class="input owner" aria-label="Venditore">
          <option :value="null">Tutti i venditori</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <button class="btn btn-primary" @click="creatingInStage = null">+ Nuova opportunità</button>
      </div>
    </div>

    <div v-if="error" class="alert alert-error" style="margin-bottom: 12px">{{ error }}</div>
    <div v-if="loading && !columns.length" class="empty">Caricamento…</div>

    <div class="board">
      <section
        v-for="col in columns"
        :key="col.id"
        class="column"
        :class="{ over: overStage === col.id }"
        @dragover.prevent="overStage = col.id"
        @dragleave="overStage = null"
        @drop.prevent="onDrop(col)"
      >
        <header :style="{ borderTopColor: col.color }">
          <div class="title">
            <h3>{{ col.name }}</h3>
            <span class="badge">{{ col.deals.length }}</span>
          </div>
          <div class="muted small">{{ money(col.total_value) }} · {{ col.probability }}%</div>
        </header>
        <div class="cards">
          <article
            v-for="deal in col.deals"
            :key="deal.id"
            class="deal"
            draggable="true"
            tabindex="0"
            @dragstart="onDragStart(deal)"
            @click="router.push({ name: 'deal', params: { id: deal.id } })"
            @keydown.enter="router.push({ name: 'deal', params: { id: deal.id } })"
          >
            <div class="deal-title">{{ deal.title }}</div>
            <div class="muted small">{{ deal.company?.name }}</div>
            <div class="meta">
              <strong>{{ money(deal.value) }}</strong>
              <span class="muted small">{{ formatDate(deal.expected_close_date) }}</span>
            </div>
            <div v-if="auth.seesEverything && deal.owner" class="muted small">{{ deal.owner.name }}</div>
          </article>
          <button v-if="!col.is_won && !col.is_lost" class="add" @click="creatingInStage = col.id">+ Aggiungi</button>
        </div>
      </section>
    </div>

    <DealFormModal
      v-if="creatingInStage !== undefined"
      :stage-id="creatingInStage ?? undefined"
      @close="creatingInStage = undefined"
      @saved="onSaved"
    />
  </div>
</template>

<style scoped>
.search { width: 260px; }
.owner { width: 180px; }
.board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 12px; align-items: flex-start; }
.column { flex: 0 0 260px; background: #eceef2; border-radius: var(--radius); display: flex; flex-direction: column; max-height: calc(100vh - 140px); }
.column.over { outline: 2px dashed #9aa3af; }
.column header { padding: 10px 12px; border-top: 4px solid; border-radius: var(--radius) var(--radius) 0 0; background: #e4e7ec; }
.title { display: flex; justify-content: space-between; align-items: center; }
.cards { padding: 8px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; }
.deal { background: #fff; border-radius: 8px; padding: 10px; box-shadow: var(--shadow); cursor: grab; border: 1px solid transparent; }
.deal:hover, .deal:focus { border-color: #cbd2dc; outline: none; }
.deal-title { font-weight: 600; margin-bottom: 2px; }
.meta { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; }
.add { background: none; border: 1px dashed #b8bfca; border-radius: 8px; padding: 6px; cursor: pointer; color: var(--muted); font: inherit; }
</style>
