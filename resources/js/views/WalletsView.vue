<script setup>
import { onMounted } from 'vue'
import { useWalletsStore } from '@/stores/wallets'
import WalletCard from '@/components/WalletCard.vue'

const walletsStore = useWalletsStore()

onMounted(async () => {
    await walletsStore.loadWallets()
})
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-semibold">Wallets</h1>


            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div></div> <RouterLink class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-500" to="/wallets/create">Create new wallet</RouterLink>
            </div>

            <p class="mt-1 text-sm text-slate-400">Balances by currency and network.</p>
        </div>
        <div v-if="formError"
             class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
            {{ formError }}
        </div>

        <div v-if="formSuccess"
             class="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ formSuccess }}
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <WalletCard
                v-for="wallet in walletsStore.wallets"
                :key="wallet.id"
                :wallet="wallet"
            />
        </div>
    </div>
</template>
