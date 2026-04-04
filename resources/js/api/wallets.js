import { http } from './http'

export function fetchWallets() {
    return http.get('/api/wallets')
}

export function fetchWallet(id) {
    return http.get(`/api/wallets/${id}`)
}
