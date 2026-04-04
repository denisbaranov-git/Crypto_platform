<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
    wallet: { type: Object, required: true },
})

const router = useRouter()

function openWallet() {
    router.push(`/wallets/${props.wallet.id}`)
}
</script>

<template>
    <button
        class="rounded-2xl border border-slate-800 bg-slate-900 p-5 text-left transition hover:border-indigo-500/60 hover:bg-slate-850"
        @click="openWallet"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-lg font-semibold">{{ wallet.currency_code }}</div>
                <div class="text-sm text-slate-400">{{ wallet.network_code }}</div>
            </div>

            <span class="rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-300">
        {{ wallet.status }}
      </span>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-slate-950 p-3">
                <div class="text-[11px] uppercase text-slate-500">Available</div>
                <div class="mt-1 text-sm font-semibold">{{ wallet.available_balance }}</div>
            </div>

            <div class="rounded-xl bg-slate-950 p-3">
                <div class="text-[11px] uppercase text-slate-500">Locked</div>
                <div class="mt-1 text-sm font-semibold">{{ wallet.locked_balance }}</div>
            </div>
        </div>
    </button>
</template>
