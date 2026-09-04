<script setup>
import { onUnmounted, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const seconds = ref(10);
const timer = window.setInterval(() => {
    seconds.value -= 1;
    if (seconds.value <= 0) {
        window.clearInterval(timer);
        router.replace("/");
    }
}, 1000);

onUnmounted(() => window.clearInterval(timer));
</script>

<template>
    <v-container class="not-found d-flex align-center justify-center py-12">
        <v-card
            class="text-center pa-8 pa-md-12"
            rounded="xl"
            max-width="620"
            elevation="8"
        >
            <v-avatar color="primary" variant="tonal" size="76" class="mb-6"
                ><v-icon icon="mdi-map-marker-question-outline" size="40"
            /></v-avatar>
            <div class="section-kicker">ERRO 404</div>
            <h1 class="text-h3 font-weight-black mt-2">
                Página não encontrada
            </h1>
            <p class="text-medium-emphasis text-body-1 mt-4">
                Parece que este caminho não existe ou foi movido para outro
                lugar.
            </p>
            <v-chip
                color="primary"
                variant="tonal"
                prepend-icon="mdi-timer-outline"
                class="mt-5"
                >Voltando para a home em {{ seconds }}s</v-chip
            >
            <v-btn
                to="/"
                color="primary"
                size="large"
                rounded="lg"
                block
                class="mt-7"
                prepend-icon="mdi-home-outline"
                >Voltar agora para a home</v-btn
            >
        </v-card>
    </v-container>
</template>

<style scoped>
.not-found {
    min-height: calc(100vh - 160px);
}
</style>
