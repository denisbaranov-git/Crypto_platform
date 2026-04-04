import axios from 'axios'

// Один HTTP-клиент для всего приложения.
//withCredentials нужен для Sanctum cookie-based SPA.
export const http = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000',
    withCredentials: true,
    withXSRFToken: true,
    // headers: {//denis
    //     'X-Requested-With': 'XMLHttpRequest',
    //     'Content-Type': 'application/json',
    // },
})
