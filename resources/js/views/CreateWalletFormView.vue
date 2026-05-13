<script setup>
import {computed, onMounted, onUnmounted, reactive, ref, watch} from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {useAuthStore} from '@/stores/auth'
import {useWalletsStore} from '@/stores/wallets'

const auth = useAuthStore()
const walletsStore = useWalletsStore()
const route = useRoute()
const router = useRouter()


const form = reactive({
    wallet_id: '',
    network_id: '',
    currency_network_id: '',
})

const formError = ref(null)
const formSuccess = ref('')
let timerId = null

// const selectedCryptoPair = computed(() =>
//     walletsStore.wallets.find((w) => String(w.id) === String(form.wallet_id))
// )

// function syncWalletFields() {
//     if (!selectedWallet.value) return
//
//     form.network_id = selectedWallet.value.network_id
//     form.currency_network_id = selectedWallet.value.currency_network_id
//
//     if (!form.destination_address && selectedWallet.value.active_address) {
//         form.destination_address = selectedWallet.value.active_address
//     }
// }

//watch(selectedWallet, syncWalletFields)

function resetForm() {
    form.wallet_id = ''
    form.network_id = ''
    form.currency_network_id = ''
}


// function presetFromQuery() {
//     const walletId = route.query.wallet_id
//     if (walletId) {
//         form.wallet_id = String(walletId)
//     }
// }
//
// async function refresh() {
//     await Promise.all([
//         walletsStore.loadWallets(),
//     ])
// }

async function submit() {
    formError.value = null
    formSuccess.value = ''
    try {
        const created = await walletsStore.storeNewWallet({
            currency_network_id: Number(form.currency_network_id),
        })
        formSuccess.value = `Wallet #${walletsStore.currentWallet.id} created successfully.`
        resetForm()
        router.push(`/wallets/${walletsStore.currentWallet.id}`)
    } catch (e) {
        formError.value = e?.response?.data?.message || e?.message || 'Unable to create wallet'
    }
}

onMounted(async () => {
    if (!auth.user) {
        await auth.fetchUser()
    }

    //await refresh()
    await  walletsStore.fetchWalletCreateFormData()
    //syncWalletFields()
   //timerId = setInterval(refresh, 15000)
})

onUnmounted(() => {
    if (timerId) {
        clearInterval(timerId)
        timerId = null
    }
})
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold">Wallet</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Create a wallet, then create addresses by pair currency/network.
                </p>
            </div>

            <div class="text-right text-xs text-slate-500">
                <div v-if="walletsStore.lastUpdatedAt">Updated: {{ walletsStore.lastUpdatedAt }}</div>
                <div v-else>Not loaded yet</div>
            </div>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div v-if="formError"
                     class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ formError }}
                </div>

                <div v-if="formSuccess"
                     class="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ formSuccess }}
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Wallet</label>
                        <select
                            v-model="form.currency_network_id"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                            required
                        >
                            <option value="" disabled>Select pair</option>
                            <option
                                v-for="data in walletsStore.walletCreateFormData"
                                :key="data.currency_network_id"
                                :value="String(data.id)"
                            >
                                {{ data.currency_code }} / {{ data.network_code }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Network ID</label>
                            <input
                                v-model="form.network_id"
                                type="number"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                                readonly
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Currency network ID</label>
                            <input
                                v-model="form.currency_network_id"
                                type="number"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                                readonly
                            />
                        </div>
                    </div>
                    <button
                        type="submit"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60">Create wallet
                    </button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-lg font-semibold">Selected pair</h2>

<!--                <div v-if="selectedWallet" class="mt-4 space-y-4">-->
<!--                    <div class="rounded-xl bg-slate-950 p-4">-->
<!--                        <div class="text-sm text-slate-400">Asset / Network</div>-->
<!--                        <div class="mt-1 text-lg font-semibold">-->
<!--                            {{ selectedWallet.currency_code }} / {{ selectedWallet.network_code }}-->
<!--                        </div>-->
<!--                    </div>-->

<!--                    <div class="grid gap-4 md:grid-cols-2">-->
<!--                        <div class="rounded-xl bg-slate-950 p-4">-->
<!--                            <div class="text-xs uppercase text-slate-500">Available</div>-->
<!--                            <div class="mt-1 text-lg font-semibold">{{ selectedWallet.available_balance }}</div>-->
<!--                        </div>-->

<!--                        <div class="rounded-xl bg-slate-950 p-4">-->
<!--                            <div class="text-xs uppercase text-slate-500">Locked</div>-->
<!--                            <div class="mt-1 text-lg font-semibold">{{ selectedWallet.locked_balance }}</div>-->
<!--                        </div>-->
<!--                    </div>-->

<!--                    <div class="rounded-xl bg-slate-950 p-4">-->
<!--                        <div class="text-xs uppercase text-slate-500">Active address</div>-->
<!--                        <div class="mt-2 break-all text-sm">-->
<!--                            {{ selectedWallet.active_address || 'No active address' }}-->
<!--                        </div>-->
<!--                    </div>-->
<!--                </div>-->

<!--                <div v-else class="mt-4 text-sm text-slate-400">-->
<!--                    Select a wallet to prefill network fields.-->
<!--                </div>-->
            </section>
        </div>

    </div>
</template>
