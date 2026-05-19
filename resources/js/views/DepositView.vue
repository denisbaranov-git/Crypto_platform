<script setup>
import {computed, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import {useAuthStore} from '@/stores/auth'
import {useDepositsStore} from "@/stores/deposits.js";
import {useRoute, useRouter} from "vue-router";

const auth = useAuthStore()
const depositStore = useDepositsStore()

const formError = ref(null)
const formSuccess = ref('')

const route = useRoute()
const router = useRouter()

let timerId = null

const depositId = computed(() => route.params.id)

async function refresh() {
    await depositStore.loadDeposit(depositId.value)
}

onMounted(async () => {
    if (!auth.user) {
        await auth.fetchUser()
    }

     await refresh()
    // timerId = setInterval(refresh, 15000)
})

onUnmounted(() => {
    // if (timerId) {
    //     clearInterval(timerId)
    //     timerId = null
    // }
})
</script>

<template>
    <div v-if="depositStore.loading" class="text-center py-4">
        <div
            class="animate-spin inline-block w-6 h-6 border-2 border-slate-400 border-t-transparent rounded-full"></div>
        <p class="mt-2 text-sm text-slate-400">Loading deposit...</p>
    </div>

    <!-- Сообщение об ошибке -->
    <div v-else-if="depositStore.error" class="rounded-2xl border border-red-800 bg-red-900/20 p-5">
        <p class="text-sm text-red-400">Error: {{ depositStore.error }}</p>
    </div>
    <!-- Контент -->
    <div v-else-if="depositStore.currentDeposit"
        class="rounded-2xl border border-slate-800 bg-slate-900 p-5 text-left transition hover:border-indigo-500/60 hover:bg-slate-850">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-lg font-semibold">{{ depositStore.currentDeposit.amount }}</div>
                <div class="text-sm text-slate-400">{{ depositStore.currentDeposit.currency }}/{{ depositStore.currentDeposit.network }}</div>
            </div>

            <span class="rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-300">
                {{ depositStore.currentDeposit.status }}
            </span>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-slate-950 p-3">
                <div class="text-[11px] uppercase text-slate-500">address from</div>
                <div class="mt-1 text-sm font-semibold">{{ depositStore.currentDeposit.fromAddress }}</div>
            </div>

            <div class="rounded-xl bg-slate-950 p-3">
                <div class="text-[11px] uppercase text-slate-500">address to</div>
                <div class="mt-1 text-sm font-semibold">{{ depositStore.currentDeposit.toAddress }}</div>
            </div>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-slate-950 p-3">
                <div class="text-[11px] uppercase text-slate-500">txid</div>
                <div class="mt-1 text-sm font-semibold">{{ depositStore.currentDeposit.txid }}</div>
            </div>

            <div class="rounded-xl bg-slate-950 p-3">
                <div class="text-[11px] uppercase text-slate-500">Confirmations</div>
                <div class="mt-1 text-sm font-semibold">{{ depositStore.currentDeposit.requiredConfirmations }}</div>
            </div>
        </div>
    </div>

</template>
