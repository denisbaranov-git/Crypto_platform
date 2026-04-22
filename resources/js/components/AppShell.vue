<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const isLoggedIn = computed(() => auth.isAuthenticated)

async function handleLogout() {
    await auth.logout()
    router.push('/login')
}
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <header class="border-b border-slate-800 bg-slate-900/60 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div>
                    <div class="text-lg font-semibold">Crypto Platform</div>
                    <div class="text-xs text-slate-400">Laravel + Vue + Sanctum</div>
                </div>

                <nav class="flex items-center gap-3 text-sm">
                    <RouterLink class="text-slate-300 hover:text-white" to="/dashboard">Dashboard</RouterLink>
                    <RouterLink class="text-slate-300 hover:text-white" to="/wallets">Wallets</RouterLink>
                    <RouterLink class="text-slate-300 hover:text-white" to="/withdrawals">Withdrawals</RouterLink>
                    <RouterLink v-if="!isLoggedIn" class="text-slate-300 hover:text-white" to="/login">Login</RouterLink>
                    <button
                        v-else
                        class="rounded-lg bg-slate-800 px-3 py-2 hover:bg-slate-700"
                        @click="handleLogout"
                    >
                        Logout
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6">
            <slot />
        </main>
    </div>
</template>
