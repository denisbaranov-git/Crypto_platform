import { computed, ref } from 'vue'
import { cancelWithdrawal, createWithdrawal, fetchWithdrawal, fetchWithdrawals } from '@/api/withdrawals'

function extractErrorMessage(error) {
    return (
        error?.response?.data?.message ||
        error?.response?.data?.error ||
        error?.message ||
        'Something went wrong'
    )
}

export const useWithdrawalsStore = () => {
    const withdrawals = ref([])
    const currentWithdrawal = ref(null)
    const loading = ref(false)
    const submitting = ref(false)
    const error = ref(null)
    const lastUpdatedAt = ref(null)

    const openWithdrawals = computed(() =>
        withdrawals.value.filter((w) =>
            ['requested', 'reserved', 'broadcast_pending', 'broadcasted', 'settled'].includes(w.status)
        )
    )

    async function loadWithdrawals(params = {}) {
        loading.value = true
        error.value = null

        try {
            const response = await fetchWithdrawals({
                per_page: 20,
                ...params,
            })

            withdrawals.value = response.data.data ?? response.data ?? []
            lastUpdatedAt.value = new Date().toLocaleString()
        } catch (e) {
            error.value = extractErrorMessage(e)
            throw e
        } finally {
            loading.value = false
        }
    }

    async function loadWithdrawal(id) {
        loading.value = true
        error.value = null

        try {
            const response = await fetchWithdrawal(id)
            currentWithdrawal.value = response.data
            return response.data
        } catch (e) {
            error.value = extractErrorMessage(e)
            throw e
        } finally {
            loading.value = false
        }
    }

    async function submitWithdrawal(payload) {
        submitting.value = true
        error.value = null

        try {
            const response = await createWithdrawal(payload)
            currentWithdrawal.value = response.data
            // После создания сразу обновляем список.
            await loadWithdrawals({ per_page: 20 })
            return response.data
        } catch (e) {
            error.value = extractErrorMessage(e)
            throw e
        } finally {
            submitting.value = false
        }
    }

    async function cancel(id, reason = 'user_request') {
        loading.value = true
        error.value = null

        try {
            const response = await cancelWithdrawal(id, reason)
            await loadWithdrawals({ per_page: 20 })
            return response.data
        } catch (e) {
            error.value = extractErrorMessage(e)
            throw e
        } finally {
            loading.value = false
        }
    }

    return {
        withdrawals,
        openWithdrawals,
        currentWithdrawal,
        loading,
        submitting,
        error,
        lastUpdatedAt,
        loadWithdrawals,
        loadWithdrawal,
        submitWithdrawal,
        cancel,
    }
}
