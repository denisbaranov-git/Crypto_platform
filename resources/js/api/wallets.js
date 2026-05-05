import { http } from './http'

export function fetchWallets() {
    return http.get('/api/wallets')
}

export function fetchWallet(id) {
    return http.get(`/api/wallets/${id}`)
}

export function storeAddress(wallet) {

    return http.post(
        '/api/wallets/${id}/addresses',
        wallet,
    )
}
export function createWallet() { //get  create form data

    return http.get(
        '/api/wallets/create',
    )
}
export function storeWallet(currency_network_id) {

    return http.post(
        '/api/wallets',
        currency_network_id,
    )
}


