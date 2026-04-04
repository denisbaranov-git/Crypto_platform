<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter, RouterLink } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')

async function submit() {
    await auth.register({
        name: name.value,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
    })

    router.push('/dashboard')
}
</script>

<template>
    <div class="mx-auto mt-16 max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-xl">
        <h1 class="mb-2 text-2xl font-semibold">Create account</h1>
        <p class="mb-6 text-sm text-slate-400">Register to start using wallets and deposits.</p>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm text-slate-300">Name</label>
                <input v-model="name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3" />
            </div>

            <div>
                <label class="mb-1 block text-sm text-slate-300">Email</label>
                <input v-model="email" type="email" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3" />
            </div>

            <div>
                <label class="mb-1 block text-sm text-slate-300">Password</label>
                <input v-model="password" type="password" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3" />
            </div>

            <div>
                <label class="mb-1 block text-sm text-slate-300">Confirm password</label>
                <input v-model="passwordConfirmation" type="password" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3" />
            </div>

            <p v-if="auth.error" class="rounded-xl border border-red-900 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                {{ auth.error }}
            </p>

            <button
                :disabled="auth.loading"
                class="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium hover:bg-indigo-500 disabled:opacity-60"
            >
                {{ auth.loading ? 'Creating…' : 'Create account' }}
            </button>
        </form>

        <p class="mt-4 text-sm text-slate-400">
            Already have an account?
            <RouterLink to="/login" class="text-indigo-400 hover:underline">Login</RouterLink>
        </p>
    </div>
</template>
