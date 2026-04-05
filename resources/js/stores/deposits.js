import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchDeposits } from '@/api/deposits'

export const useDepositsStore = defineStore('deposits', () => {
    const deposits = ref([])
    const loading = ref(false)

    async function loadDeposits() {
        loading.value = true

        try {
            const response = await fetchDeposits()
            deposits.value = response.data.data
        } finally {
            loading.value = false
        }
    }

    return {
        deposits,
        loading,
        loadDeposits,
    }
})
