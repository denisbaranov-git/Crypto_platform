import { http } from './http'

export function fetchWithdrawals(params = {}) {
    return http.get('/api/withdrawals', { params })
}

export function fetchWithdrawal(id) {
    return http.get(`/api/withdrawals/${id}`)
}

export function createWithdrawal(payload, idempotencyKey) {
    // Ключ idempotencyKey создаётся не на каждом рендере, а один раз на submission intent.
    // Ключ idempotencyKey хранится в состоянии формы или localStorage до успешного ответа.
    if (!idempotencyKey) {
        throw new Error('idempotencyKey is required')
    }

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
