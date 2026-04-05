import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'; // Импортируем плагин
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css','resources/js/main.js'],//denis
            refresh: true,
        }),
        vue(),
        tailwindcss(), // denis
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
})
