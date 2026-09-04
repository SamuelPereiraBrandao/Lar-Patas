<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import PetGallery from "../../components/pets/PetGallery.vue";

const props = defineProps({ id: String });
const route = useRoute();
const pet = ref(null);
const interestSnackbar = ref(route.query.interest === "success");
const messages = ref([]),
    messageDraft = ref(""),
    chatLoading = ref(false);
const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";
const headers = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-CSRF-TOKEN": csrf,
};
const shelterLocation = computed(() =>
    pet.value?.shelter
        ? `${pet.value.shelter.district || pet.value.shelter.city}, ${pet.value.shelter.city} - ${pet.value.shelter.state}`
        : "Sede não informada",
);
const interestedLabel = computed(
    () =>
        `${pet.value?.adoptions_count || 0} ${pet.value?.adoptions_count === 1 ? "pessoa interessada" : "pessoas interessadas"}`,
);
function latestInterest(value) {
    return value
        ? new Intl.DateTimeFormat("pt-BR", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "";
}
async function toggleLike() {
    const response = await fetch(`/api/pets/${pet.value.id}/likes`, {
        method: "POST",
        credentials: "same-origin",
        headers,
    });
    if (!response.ok) return;
    const data = await response.json();
    pet.value.is_liked = data.liked;
    pet.value.likes_count = data.likes_count;
}
async function loadConversation() {
    chatLoading.value = true;
    const response = await fetch(`/api/pets/${pet.value.id}/messages`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) messages.value = (await response.json()).data;
    chatLoading.value = false;
}
async function sendMessage() {
    if (!messageDraft.value.trim()) return;
    const response = await fetch(`/api/pets/${pet.value.id}/messages`, {
        method: "POST",
        credentials: "same-origin",
        headers,
        body: JSON.stringify({ body: messageDraft.value }),
    });
    if (!response.ok) return;
    messages.value.push((await response.json()).data);
    messageDraft.value = "";
}
onMounted(async () => {
    pet.value = (await (await fetch(`/api/pets/${props.id}`)).json()).data;
    loadConversation();
});
</script>

<template>
    <v-snackbar
        v-model="interestSnackbar"
        color="success"
        :timeout="3000"
        location="top"
        rounded="lg"
        >Interesse marcado! Você pode acompanhá-lo na sua Visão geral.<template
            #actions
            ><v-btn variant="text" @click="interestSnackbar = false"
                >Fechar</v-btn
            ></template
        ></v-snackbar
    >
    <v-container v-if="pet" class="py-10">
        <v-btn to="/" variant="text" prepend-icon="mdi-arrow-left"
            >Voltar aos pets</v-btn
        >
        <v-row class="mt-3">
            <v-col cols="12" md="7"
                ><PetGallery
                    :species="pet.species"
                    :photos="[pet.image_url, ...(pet.gallery_urls || [])]"
                /><v-card
                    v-if="pet.shelter"
                    class="mt-5 pa-5"
                    rounded="xl"
                    variant="tonal"
                    color="primary"
                    ><div class="d-flex align-center ga-3">
                        <v-avatar color="primary" variant="flat"
                            ><v-icon icon="mdi-home-heart"
                        /></v-avatar>
                        <div>
                            <div class="font-weight-bold">
                                {{ pet.shelter.name }}
                            </div>
                            <div class="text-body-2">
                                Sede de acolhimento: {{ shelterLocation }}
                            </div>
                        </div>
                    </div></v-card
                ></v-col
            >
            <v-col cols="12" md="5">
                <v-chip color="success">Disponível para adoção</v-chip
                ><v-chip
                    v-if="pet.is_interested"
                    color="secondary"
                    class="ml-2"
                    prepend-icon="mdi-heart"
                    >Seu interesse está marcado</v-chip
                >
                <h1 class="text-h3 font-weight-black mt-4">{{ pet.name }}</h1>
                <p class="text-h6 text-medium-emphasis">
                    {{ pet.species === "cat" ? "Gato" : "Cachorro" }} ·
                    {{ pet.age_label }} · Porte {{ pet.size }}
                </p>
                <v-divider class="my-6" />
                <p class="text-body-1">{{ pet.description }}</p>
                <v-list class="bg-transparent mt-4"
                    ><v-list-item
                        prepend-icon="mdi-map-marker"
                        :title="pet.city"
                        subtitle="Localização informada do pet" /><v-list-item
                        v-if="pet.shelter"
                        prepend-icon="mdi-home-map-marker"
                        :title="shelterLocation"
                        :subtitle="`Sede de acolhimento: ${pet.shelter.name}`" /><v-list-item
                        prepend-icon="mdi-paw"
                        :title="pet.temperament"
                        subtitle="Temperamento" /><v-list-item
                        prepend-icon="mdi-shield-check"
                        title="Adoção com análise de perfil"
                        subtitle="A ONG entrará em contato para agendar uma visita"
                /></v-list>
                <v-card
                    v-if="pet.adoptions_count"
                    class="pa-4 mb-4"
                    rounded="lg"
                    variant="tonal"
                    color="secondary"
                    ><div class="d-flex align-center ga-3">
                        <v-avatar color="secondary" size="42"
                            ><v-icon icon="mdi-account-heart-outline"
                        /></v-avatar>
                        <div>
                            <div class="font-weight-bold">
                                {{ interestedLabel }}
                            </div>
                            <div class="text-caption">
                                {{
                                    pet.adoptions_count === 1
                                        ? "Uma pessoa já demonstrou interesse."
                                        : "Outras famílias também estáo conhecendo este pet."
                                }}
                            </div>
                            <div
                                v-if="pet.latest_interest_at"
                                class="text-caption mt-1"
                            >
                                Último interesse registrado em
                                {{ latestInterest(pet.latest_interest_at) }}.
                            </div>
                        </div>
                    </div></v-card
                >
                <div class="d-flex ga-2 mb-4">
                    <v-btn
                        :color="pet.is_liked ? 'error' : 'primary'"
                        :prepend-icon="
                            pet.is_liked ? 'mdi-heart' : 'mdi-heart-outline'
                        "
                        variant="tonal"
                        @click="toggleLike"
                        >{{ pet.likes_count || 0 }} curtidas</v-btn
                    ><v-btn
                        v-if="pet.shelter"
                        :to="'/perfil'"
                        variant="text"
                        prepend-icon="mdi-account-circle-outline"
                        >Ver perfil da ONG</v-btn
                    >
                </div>
                <v-alert
                    v-if="pet.is_interested"
                    type="success"
                    variant="tonal"
                    class="mb-4"
                    rounded="lg"
                    >Você já demonstrou interesse por {{ pet.name }}. Acompanhe
                    atualizações, visitas e a fila no seu painel.</v-alert
                >
                <v-btn
                    v-if="pet.is_interested"
                    to="/painel"
                    color="secondary"
                    size="large"
                    block
                    rounded="lg"
                    prepend-icon="mdi-heart"
                    >Acompanhar meu interesse</v-btn
                ><v-btn
                    v-else
                    :to="`/adotar/${pet.id}`"
                    color="primary"
                    size="large"
                    block
                    rounded="lg"
                    >Quero adotar {{ pet.name }}</v-btn
                >
            </v-col>
        </v-row>
        <v-card class="conversation-card mt-7" rounded="xl" variant="outlined">
            <div class="pa-5 pb-3 d-flex align-center ga-3">
                <v-avatar color="primary" variant="tonal">
                    <v-icon icon="mdi-message-text-outline" />
                </v-avatar>
                <div>
                    <h2 class="text-h6 font-weight-bold">
                        Conversa da comunidade
                    </h2>
                    <p class="text-body-2 text-medium-emphasis">
                        Compartilhe experiências e tire dúvidas sobre
                        {{ pet.name }}.
                    </p>
                </div>
            </div>
            <v-divider />
            <v-card-text class="pa-5">
                <div v-if="chatLoading" class="text-center py-6">
                    <v-progress-circular indeterminate color="primary" />
                </div>
                <div v-else class="chat-list">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="chat-message"
                    >
                        <v-avatar size="38" color="primary">
                            <v-img
                                v-if="message.user.avatar_url"
                                :src="message.user.avatar_url"
                                cover
                            />
                            <span v-else>{{ message.user.name?.[0] }}</span>
                        </v-avatar>
                        <div class="chat-content">
                            <div class="d-flex align-center flex-wrap ga-2">
                                <b>{{ message.user.name }}</b>
                                <span class="chat-date">{{
                                    new Date(message.created_at).toLocaleString(
                                        "pt-BR",
                                    )
                                }}</span>
                            </div>
                            <p>{{ message.body }}</p>
                        </div>
                    </div>
                    <p
                        v-if="!messages.length"
                        class="text-medium-emphasis py-3"
                    >
                        Ainda não há comentários sobre este pet. Seja a primeira
                        pessoa a participar.
                    </p>
                </div>
                <div class="comment-composer mt-5">
                    <v-avatar color="primary" size="40"
                        ><v-icon icon="mdi-account"
                    /></v-avatar>
                    <v-textarea
                        v-model="messageDraft"
                        hide-details
                        rows="2"
                        auto-grow
                        variant="outlined"
                        label="Escreva um comentário"
                        @keydown.ctrl.enter.prevent="sendMessage"
                    />
                    <v-btn
                        icon="mdi-send"
                        color="primary"
                        size="large"
                        @click="sendMessage"
                    />
                </div>
            </v-card-text>
        </v-card>
    </v-container>
    <v-container v-else class="py-16 text-center"
        ><v-progress-circular indeterminate color="primary"
    /></v-container>
</template>

<style scoped>
.chat-list {
    display: grid;
    gap: 10px;
    max-height: 420px;
    overflow: auto;
}
.chat-message {
    display: flex;
    gap: 10px;
    padding: 12px;
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.05);
}
.chat-content {
    display: grid;
    gap: 3px;
}
.chat-date {
    font-size: 0.72rem;
    color: rgba(var(--v-theme-on-surface), 0.55);
}
.chat-message p {
    margin: 5px 0 0;
}
.comment-composer {
    display: flex;
    align-items: flex-end;
    gap: 12px;
}
.comment-composer .v-textarea {
    flex: 1;
}
@media (max-width: 600px) {
    .comment-composer {
        align-items: flex-start;
    }
}
</style>
