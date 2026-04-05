<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/StatCard.vue'
import WalletCard from '@/components/WalletCard.vue'

const auth = useAuthStore()
const dashboard = useDashboardStore()

const selectedNetwork = ref('all')
const search = ref('')

let timerId = null

async function refresh() {
    await dashboard.loadDashboard()
}

onMounted(async () => {
    if (!auth.user) {
        await auth.fetchUser()
    }

    await refresh()

    // Live updates для MVP: polling каждые 15 секунд.
    timerId = setInterval(refresh, 15000)
})

onUnmounted(() => {
    if (timerId) {
        clearInterval(timerId)
        timerId = null
    }
})

// Когда меняется фильтр сети, просто перезагружаем данные.
watch(selectedNetwork, async () => {
    await refresh()
})

// Локальный фильтр по имени/кодам.
const filteredWallets = computed(() => {
    const q = search.value.trim().toLowerCase()

    return dashboard.wallets.filter((wallet) => {
        const matchesSearch =
            !q ||
            wallet.currency_code.toLowerCase().includes(q) ||
            wallet.network_code.toLowerCase().includes(q)

        const matchesNetwork =
            selectedNetwork.value === 'all' || wallet.network_code === selectedNetwork.value

        return matchesSearch && matchesNetwork
    })
})
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Hello, {{ auth.user?.name ?? 'Guest' }}
                </p>
            </div>

            <div class="text-right text-xs text-slate-500">
                <div v-if="dashboard.lastUpdatedAt">Updated: {{ dashboard.lastUpdatedAt }}</div>
                <div v-else>Not loaded yet</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <StatCard title="Total balance" :value="dashboard.summary.total_balance" />
            <StatCard title="Available" :value="dashboard.summary.available_balance" />
            <StatCard title="Locked" :value="dashboard.summary.locked_balance" />
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h2 class="text-lg font-semibold">Wallets</h2>

                <div class="flex gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search wallet..."
                        class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 outline-none"
                    />

                    <select
                        v-model="selectedNetwork"
                        class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2 outline-none"
                    >
                        <option value="all">All networks</option>
                        <option value="ethereum">Ethereum</option>
                        <option value="tron">Tron</option>
                        <option value="bitcoin">Bitcoin</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <WalletCard
                    v-for="wallet in filteredWallets"
                    :key="wallet.id"
                    :wallet="wallet"
                />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Recent deposits</h2>

                <p v-if="!dashboard.recentDeposits.length" class="text-sm text-slate-400">
                    No deposits yet.
                </p>
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Recent withdrawals</h2>
                <p class="text-sm text-slate-400">Coming next.</p>
            </section>
        </div>
    </div>
</template>
