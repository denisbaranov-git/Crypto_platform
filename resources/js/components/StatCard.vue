<script setup>
defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
})
</script>

<template>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <div class="text-sm text-slate-400">{{ title }}</div>
        <div class="mt-2 text-2xl font-semibold">{{ value }}</div>
    </div>
</template>
