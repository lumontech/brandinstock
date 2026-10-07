<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AppModal from './AppModal.vue'
import { useLookups } from '@/composables/useLookups'
import { useAuthStore } from '@/stores/auth'
import { companyTypes, leadSources, segments } from '@/utils/format'

/** Modifica massiva: si sceglie un campo e il valore da applicare a tutti i record selezionati. */
const props = defineProps<{ count: number; busy?: boolean }>()
const emit = defineEmits<{ close: []; apply: [changes: Record<string, string | number | null>] }>()

const auth = useAuthStore()
const { users } = useLookups()

type Field = 'segment' | 'source' | 'type' | 'owner_id' | 'status'
const field = ref<Field>('segment')
const value = ref('')

const fields = computed(() => {
  const list: { key: Field; label: string }[] = [
    { key: 'segment', label: 'Categoria' },
    { key: 'source', label: 'Provenienza' },
    { key: 'type', label: 'Tipologia' },
  ]
  if (auth.seesEverything) list.push({ key: 'owner_id', label: 'Venditore' })
  list.push({ key: 'status', label: 'Stato (lead / cliente)' })
  return list
})

const options = computed<Record<string, string>>(() => {
  switch (field.value) {
    case 'segment': return segments
    case 'source': return leadSources
    case 'type': return companyTypes
    case 'status': return { lead: 'Lead', customer: 'Cliente' }
    case 'owner_id': return Object.fromEntries(users.value.map((u) => [String(u.id), u.name]))
    default: return {}
  }
})
// Provenienza e tipologia si possono anche svuotare.
const clearable = computed(() => field.value === 'source' || field.value === 'type')

watch(field, () => (value.value = ''))

function submit() {
  if (value.value === '' && !clearable.value) return
  const v = value.value === '' ? null : field.value === 'owner_id' ? Number(value.value) : value.value
  emit('apply', { [field.value]: v })
}
</script>

<template>
  <AppModal :title="`Modifica ${props.count} record`" @close="emit('close')">
    <form class="stack" @submit.prevent="submit">
      <div class="field">
        <label for="bulk-field">Campo da modificare</label>
        <select id="bulk-field" v-model="field" class="input">
          <option v-for="f in fields" :key="f.key" :value="f.key">{{ f.label }}</option>
        </select>
      </div>
      <div class="field">
        <label for="bulk-value">Nuovo valore</label>
        <select id="bulk-value" v-model="value" class="input">
          <option value="">{{ clearable ? '— (svuota il campo)' : 'Scegli…' }}</option>
          <option v-for="(label, key) in options" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <p v-if="field === 'status'" class="muted small">
        I record passati a "Cliente" compariranno in Clienti; quelli riportati a "Lead" tornano in Leads.
      </p>
      <div class="toolbar" style="justify-content: flex-end">
        <button type="button" class="btn" @click="emit('close')">Annulla</button>
        <button type="submit" class="btn btn-primary" :disabled="busy || (value === '' && !clearable)">Applica a {{ props.count }}</button>
      </div>
    </form>
  </AppModal>
</template>
