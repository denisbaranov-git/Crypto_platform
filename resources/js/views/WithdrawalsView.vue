<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useWalletsStore } from '@/stores/wallets'
import { useWithdrawalsStore } from '@/stores/withdrawals'
import WithdrawalStatusBadge from '@/components/WithdrawalStatusBadge.vue'

const auth = useAuthStore()
const walletsStore = useWalletsStore()
const withdrawalsStore = useWithdrawalsStore()
const route = useRoute()
const router = useRouter()

const form = reactive({
    wallet_id: '',
    network_id: '',
    currency_network_id: '',
    destination_address: '',
    destination_tag: '',
    amount: '',
    note: '',
})

const formError = ref(null)
const formSuccess = ref('')
let timerId = null

const selectedWallet = computed(() =>
    walletsStore.wallets.find((w) => String(w.id) === String(form.wallet_id))
)

// Важно: ваши wallet API должны возвращать network_id и currency_network_id.
// Если их нет, добавьте их в ответ WalletController.
function syncWalletFields() {
    if (!selectedWallet.value) return

    form.network_id = selectedWallet.value.network_id
    form.currency_network_id = selectedWallet.value.currency_network_id

    // Удобный UX: если у кошелька уже есть активный адрес, можно подставить его.
    if (!form.destination_address && selectedWallet.value.active_address) {
        form.destination_address = selectedWallet.value.active_address
    }
}

watch(selectedWallet, syncWalletFields)

function presetFromQuery() {
    const walletId = route.query.wallet_id
    if (walletId) {
        form.wallet_id = String(walletId)
    }
}

async function refresh() {
    await Promise.all([
        walletsStore.loadWallets(),
        withdrawalsStore.loadWithdrawals({ per_page: 20 }),
    ])
}

async function submit() {
    formError.value = null
    formSuccess.value = ''

    try {
        const created = await withdrawalsStore.submitWithdrawal({
            network_id: Number(form.network_id),
            currency_network_id: Number(form.currency_network_id),
            destination_address: form.destination_address.trim(),
            destination_tag: form.destination_tag?.trim() || null,
            amount: form.amount.trim(),
            metadata: {
                note: form.note.trim() || null,
                source: 'spa_withdrawal_form',
                wallet_id: form.wallet_id ? Number(form.wallet_id) : null,
            },
        })

        formSuccess.value = `Withdrawal #${created.id} created successfully.`

        // Очищаем только ввод пользователя, wallet selection оставляем.
        form.destination_address = ''
        form.destination_tag = ''
        form.amount = ''
        form.note = ''

        await withdrawalsStore.loadWithdrawals({ per_page: 20 })

        // Если хотите, можно автоперекинуть на detail:
        // router.push(`/withdrawals/${created.id}`)
    } catch (e) {
        formError.value =
            e?.response?.data?.message ||
            e?.message ||
            'Unable to create withdrawal'
    }
}

async function cancelWithdrawal(id) {
    if (!window.confirm('Cancel this withdrawal?')) return
    await withdrawalsStore.cancel(id, 'user_cancelled')
}

