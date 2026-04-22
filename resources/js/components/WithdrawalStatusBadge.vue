<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, required: true },
})

const classes = computed(() => {
    switch (props.status) {
        case 'requested':
        case 'reserved':
        case 'broadcast_pending':
            return 'bg-amber-500/15 text-amber-300 border-amber-500/30'
        case 'broadcasted':
        case 'settled':
            return 'bg-sky-500/15 text-sky-300 border-sky-500/30'
        case 'confirmed':
            return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30'
        case 'released':
            return 'bg-slate-500/15 text-slate-300 border-slate-500/30'
        case 'cancelled':
        case 'failed':
        case 'reorged':
        case 'reversed':
            return 'bg-rose-500/15 text-rose-300 border-rose-500/30'
        default:
            return 'bg-slate-500/15 text-slate-300 border-slate-500/30'
    }
})
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium" :class="classes">
        {{ status }}
    </span>
</template>
