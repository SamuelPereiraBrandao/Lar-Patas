<script setup>
import { useRouter } from 'vue-router';
import { clearSession, isLogged, theme, toggleTheme, userName } from '../../stores/ui';

const router = useRouter();
async function logout() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    await fetch('/logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    clearSession();
    router.push('/');
}
</script>

<template>
    <v-navigation-drawer v-if="isLogged" permanent rail expand-on-hover rail-width="76" width="268" color="surface"
        class="app-sidebar">
        <div class="pa-5 d-flex align-center ga-3"><span class="brand-mark"><v-icon icon="mdi-paw" /></span><b
                class="sidebar-label">Lar & Patas</b></div><v-divider />
        <div class="pa-5"><v-avatar color="primary" size="42"><span class="text-white font-weight-bold">{{
                    userName.slice(0, 1) }}</span></v-avatar>
            <div class="font-weight-bold mt-2 sidebar-label">{{ userName }}</div>
            <div class="text-caption text-medium-emphasis sidebar-label">Perfil de adotante</div>
        </div>
        <v-list nav density="comfortable" class="px-3">
            <v-list-item to="/" prepend-icon="mdi-paw"
                title="Encontrar um pet" />
            <v-list-item to="/painel"
                prepend-icon="mdi-view-dashboard-outline" title="Visão geral" />
                <v-list-item to="/perfil"
                prepend-icon="mdi-account-cog-outline" title="Meu perfil" />
                <v-divider class="my-2" /><v-list-subheader
                class="sidebar-label">ADMINISTRAÇÃO</v-list-subheader><v-list-item to="/admin/sedes"
                prepend-icon="mdi-home-city-outline" title="Sedes de acolhimento" /><v-list-item to="/admin/pets"
                prepend-icon="mdi-paw-outline" title="Pets e interessados" /><v-list-item to="/admin/usuarios"
                prepend-icon="mdi-account-group-outline" title="Usuários" /></v-list>
        <template #append><v-divider />
            <div class="pa-3"><v-btn variant="text" block prepend-icon="mdi-theme-light-dark" @click="toggleTheme"><span
                        class="sidebar-label">{{ theme === 'larEPatas' ? 'Modo escuro' : 'Modo claro'
                        }}</span></v-btn><v-btn variant="text" block color="error" prepend-icon="mdi-logout"
                    @click="logout"><span class="sidebar-label">Sair</span></v-btn></div>
        </template>
    </v-navigation-drawer>
</template>

<style scoped>
.app-sidebar :deep(.v-navigation-drawer__content) {
    overflow-x: hidden;
}

.app-sidebar:not(.v-navigation-drawer--is-hovering) .sidebar-label {
    display: none;
}

.app-sidebar:not(.v-navigation-drawer--is-hovering) :deep(.v-btn) {
    min-width: 48px;
    padding-inline: 10px;
}
</style>
