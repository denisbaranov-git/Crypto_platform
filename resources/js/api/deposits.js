import { http } from './http'

export function fetchDeposits() {
    return http.get('/api/deposits')
}
