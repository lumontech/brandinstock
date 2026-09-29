<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { http } from '@/api/http'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { dealSources, formatDate, leadSources, money, segments } from '@/utils/format'

interface StageStat { id: number; name: string; color: string; probability: number; deals_count: number; total_value: number }
interface SellerStat { id: number; name: string; deals_count: number; total_value: number }
interface SegmentStat { segment: string; open_count: number; open_value: number; won_year_count: number; won_year_value: number }
interface MonthStat { month: string; won_count: number; won_value: number }
interface LostStat { reason: string; deals_count: number; total_value: number }
interface SourceStat { source: string; deals_count: number; won_value: number }
interface ClientSourceStat { source: string; clients_count: number; won_value: number }
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
  by_segment: SegmentStat[]
  monthly: MonthStat[]
  lost_reasons: LostStat[]
  by_source: SourceStat[]
  clients_by_source: ClientSourceStat[]
}

const auth = useAuthStore()
const { users, loadUsers } = useLookups()
const stats = ref<Stats | null>(null)
const ownerId = ref<number | null>(null)
const segment = ref('')
const hoverMonth = ref<MonthStat | null>(null)
const monthLabel = new Intl.DateTimeFormat('it-IT', { month: 'short' })
const monthLong = new Intl.DateTimeFormat('it-IT', { month: 'long', year: 'numeric' })
const fmtMonth = (m: string, long = false) => (long ? monthLong : monthLabel).format(new Date(`${m}-01T12:00:00`))

const maxStage = computed(() => Math.max(1, ...(stats.value?.pipeline.map((s) => s.total_value) ?? [1])))
const maxSeller = computed(() => Math.max(1, ...(stats.value?.by_seller?.map((s) => s.total_value) ?? [1])))
const maxSegment = computed(() => Math.max(1, ...(stats.value?.by_segment.map((s) => Math.max(s.open_value, s.won_year_value)) ?? [1])))
const maxMonth = computed(() => Math.max(1, ...(stats.value?.monthly.map((m) => m.won_value) ?? [1])))
const maxLost = computed(() => Math.max(1, ...(stats.value?.lost_reasons.map((l) => l.deals_count) ?? [1])))
const yearWon = computed(() => stats.value?.monthly.reduce((t, m) => t + m.won_value, 0) ?? 0)

async function load() {
  stats.value = (await http.get<Stats>('/dashboard', { params: { owner_id: ownerId.value || undefined, segment: segment.value || undefined } })).data
}

