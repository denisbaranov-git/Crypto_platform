import { computed, reactive, ref } from 'vue'
import { cancelWithdrawal, createWithdrawal, fetchWithdrawal, fetchWithdrawals } from '@/api/withdrawals'

const DRAFT_KEY_STORAGE = 'withdrawal:draft:idempotency_key'

function generateKey() {
    return window.crypto?.randomUUID?.() ?? `withdrawal-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function readStoredKey() {
    try {
        return sessionStorage.getItem(DRAFT_KEY_STORAGE)
    } catch {
        return null
    }
}

function writeStoredKey(value) {
    try {
        if (value) {
            sessionStorage.setItem(DRAFT_KEY_STORAGE, value)
        } else {
            sessionStorage.removeItem(DRAFT_KEY_STORAGE)
        }
    } catch {
        // sessionStorage can be unavailable in some environments
    }
}

export const useWithdrawalsStore = () => {
    const withdrawals = ref([])
    const currentWithdrawal = ref(null)
    const loading = ref(false)
    const submitting = ref(false)
    const error = ref(null)
    const lastUpdatedAt = ref(null)

    const draft = reactive({
        idempotencyKey: readStoredKey(),
        startedAt: null,
        submittedAt: null,
    })

    const hasDraftKey = computed(() => Boolean(draft.idempotencyKey))

    function ensureDraftKey() {
        if (!draft.idempotencyKey) {
            draft.idempotencyKey = generateKey()
            draft.startedAt = new Date().toISOString()
            draft.submittedAt = null
            writeStoredKey(draft.idempotencyKey)
        }

        return draft.idempotencyKey
    }

    function startNewDraft() {
        draft.idempotencyKey = generateKey()
        draft.startedAt = new Date().toISOString()
        draft.submittedAt = null
        writeStoredKey(draft.idempotencyKey)

        return draft.idempotencyKey
    }

    function clearDraft() {
        draft.idempotencyKey = null
        draft.startedAt = null
        draft.submittedAt = null
        writeStoredKey(null)
    }

    function markSubmitted() {
        draft.submittedAt = new Date().toISOString()
    }

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
            error.value = e?.response?.data?.message || e?.message || 'Something went wrong'
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
            error.value = e?.response?.data?.message || e?.message || 'Something went wrong'
            throw e
        } finally {
            loading.value = false
        }
    }

    async function submitWithdrawal(payload) {
        submitting.value = true
        error.value = null

        try {
            const key = ensureDraftKey()

            const response = await createWithdrawal(payload, key)
            const created = response.data

            markSubmitted()
            clearDraft()

            currentWithdrawal.value = created
            await loadWithdrawals({ per_page: 20 })

            return created
        } catch (e) {
            error.value = e?.response?.data?.message || e?.message || 'Something went wrong'
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
            error.value = e?.response?.data?.message || e?.message || 'Something went wrong'
            throw e
        } finally {
            loading.value = false
        }
    }

    return {
        withdrawals,
        currentWithdrawal,
        loading,
        submitting,
        error,
        lastUpdatedAt,
        draft,
        hasDraftKey,
        ensureDraftKey,
        startNewDraft,
        clearDraft,
        loadWithdrawals,
        loadWithdrawal,
        submitWithdrawal,
        cancel,
    }
}
