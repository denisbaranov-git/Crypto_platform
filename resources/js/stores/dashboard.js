import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { fetchDashboard } from '@/api/dashboard'

export const useDashboardStore = defineStore('dashboard', () => {
    const summary = ref({
        total_balance: '0',
        available_balance: '0',
        locked_balance: '0',
    })
    const wallets = ref([])
    const recentDeposits = ref([])
    const recentWithdrawals = ref([])
    const loading = ref(false)

    const walletCount = computed(() => wallets.value.length)

    async function loadDashboard() {
        loading.value = true
        try {
            const response = await fetchDashboard()

            summary.value = response.data.summary
            wallets.value = response.data.wallets
            recentDeposits.value = response.data.recent_deposits
            recentWithdrawals.value = response.data.recent_withdrawals
        } finally {
            loading.value = false
        }
    }

    return {
        summary,
        wallets,
        recentDeposits,
        recentWithdrawals,
        loading,
        walletCount,
        loadDashboard,
    }
})
