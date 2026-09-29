<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="settings">
    <aside class="subnav" aria-label="Impostazioni">
      <h2>Impostazioni</h2>
      <template v-if="auth.isAdmin && !auth.needsTwoFactorSetup">
        <div class="group">CRM</div>
        <RouterLink :to="{ name: 'stages' }">Fasi pipeline</RouterLink>
        <RouterLink :to="{ name: 'users' }">Utenti</RouterLink>
      </template>
      <div class="group">Account</div>
      <RouterLink :to="{ name: 'security' }">Sicurezza account</RouterLink>
    </aside>
    <section class="body">
      <RouterView />
    </section>
  </div>
</template>

<style scoped>
.settings { display: flex; gap: 24px; align-items: flex-start; }
.subnav { width: 200px; flex-shrink: 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 10px; position: sticky; top: 24px; display: flex; flex-direction: column; gap: 2px; }
.subnav h2 { font-size: 0.95rem; padding: 0 8px; margin: 0 0 6px; }
.group { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); padding: 10px 8px 4px; }
.subnav a { text-decoration: none; padding: 7px 8px; border-radius: 6px; color: var(--text); }
.subnav a:hover { background: #f0f1f4; }
.subnav a.router-link-exact-active { background: #111827; color: #fff; }
.body { flex: 1; min-width: 0; }
@media (max-width: 800px) {
  .settings { flex-direction: column; }
  .subnav { width: 100%; position: static; flex-direction: row; flex-wrap: wrap; }
  .subnav h2, .group { display: none; }
}
</style>
