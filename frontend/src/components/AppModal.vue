<script setup lang="ts">
defineProps<{ title: string; wide?: boolean }>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <div class="backdrop" @mousedown.self="emit('close')">
    <div class="modal card" :class="{ wide }" role="dialog" aria-modal="true" :aria-label="title">
      <div class="head">
        <h2>{{ title }}</h2>
        <button class="btn btn-sm" aria-label="Chiudi" @click="emit('close')">✕</button>
      </div>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.backdrop { position: fixed; inset: 0; background: rgb(17 24 39 / 45%); display: flex; align-items: flex-start; justify-content: center; padding: 60px 16px; z-index: 50; overflow-y: auto; }
.modal { width: 100%; max-width: 520px; }
.modal.wide { max-width: 760px; }
.head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.head h2 { margin: 0; }
</style>
