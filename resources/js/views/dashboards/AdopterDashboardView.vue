<script setup>
import { onMounted, ref } from "vue";
import PetGallery from "../../components/pets/PetGallery.vue";
import { notify } from "../../stores/ui";

const interests = ref([]);
const loading = ref(true);
const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";

async function load() {
    loading.value = true;
    const response = await fetch("/api/dashboards/receiver", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) interests.value = (await response.json()).data;
    loading.value = false;
}

async function remove(interest) {
    const response = await fetch(`/api/adoptions/${interest.id}`, {
        method: "DELETE",
        credentials: "same-origin",
        headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" },
    });
    if (!response.ok) return notify("Não foi possível remover o interesse. Entre em contato com a equipe.", "error");
    await load();
}

function peopleLabel(total) {
    return `${total} ${total === 1 ? "pessoa interessada" : "pessoas interessadas"}`;
}
function visitsLabel(total) {
    return `${total} ${total === 1 ? "visita prevista" : "visitas previstas"}`;
}
function interestStatusLabel(interest) {
    if (interest.released_at) return "Adoção concluída";
    if (interest.status === "approved" && interest.pickup_at) return "Aguardando liberação";
    return {
        pending: "Aguardando análise",
        approved: "Aprovada",
        rejected: "Não aprovada",
        cancelled: "Cancelada",
    }[interest.status] || interest.status;
}

onMounted(load);
</script>

<template>
    <v-container class="py-10">
        <div class="d-flex flex-wrap align-center justify-space-between mb-8">
            <div>
                <div class="section-kicker">MEUS INTERESSES</div>
                <h1 class="text-h4 font-weight-bold">
                    Pets que quero conhecer
                </h1>
                <p class="text-medium-emphasis mt-2">
                    Acompanhe pessoas interessadas, a fila e posséveis visitas.
                </p>
            </div>
            <v-btn to="/" color="primary" prepend-icon="mdi-paw"
                >Encontrar mais pets</v-btn
            >
        </div>
        <v-row>
            <v-col v-if="loading" cols="12" class="text-center pa-12"
                ><v-progress-circular indeterminate color="primary"
            /></v-col>
            <v-col
                v-for="interest in interests"
                :key="interest.id"
                cols="12"
                md="6"
                lg="4"
            >
                <v-card rounded="xl" class="h-100 overflow-hidden">
                    <PetGallery :species="interest.pet.species" :photos="interest.pet.gallery_urls?.length ? interest.pet.gallery_urls : [interest.pet.image_url].filter(Boolean)" :height="205" />
                    <v-card-item class="pt-5"
                        ><v-card-title>{{ interest.pet.name }}</v-card-title
                        ><v-card-subtitle
                            >{{ interest.pet.city }} ·
                            {{ interest.pet.age_label }}</v-card-subtitle
                        ></v-card-item
                    >
                    <v-card-text>
                        <v-alert
                            color="secondary"
                            variant="tonal"
                            density="compact"
                            class="mb-3"
                            icon="mdi-heart"
                            >{{ interestStatusLabel(interest) }}</v-alert
                        >
                        <div class="d-flex flex-wrap ga-2">
                            <v-chip
                                color="primary"
                                variant="tonal"
                                size="small"
                                prepend-icon="mdi-account-group"
                                >{{ peopleLabel(interest.pet.adoptions_count) }}
                                <span class="ml-1">(inclui você)</span></v-chip
                            ><v-chip
                                variant="tonal"
                                size="small"
                                prepend-icon="mdi-calendar-clock"
                                >{{
                                    visitsLabel(interest.pet.visits_count)
                                }}</v-chip
                            >
                        </div>
                        <p class="text-caption mt-4">
                            Status da solicitação:
                            <strong>{{ interestStatusLabel(interest) }}</strong>
                        </p>
                        <v-card v-if="interest.pickup_at && !interest.released_at" color="primary" variant="tonal" rounded="lg" class="pa-4 mt-4">
                            <b><v-icon icon="mdi-calendar-check-outline" size="20" /> Venha buscar {{ interest.pet.name }}</b>
                            <p class="text-body-2 mt-2">{{ new Date(interest.pickup_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: interest.pickup_timezone || 'America/Sao_Paulo' }) }} ({{ interest.pickup_timezone }})</p>
                            <p class="text-body-2 mt-2">{{ interest.pickup_location }}</p>
                            <p class="text-body-2 mt-3" style="white-space: pre-wrap">{{ interest.pickup_message }}</p>
                            <div v-if="interest.verification_code" class="mt-4"><span class="text-caption">Seu código de verificação</span><div class="text-h4 font-weight-bold" style="letter-spacing: .15em">{{ interest.verification_code }}</div></div>
                            <p class="text-caption mt-2">Apresente o código à equipe na retirada para concluir a adoção.</p>
                        </v-card>
                    </v-card-text>
                    <v-card-actions class="pa-4"
                        ><v-btn
                            :to="`/pets/${interest.pet.id}`"
                            variant="text"
                            color="primary"
                            >Ver perfil</v-btn
                        ><v-spacer /><v-btn
                            v-if="interest.status !== 'approved'"
                            color="error"
                            variant="text"
                            @click="remove(interest)"
                            >Remover interesse</v-btn
                        ></v-card-actions
                    >
                </v-card>
            </v-col>
            <v-col v-if="!loading && !interests.length" cols="12"
                ><v-card class="pa-10 text-center" rounded="xl"
                    ><v-icon
                        icon="mdi-heart-outline"
                        size="44"
                        color="primary"
                    />
                    <h2 class="text-h6 mt-4">
                        Você ainda não marcou nenhum pet
                    </h2>
                    <v-btn to="/" color="primary" class="mt-4"
                        >Explorar pets</v-btn
                    ></v-card
                ></v-col
            >
        </v-row>
    </v-container>
</template>
