<script setup lang="ts">
import { reactive, ref } from 'vue'
import { errorMessage, http } from '@/api/http'
import { activityLabels, formatDateTime } from '@/utils/format'
import type { Activity, ActivityType } from '@/types'

const props = defineProps<{ activities: Activity[]; dealId?: number; companyId?: number; showContext?: boolean }>()
const emit = defineEmits<{ changed: [] }>()

const form = reactive({ type: 'call' as ActivityType, subject: '', due_at: '', description: '' })
const error = ref('')
const showForm = ref(false)

async function add() {
  error.value = ''
  try {
    await http.post('/activities', {
      ...form,
      due_at: form.due_at ? new Date(form.due_at).toISOString() : null,
      description: form.description || null,
      deal_id: props.dealId ?? null,
      company_id: props.companyId ?? null,
    })
    Object.assign(form, { subject: '', due_at: '', description: '' })
    showForm.value = false
    emit('changed')
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function toggle(activity: Activity) {
  await http.post(`/activities/${activity.id}/toggle-complete`)
  emit('changed')
}
</script>

<template>
  <div class="stack">
    <div v-if="dealId || companyId">
      <button v-if="!showForm" class="btn btn-sm" @click="showForm = true">+ Aggiungi attività</button>
      <form v-else class="card stack" @submit.prevent="add">
        <div v-if="error" class="alert alert-error">{{ error }}</div>
        <div class="form-grid">
          <div class="field">
            <label for="act-type">Tipo</label>
            <select id="act-type" v-model="form.type" class="input">
              <option v-for="(label, key) in activityLabels" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div class="field">
            <label for="act-due">Scadenza</label>
            <input id="act-due" v-model="form.due_at" class="input" type="datetime-local" />
          </div>
          <div class="field full">
            <label for="act-subject">Oggetto *</label>
            <input id="act-subject" v-model="form.subject" class="input" required maxlength="255" />
          </div>
          <div class="field full">
            <label for="act-desc">Dettagli</label>
            <textarea id="act-desc" v-model="form.description" class="input" rows="2" />
          </div>
        </div>
        <div class="toolbar">
          <button class="btn btn-primary btn-sm" type="submit">Salva</button>
          <button class="btn btn-sm" type="button" @click="showForm = false">Annulla</button>
        </div>
      </form>
    </div>

    <div v-if="!activities.length" class="empty">Nessuna attività.</div>
    <ul class="list">
      <li v-for="a in activities" :key="a.id" :class="{ done: a.completed_at }">
        <input type="checkbox" :checked="!!a.completed_at" :aria-label="`Completa ${a.subject}`" @change="toggle(a)" />
        <div class="body">
          <div>
            <span class="badge">{{ activityLabels[a.type] }}</span>
            <strong class="subject">{{ a.subject }}</strong>
            <span v-if="a.is_overdue" class="badge badge-danger">In ritardo</span>
          </div>
          <div class="muted small">
            {{ formatDateTime(a.due_at) }}
            <template v-if="showContext">
              <RouterLink v-if="a.deal" :to="{ name: 'deal', params: { id: a.deal.id } }"> · {{ a.deal.title }}</RouterLink>
              <RouterLink v-else-if="a.company" :to="{ name: 'company', params: { id: a.company.id } }"> · {{ a.company.name }}</RouterLink>
            </template>
            <span v-if="a.user"> · {{ a.user.name }}</span>
          </div>
          <div v-if="a.description" class="small desc">{{ a.description }}</div>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.list li { display: flex; gap: 10px; align-items: flex-start; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: #fff; }
.list li.done .subject { text-decoration: line-through; color: var(--muted); }
.subject { margin: 0 6px; }
.desc { margin-top: 4px; white-space: pre-wrap; }
.body { flex: 1; }
</style>
