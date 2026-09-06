<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";

const props = defineProps({ id: String });
const router = useRouter();
const pet = ref(null);
const loading = ref(false);
const message = ref("");
const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";

async function interest() {
    loading.value = true;
    message.value = "";
    const response = await fetch(`/api/pets/${props.id}/adoptions`, {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    loading.value = false;
    if (response.ok) {
        await router.push({
            path: `/pets/${props.id}`,
            query: { interest: "success" },
        });
        return;
    }
    message.value =
        (await response.json()).message ||
        "Não foi possível registrar o interesse.";
}

onMounted(async () => {
    pet.value = (await (await fetch(`/api/pets/${props.id}`)).json()).data;
});
</script>

<template>
    <v-container class="py-12" style="max-width: 760px">
        <v-btn to="/" variant="text" prepend-icon="mdi-arrow-left"
            >Voltar</v-btn
        >
        <v-card v-if="pet" class="mt-4 pa-6 pa-md-9" rounded="xl">
            <div class="text-center">
                <v-avatar color="primary" size="58"
                    ><v-icon icon="mdi-heart-outline" size="30"
                /></v-avatar>
                <div class="section-kicker mt-5">INTERESSE EM ADOÇÃO</div>
                <h1 class="text-h4 font-weight-bold mt-2">
                    Quer conhecer {{ pet.name }}?
                </h1>
                <p class="text-medium-emphasis mt-3">
                    Usaremos seu perfil de adotante para registrar o interesse.
                    A ONG poderá avaliar a compatibilidade e entrar em contato
                    sobre a visita.
                </p>
            </div>
            <v-alert v-if="message" type="error" variant="tonal" class="my-6">{{
                message
            }}</v-alert>
            <v-card class="pa-4 mt-6" variant="tonal" color="primary">
                <div class="font-weight-bold">{{ pet.name }}</div>
                <div class="text-caption">
                    {{ pet.city }} · {{ pet.age_label }} · Porte {{ pet.size }}
                </div>
            </v-card>
            <v-btn
                v-if="pet.can_adopt"
                :loading="loading"
                color="primary"
                size="large"
                block
                class="mt-6"
                @click="interest"
                >Marcar como pet de interesse <v-icon end icon="mdi-heart"
            /></v-btn>
            <v-alert v-else type="info" variant="tonal" class="mt-6"
                >Este pet não está disponível para você registrar interesse.
                <router-link :to="`/pets/${pet.id}`"
                    >Voltar ao pet</router-link
                ></v-alert
            >
        </v-card>
    </v-container>
</template>
