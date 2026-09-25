<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { http } from '@/api/http'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { formatDate, money } from '@/utils/format'

interface StageStat { id: number; name: string; color: string; probability: number; deals_count: number; total_value: number }
interface SellerStat { id: number; name: string; deals_count: number; total_value: number }
interface Stats {
  open_value: number
  open_count: number
  weighted_value: number
  won_this_month_value: number
  won_this_month_count: number
  win_rate_quarter: number | null
  pipeline: StageStat[]
  by_seller: SellerStat[] | null
  my_overdue_activities: number
  my_today_activities: number
  closing_soon: { id: number; title: string; company: string; value: number; expected_close_date: string }[]
}

const auth = useAuthStore()
const { users, loadUsers } = useLookups()
const stats = ref<Stats | null>(null)
const ownerId = ref<number | null>(null)

const maxStage = computed(() => Math.max(1, ...(stats.value?.pipeline.map((s) => s.total_value) ?? [1])))
const maxSeller = computed(() => Math.max(1, ...(stats.value?.by_seller?.map((s) => s.total_value) ?? [1])))

async function load() {
  stats.value = (await http.get<Stats>('/dashboard', { params: { owner_id: ownerId.value || undefined } })).data
}

onMounted(async () => {
  if (auth.seesEverything) await loadUsers()
  await load()
})
watch(ownerId, load)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Dashboard</h1>
      <select v-if="auth.seesEverything" v-model="ownerId" class="input" style="width: 200px" aria-label="Venditore">
        <option :value="null">Tutto il team</option>
        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
    </div>

    <template v-if="stats">
      <div class="kpis">
        <div class="card kpi"><div class="label">Pipeline aperta</div><div class="value">{{ money(stats.open_value) }}</div><div class="muted small">{{ stats.open_count }} opportunità</div></div>
        <div class="card kpi"><div class="label">Previsione ponderata</div><div class="value">{{ money(stats.weighted_value) }}</div><div class="muted small">valore × probabilità di fase</div></div>
        <div class="card kpi"><div class="label">Vinto questo mese</div><div class="value">{{ money(stats.won_this_month_value) }}</div><div class="muted small">{{ stats.won_this_month_count }} chiuse</div></div>
        <div class="card kpi"><div class="label">Tasso di vittoria (trimestre)</div><div class="value">{{ stats.win_rate_quarter === null ? '—' : stats.win_rate_quarter + '%' }}</div></div>
        <RouterLink :to="{ name: 'activities' }" class="card kpi" style="text-decoration: none">
          <div class="label">Le mie attività</div>
          <div class="value" :style="{ color: stats.my_overdue_activities ? 'var(--danger)' : undefined }">{{ stats.my_overdue_activities }}</div>
          <div class="muted small">in ritardo · {{ stats.my_today_activities }} oggi</div>
        </RouterLink>
      </div>

      <div class="grid-2">
        <div class="card">
          <h2>Valore per fase</h2>
          <div v-for="s in stats.pipeline" :key="s.id" class="bar-row">
            <div class="bar-label"><span>{{ s.name }}</span><span class="muted small">{{ s.deals_count }} · {{ money(s.total_value) }}</span></div>
            <div class="bar"><div :style="{ width: (s.total_value / maxStage) * 100 + '%', background: s.color }" /></div>
          </div>
        </div>

        <div v-if="stats.by_seller" class="card">
          <h2>Pipeline per venditore</h2>
          <div v-if="!stats.by_seller.length" class="empty">Nessun dato.</div>
          <div v-for="s in stats.by_seller" :key="s.id" class="bar-row">
            <div class="bar-label"><span>{{ s.name }}</span><span class="muted small">{{ s.deals_count }} · {{ money(s.total_value) }}</span></div>
            <div class="bar"><div :style="{ width: (s.total_value / maxSeller) * 100 + '%' }" /></div>
          </div>
        </div>

        <div class="card">
          <h2>In chiusura nei prossimi 14 giorni</h2>
          <div v-if="!stats.closing_soon.length" class="empty">Nessuna opportunità in scadenza.</div>
          <table v-else class="table">
            <tbody>
              <tr v-for="d in stats.closing_soon" :key="d.id" class="clickable" @click="$router.push({ name: 'deal', params: { id: d.id } })">
                <td><strong>{{ d.title }}</strong><div class="muted small">{{ d.company }}</div></td>
                <td>{{ money(d.value) }}</td>
                <td class="muted">{{ formatDate(d.expected_close_date) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.bar-row { margin-bottom: 10px; }
.bar-label { display: flex; justify-content: space-between; margin-bottom: 4px; }
.bar { background: #eef0f3; border-radius: 4px; height: 10px; overflow: hidden; }
.bar div { height: 100%; background: var(--primary); border-radius: 4px; min-width: 2px; }
</style>
