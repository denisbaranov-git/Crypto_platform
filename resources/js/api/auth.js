import { http } from './http'

export function csrfCookie() {
    // Sanctum выдает CSRF-cookie перед логином SPA.
    return http.get('/sanctum/csrf-cookie')
}

export function login(payload) {
    return http.post('/login', payload)
}

export function register(payload) {
    return http.post('/register', payload)
}

export function logout() {
    return http.post('/logout')
}

export function me() {
    return http.get('/api/me')
}

export function mobileLogin(payload) {
    return http.post('/api/mobile/login', payload)
}
