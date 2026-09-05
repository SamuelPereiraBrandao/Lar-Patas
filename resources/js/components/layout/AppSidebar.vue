<script setup>
import { computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import {
    clearSession,
    isLogged,
    setSession,
    theme,
    toggleTheme,
    userAvatar,
    userName,
    userRoles,
} from "../../stores/ui";

const router = useRouter();
const isAdmin = computed(() => userRoles.value.includes("admin"));
const roleBadges = computed(() =>
    [
        {
            role: "admin",
            label: "Admin",
            icon: "mdi-shield-crown-outline",
            color: "secondary",
        },
        {
            role: "adopter",
            label: "Adotante",
            icon: "mdi-heart-outline",
            color: "primary",
        },
        {
            role: "donor",
            label: "Doador",
            icon: "mdi-paw-outline",
            color: "info",
        },
    ].filter((item) => userRoles.value.includes(item.role)),
);

async function hydrateUser() {
    const response = await fetch("/auth/user", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) setSession((await response.json()).user);
}
async function logout() {
    const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || "";
    await fetch("/logout", {
        method: "POST",
        credentials: "same-origin",
        headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" },
    });
    clearSession();
    router.push("/");
}
onMounted(hydrateUser);
</script>

<template>
    <v-navigation-drawer
        v-if="isLogged"
        permanent
        rail
        expand-on-hover
        rail-width="76"
        width="284"
        color="surface"
        class="app-sidebar"
    >
        <div class="sidebar-brand pa-5 d-flex align-center ga-3">
            <span class="brand-mark"><v-icon icon="mdi-paw" /></span>
            <div class="sidebar-label">
                <b class="text-h6">Lar & Patas</b>
                <div class="text-caption opacity-70">Adoção responsável</div>
            </div>
        </div>
        <div class="pa-4">
            <v-card to="/perfil" class="profile-card pa-3" rounded="xl" elevation="0"
                ><div class="d-flex align-center ga-3">
                    <v-avatar color="primary" size="44"
                        ><v-img
                            v-if="userAvatar"
                            :src="userAvatar"
                            cover
                        /><span v-else class="text-white font-weight-bold">{{
                            userName.slice(0, 1)
                        }}</span></v-avatar
                    >
                    <div class="sidebar-label overflow-hidden">
                        <div class="font-weight-bold text-truncate">
                            {{ userName }}
                        </div>
                        <div class="d-flex flex-wrap ga-1 mt-2">
                            <v-chip
                                v-for="badge in roleBadges"
                                :key="badge.role"
                                :color="badge.color"
                                size="x-small"
                                variant="tonal"
                                :prepend-icon="badge.icon"
                                >{{ badge.label }}</v-chip
                            >
                        </div>
                    </div>
                </div></v-card
            >
        </div>
        <v-list nav density="comfortable" class="px-3 sidebar-nav"
            ><v-list-subheader class="sidebar-label">NAVEGAÇÃO</v-list-subheader
            ><v-list-item
                to="/"
                prepend-icon="mdi-magnify"
                title="Explorar pets"
                rounded="lg"
            /><v-list-item
                to="/painel"
                prepend-icon="mdi-heart-multiple-outline"
                title="Meus interesses"
                rounded="lg"
            /><v-list-item
                to="/perfil"
                prepend-icon="mdi-account-cog-outline"
                title="Meu perfil"
                rounded="lg"
            />
            <v-list-item
                    to="/meus-pets"
                    prepend-icon="mdi-paw-outline"
                    title="Meus pets"
                    rounded="lg"
            />
            <template v-if="isAdmin"
                ><v-divider class="my-3" /><v-list-subheader
                    class="sidebar-label"
                    >ADMINISTRAÇÃO</v-list-subheader
                ><v-list-item
                    to="/admin/pets"
                    prepend-icon="mdi-cog-outline"
                    title="Configurações de pets"
                    rounded="lg" /><v-list-item
                    to="/admin/usuarios"
                    prepend-icon="mdi-account-group-outline"
                    title="Usuários e cargos"
                    rounded="lg"
            /></template>
        </v-list>
        <template #append
            ><div class="pa-3">
                <v-card class="sidebar-actions pa-1" rounded="lg" elevation="0"
                    ><v-btn
                        variant="text"
                        block
                        :prepend-icon="
                            theme === 'larEPatas'
                                ? 'mdi-weather-night'
                                : 'mdi-white-balance-sunny'
                        "
                        @click="toggleTheme"
                        ><span class="sidebar-label">{{
                            theme === "larEPatas" ? "Modo escuro" : "Modo claro"
                        }}</span></v-btn
                    ><v-btn
                        variant="text"
                        block
                        color="error"
                        prepend-icon="mdi-logout"
                        @click="logout"
                        ><span class="sidebar-label">Sair da conta</span></v-btn
                    ></v-card
                >
            </div></template
        >
    </v-navigation-drawer>
</template>

<style scoped>
.app-sidebar :deep(.v-navigation-drawer__content) {
    overflow-x: hidden;
}
.sidebar-brand {
    background: linear-gradient(135deg, rgba(15, 118, 110, 0.16), transparent);
}
.profile-card,
.sidebar-actions {
    border: 1px solid rgba(88, 184, 170, 0.14);
    background: rgba(22, 62, 55, 0.13);
}
.sidebar-nav :deep(.v-list-item--active) {
    background: rgba(50, 201, 183, 0.16);
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
}
.sidebar-nav :deep(.v-list-item) {
    margin-bottom: 4px;
}
.app-sidebar:not(.v-navigation-drawer--is-hovering) .sidebar-label {
    display: none;
}
.app-sidebar:not(.v-navigation-drawer--is-hovering) :deep(.v-btn) {
    min-width: 48px;
    padding-inline: 10px;
}
.app-sidebar:not(.v-navigation-drawer--is-hovering) .profile-card {
    padding: 0 !important;
    background: transparent;
    border-color: transparent;
}
</style>
