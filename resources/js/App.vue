<script setup>
import AppFooter from "./components/layout/AppFooter.vue";
import AppHeader from "./components/layout/AppHeader.vue";
import AppSidebar from "./components/layout/AppSidebar.vue";
import AppToast from "./components/layout/AppToast.vue";
import { theme } from "./stores/ui";
</script>
<template>
    <v-app class="pet-page" :theme="theme"
        ><AppSidebar /><AppHeader /><v-main><router-view /></v-main
        ><AppFooter /><AppToast
    /></v-app>
</template>
