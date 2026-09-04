<script setup>
import { ref } from "vue";
import { isLogged, theme, toggleTheme } from "../../stores/ui";

const drawer = ref(false);
</script>

<template>
    <template v-if="!isLogged">
        <v-app-bar class="app-header" color="white" elevation="0" height="76">
            <v-container class="d-flex align-center"
                ><router-link
                    to="/"
                    class="d-flex align-center ga-3 text-decoration-none"
                    ><span class="brand-mark"
                        ><v-icon icon="mdi-paw" size="23" /></span
                    ><span
                        class="text-h6 font-weight-black"
                        style="color: #173b37"
                        >Lar <span style="color: #0f766e">& Patas</span></span
                    ></router-link
                ><v-spacer />
                <div class="d-none d-sm-flex align-center ga-2">
                    <v-tooltip
                        :text="
                            theme === 'larEPatas'
                                ? 'Ativar modo escuro'
                                : 'Ativar modo claro'
                        "
                        ><template #activator="{ props }"
                            ><v-btn
                                v-bind="props"
                                :icon="
                                    theme === 'larEPatas'
                                        ? 'mdi-weather-night'
                                        : 'mdi-white-balance-sunny'
                                "
                                color="primary"
                                variant="text"
                                @click="toggleTheme" /></template></v-tooltip
                    ><v-btn
                        to="/entrar"
                        color="primary"
                        variant="flat"
                        rounded="lg"
                        >Entrar</v-btn
                    >
                </div>
                <v-btn
                    class="d-sm-none"
                    icon="mdi-menu"
                    color="primary"
                    @click="drawer = !drawer"
            /></v-container>
        </v-app-bar>
        <v-navigation-drawer
            v-model="drawer"
            location="right"
            temporary
            width="290"
            ><div class="pa-5 d-flex align-center ga-3">
                <span class="brand-mark"><v-icon icon="mdi-paw" /></span
                ><span class="font-weight-bold">Lar & Patas</span>
            </div>
            <v-divider /><v-list nav density="comfortable" class="pa-3"
                ><v-list-item
                    :prepend-icon="
                        theme === 'larEPatas'
                            ? 'mdi-weather-night'
                            : 'mdi-white-balance-sunny'
                    "
                    :title="
                        theme === 'larEPatas' ? 'Modo escuro' : 'Modo claro'
                    "
                    @click="toggleTheme" /><v-list-item
                    to="/entrar"
                    prepend-icon="mdi-login"
                    title="Entrar" /></v-list
        ></v-navigation-drawer>
    </template>
</template>
