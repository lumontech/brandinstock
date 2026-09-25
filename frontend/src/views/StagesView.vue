<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { errorMessage, http } from '@/api/http'
import { useLookups } from '@/composables/useLookups'
import type { Stage } from '@/types'

type EditableStage = Omit<Stage, 'id' | 'position'> & { id?: number }

const { loadStages } = useLookups()
const stages = ref<EditableStage[]>([])
const message = ref<{ ok: boolean; text: string } | null>(null)

onMounted(async () => {
  stages.value = (await loadStages(true)).map((s) => ({ ...s }))
})

function move(index: number, delta: number) {
  const target = index + delta
  if (target < 0 || target >= stages.value.length) return
  const list = stages.value
  ;[list[index], list[target]] = [list[target]!, list[index]!]
}

function add() {
  stages.value.splice(Math.max(0, stages.value.findIndex((s) => s.is_won || s.is_lost)), 0, {
    name: 'Nuova fase', probability: 50, color: '#64748b', is_won: false, is_lost: false,
  })
}

async function save() {
  message.value = null
  try {
    await http.put('/stages', { stages: stages.value })
    stages.value = (await loadStages(true)).map((s) => ({ ...s }))
    message.value = { ok: true, text: 'Fasi salvate.' }
  } catch (e) {
    message.value = { ok: false, text: errorMessage(e) }
  }
}
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Fasi della pipeline</h1>
      <div class="toolbar">
        <button class="btn" @click="add">+ Fase</button>
        <button class="btn btn-primary" @click="save">Salva</button>
      </div>
    </div>
    <div v-if="message" class="alert" :class="message.ok ? 'alert-success' : 'alert-error'" style="margin-bottom: 12px">{{ message.text }}</div>
    <div class="card">
      <table class="table">
        <thead><tr><th>Ordine</th><th>Nome</th><th>Probabilità %</th><th>Colore</th><th>Vinto</th><th>Perso</th><th /></tr></thead>
        <tbody>
          <tr v-for="(s, i) in stages" :key="s.id ?? `new-${i}`">
            <td class="toolbar">
              <button class="btn btn-sm" aria-label="Sposta su" @click="move(i, -1)">↑</button>
              <button class="btn btn-sm" aria-label="Sposta giù" @click="move(i, 1)">↓</button>
            </td>
            <td><input v-model="s.name" class="input" maxlength="60" aria-label="Nome fase" /></td>
            <td><input v-model.number="s.probability" class="input" type="number" min="0" max="100" style="width: 90px" aria-label="Probabilità" /></td>
            <td><input v-model="s.color" type="color" aria-label="Colore" /></td>
            <td><input v-model="s.is_won" type="checkbox" aria-label="Fase vinta" /></td>
            <td><input v-model="s.is_lost" type="checkbox" aria-label="Fase persa" /></td>
            <td><button class="btn btn-sm btn-danger" @click="stages.splice(i, 1)">Rimuovi</button></td>
          </tr>
        </tbody>
      </table>
      <p class="muted small">Una fase che contiene opportunità non può essere rimossa: sposta prima le opportunità.</p>
    </div>
  </div>
</template>
