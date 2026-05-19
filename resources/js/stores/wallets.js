import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {fetchWallets, fetchWallet, createWallet, storeAddress, storeWallet, activateAddress} from '@/api/wallets'

export const useWalletsStore = defineStore('wallets', () => {
    const wallets = ref([])
    const currentWallet = ref(null)
    const loading = ref(false)

    const totalAvailable = computed(() =>
        wallets.value.reduce((sum, wallet) => sum + Number(wallet.available_balance), 0)
    )
    const walletCreateFormData = ref(null)

    async function loadWallets() {
        loading.value = true
        try {
            const response = await fetchWallets()
            //API может возвращать { data: [...] } или { data: { data: [...] } }
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
    async function issueWalletAddress(wallet) {
        loading.value = true
        try {
            const response = await storeAddress(wallet)
            //currentWallet.value = response.data.data ?? response.data ?? null //currentWalletAddress
        } finally {
            loading.value = false
        }
    }
    async function activateWalletAddress(id, wallet_address_id) {
        loading.value = true
        try {
            const response = await activateAddress(id,wallet_address_id)
        } finally {
            loading.value = false
        }
    }
    async function fetchWalletCreateFormData() {
        loading.value = true
        try {
            const response = await createWallet()
            walletCreateFormData.value = response.data.data ?? response.data ?? null
        } finally {
            loading.value = false
        }
    }
    async function storeNewWallet(currency_network_id) {
        loading.value = true
        try {
            const response = await storeWallet(currency_network_id)
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
        issueWalletAddress,
        fetchWalletCreateFormData,
        walletCreateFormData,
        storeNewWallet,
        activateWalletAddress,
    }
})
