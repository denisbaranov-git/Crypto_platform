<script setup>
import {onMounted, onUnmounted, computed, ref} from 'vue'
import {useDepositsStore} from '@/stores/deposits'
import {useAuthStore} from "@/stores/auth.js"
import {useRouter} from "vue-router";

const depositsStore = useDepositsStore()
const auth = useAuthStore()
const router = useRouter()

let timerId = null

// Флаг для предотвращения повторных загрузок
const isLoading = ref(false)

onMounted(async () => {
    if (!auth.user) {
        await auth.fetchUser()
    }
    await depositsStore.loadDeposits()

    timerId = setInterval(async () => {
        if (isLoading.value) return // если загрузка ещё идёт, пропускаем
        isLoading.value = true
        try {
            // Загружаем текущую страницу заново
            await depositsStore.loadDeposits(depositsStore.currentPage)
        } finally {
            isLoading.value = false
        }
    }, 30000)
})

onUnmounted(() => {
    if (timerId) {
        clearInterval(timerId)
        timerId = null
    }
})

// Вычисляемые свойства для отображения
const showingFrom = computed(() => {
    if (depositsStore.total === 0) return 0
    return (depositsStore.currentPage - 1) * depositsStore.perPage + 1
})

const showingTo = computed(() => {
    return Math.min(
        depositsStore.currentPage * depositsStore.perPage,
        depositsStore.total
    )
})

// Генерация массива страниц для отображения
const pagesToShow = computed(() => {
    const pages = []
    const maxVisible = 5 // Сколько номеров страниц показывать

    let start = Math.max(1, depositsStore.currentPage - Math.floor(maxVisible / 2))
    let end = Math.min(depositsStore.lastPage, start + maxVisible - 1)

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1)
    }

    for (let i = start; i <= end; i++) {
        pages.push(i)
    }

    return pages
})

function depositShow(id) {
    router.push(`/deposits/${id}`)
}
</script>

<template>
    <div class="space-y-6">
        <!-- Индикатор загрузки -->
        <div v-if="depositsStore.loading" class="text-center py-4">
            <div class="animate-spin inline-block w-6 h-6 border-2 border-slate-400 border-t-transparent rounded-full"></div>
            <p class="mt-2 text-sm text-slate-400">Loading deposits...</p>
        </div>

        <!-- Сообщение об ошибке -->
        <div v-else-if="depositsStore.error" class="rounded-2xl border border-red-800 bg-red-900/20 p-5">
            <p class="text-sm text-red-400">Error: {{ depositsStore.error }}</p>
        </div>

        <!-- Контент -->
        <div v-else class="space-y-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div v-if="!depositsStore.deposits.length" class="text-sm text-slate-400">
                    No deposits yet.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="deposit in depositsStore.deposits"
                        :key="deposit.id"
                        class="rounded-xl bg-slate-950 px-4 py-3 text-sm"
                        @click="depositShow(deposit.id)"
                    >
                        <div class="flex items-center justify-between">
                            <span>{{ deposit.amount }} {{ deposit.currency }}</span>
                            <span class="text-slate-400">{{ deposit.status }}</span>
                        </div>
                        <div class="mt-1 break-all text-slate-500">
                            {{ deposit.txid }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Пагинация -->
            <div v-if="depositsStore.total > 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                <div class="flex items-center justify-between">
                    <!-- Информация о записях -->
                    <div class="text-sm text-slate-400">
                        Showing {{ showingFrom }}-{{ showingTo }} of {{ depositsStore.total }}
                    </div>

                    <!-- Кнопки пагинации -->
                    <div class="flex items-center space-x-2">
                        <!-- Первая страница -->
                        <button
                            @click="depositsStore.goToPage(1)"
                            :disabled="depositsStore.currentPage === 1"
                            class="px-2 py-1 text-sm rounded-lg transition-colors"
                            :class="depositsStore.currentPage === 1
                                ? 'text-slate-600 cursor-not-allowed'
                                : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                        >
                            ««
                        </button>

                        <!-- Предыдущая -->
                        <button
                            @click="depositsStore.prevPage()"
                            :disabled="depositsStore.currentPage === 1"
                            class="px-3 py-1 text-sm rounded-lg transition-colors"
                            :class="depositsStore.currentPage === 1
                                ? 'text-slate-600 cursor-not-allowed'
                                : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                        >
                            «
                        </button>

                        <!-- Номера страниц -->
                        <button
                            v-for="page in pagesToShow"
                            :key="page"
                            @click="depositsStore.goToPage(page)"
                            class="px-3 py-1 text-sm rounded-lg transition-colors"
                            :class="page === depositsStore.currentPage
                                ? 'bg-blue-600 text-white'
                                : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                        >
                            {{ page }}
                        </button>

                        <!-- Следующая -->
                        <button
                            @click="depositsStore.nextPage()"
                            :disabled="depositsStore.currentPage === depositsStore.lastPage"
                            class="px-3 py-1 text-sm rounded-lg transition-colors"
                            :class="depositsStore.currentPage === depositsStore.lastPage
                                ? 'text-slate-600 cursor-not-allowed'
                                : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                        >
                            »
                        </button>

                        <!-- Последняя страница -->
                        <button
                            @click="depositsStore.goToPage(depositsStore.lastPage)"
                            :disabled="depositsStore.currentPage === depositsStore.lastPage"
                            class="px-2 py-1 text-sm rounded-lg transition-colors"
                            :class="depositsStore.currentPage === depositsStore.lastPage
                                ? 'text-slate-600 cursor-not-allowed'
                                : 'text-slate-400 hover:text-white hover:bg-slate-800'"
                        >
                            »»
                        </button>
                    </div>

                    <!-- Выбор количества на странице -->
                    <select
                        v-model="depositsStore.perPage"
                        @change="depositsStore.loadDeposits(1)"
                        class="bg-slate-800 text-slate-300 text-sm rounded-lg px-3 py-1 border border-slate-700 focus:outline-none focus:border-slate-600"
                    >
                        <option :value="10">10 per page</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>
