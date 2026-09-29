<script setup lang="ts">
import { reactive, ref } from 'vue'
import AppModal from './AppModal.vue'
import { errorMessage, http } from '@/api/http'
import { paymentTermsOptions } from '@/utils/format'
import type { Company } from '@/types'

/** Dati di fatturazione del cliente (fattura elettronica: SDI o PEC). */
const props = defineProps<{ company: Company }>()
const emit = defineEmits<{ close: []; saved: [company: Company] }>()

const c = props.company
const form = reactive({
  billing_name: c.billing_name ?? c.name,
  vat_number: c.vat_number ?? '',
  tax_code: c.tax_code ?? '',
  billing_address: c.billing_address ?? c.address ?? '',
  billing_zip: c.billing_zip ?? '',
  billing_city: c.billing_city ?? c.city ?? '',
  billing_province: c.billing_province ?? c.province ?? '',
  billing_country: c.billing_country ?? c.country ?? 'IT',
  sdi_code: c.sdi_code ?? '',
  pec: c.pec ?? '',
  iban: c.iban ?? '',
  payment_terms: c.payment_terms ?? '',
  billing_notes: c.billing_notes ?? '',
})
const error = ref('')
const saving = ref(false)

async function submit() {
  saving.value = true
  error.value = ''
  const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
  try {
    const { data } = await http.put<{ data: Company }>(`/companies/${c.id}`, payload)
    emit('saved', data.data)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal title="Dati di fatturazione" wide @close="emit('close')">
    <form class="stack" @submit.prevent="submit">
      <div v-if="error" class="alert alert-error">{{ error }}</div>
      <p class="muted small">Per la fattura elettronica servono intestazione, P.IVA o codice fiscale, indirizzo completo e codice SDI oppure PEC.</p>
      <div class="form-grid">
        <div class="field full"><label for="b-name">Intestazione fattura *</label><input id="b-name" v-model="form.billing_name" class="input" maxlength="255" /></div>
        <div class="field"><label for="b-vat">Partita IVA</label><input id="b-vat" v-model="form.vat_number" class="input" maxlength="32" /></div>
        <div class="field"><label for="b-cf">Codice fiscale</label><input id="b-cf" v-model="form.tax_code" class="input" maxlength="16" /></div>
        <div class="field full"><label for="b-addr">Indirizzo sede legale *</label><input id="b-addr" v-model="form.billing_address" class="input" maxlength="255" placeholder="Via e numero civico" /></div>
        <div class="field"><label for="b-zip">CAP *</label><input id="b-zip" v-model="form.billing_zip" class="input" maxlength="10" inputmode="numeric" /></div>
        <div class="field"><label for="b-city">Città *</label><input id="b-city" v-model="form.billing_city" class="input" maxlength="100" /></div>
        <div class="field"><label for="b-prov">Provincia</label><input id="b-prov" v-model="form.billing_province" class="input" maxlength="10" /></div>
        <div class="field"><label for="b-country">Paese (ISO)</label><input id="b-country" v-model="form.billing_country" class="input" maxlength="2" /></div>
        <div class="field"><label for="b-sdi">Codice destinatario SDI</label><input id="b-sdi" v-model="form.sdi_code" class="input" maxlength="7" placeholder="7 caratteri" /></div>
        <div class="field"><label for="b-pec">PEC</label><input id="b-pec" v-model="form.pec" class="input" type="email" /></div>
        <div class="field"><label for="b-iban">IBAN</label><input id="b-iban" v-model="form.iban" class="input" maxlength="40" /></div>
        <div class="field">
          <label for="b-terms">Condizioni di pagamento</label>
          <input id="b-terms" v-model="form.payment_terms" class="input" list="payment-terms" maxlength="100" />
          <datalist id="payment-terms"><option v-for="t in paymentTermsOptions" :key="t" :value="t" /></datalist>
        </div>
        <div class="field full"><label for="b-notes">Note per l'amministrazione</label><textarea id="b-notes" v-model="form.billing_notes" class="input" rows="2" maxlength="2000" /></div>
      </div>
      <div class="toolbar" style="justify-content: flex-end">
        <button type="button" class="btn" @click="emit('close')">Annulla</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">Salva</button>
      </div>
    </form>
  </AppModal>
</template>
