<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="shell">
    <aside class="sidebar">
      <div class="brand">Brandinstock <span>CRM</span></div>
      <nav v-if="!auth.needsTwoFactorSetup">
        <RouterLink :to="{ name: 'pipeline' }">Pipeline</RouterLink>
        <RouterLink :to="{ name: 'dashboard' }">Dashboard</RouterLink>
        <RouterLink :to="{ name: 'companies' }">Aziende</RouterLink>
        <RouterLink :to="{ name: 'activities' }">Attività</RouterLink>
        <template v-if="auth.isAdmin">
          <div class="section">Amministrazione</div>
          <RouterLink :to="{ name: 'users' }">Utenti</RouterLink>
          <RouterLink :to="{ name: 'stages' }">Fasi pipeline</RouterLink>
        </template>
      </nav>
      <div class="user">
        <RouterLink :to="{ name: 'security' }" class="user-name">{{ auth.user?.name }}</RouterLink>
        <div class="role">{{ auth.user?.role_label }}</div>
        <button class="logout" @click="logout">Esci</button>
      </div>
    </aside>
    <main class="content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.shell { display: flex; min-height: 100vh; }
.sidebar { width: 220px; flex-shrink: 0; background: #111827; color: #d1d5db; display: flex; flex-direction: column; padding: 20px 12px; position: sticky; top: 0; height: 100vh; }
.brand { color: #fff; font-weight: 700; font-size: 1.1rem; padding: 0 10px 20px; }
.brand span { color: #b08d57; }
nav { display: flex; flex-direction: column; gap: 2px; flex: 1; }
nav a { color: #d1d5db; text-decoration: none; padding: 8px 10px; border-radius: 6px; }
nav a:hover { background: #1f2937; color: #fff; }
nav a.router-link-active { background: #374151; color: #fff; }
.section { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; padding: 16px 10px 4px; }
.user { border-top: 1px solid #374151; padding: 12px 10px 0; }
.user-name { color: #fff; font-weight: 600; text-decoration: none; }
.role { font-size: 12px; color: #9ca3af; margin: 2px 0 8px; }
.logout { background: none; border: 1px solid #374151; color: #d1d5db; border-radius: 6px; padding: 4px 10px; cursor: pointer; }
.content { flex: 1; padding: 24px; min-width: 0; }
@media (max-width: 800px) {
  .shell { flex-direction: column; }
  .sidebar { width: 100%; height: auto; position: static; }
  nav { flex-direction: row; flex-wrap: wrap; }
  .section { display: none; }
}
</style>
