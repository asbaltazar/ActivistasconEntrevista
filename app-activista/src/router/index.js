import { createRouter, createWebHistory } from 'vue-router';
import { useActivistStore } from '@/stores/activist';
import LoginView from '@/views/LoginView.vue';
import DashboardView from '@/views/DashboardView.vue';
import CaptureWizardView from '@/views/CaptureWizardView.vue';
import MyTalentsView from '@/views/MyTalentsView.vue';

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
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true },
    },
    {
      path: '/captura',
      name: 'capture',
      component: CaptureWizardView,
      meta: { requiresAuth: true },
    },
    {
      path: '/mis-talentos',
      name: 'my-talents',
      component: MyTalentsView,
      meta: { requiresAuth: true },
    },
  ],
});

router.beforeEach((to) => {
  const store = useActivistStore();
  if (to.meta.requiresAuth && !store.isAuthenticated) {
    return { name: 'login' };
  }
  if (to.meta.guest && store.isAuthenticated && !store.mustChangePassword) {
    return { name: 'dashboard' };
  }
  return true;
});

export default router;

