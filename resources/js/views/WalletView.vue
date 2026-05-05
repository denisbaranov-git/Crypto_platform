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

async function issueWalletAddress() { //denis???
    formError.value = null
    formSuccess.value = ''
    try {
        const created = await walletsStore.issueWalletAddress({
            user_id: null,
            network_id: Number(walletsStore.currentWallet.network_id),
            network_code: walletsStore.currentWallet.network_code,
            currency_network_id: Number(walletsStore.currentWallet.currency_network_id),
        })
        await loadWallet();
        formSuccess.value = `Address #${created.id} created successfully.`
    } catch (e) {
        formError.value = e?.response?.data?.message || e?.message || 'Unable to create address'
    }
}

function goToWithdraw() {
    const wallet = walletsStore.currentWallet
    if (!wallet) return

    // Важно: wallet API должен отдавать network_id и currency_network_id.
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

            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <h2 class="mb-4 text-lg font-semibold">Addresses</h2>
                <button
                    class="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-medium text-white hover:bg-indigo-500"
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
                    <div class="break-all text-sm">{{ address.address }}</div>
                    <span class="text-xs" :class="address.is_active ? 'text-emerald-300' : 'text-slate-400'">
                        {{ address.is_active ? 'active' : 'inactive' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
