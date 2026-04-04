import { http } from './http'

export function fetchDashboard() {
    return http.get('/api/dashboard')
}
