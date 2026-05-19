import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchDeposits, fetchDeposit } from '@/api/deposits'

export const useDepositsStore = defineStore('deposits', () => {
    const deposits = ref([])
    const currentDeposit = ref(null)
    const loading = ref(false)
    const error = ref(null)
    const lastUpdatedAt = ref(null)

    // Состояние пагинации
    const currentPage = ref(1)
    const lastPage = ref(1)
    const perPage = ref(5)
    const total = ref(0)
    async function loadDeposits(params = {}) {
        loading.value = true
        error.value = null

        try {
            const response = await fetchDeposits({
                per_page: perPage.value,
                ...params,
                // params Передаем фильтры если есть
                // status: statusFilter.value,
                // currency: currencyFilter.value,
                // network: networkFilter.value,
            })

            deposits.value = response.data.data ?? response.data.items ?? []
            //pagination
            currentPage.value = response.data.currentPage ?? response.data.current_page
            lastPage.value = response.data.lastPage ?? response.data.last_page
            perPage.value = response.data.perPage ?? response.data.per_page
            total.value = response.data.total
            lastUpdatedAt.value = new Date().toLocaleString()
        } catch (e) {
            error.value = e?.response?.data?.message || e?.message || 'Something went wrong'
            throw e
        } finally {
            loading.value = false
        }
    }
    async function loadDeposit(id) {
        loading.value = true
        error.value = null
        try {
            const response = await fetchDeposit(id)
            currentDeposit.value = response.data.data ?? response.data ?? []
            return response.data
        } catch (e) {
            error.value = e?.response?.data?.message || e?.message || 'Something went wrong'
            throw e
        } finally {
            loading.value = false
        }
    }
    function nextPage() {
        if (currentPage.value < lastPage.value) {
            loadDeposits({ page: currentPage.value + 1 })
        }
    }

    function prevPage() {
        if (currentPage.value > 1) {
            loadDeposits({ page: currentPage.value - 1})
        }
    }

    function goToPage(page) {
        if (page >= 1 && page <= lastPage.value) {
            loadDeposits({ page: page})
        }
    }

    return {
        deposits,
        loading,
        loadDeposits,
        loadDeposit,
        nextPage,
        prevPage,
        goToPage,
        currentPage,
        currentDeposit,
        lastPage,
        perPage,
        total,
    }
})
