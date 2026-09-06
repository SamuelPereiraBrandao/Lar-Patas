<script setup>
import * as Ably from "ably";
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import FavoriteButton from "../../components/pets/FavoriteButton.vue";
import { petStatusLabel } from "../../stores/pets";
import PetHealthDialog from "../../components/pets/PetHealthDialog.vue";
import PetGallery from "../../components/pets/PetGallery.vue";
import { notify, userAvatar, userName } from "../../stores/ui";

const props = defineProps({ id: String });
const route = useRoute();
const pet = ref(null);
const healthOpen = ref(false);
const confirmedOwners = computed(() => [
    ...new Map(
        [pet.value?.owner, ...(pet.value?.caretakers || [])]
            .filter(Boolean)
            .map((owner) => [owner.id, owner]),
    ).values(),
]);
const respondingOwner = ref(false);
const petUnavailable = ref(false);
async function respondOwner(accept) {
    respondingOwner.value = true;
    try {
        const response = await fetch(
            `/api/profile/pets/${pet.value.id}/owner-request`,
            {
                method: "PATCH",
                credentials: "same-origin",
                headers,
                body: JSON.stringify({ accept }),
            },
        );
        if (!response.ok) throw new Error();
        pet.value.has_pending_owner_request = false;
        window.dispatchEvent(new Event("notifications:read"));
        window.dispatchEvent(new Event("pets:changed"));
        notify(
            accept
                ? "Convite aceito! O pet agora aparece no seu perfil."
                : "Convite recusado.",
        );
    } catch {
        notify("Não foi possível responder ao convite.", "error");
    } finally {
        respondingOwner.value = false;
    }
}
const messages = ref([]),
    messageDraft = ref(""),
    chatLoading = ref(false),
    commentsPage = ref(1),
    hasMoreComments = ref(false),
    loadingMoreComments = ref(false);
