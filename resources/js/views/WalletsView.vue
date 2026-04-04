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
            <p class="mt-1 text-sm text-slate-400">Your balances by currency and network.</p>
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
