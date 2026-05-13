import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import * as authApi from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null)
    const userRegister = ref(null)
    const loading = ref(false)
    const error = ref(null)

    const isAuthenticated = computed(() => user.value !== null)

    async function fetchUser() {
        try {
            const response = await authApi.me()
            user.value = response.data
        } catch {
            user.value = null
        }
    }

    async function login(email, password) {
        loading.value = true
        error.value = null

        try {
            await authApi.csrfCookie()
            await authApi.login({ email, password })
            userRegister.value = null
            await fetchUser()
        } catch (e) {
            error.value = 'Неверный email или пароль'
            throw e
        } finally {
            loading.value = false
        }
    }

    async function register(payload) {
        loading.value = true
        error.value = null
        try {
            await authApi.csrfCookie()
            const response = await authApi.register(payload)
            userRegister.value = response.data.data ?? response.data ?? null
            await fetchUser()
        } catch (e) {
            error.value = 'Не удалось зарегистрировать пользователя '
            if(e.response?.status === 422){
                const errors = e.response.data.errors
                for (let field in errors){
                    errors[field].forEach(message => {
                        error.value += message;
                    })
                }
            }

            throw e
        } finally {
            loading.value = false
        }
    }

    async function logout() {
        await authApi.logout()
        user.value = null
    }

    return {
        user,
        loading,
        userRegister,
        error,
        isAuthenticated,
        fetchUser,
        login,
        register,
        logout,
    }
})