const profileSummaries = reactive({});
let realtime;
function sizeLabel(size) {
    return { small: "pequeno", medium: "médio", large: "grande" }[size] || size;
}
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
const backTarget = computed(() =>
    route.query.from === "favorites"
        ? "/favoritos"
        : route.query.from === "my-pets"
          ? "/meus-pets"
          : route.query.from === "profile"
            ? `/perfil/${route.query.profile}`
            : "/pets",
);
const backLabel = computed(() =>
    route.query.from === "favorites"
        ? "Voltar aos favoritos"
        : route.query.from === "my-pets"
          ? "Voltar aos meus pets"
          : route.query.from === "profile"
            ? "Voltar ao perfil"
            : "Voltar aos pets",
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
async function loadConversation(page = 1, append = false) {
    chatLoading.value = true;
    const response = await fetch(
        `/api/pets/${pet.value.id}/messages?page=${page}`,
        {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        },
    );
    if (response.ok) {
        const data = await response.json();
        messages.value = append ? [...data.data, ...messages.value] : data.data;
        commentsPage.value = data.pagination?.current_page || page;
        hasMoreComments.value = !!data.pagination?.has_more;
    }
    chatLoading.value = false;
}
async function loadMoreComments() {
    if (!hasMoreComments.value || loadingMoreComments.value) return;
    loadingMoreComments.value = true;
    await loadConversation(commentsPage.value + 1, true);
    loadingMoreComments.value = false;
}
async function loadProfileSummary(userId) {
    if (!userId || profileSummaries[userId]) return;
    const response = await fetch(`/api/users/${userId}/profile`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) profileSummaries[userId] = await response.json();
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
    const message = (await response.json()).data;
    if (!messages.value.some((item) => item.id === message.id)) {
        messages.value.push(message);
    }
    messageDraft.value = "";
}
function connectRealtime() {
    if (!pet.value?.id) return;
    realtime = new Ably.Realtime({
        authUrl: "/api/realtime/token",
        authMethod: "GET",
    });
    const channel = realtime.channels.get(`pet:${pet.value.id}:messages`);
    channel.subscribe("message:created", (event) => {
        const message = event.data;
        if (
            message?.id &&
            !messages.value.some((item) => item.id === message.id)
        ) {
            messages.value.push(message);
        }
    });
}
watch(
    () => props.id,
    async (id) => {
        realtime?.close();
        pet.value = null;
        messages.value = [];
        petUnavailable.value = false;
        const response = await fetch(`/api/pets/${id}`, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        }).catch(() => null);
        if (id !== props.id) return;
        if (!response?.ok) {
            petUnavailable.value = true;
            return;
        }
        pet.value = (await response.json()).data;
        loadConversation();
        connectRealtime();
        if (route.query.interest === "success") {
            notify(
                "Interesse marcado! Você pode acompanhá-lo na sua Visão geral.",
            );
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => realtime?.close());
</script>

<template>
    <v-container v-if="pet" class="py-10">
        <v-btn :to="backTarget" variant="text" prepend-icon="mdi-arrow-left">{{
            backLabel
        }}</v-btn>
        <v-card
            v-if="pet.has_pending_owner_request"
            rounded="xl"
            color="primary"
            variant="tonal"
            class="pa-5 mt-4"
        >
            <div class="text-h6">Convite para ser dono de {{ pet.name }}</div>
            <p class="mt-2">
                Ao aceitar, este pet aparecerá no seu perfil e você poderá
                editar os dados e as fotos dele.
            </p>
            <div class="d-flex flex-wrap ga-2 mt-4">
                <v-btn
                    color="primary"
                    variant="flat"
                    :disabled="respondingOwner"
                    @click="respondOwner(true)"
                    >Aceitar convite</v-btn
                ><v-btn
                    variant="outlined"
                    :disabled="respondingOwner"
                    @click="respondOwner(false)"
                    >Recusar</v-btn
                >
            </div>
        </v-card>
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
                <v-chip
                    :color="
                        pet.ownership_kind === 'guardian'
                            ? 'primary'
                            : 'success'
                    "
                    >{{ petStatusLabel(pet) }}</v-chip
                ><v-chip
                    v-if="pet.is_interested"
                    color="secondary"
                    class="ml-2"
                    prepend-icon="mdi-heart"
                    >Seu interesse está marcado</v-chip
                >
                <h1 class="text-h3 font-weight-black mt-4">{{ pet.name }}</h1>
                <div class="d-flex ga-3 align-center my-4">
                    <FavoriteButton :pet="pet" /><v-btn
                        v-if="pet.can_view_health"
                        variant="tonal"
                        prepend-icon="mdi-medical-bag"
                        @click="healthOpen = true"
                        >Ficha de saúde</v-btn
                    >
                </div>
                <p class="text-h6 text-medium-emphasis">
                    {{ pet.species === "cat" ? "Gato" : "Cachorro" }} ·
                    {{ pet.age_label }} · Porte {{ sizeLabel(pet.size) }}
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
                        v-if="pet.can_adopt"
                        prepend-icon="mdi-shield-check"
                        title="Adoção com análise de perfil"
                        subtitle="A ONG entrará em contato para agendar uma visita"
                /></v-list>
                <v-menu
                    v-for="owner in pet.ownership_kind === 'guardian' ||
                    pet.status === 'adopted'
                        ? confirmedOwners
                        : []"
                    :key="owner.id"
                    open-on-hover
                    open-on-click
                    location="bottom start"
                    :close-delay="120"
                    @update:model-value="
                        (open) => open && loadProfileSummary(owner.id)
                    "
                    ><template #activator="{ props: menuProps }"
                        ><v-card
                            v-bind="menuProps"
                            class="owner-card pa-4 mb-4 cursor-pointer"
                            rounded="lg"
                            variant="tonal"
                            color="primary"
                            ><div class="d-flex align-center ga-3">
                                <v-avatar size="42" color="primary"
                                    ><v-img
                                        v-if="
                                            owner.avatar_url ||
                                            owner.avatar_path
                                        "
                                        :src="
                                            owner.avatar_url ||
                                            `/storage/${owner.avatar_path}`
                                        "
                                        cover
                                    /><span v-else>{{
                                        owner.name?.[0]
                                    }}</span></v-avatar
                                >
                                <div>
                                    <div class="text-caption">Dono</div>
                                    <b>{{ owner.name }}</b>
                                </div>
                                <v-spacer /><v-icon>mdi-chevron-right</v-icon>
                            </div></v-card
                        ></template
                    ><v-card class="profile-popover pa-3" rounded="xl"
                        ><div class="d-flex align-center ga-3">
                            <v-avatar size="42" color="primary"
                                ><v-img
                                    v-if="owner.avatar_url || owner.avatar_path"
                                    :src="
                                        owner.avatar_url ||
                                        `/storage/${owner.avatar_path}`
                                    "
                                    cover
                            /></v-avatar>
                            <div>
                                <b>{{ owner.name }}</b>
                                <div class="text-caption">
                                    {{
                                        profileSummaries[owner.id]?.profile
                                            ?.city || owner.city
                                    }}
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="profileSummaries[owner.id]?.stats"
                            class="text-caption mt-2"
                        >
                            <b>{{
                                profileSummaries[owner.id].stats.adoptions
                            }}</b>
                            adoções ·
                            <b>{{ profileSummaries[owner.id].stats.posts }}</b>
                            publicações
                        </div>
                        <v-btn
                            :to="`/perfil/${owner.id}`"
                            block
                            size="small"
                            color="primary"
                            variant="tonal"
                            class="mt-3"
                            >Ver perfil</v-btn
                        ></v-card
                    ></v-menu
                >
                <v-card
                    v-if="
                        pet.ownership_kind !== 'guardian' &&
                        pet.status !== 'adopted' &&
                        pet.adoptions_count
                    "
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
                    v-if="
                        pet.ownership_kind !== 'guardian' && pet.is_interested
                    "
                    type="success"
                    variant="tonal"
                    class="mb-4"
                    rounded="lg"
                    >Você já demonstrou interesse por {{ pet.name }}. Acompanhe
                    atualizações, visitas e a fila no seu painel.</v-alert
                >
                <v-btn
                    v-if="
                        pet.ownership_kind !== 'guardian' && pet.is_interested
                    "
                    to="/painel"
                    color="secondary"
                    size="large"
                    block
                    rounded="lg"
                    prepend-icon="mdi-heart"
                    >Acompanhar meu interesse</v-btn
                ><v-btn
                    v-else-if="pet.can_adopt"
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
                    <v-btn
                        v-if="hasMoreComments"
                        :loading="loadingMoreComments"
                        variant="text"
                        color="primary"
                        prepend-icon="mdi-chevron-up"
                        @click="loadMoreComments"
                        >Carregar comentários anteriores</v-btn
                    >
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
                        <div
                            class="chat-content"
                            @mouseenter="loadProfileSummary(message.user.id)"
                        >
                            <div class="d-flex align-center flex-wrap ga-2">
                                <v-menu
                                    open-on-hover
                                    location="bottom start"
                                    :open-delay="180"
                                    :close-delay="120"
                                >
                                    <template #activator="{ props }">
                                        <a
                                            v-bind="props"
                                            class="comment-author"
                                            :href="`/perfil/${message.user.id}`"
                                            >{{ message.user.name }}</a
                                        >
                                    </template>
                                    <v-card
                                        class="profile-popover pa-4"
                                        rounded="xl"
                                    >
                                        <div class="d-flex align-center ga-3">
                                            <v-avatar color="primary" size="48">
                                                <v-img
                                                    v-if="
                                                        profileSummaries[
                                                            message.user.id
                                                        ]?.profile.avatar_url
                                                    "
                                                    :src="
                                                        profileSummaries[
                                                            message.user.id
                                                        ].profile.avatar_url
                                                    "
                                                    cover
                                                />
                                                <span v-else>{{
                                                    message.user.name?.[0]
                                                }}</span>
                                            </v-avatar>
                                            <div>
                                                <b>{{ message.user.name }}</b>
                                                <div class="text-caption">
                                                    {{
                                                        profileSummaries[
                                                            message.user.id
                                                        ]?.profile.city ||
                                                        message.user.city ||
                                                        "Localidade não informada"
                                                    }}{{
                                                        profileSummaries[
                                                            message.user.id
                                                        ]?.profile.state ||
                                                        message.user.state
                                                            ? ` · ${profileSummaries[message.user.id]?.profile.state || message.user.state}`
                                                            : ""
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-if="
                                                profileSummaries[
                                                    message.user.id
                                                ]
                                            "
                                            class="d-flex ga-4 mt-3 text-caption"
                                        >
                                            <span
                                                ><b>{{
                                                    profileSummaries[
                                                        message.user.id
                                                    ].stats.interests
                                                }}</b>
                                                interesses</span
                                            >
                                            <span
                                                ><b>{{
                                                    profileSummaries[
                                                        message.user.id
                                                    ].stats.adoptions
                                                }}</b>
                                                adoções</span
                                            >
                                        </div>
                                        <v-btn
                                            :to="`/perfil/${message.user.id}`"
                                            color="primary"
                                            variant="tonal"
                                            block
                                            class="mt-3"
                                            >Ver perfil</v-btn
                                        >
                                    </v-card>
                                </v-menu>
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
                    <v-avatar color="primary" size="40">
                        <v-img v-if="userAvatar" :src="userAvatar" cover />
                        <span v-else>{{ userName?.[0] || "A" }}</span>
                    </v-avatar>
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
        <PetHealthDialog v-model="healthOpen" :pet="pet" />
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
.comment-author {
    color: inherit;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}
.comment-author:hover {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
}
.profile-popover {
    width: 280px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
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
