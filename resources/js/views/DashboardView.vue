<script setup>
import { computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/StatCard.vue'
import WalletCard from '@/components/WalletCard.vue'

const auth = useAuthStore()
const dashboard = useDashboardStore()

onMounted(async () => {
    // Если пользователь ещё не известен, store подгрузит его через /api/me.
    if (!auth.user) {
        await auth.fetchUser()
    }

    // Грузим dashboard payload.
    await dashboard.loadDashboard()
})

const totalWallets = computed(() => dashboard.walletCount)
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-semibold">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-400">
                Welcome, {{ auth.user?.name ?? 'Guest' }}
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <StatCard title="Total balance" :value="dashboard.summary.total_balance" />
            <StatCard title="Available" :value="dashboard.summary.available_balance" />
            <StatCard title="Locked" :value="dashboard.summary.locked_balance" />
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Wallets</h2>
                <span class="text-sm text-slate-400">{{ totalWallets }} items</span>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <WalletCard
                    v-for="wallet in dashboard.wallets"
                    :key="wallet.id"
                    :wallet="wallet"
                />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Recent deposits</h2>
                <p class="text-sm text-slate-400" v-if="!dashboard.recentDeposits.length">
                    No deposits yet.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Recent withdrawals</h2>
                <p class="text-sm text-slate-400" v-if="!dashboard.recentWithdrawals.length">
                    No withdrawals yet.
                </p>
            </div>
        </div>
    </div>
</template>
