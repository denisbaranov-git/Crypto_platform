import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { fetchWallets, fetchWallet } from '@/api/wallets'

export const useWalletsStore = defineStore('wallets', () => {
    const wallets = ref([])
    const currentWallet = ref(null)
    const loading = ref(false)

    const totalAvailable = computed(() =>
        wallets.value.reduce((sum, wallet) => sum + Number(wallet.available_balance), 0)
    )

    async function loadWallets() {
        loading.value = true
        try {
            const response = await fetchWallets()
            // Предполагается, что API может возвращать { data: [...] } или { data: { data: [...] } }
            wallets.value = response.data.data ?? response.data ?? []
        } finally {
            loading.value = false
        }
    }

    async function loadWallet(id) {
        loading.value = true
        try {
            const response = await fetchWallet(id)
            currentWallet.value = response.data.data ?? response.data ?? null
        } finally {
            loading.value = false
        }
    }

    return {
        wallets,
        currentWallet,
        loading,
        totalAvailable,
        loadWallets,
        loadWallet,
    }
})
