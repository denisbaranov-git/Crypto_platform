import { http } from './http'

function buildIdempotencyKey() {
    // Генерируем ключ на клиенте, чтобы повторный submit не создал дубль.
    return window.crypto?.randomUUID?.() ?? `withdrawal-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export function fetchWithdrawals(params = {}) {
    return http.get('/api/withdrawals', { params })
}

export function fetchWithdrawal(id) {
    return http.get(`/api/withdrawals/${id}`)
}

export function createWithdrawal(payload) {
    const idempotencyKey = payload.idempotency_key || buildIdempotencyKey()

    return http.post(
        '/api/withdrawals',
        {
            ...payload,
            idempotency_key: idempotencyKey,
        },
        {
            headers: {
                'Idempotency-Key': idempotencyKey,
            },
        }
    )
}

export function cancelWithdrawal(id, reason = 'user_request') {
    return http.post(`/api/withdrawals/${id}/cancel`, { reason })
}
