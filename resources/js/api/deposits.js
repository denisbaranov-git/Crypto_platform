import { http } from './http'

export function fetchDeposits(param) {
    return http.get('/api/deposits', {params: param})
}
export function fetchDeposit(id) {
    return http.get(`/api/deposits/${id}`)
}
