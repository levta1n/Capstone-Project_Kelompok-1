import { createRouter, createWebHistory } from 'vue-router'
import LandingPage from '../views/LandingPage.vue'
import DashboardPage from '../views/DashboardPage.vue'
import RequesterDashboardPage from '../views/RequesterDashboardPage.vue'
import PasswordUpdatePage from '../views/PasswordUpdatePage.vue'
import { supabase } from '../utils/supabase'
import { getProfile } from '../services/profileService'

export const routes = [
  {
    path: '/',
    name: 'landing',
    component: LandingPage,
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/my-requests',
    name: 'requester-dashboard',
    component: RequesterDashboardPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/change-password',
    name: 'change-password',
    component: PasswordUpdatePage,
    meta: { requiresAuth: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: PasswordUpdatePage,
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  if (!supabase) {
    return to.meta.requiresAuth ? { name: 'landing' } : true
  }

  const { data: { session }, error } = await supabase.auth.getSession()
  if (error) return to.meta.requiresAuth ? { name: 'landing' } : true

  if (to.meta.requiresAuth && !session) {
    return { name: 'landing' }
  }

  if (session && to.name === 'reset-password') return true

  if (session && ['landing', 'dashboard', 'requester-dashboard', 'change-password'].includes(to.name)) {
    const profile = await getProfile(session.user.id).catch(() => null)
    if (!profile) return to.name === 'landing' ? true : { name: 'landing' }

    if (to.name === 'change-password') {
      return profile.must_change_password
        ? true
        : { name: profile.role === 'requester' ? 'requester-dashboard' : 'dashboard' }
    }

    const destination = profile.role === 'requester' ? 'requester-dashboard' : 'dashboard'
    if (to.name !== destination) return { name: destination }
  }

  return true
})

export default router
