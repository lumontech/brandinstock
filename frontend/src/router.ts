import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
    {
      path: '/',
      component: AppLayout,
      children: [
        { path: '', redirect: { name: 'dashboard' } },
        { path: 'pipeline', name: 'pipeline', component: () => import('@/views/PipelineView.vue') },
        { path: 'cruscotto', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'dashboard', redirect: { name: 'dashboard' } },
        { path: 'opportunita/:id', name: 'deal', component: () => import('@/views/DealDetailView.vue'), props: true },
        { path: 'leads', name: 'leads', component: () => import('@/views/CompaniesView.vue'), props: { mode: 'lead' } },
        { path: 'clienti', name: 'companies', component: () => import('@/views/CompaniesView.vue'), props: { mode: 'customer' } },
        { path: 'clienti/:id', name: 'company', component: () => import('@/views/CompanyDetailView.vue'), props: true },
        { path: 'aziende/:rest(.*)*', redirect: (to) => `/clienti/${([] as string[]).concat(to.params.rest ?? []).join('/')}` },
        { path: 'attivita', name: 'activities', component: () => import('@/views/ActivitiesView.vue') },
        {
          path: 'impostazioni',
          name: 'settings',
          component: () => import('@/layouts/SettingsLayout.vue'),
          redirect: () => (useAuthStore().isAdmin ? { name: 'stages' } : { name: 'security' }),
          children: [
            { path: 'sicurezza', name: 'security', component: () => import('@/views/SecurityView.vue') },
            { path: 'utenti', name: 'users', component: () => import('@/views/UsersView.vue'), meta: { admin: true } },
            { path: 'fasi', name: 'stages', component: () => import('@/views/StagesView.vue'), meta: { admin: true } },
          ],
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.loaded) await auth.fetchUser()

  if (to.meta.guest) {
    return auth.user ? { name: 'dashboard' } : true
  }
  if (!auth.user) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  // Ruoli con 2FA obbligatoria: finché non è attiva si può solo configurarla.
  if (auth.needsTwoFactorSetup && to.name !== 'security' && to.name !== 'settings') {
    return { name: 'security' }
  }
  // Controllo solo di UX: l'autorizzazione vera è sempre lato server.
  if (to.meta.admin && !auth.isAdmin) {
    return { name: 'dashboard' }
  }
  return true
})

export default router
