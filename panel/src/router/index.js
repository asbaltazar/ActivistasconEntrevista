import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import LoginView from '@/views/LoginView.vue';
import PanelLayout from '@/components/PanelLayout.vue';
import DashboardView from '@/views/DashboardView.vue';
import ActivistsView from '@/views/ActivistsView.vue';
import TalentsView from '@/views/TalentsView.vue';
import MapView from '@/views/MapView.vue';
import ReportsView from '@/views/ReportsView.vue';
import SmsView from '@/views/SmsView.vue';
import AuditSettingsView from '@/views/AuditSettingsView.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true },
    },
    {
      path: '/',
      component: PanelLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: DashboardView },
        { path: 'activistas', name: 'activists', component: ActivistsView },
        { path: 'talentos', name: 'talents', component: TalentsView },
        { path: 'mapa', name: 'map', component: MapView },
        { path: 'reportes', name: 'reports', component: ReportsView },
        { path: 'sms', name: 'sms', component: SmsView },
        { path: 'auditoria', name: 'audit', component: AuditSettingsView },
      ],
    },
  ],
});

router.beforeEach((to) => {
  const auth = useAuthStore();
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' };
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' };
  }
  return true;
});

export default router;
