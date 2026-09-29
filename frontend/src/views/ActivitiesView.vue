<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { http } from '@/api/http'
import ActivityList from '@/components/ActivityList.vue'
import type { Activity, Paginated } from '@/types'

const tabs = [
  { key: 'overdue', label: 'In ritardo' },
  { key: 'today', label: 'Oggi' },
  { key: 'upcoming', label: 'Prossime' },
  { key: 'open', label: 'Tutte aperte' },
  { key: 'done', label: 'Completate' },
] as const

const status = ref<(typeof tabs)[number]['key']>('today')
const activities = ref<Activity[]>([])

async function load() {
  activities.value = (await http.get<Paginated<Activity>>('/activities', { params: { status: status.value } })).data.data
}

onMounted(load)
watch(status, load)
</script>

<template>
  <div>
    <div class="page-header"><h1>Attività</h1></div>
    <div class="toolbar" style="margin-bottom: 16px">
      <button v-for="t in tabs" :key="t.key" class="btn btn-sm" :class="{ 'btn-primary': status === t.key }" @click="status = t.key">{{ t.label }}</button>
    </div>
    <div class="card">
      <ActivityList :activities="activities" show-context @changed="load" />
      <p class="muted small">Le nuove attività si creano dalla scheda di un'opportunità o di un cliente.</p>
    </div>
  </div>
</template>
