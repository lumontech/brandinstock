<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { http } from '@/api/http'
import type { Company, Paginated } from '@/types'

/**
 * Scelta di un lead o cliente cercando per nome, P.IVA o città: mostra solo i risultati
 * che corrispondono a quello che si scrive, invece di un elenco con tutti i record.
 */
const props = defineProps<{ modelValue: number | null; initialName?: string | null; inputId?: string; required?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [id: number | null] }>()

const query = ref('')
const selectedName = ref(props.initialName ?? '')
const results = ref<Company[]>([])
const open = ref(false)
const active = ref(0)
const loading = ref(false)

onMounted(async () => {
  // Record già scelto (es. opportunità creata dalla scheda del cliente): ne mostro il nome.
  if (props.modelValue && !selectedName.value) {
    try {
      selectedName.value = (await http.get<{ data: Company }>(`/companies/${props.modelValue}`)).data.data.name
    } catch {
      /* il nome non è indispensabile */
    }
  }
})

let timer: number | undefined
let seq = 0
watch(query, (q) => {
  clearTimeout(timer)
  if (!q.trim()) {
    results.value = []
    return
  }
  timer = window.setTimeout(async () => {
    const mine = ++seq
    loading.value = true
    try {
      const { data } = await http.get<Paginated<Company>>('/companies', { params: { q: q.trim(), per_page: 10, sort: 'name' } })
      if (mine === seq) {
        results.value = data.data
        active.value = 0
        open.value = true
      }
    } finally {
      if (mine === seq) loading.value = false
    }
  }, 200)
})

function choose(company: Company) {
  emit('update:modelValue', company.id)
  selectedName.value = company.name
  query.value = ''
  results.value = []
  open.value = false
}

function clear() {
  emit('update:modelValue', null)
  selectedName.value = ''
}

function onKey(event: KeyboardEvent) {
  if (!open.value || !results.value.length) return
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    active.value = (active.value + 1) % results.value.length
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    active.value = (active.value - 1 + results.value.length) % results.value.length
  } else if (event.key === 'Enter') {
    event.preventDefault()
    choose(results.value[active.value])
  } else if (event.key === 'Escape') {
    open.value = false
  }
}
</script>

<template>
  <div class="picker">
    <div v-if="modelValue" class="chosen input">
      <span class="name">{{ selectedName || 'Selezionato' }}</span>
      <button type="button" class="btn btn-sm" @click="clear">Cambia</button>
    </div>
    <template v-else>
      <input
        :id="inputId"
        v-model="query"
        class="input"
        autocomplete="off"
        placeholder="Scrivi il nome, la P.IVA o la città…"
        role="combobox"
        :aria-expanded="open"
        :required="required"
        @keydown="onKey"
        @focus="open = results.length > 0"
        @blur="open = false"
      />
      <ul v-if="open" class="results card" role="listbox">
        <li
          v-for="(c, i) in results"
          :key="c.id"
          role="option"
          :aria-selected="i === active"
          :class="{ active: i === active }"
          @mousedown.prevent="choose(c)"
          @mouseenter="active = i"
        >
          <strong>{{ c.name }}</strong>
          <span class="muted small">{{ [c.contact_person, c.city].filter(Boolean).join(' · ') }}</span>
          <span class="tag" :class="c.status">{{ c.status === 'customer' ? 'Cliente' : 'Lead' }}</span>
        </li>
      </ul>
      <p v-if="query.trim() && !loading && !results.length" class="muted small none">Nessun lead o cliente trovato.</p>
    </template>
  </div>
</template>

<style scoped>
.picker { position: relative; }
.chosen { display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #f8fafc; }
.chosen .name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.results { position: absolute; z-index: 30; top: calc(100% + 4px); left: 0; right: 0; margin: 0; padding: 4px; list-style: none; max-height: 280px; overflow-y: auto; }
.results li { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
.results li.active { background: #eff6ff; }
.results li .muted { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tag { font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 999px; background: #dbeafe; color: #1e40af; }
.tag.customer { background: #dcfce7; color: #166534; }
.none { margin: 4px 0 0; }
</style>
