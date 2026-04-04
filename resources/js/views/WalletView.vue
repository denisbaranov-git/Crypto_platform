<script setup>
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWalletsStore } from '@/stores/wallets'

const route = useRoute()
const router = useRouter()
const walletsStore = useWalletsStore()

const walletId = computed(() => route.params.id)

async function load() {
    await walletsStore.loadWallet(walletId.value)
}

onMounted(load)

// Если пользователь переключится на другой wallet без полной перезагрузки,
// watch отреагирует и перезагрузит данные.
watch(walletId, load)
</script>

<template>
    <div v-if="walletsStore.currentWallet" class="space-y-6">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h1 class="text-2xl font-semibold">
                {{ walletsStore.currentWallet.currency_code }} / {{ walletsStore.currentWallet.network_code }}
            </h1>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs uppercase text-slate-500">Available</div>
                    <div class="mt-2 text-xl font-semibold">{{ walletsStore.currentWallet.available_balance }}</div>
                </div>

                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs uppercase text-slate-500">Locked</div>
                    <div class="mt-2 text-xl font-semibold">{{ walletsStore.currentWallet.locked_balance }}</div>
                </div>

                <div class="rounded-xl bg-slate-950 p-4">
                    <div class="text-xs uppercase text-slate-500">Active address</div>
                    <div class="mt-2 break-all text-sm font-medium">{{ walletsStore.currentWallet.active_address }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="mb-4 text-lg font-semibold">Addresses</h2>

            <div class="space-y-3">
                <div
                    v-for="address in walletsStore.currentWallet.addresses"
                    :key="address.address"
                    class="flex items-center justify-between rounded-xl bg-slate-950 px-4 py-3"
                >
                    <div class="break-all text-sm">{{ address.address }}</div>
                    <span
                        class="rounded-full px-3 py-1 text-xs"
                        :class="address.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-300'"
                    >
            {{ address.is_active ? 'active' : 'inactive' }}
          </span>
                </div>
            </div>
        </div>
    </div>
</template>
