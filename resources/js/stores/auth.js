import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import * as authApi from '@/api/auth'
import {http} from "@/api/http.js";

export const useAuthStore = defineStore('auth', () => {
    // STATE
    const user = ref(null)
    const loading = ref(false)
    const error = ref(null)

    // GETTER: удобная derived-переменная.
    const isAuthenticated = computed(() => user.value !== null)

    // Загружаем текущего пользователя из /api/me.
    async function fetchUser() {
        try {
            const response = await authApi.me()
            user.value = response.data
        } catch {
            user.value = null
        }
    }

    // Web SPA login (cookie/session).
    async function login(email, password) {
        loading.value = true
        error.value = null

        try {
            await authApi.csrfCookie()
            await authApi.login({ email, password })
            await fetchUser()
        } catch (e) {
            error.value = 'Неверный email или пароль'
            throw e
        } finally {
            loading.value = false
        }
    }

    // Register + auto-login.
    async function register(payload) {
        loading.value = true
        error.value = null

        try {
            await authApi.csrfCookie()
            //await authApi.register(payload)
            await http.post('/register', {
                name: 'Test',
                email: 'test@test.com',
                password: '123456',
                password_confirmation: '123456'
            })
            await fetchUser()
        } catch (e) {
            error.value = 'Не удалось зарегистрировать пользователя'
            throw e
        } finally {
            loading.value = false
        }
    }

    // Logout web SPA.
    async function logout() {
        await authApi.logout()
        user.value = null
    }

    return {
        user,
        loading,
        error,
        isAuthenticated,
        fetchUser,
        login,
        register,
        logout,
    }
})
