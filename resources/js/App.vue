<script setup>
import AppFooter from "./components/layout/AppFooter.vue";
import AppHeader from "./components/layout/AppHeader.vue";
import AppMessages from "./components/layout/AppMessages.vue";
import AppNotifications from "./components/layout/AppNotifications.vue";
import AppSidebar from "./components/layout/AppSidebar.vue";
import AppToast from "./components/layout/AppToast.vue";
import { theme } from "./stores/ui";
</script>
<template>
    <v-app class="pet-page" :theme="theme"
        ><AppSidebar /><AppHeader /><AppNotifications /><AppMessages /><v-main
            ><router-view /></v-main
        ><AppFooter /><AppToast
    /></v-app>
</template>

<style>
html {
    scrollbar-width: thin;
    scrollbar-color: rgb(var(--v-theme-primary)) #10201d;
}
* {
    scrollbar-width: thin;
    scrollbar-color: rgb(var(--v-theme-primary)) #10201d;
}
*::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
*::-webkit-scrollbar-track {
    background: #10201d;
    border-radius: 999px;
}
*::-webkit-scrollbar-thumb {
    background: rgba(var(--v-theme-primary), 0.76);
    border: 1px solid #10201d;
    border-radius: 999px;
}
*::-webkit-scrollbar-thumb:hover {
    background: rgb(var(--v-theme-primary));
}
</style>
