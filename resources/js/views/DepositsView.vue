<script setup>
import { onMounted } from 'vue'
import { useDepositsStore } from '@/stores/deposits'

const depositsStore = useDepositsStore()

onMounted(async () => {
    await depositsStore.loadDeposits()
})
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-semibold">Deposits</h1>
            <p class="mt-1 text-sm text-slate-400">Recent incoming transactions.</p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div v-if="!depositsStore.deposits.length" class="text-sm text-slate-400">
                No deposits yet.
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="deposit in depositsStore.deposits"
                    :key="deposit.id"
                    class="rounded-xl bg-slate-950 px-4 py-3 text-sm"
                >
                    <div class="flex items-center justify-between">
                        <span>{{ deposit.amount }} {{ deposit.currency_code }}</span>
                        <span class="text-slate-400">{{ deposit.status }}</span>
                    </div>
                    <div class="mt-1 break-all text-slate-500">
                        {{ deposit.txid }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