onMounted(async () => {
  if (auth.seesEverything) await loadUsers()
  await load()
})
watch([ownerId, segment], load)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Cruscotto</h1>
      <div class="toolbar">
        <nav class="tabs" aria-label="Categoria cliente">
          <button class="tab" :class="{ active: !segment }" @click="segment = ''">Tutti</button>
          <button v-for="(label, key) in segments" :key="key" class="tab" :class="{ active: segment === key }" @click="segment = key">{{ label }}</button>
        </nav>
        <select v-if="auth.seesEverything" v-model="ownerId" class="input" style="width: 200px" aria-label="Venditore">
          <option :value="null">Tutto il team</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
      </div>
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
        <div class="card wide">
          <div class="card-head">
            <h2>Vinto negli ultimi 12 mesi</h2>
            <span class="muted small">Totale {{ money(yearWon) }}</span>
          </div>
          <div class="months" role="img" :aria-label="`Valore vinto per mese, totale ${money(yearWon)}`" @mouseleave="hoverMonth = null">
            <div
              v-for="m in stats.monthly"
              :key="m.month"
              class="month"
              tabindex="0"
              @mouseenter="hoverMonth = m"
              @focus="hoverMonth = m"
            >
              <div class="col-area">
                <div class="col" :class="{ active: hoverMonth?.month === m.month }" :style="{ height: (m.won_value / maxMonth) * 100 + '%' }" />
              </div>
              <span class="muted small">{{ fmtMonth(m.month) }}</span>
            </div>
          </div>
          <p class="tooltip-line small">
            <template v-if="hoverMonth"><strong>{{ fmtMonth(hoverMonth.month, true) }}</strong>: {{ money(hoverMonth.won_value) }} · {{ hoverMonth.won_count }} opportunità vinte</template>
            <span v-else class="muted">Passa sopra una colonna per vedere il dettaglio del mese.</span>
          </p>
        </div>

        <div class="card">
          <h2>Per categoria cliente</h2>
          <table class="table seg-table">
            <thead><tr><th>Categoria</th><th class="num">Pipeline aperta</th><th class="num">Vinto nell'anno</th></tr></thead>
            <tbody>
              <tr v-for="s in stats.by_segment" :key="s.segment">
                <td><span class="pill" :class="`seg-${s.segment}`">{{ segments[s.segment] }}</span></td>
                <td class="num">
                  {{ money(s.open_value) }} <span class="muted small">({{ s.open_count }})</span>
                  <div class="mini"><div :style="{ width: (s.open_value / maxSegment) * 100 + '%' }" /></div>
                </td>
                <td class="num">
                  {{ money(s.won_year_value) }} <span class="muted small">({{ s.won_year_count }})</span>
                  <div class="mini won"><div :style="{ width: (s.won_year_value / maxSegment) * 100 + '%' }" /></div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h2>Motivi di perdita (anno)</h2>
          <div v-if="!stats.lost_reasons.length" class="empty">Nessuna opportunità persa quest'anno.</div>
          <div v-for="l in stats.lost_reasons" :key="l.reason" class="bar-row" :title="`${l.deals_count} opportunità, ${money(l.total_value)}`">
            <div class="bar-label"><span>{{ l.reason }}</span><span class="muted small">{{ l.deals_count }} · {{ money(l.total_value) }}</span></div>
            <div class="bar"><div class="lost" :style="{ width: (l.deals_count / maxLost) * 100 + '%' }" /></div>
          </div>
        </div>

        <div class="card">
          <h2>Provenienza dei lead (anno)</h2>
          <div v-if="!stats.clients_by_source.length" class="empty">Nessun lead inserito quest'anno.</div>
          <table v-else class="table">
            <thead><tr><th>Provenienza</th><th class="num">Lead</th><th class="num">Vinto</th></tr></thead>
            <tbody>
              <tr v-for="s in stats.clients_by_source" :key="s.source">
                <td>{{ s.source === 'non_indicata' ? 'Non indicata' : (leadSources[s.source] ?? s.source) }}</td>
                <td class="num">{{ s.clients_count }}</td>
                <td class="num">{{ money(s.won_value) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h2>Origine delle opportunità (anno)</h2>
          <div v-if="!stats.by_source.length" class="empty">Nessuna opportunità creata quest'anno.</div>
          <table v-else class="table">
            <thead><tr><th>Origine</th><th class="num">Opportunità</th><th class="num">Vinto</th></tr></thead>
            <tbody>
              <tr v-for="s in stats.by_source" :key="s.source">
                <td>{{ dealSources[s.source] ?? s.source }}</td>
                <td class="num">{{ s.deals_count }}</td>
                <td class="num">{{ money(s.won_value) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

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
.tabs { display: flex; gap: 2px; background: #e9ebef; padding: 3px; border-radius: 8px; }
.tab { border: none; background: none; padding: 6px 12px; border-radius: 6px; font: inherit; cursor: pointer; color: var(--muted); font-weight: 500; }
.tab.active { background: var(--surface); color: var(--text); box-shadow: var(--shadow); }
.wide { grid-column: 1 / -1; }
.card-head { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
.months { display: grid; grid-template-columns: repeat(12, 1fr); gap: 6px; height: 170px; align-items: end; border-bottom: 1px solid var(--border); padding-top: 8px; }
.month { display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; outline: none; cursor: default; }
.col-area { flex: 1; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
.col { width: min(28px, 70%); background: #1f2937; border-radius: 4px 4px 0 0; min-height: 2px; transition: background 0.15s; }
.col.active, .month:focus-visible .col { background: #b08d57; }
.tooltip-line { margin: 10px 0 0; min-height: 1.4em; }
.table .num { text-align: right; font-variant-numeric: tabular-nums; }
.seg-table td { vertical-align: top; }
.mini { height: 4px; background: #eef0f3; border-radius: 2px; margin-top: 4px; overflow: hidden; }
.mini div { height: 100%; background: #6b7280; border-radius: 2px; }
.mini.won div { background: #16a34a; }
.pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.seg-b2b { background: #dbeafe; color: #1e40af; }
.seg-b2c { background: #dcfce7; color: #166534; }
.seg-franchising { background: #ede9fe; color: #5b21b6; }
.bar .lost { background: #dc2626; }
.bar-row { margin-bottom: 10px; }
.bar-label { display: flex; justify-content: space-between; margin-bottom: 4px; }
.bar { background: #eef0f3; border-radius: 4px; height: 10px; overflow: hidden; }
.bar div { height: 100%; background: var(--primary); border-radius: 4px; min-width: 2px; }
</style>