onMounted(async () => {
    if (!auth.user) {
        await auth.fetchUser()
    }

    await refresh()
    presetFromQuery()
    syncWalletFields()

    // Поллинг как в остальных экранах.
    timerId = setInterval(refresh, 15000)
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
                <h1 class="text-3xl font-semibold">Withdrawals</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Create a withdrawal, track broadcast, settlement and confirmation.
                </p>
            </div>

            <div class="text-right text-xs text-slate-500">
                <div v-if="withdrawalsStore.lastUpdatedAt">Updated: {{ withdrawalsStore.lastUpdatedAt }}</div>
                <div v-else>Not loaded yet</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <!-- Left: create withdrawal -->
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-lg font-semibold">New withdrawal</h2>

                <div v-if="formError" class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ formError }}
                </div>

                <div v-if="formSuccess" class="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ formSuccess }}
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Wallet</label>
                        <select
                            v-model="form.wallet_id"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                            required
                        >
                            <option value="" disabled>Select wallet</option>
                            <option
                                v-for="wallet in walletsStore.wallets"
                                :key="wallet.id"
                                :value="String(wallet.id)"
                            >
                                {{ wallet.currency_code }} / {{ wallet.network_code }} — {{ wallet.available_balance }}
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

                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Destination address</label>
                        <input
                            v-model="form.destination_address"
                            type="text"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                            placeholder="Enter external wallet address"
                            required
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-slate-300">Destination tag / memo</label>
                        <input
                            v-model="form.destination_tag"
                            type="text"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                            placeholder="Optional"
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Amount</label>
                            <input
                                v-model="form.amount"
                                type="text"
                                inputmode="decimal"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                                placeholder="0.00"
                                required
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm text-slate-300">Internal note</label>
                            <input
                                v-model="form.note"
                                type="text"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none"
                                placeholder="Optional note"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-950 p-4 text-sm text-slate-400">
                        <div class="font-medium text-slate-200">Fee policy</div>
                        <p class="mt-2">
                            Fee is calculated server-side from <code>fee_rules</code>.
                            The backend will reserve <code>amount + fee</code>.
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="withdrawalsStore.submitting"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ withdrawalsStore.submitting ? 'Submitting...' : 'Create withdrawal' }}
                    </button>
                </form>
            </section>

            <!-- Right: wallet context -->
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-lg font-semibold">Selected wallet</h2>

                <div v-if="selectedWallet" class="mt-4 space-y-4">
                    <div class="rounded-xl bg-slate-950 p-4">
                        <div class="text-sm text-slate-400">Asset / Network</div>
                        <div class="mt-1 text-lg font-semibold">
                            {{ selectedWallet.currency_code }} / {{ selectedWallet.network_code }}
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl bg-slate-950 p-4">
                            <div class="text-xs uppercase text-slate-500">Available</div>
                            <div class="mt-1 text-lg font-semibold">{{ selectedWallet.available_balance }}</div>
                        </div>

                        <div class="rounded-xl bg-slate-950 p-4">
                            <div class="text-xs uppercase text-slate-500">Locked</div>
                            <div class="mt-1 text-lg font-semibold">{{ selectedWallet.locked_balance }}</div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-950 p-4">
                        <div class="text-xs uppercase text-slate-500">Active address</div>
                        <div class="mt-2 break-all text-sm">
                            {{ selectedWallet.active_address || 'No active address' }}
                        </div>
                    </div>
                </div>

                <div v-else class="mt-4 text-sm text-slate-400">
                    Select a wallet to prefill network fields and withdrawal context.
                </div>
            </section>
        </div>

        <!-- Withdrawals list -->
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-semibold">Recent withdrawals</h2>
                <div class="text-xs text-slate-500">
                    Showing {{ withdrawalsStore.withdrawals.length }} items
                </div>
            </div>

            <div v-if="withdrawalsStore.loading" class="mt-4 text-sm text-slate-400">
                Loading withdrawals...
            </div>

            <div v-else-if="!withdrawalsStore.withdrawals.length" class="mt-4 text-sm text-slate-400">
                No withdrawals yet.
            </div>

            <div v-else class="mt-4 space-y-3">
                <div
                    v-for="w in withdrawalsStore.withdrawals"
                    :key="w.id"
                    class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-4"
                >
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-3">
                                <div class="text-sm font-medium">
                                    #{{ w.id }} · {{ w.amount }}
                                </div>
                                <WithdrawalStatusBadge :status="w.status" />
                            </div>

                            <div class="text-xs text-slate-400">
                                To: <span class="break-all text-slate-300">{{ w.destination_address }}</span>
                            </div>

                            <div v-if="w.destination_tag" class="text-xs text-slate-400">
                                Tag: {{ w.destination_tag }}
                            </div>

                            <div v-if="w.txid" class="break-all text-xs text-slate-500">
                                Tx: {{ w.txid }}
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                v-if="['reserved', 'broadcast_pending'].includes(w.status)"
                                class="rounded-lg border border-slate-700 px-3 py-2 text-sm hover:bg-slate-800"
                                @click="cancelWithdrawal(w.id)"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
