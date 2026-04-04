import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import DashboardView from '@/views/DashboardView.vue'
import WalletsView from '@/views/WalletsView.vue'
import WalletView from '@/views/WalletView.vue'

const routes = [
    { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },

    { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
    { path: '/wallets', name: 'wallets', component: WalletsView, meta: { requiresAuth: true } },
    { path: '/wallets/:id', name: 'wallet.show', component: WalletView, meta: { requiresAuth: true } },

    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

// Global guard: before each navigation.
router.beforeEach(async (to) => {
    const auth = useAuthStore()

    // If we don't know the user yet, ask Laravel /api/me.
    if (!auth.user) {
        await auth.fetchUser()
    }

    // Protected route, but no user found.
    if (to.meta.requiresAuth && !auth.user) {
        return '/login'
    }

    // Guest-only route, but user already logged in.
    if (to.meta.guestOnly && auth.user) {
        return '/dashboard'
    }
})

export default router
