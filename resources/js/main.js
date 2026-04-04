import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'

const app = createApp(App)

// Pinia — глобальное состояние приложения.
app.use(createPinia())

// Vue Router — клиентская навигация SPA.
app.use(router)

app.mount('#app')
