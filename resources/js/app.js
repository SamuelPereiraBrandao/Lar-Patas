import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';
import { mdi } from 'vuetify/iconsets/mdi';
import 'vuetify/styles';
import App from './App.vue';
import router from './router';
const vuetify = createVuetify({
    components,
    directives,
    icons: { defaultSet: 'mdi', sets: { mdi } },
    theme: {
        defaultTheme: 'larEPatas',
        themes: {
            larEPatas: {
                dark: false,
                colors: {
                    primary: '#0f766e',
                    secondary: '#f59e0b',
                    success: '#16a34a',
                    surface: '#ffffff',
                    background: '#f7faf9',
                },
            },
            larEPatasDark: {
                dark: true,
                colors: { primary: '#4fd1c5', secondary: '#fbbf24', success: '#4ade80', surface: '#17211f', background: '#0e1514' },
            },
        },
    },
});

createApp(App).use(createPinia()).use(router).use(vuetify).mount('#app');
