<script setup>
import { ref, onMounted } from "vue";
import { request } from "../../stores/requests";
import { notify } from "../../stores/ui";
const users = ref([]),
    loading = ref(true);
async function load() {
    try {
        users.value = (await request("/api/safety/blocks")).data;
    } catch (e) {
        notify(e.message, "error");
    } finally {
        loading.value = false;
    }
}
async function unblock(user) {
    try {
        await request(`/api/safety/blocks/${user.id}`, "DELETE");
        await load();
        notify(
            "Bloqueio removido. A amizade não é restaurada automaticamente.",
        );
    } catch (e) {
        notify(e.message, "error");
    }
}
onMounted(load);
</script>
<template>
    <v-container class="py-8" style="max-width: 760px"
        ><div class="section-kicker">SUA CONTA</div>
        <h1 class="text-h4 font-weight-bold mb-3">Privacidade e bloqueios</h1>
        <p class="text-medium-emphasis mb-6">
            Contas bloqueadas não podem iniciar conversas nem interagir com seu
            perfil. Publicações públicas ainda podem ser vistas por visitantes
            sem login.
        </p>
        <v-card rounded="xl" class="pa-5"
            ><v-progress-linear v-if="loading" indeterminate />
            <p v-else-if="!users.length" class="pa-6 text-center">
                Você não bloqueou ninguém.
            </p>
            <v-list-item
                v-for="user in users"
                :key="user.id"
                :title="user.name"
                :prepend-avatar="user.avatar_url"
                ><template #append
                    ><v-btn variant="tonal" @click="unblock(user)"
                        >Desbloquear</v-btn
                    ></template
                ></v-list-item
            ></v-card
        ></v-container
    >
</template>
