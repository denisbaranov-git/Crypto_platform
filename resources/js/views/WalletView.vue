<script setup>
import {computed, onMounted, ref, watch} from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWalletsStore } from '@/stores/wallets'

const route = useRoute()
const router = useRouter()
const walletsStore = useWalletsStore()

const formError = ref(null)
const formSuccess = ref('')

const walletId = computed(() => route.params.id)

async function loadWallet() {
    await walletsStore.loadWallet(walletId.value)
}

async function issueWalletAddress() {
    formError.value = null
    formSuccess.value = ''
    try {
        await walletsStore.issueWalletAddress({
            user_id: null,
            id: Number(walletsStore.currentWallet.network_id),
            network_id: Number(walletsStore.currentWallet.network_id),
            network_code: walletsStore.currentWallet.network_code,
            currency_network_id: Number(walletsStore.currentWallet.currency_network_id),
        })
        await loadWallet();
        formSuccess.value = `Address created successfully.`
    } catch (e) {
        formError.value = e?.response?.data?.message || e?.message || 'Unable to create address'
    }
}

async function activateWalletAddress(wallet_address_id) {
    formError.value = null
    formSuccess.value = ''
    try {
        await walletsStore.activateWalletAddress(
            Number(walletsStore.currentWallet.id),
            wallet_address_id,
        )
        await loadWallet();
        formSuccess.value = `Address activated.`
    } catch (e) {
        formError.value = e?.response?.data?.message || e?.message || 'Unable to activate address'
    }
}
function goToWithdraw() {
    const wallet = walletsStore.currentWallet
    if (!wallet) return

    router.push({
        path: '/withdrawals',
        query: {
            wallet_id: wallet.id,
        },
    })
}
// onMounted(async () => {
//     if (!auth.user) {
//         await auth.fetchUser() //return ['id' => $user->id,'name' => $user->name,'email' => $user->email,'status' => $user->status,];
//     }
//     await refresh()
//     timerId = setInterval(refresh, 15000)
// })

onMounted(loadWallet)
// Если route param поменялся, подтягиваем новый wallet.
watch(walletId, loadWallet)
</script>

<template>
    <div v-if="walletsStore.currentWallet" class="space-y-6">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ walletsStore.currentWallet.currency_code }} / {{ walletsStore.currentWallet.network_code }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Wallet detail and withdrawal entry point.
                    </p>
                </div>

                <button
                    class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-500"
                    @click="goToWithdraw"
                >
                    Withdraw from this wallet
                </button>
            </div>

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
                    <div class="mt-2 break-all text-sm font-medium">
                        {{ walletsStore.currentWallet.active_address }}
                    </div>
                </div>
            </div>
        </div>

        <!-- existing addresses block stays unchanged -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <div v-if="formError"
                 class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ formError }}
            </div>

            <div v-if="formSuccess"
                 class="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ formSuccess }}
            </div>

            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between mb-6">
                <h2 class="text-lg font-semibold">Addresses</h2>
                <button
                    class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-500 transition-colors"
                    @click="issueWalletAddress"
                >
                    Create new address
                </button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="address in walletsStore.currentWallet.addresses"
                    :key="address.address"
                    class="flex items-center justify-between rounded-xl bg-slate-950 px-4 py-3"
                >
                    <div class="break-all text-sm flex-1">{{ address.address }}</div>
                    <div class="flex items-center gap-3 ml-4">
                <span
                    class="text-xs font-medium"
                    :class="address.is_active ? 'text-emerald-300' : 'text-slate-400'"
                >
                    {{ address.is_active ? 'active' : 'inactive' }}
                </span>
                        <button
                            v-if="!address.is_active"
                            class="relative group text-xs font-medium text-emerald-300 border border-emerald-500/30 rounded-lg px-2.5 py-1 hover:bg-emerald-500/10 hover:border-emerald-500/50 transition-all"
                            @click="activateWalletAddress(address.id)"
                        >
                            activate
                            <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs font-medium text-white bg-slate-700 rounded-md opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap">
                        Make this address active
                    </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
