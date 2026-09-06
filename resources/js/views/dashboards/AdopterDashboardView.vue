<script setup>
import { onMounted, ref } from "vue";
import AdoptionCareDialog from "../../components/admin/AdoptionCareDialog.vue";
import PetGallery from "../../components/pets/PetGallery.vue";
import { notify } from "../../stores/ui";

const interests = ref([]);
const careOpen = ref(false),
    careAction = ref("followup"),
    careAdoption = ref(null);
function openCare(interest, action) {
    careAdoption.value = interest;
    careAction.value = action;
    careOpen.value = true;
}
function stage(interest) {
    return interest.released_at ? 4 : interest.pickup_at ? 3 : 2;
}
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
    if (!response.ok)
        return notify(
            "Não foi possível remover o interesse. Entre em contato com a equipe.",
            "error",
        );
    await load();
}

function peopleLabel(total) {
    return `${total} ${total === 1 ? "pessoa interessada" : "pessoas interessadas"}`;
}
function visitsLabel(total) {
    return `${total} ${total === 1 ? "visita prevista" : "visitas previstas"}`;
}
function interestStatusLabel(interest) {
    if (interest.cancelled_at) return "Cancelada";
    if (interest.released_at) return "Adoção concluída";
    if (interest.status === "approved" && interest.pickup_at)
        return "Aguardando liberação";
    return (
        {
            pending: "Aguardando análise",
            approved: "Aprovada",
            rejected: "Não aprovada",
            cancelled: "Cancelada",
        }[interest.status] || interest.status
    );
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
                    Acompanhe pessoas interessadas, a fila e possíveis visitas.
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
                    <PetGallery
                        :species="interest.pet.species"
                        :photos="
                            interest.pet.gallery_urls?.length
                                ? interest.pet.gallery_urls
                                : [interest.pet.image_url].filter(Boolean)
                        "
                        :height="205"
                    />
                    <v-card-item class="pt-5"
                        ><v-card-title>{{ interest.pet.name }}</v-card-title
                        ><v-card-subtitle
                            >{{ interest.pet.city }} ·
                            {{ interest.pet.age_label }}</v-card-subtitle
                        ></v-card-item
                    >
                    <v-card-text>
                        <ol
                            v-if="
                                !interest.cancelled_at &&
                                interest.status !== 'rejected'
                            "
                            class="adoption-steps mb-5"
                        >
                            <li
                                v-for="(label, index) in [
                                    'Solicitação',
                                    'Análise',
                                    'Retirada',
                                    'Concluída',
                                ]"
                                :key="label"
                                :class="{
                                    done: index + 1 < stage(interest),
                                    current: index + 1 === stage(interest),
                                }"
                            >
                                {{ label }}
                            </li>
                        </ol>
                        <p class="text-caption text-medium-emphasis mb-3">
                            Solicitação feita em
                            {{
                                new Date(
                                    interest.created_at,
                                ).toLocaleDateString("pt-BR")
                            }}<span v-if="interest.released_at">
                                · Adoção concluída em
                                {{
                                    new Date(
                                        interest.released_at,
                                    ).toLocaleDateString("pt-BR")
                                }}</span
                            >
                        </p>
                        <p
                            v-if="
                                interest.status === 'pending' &&
                                !interest.cancelled_at
                            "
                            class="text-body-2 mb-4"
                        >
                            Próximo passo: a equipe analisará seu perfil e
                            confirmará a retirada pelo painel.
                        </p>
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
                        <v-card
                            v-if="interest.pickup_at && !interest.released_at"
                            color="primary"
                            variant="tonal"
                            rounded="lg"
                            class="pa-4 mt-4"
                        >
                            <b
                                ><v-icon
                                    icon="mdi-calendar-check-outline"
                                    size="20"
                                />
                                Venha buscar {{ interest.pet.name }}</b
                            >
                            <p class="text-body-2 mt-2">
                                {{
                                    new Date(interest.pickup_at).toLocaleString(
                                        "pt-BR",
                                        {
                                            dateStyle: "short",
                                            timeStyle: "short",
                                            timeZone:
                                                interest.pickup_timezone ||
                                                "America/Sao_Paulo",
                                        },
                                    )
                                }}
                                ({{ interest.pickup_timezone }})
                            </p>
                            <p class="text-body-2 mt-2">
                                {{ interest.pickup_location }}
                            </p>
                            <p
                                class="text-body-2 mt-3"
                                style="white-space: pre-wrap"
                            >
                                {{ interest.pickup_message }}
                            </p>
                            <div v-if="interest.verification_code" class="mt-4">
                                <span class="text-caption"
                                    >Seu código de verificação</span
                                >
                                <div
                                    class="text-h4 font-weight-bold"
                                    style="letter-spacing: 0.15em"
                                >
                                    {{ interest.verification_code }}
                                </div>
                            </div>
                            <p class="text-caption mt-2">
                                Apresente o código à equipe na retirada para
                                concluir a adoção.
                            </p>
                        </v-card>
                    </v-card-text>
                    <v-alert
                        v-if="interest.support_message"
                        color="primary"
                        variant="tonal"
                        class="mx-4 mb-4"
                        title="Resposta da equipe"
                        ><p style="white-space: pre-wrap">
                            {{ interest.support_message }}
                        </p></v-alert
                    >
                    <div class="d-flex flex-wrap ga-2 px-4 pb-3">
                        <v-btn
                            v-if="
                                interest.pickup_at &&
                                !interest.released_at &&
                                !interest.cancelled_at
                            "
                            size="small"
                            variant="tonal"
                            @click="openCare(interest, 'reschedule')"
                            >Pedir reagendamento</v-btn
                        >
                        <v-btn
                            v-if="
                                !interest.released_at &&
                                !interest.cancelled_at &&
                                interest.status !== 'rejected'
                            "
                            size="small"
                            variant="text"
                            color="error"
                            @click="openCare(interest, 'cancel')"
                            >Cancelar solicitação</v-btn
                        >
                        <v-btn
                            v-if="interest.released_at"
                            color="primary"
                            variant="tonal"
                            @click="openCare(interest, 'followup')"
                            >{{
                                interest.followup_completed_at
                                    ? "Atualizar adaptação"
                                    : "Contar como está a adaptação"
                            }}</v-btn
                        >
                    </div>
                    <v-alert
                        v-if="interest.reschedule_requested_at"
                        color="warning"
                        variant="tonal"
                        class="mx-4 mb-3"
                        >Pedido de reagendamento enviado. Aguarde a equipe
                        confirmar.</v-alert
                    >
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
        <AdoptionCareDialog
            v-model="careOpen"
            :adoption="careAdoption"
            :action="careAction"
            @saved="load"
        />
    </v-container>
</template>
