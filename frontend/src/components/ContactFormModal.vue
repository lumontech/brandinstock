<script setup lang="ts">
import { reactive, ref } from 'vue'
import AppModal from './AppModal.vue'
import { errorMessage, http } from '@/api/http'
import type { Contact } from '@/types'

const props = defineProps<{ companyId: number; contact?: Contact | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const form = reactive({
  company_id: props.companyId,
  first_name: props.contact?.first_name ?? '',
  last_name: props.contact?.last_name ?? '',
  job_title: props.contact?.job_title ?? '',
  email: props.contact?.email ?? '',
  phone: props.contact?.phone ?? '',
  notes: props.contact?.notes ?? '',
  marketing_consent: props.contact?.marketing_consent ?? false,
})
const error = ref('')

async function submit() {
  error.value = ''
  const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
  try {
    if (props.contact) await http.put(`/contacts/${props.contact.id}`, payload)
    else await http.post('/contacts', payload)
    emit('saved')
  } catch (e) {
    error.value = errorMessage(e)
  }
}
</script>

<template>
  <AppModal :title="contact ? 'Modifica referente' : 'Nuovo referente'" @close="emit('close')">
    <form class="stack" @submit.prevent="submit">
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <div class="form-grid">
        <div class="field"><label for="ct-first">Nome *</label><input id="ct-first" v-model="form.first_name" class="input" required /></div>
        <div class="field"><label for="ct-last">Cognome</label><input id="ct-last" v-model="form.last_name" class="input" /></div>
        <div class="field full"><label for="ct-role">Ruolo</label><input id="ct-role" v-model="form.job_title" class="input" placeholder="Buyer, titolare…" /></div>
        <div class="field"><label for="ct-email">Email</label><input id="ct-email" v-model="form.email" class="input" type="email" /></div>
        <div class="field"><label for="ct-phone">Telefono</label><input id="ct-phone" v-model="form.phone" class="input" /></div>
        <div class="field full"><label for="ct-notes">Note</label><textarea id="ct-notes" v-model="form.notes" class="input" rows="2" /></div>
        <label class="checkbox full"><input v-model="form.marketing_consent" type="checkbox" /> Consenso marketing (GDPR)</label>
      </div>
      <div class="toolbar" style="justify-content: flex-end">
        <button type="button" class="btn" @click="emit('close')">Annulla</button>
        <button type="submit" class="btn btn-primary">Salva</button>
      </div>
    </form>
  </AppModal>
</template>
