<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import { useRoute, useRouter } from "vue-router";
import { request } from "../../stores/requests";
import { petStatusLabel } from "../../stores/pets";
const router = useRouter();
const removeFriendOpen = ref(false);
const removingFriend = ref(false);
async function confirmRemoveFriend() {
    if (removingFriend.value) return;
    removingFriend.value = true;
    try {
        await request(
            `/api/users/${profile.value.id}/friend-requests`,
            "DELETE",
        );
        friendRequestSent.value = false;
        friendshipStatus.value = null;
        removeFriendOpen.value = false;
        window.dispatchEvent(new Event("friendship:changed"));
        notify("Amizade removida.");
    } catch (error) {
        notify(error.message, "error");
    } finally {
        removingFriend.value = false;
    }
}
const blockedProfile = ref(null);
const profileUnavailable = ref(false);
const unblocking = ref(false);
async function unblockProfile() {
    if (unblocking.value || !blockedProfile.value) return;
    unblocking.value = true;
    try {
        await request(
            `/api/safety/blocks/${blockedProfile.value.id}`,
            "DELETE",
        );
        await load();
        notify(
            "Usuário desbloqueado. A amizade não é restaurada automaticamente.",
        );
    } catch (error) {
        notify(error.message, "error");
    } finally {
        unblocking.value = false;
    }
}
const blockOpen = ref(false),
    blocking = ref(false);
async function blockUser() {
    blocking.value = true;
    try {
        await request("/api/safety/blocks/" + profile.value.id, "PUT");
        blockOpen.value = false;
        window.dispatchEvent(new Event("friendship:changed"));
        notify("Usuário bloqueado.");
        router.push("/privacidade");
    } catch (error) {
        notify(error.message, "error");
    } finally {
        blocking.value = false;
    }
}
import { notify, openChat, setSession } from "../../stores/ui";
import ProfileEditDialog from "./profile/ProfileEditDialog.vue";
import ImageViewerDialog from "./profile/ImageViewerDialog.vue";
import ProfilePostCard from "./profile/ProfilePostCard.vue";
import PostComposer from "./profile/PostComposer.vue";

const props = defineProps({
    userId: { type: [String, Number], default: null },
});
const route = useRoute();
const friendshipId = ref(null),
    respondingFriend = ref(false);
const viewerPosts = computed(() =>
    viewerPost.value &&
    !posts.value.some((post) => post.id === viewerPost.value.id)
        ? [viewerPost.value, ...posts.value]
        : posts.value,
);
let postRequestVersion = 0;
async function openNotifiedPost() {
    const id = route.query.post;
    const version = ++postRequestVersion;
    if (!id) return;
    try {
        const response = await fetch(
            `/api/profile/posts/${encodeURIComponent(id)}`,
            {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            },
        );
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (version !== postRequestVersion) return;
        viewerOpen.value = false;
        viewerPost.value = result.data;
        await nextTick();
        viewerOpen.value = true;
    } catch {
        if (version === postRequestVersion)
            notify("Esta postagem não está mais disponível.", "error");
    }
}
watch(() => [route.query.post, route.query.notification], openNotifiedPost);
async function respondFriend(status) {
    respondingFriend.value = true;
    try {
        const response = await fetch(
            `/api/friend-requests/${friendshipId.value}`,
            {
                method: "PATCH",
                credentials: "same-origin",
                headers: jsonHeaders,
                body: JSON.stringify({ status }),
            },
        );
        if (!response.ok) throw new Error();
        await load();
        window.dispatchEvent(new Event("notifications:read"));
        notify(
            status === "accepted"
                ? "Solicitação aceita."
                : "Solicitação recusada.",
        );
    } catch {
        notify("Não foi possível responder à solicitação.", "error");
    } finally {
        respondingFriend.value = false;
    }
}

const profile = ref(null),
    posts = ref([]),
    adoptedPets = ref([]),
    stats = ref({ pets: 0, posts: 0 }),
    locations = ref([]),
    editOpen = ref(false),
    viewerOpen = ref(false),
    viewerPost = ref(null),
    viewerTitle = ref(""),
    petPostFilter = ref(null),
    page = ref(1),
    hasMore = ref(false),
    loadingMore = ref(false),
    canEdit = ref(!props.userId),
    friendRequestSent = ref(false),
    friendshipStatus = ref(null),
    feedEnd = ref(null);
let feedObserver;
const commentDrafts = reactive({});
const filteredPosts = computed(() =>
    petPostFilter.value
        ? posts.value.filter((post) => post.pet_id === petPostFilter.value)
        : posts.value,
);
const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";
const jsonHeaders = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-CSRF-TOKEN": csrf,
};
const formHeaders = { Accept: "application/json", "X-CSRF-TOKEN": csrf };
const initials = computed(() =>
    (profile.value?.name || "A")
        .split(" ")
        .map((word) => word[0])
        .slice(0, 2)
        .join("")
        .toUpperCase(),
);
function openImagePost(url, title) {
    if (!url) return;
    viewerPost.value = posts.value.find((post) => post.image_url === url) || {
        image_url: url,
        body: "",
        comments: [],
    };
    viewerTitle.value = title;
    viewerOpen.value = true;
}
function openPost(post) {
    viewerPost.value = post;
    viewerTitle.value = "";
    viewerOpen.value = true;
}
function say(message, type = "success") {
    notify(message, type);
}
const friendActionLabel = computed(() => {
    if (friendshipStatus.value === "accepted") return "Remover amizade";
    if (friendshipStatus.value === "sent")
        return "Cancelar solicitação de amizade";
    return "Adicionar amigo";
});
const friendActionIcon = computed(() =>
    friendshipStatus.value === "accepted" || friendshipStatus.value === "sent"
        ? "mdi-close-circle-outline"
        : "mdi-account-plus-outline",
);
const friendActionColor = computed(() =>
    friendshipStatus.value === "accepted" || friendshipStatus.value === "sent"
        ? "error"
        : "primary",
);
async function addFriend() {
    const response = await fetch(
        `/api/users/${profile.value.id}/friend-requests`,
        { method: "POST", credentials: "same-origin", headers: jsonHeaders },
    );
    notify(
        response.ok
            ? "Solicitação de amizade enviada."
            : "Não foi possível enviar a solicitação.",
        response.ok ? "success" : "error",
    );
    if (response.ok) {
        friendRequestSent.value = true;
        friendshipStatus.value = "sent";
    }
}
async function cancelFriendRequest() {
    const response = await fetch(
        `/api/users/${profile.value.id}/friend-requests`,
        { method: "DELETE", credentials: "same-origin", headers: jsonHeaders },
    );
    if (response.ok) {
        friendRequestSent.value = false;
        friendshipStatus.value = null;
        notify("Solicitação de amizade cancelada.");
    }
}
async function load() {
    blockedProfile.value = null;
    profileUnavailable.value = false;
    const socialUrl = props.userId
        ? `/api/users/${props.userId}/profile?page=1`
        : "/api/profile/social?page=1";
    const [social, location] = await Promise.all([
        fetch(socialUrl, {
            credentials: "same-origin",
            headers: formHeaders,
        }),
        fetch("/api/locations", {
            credentials: "same-origin",
            headers: formHeaders,
        }),
    ]);
    if (!social.ok) {
        profile.value = null;
        profileUnavailable.value = true;
        if (props.userId && [403, 404].includes(social.status)) {
            try {
                const result = await request("/api/safety/blocks");
                blockedProfile.value =
                    result.data.find(
                        (user) => String(user.id) === String(props.userId),
                    ) || null;
            } catch {
                // Keep the generic unavailable state when block information cannot be loaded.
            }
        }
        return;
    }
    const data = await social.json();
    profile.value = data.profile;
    posts.value = data.posts;
    page.value = data.pagination?.current_page || 1;
    hasMore.value = !!data.pagination?.has_more;
    adoptedPets.value = data.adopted_pets;
    stats.value = data.stats;
    canEdit.value = data.is_owner ?? !props.userId;
    friendshipStatus.value = data.friendship_status;
    friendshipId.value = data.friendship_id;
    friendRequestSent.value = friendshipStatus.value === "sent";
    if (location.ok) locations.value = (await location.json()).data;
}
async function loadMore() {
    if (!hasMore.value || loadingMore.value) return;
    loadingMore.value = true;
    const endpoint = props.userId
        ? `/api/users/${props.userId}/profile?page=${page.value + 1}`
        : `/api/profile/social?page=${page.value + 1}`;
    const response = await fetch(endpoint, {
        credentials: "same-origin",
        headers: formHeaders,
    });
    if (response.ok) {
        const data = await response.json();
        posts.value.push(...data.posts);
        page.value = data.pagination?.current_page || page.value + 1;
        hasMore.value = !!data.pagination?.has_more;
    }
    loadingMore.value = false;
}
function postPublished(post) {
    posts.value.unshift(post);
    stats.value.posts++;
}
async function comment(post, value = null) {
    const body = value || commentDrafts[post.id]?.trim();
    if (!body) return;
    const response = await fetch(`/api/profile/posts/${post.id}/comments`, {
        method: "POST",
        credentials: "same-origin",
        headers: jsonHeaders,
        body: JSON.stringify({ body }),
    });
    const data = await response.json();
    if (!response.ok) return say(data.message || "Não foi possível comentar.");
    post.comments.push(data.comment);
    commentDrafts[post.id] = "";
}
function saved(user) {
    profile.value = user;
    setSession(user);
    load();
}
onMounted(async () => {
    window.addEventListener("pets:changed", load);
    await load();
    await nextTick();
    await openNotifiedPost();
    feedObserver = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) loadMore();
        },
        { rootMargin: "240px" },
    );
    if (feedEnd.value) feedObserver.observe(feedEnd.value);
});
onBeforeUnmount(() => {
    feedObserver?.disconnect();
    window.removeEventListener("pets:changed", load);
});
</script>

<template>
    <v-container class="py-8" style="max-width: 1040px">
        <v-card
            v-if="blockedProfile || profileUnavailable"
            rounded="xl"
            elevation="0"
            class="border pa-6 pa-md-10 text-center"
        >
            <v-avatar color="primary" variant="tonal" size="72" class="mb-4"
                ><v-icon
                    :icon="
                        blockedProfile
                            ? 'mdi-account-cancel-outline'
                            : 'mdi-account-question-outline'
                    "
                    size="36"
            /></v-avatar>
            <h1 class="text-h5 font-weight-bold mb-3">
                {{
                    blockedProfile
                        ? "Você bloqueou este usuário"
                        : "Perfil indisponível"
                }}
            </h1>
            <p class="text-body-1 text-medium-emphasis mb-6">
                {{
                    blockedProfile
                        ? `Desbloqueie ${blockedProfile.name} para visualizar o perfil e permitir novas interações. A amizade não será restaurada automaticamente.`
                        : "Não foi possível abrir este perfil. Ele pode estar indisponível ou você não tem acesso."
                }}
            </p>
            <div class="d-flex flex-wrap justify-center ga-3">
                <v-btn variant="text" to="/">Voltar ao início</v-btn>
                <v-btn
                    v-if="blockedProfile"
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-account-check-outline"
                    :loading="unblocking"
                    @click="unblockProfile"
                    >Desbloquear usuário</v-btn
                >
            </div>
        </v-card>
        <template v-if="profile">
            <section class="profile-hero mb-7">
                <div
                    class="banner banner-clickable"
                    :style="
                        profile.banner_url
                            ? { backgroundImage: `url(${profile.banner_url})` }
                            : {}
                    "
                    @click="openImagePost(profile.banner_url, 'Imagem de capa')"
                />
                <div class="hero-content px-5 px-md-8 pb-6">
                    <v-avatar
                        class="avatar"
                        size="112"
                        :class="{ 'avatar-clickable': profile.avatar_url }"
                        @click="
                            openImagePost(profile.avatar_url, 'Foto de perfil')
                        "
                        ><v-img
                            v-if="profile.avatar_url"
                            :src="profile.avatar_url"
                            cover
                        /><span v-else>{{ initials }}</span></v-avatar
                    >
                    <div class="heading">
                        <div>
                            <h1>{{ profile.name }}</h1>
                            <p>
                                {{
                                    profile.city
                                        ? `${profile.city}${profile.state ? `, ${profile.state}` : ""}`
                                        : "Perfil de adotante"
                                }}
                            </p>
                        </div>
                        <div class="profile-actions">
                            <v-btn
                                v-if="canEdit"
                                color="primary"
                                prepend-icon="mdi-pencil"
                                @click="editOpen = true"
                                >Editar perfil</v-btn
                            >
                            <v-btn
                                v-else-if="friendshipStatus === 'accepted'"
                                color="primary"
                                prepend-icon="mdi-message-text"
                                variant="flat"
                                @click="openChat(profile)"
                                >Mensagem</v-btn
                            >
                            <div
                                v-else-if="friendshipStatus === 'received'"
                                class="d-flex ga-2"
                            >
                                <v-btn
                                    color="primary"
                                    :disabled="respondingFriend"
                                    @click="respondFriend('accepted')"
                                    >Aceitar amizade</v-btn
                                >
                                <v-btn
                                    variant="tonal"
                                    :disabled="respondingFriend"
                                    @click="respondFriend('rejected')"
                                    >Recusar</v-btn
                                >
                            </div>
                            <v-btn
                                v-else
                                :color="friendActionColor"
                                :prepend-icon="friendActionIcon"
                                @click="
                                    friendshipStatus
                                        ? cancelFriendRequest()
                                        : addFriend()
                                "
                                >{{ friendActionLabel }}</v-btn
                            >
                            <v-menu v-if="!canEdit" location="bottom end">
                                <template #activator="{ props: menuProps }">
                                    <v-btn
                                        v-bind="menuProps"
                                        icon="mdi-dots-horizontal"
                                        variant="tonal"
                                        color="primary"
                                        rounded="lg"
                                        size="40"
                                        aria-label="Mais opções do perfil"
                                    />
                                </template>
                                <v-list
                                    rounded="lg"
                                    elevation="3"
                                    min-width="240"
                                    class="pa-2"
                                >
                                    <v-list-item
                                        v-if="friendshipStatus === 'accepted'"
                                        prepend-icon="mdi-account-minus-outline"
                                        title="Remover amizade"
                                        rounded="lg"
                                        @click="removeFriendOpen = true"
                                    />
                                    <v-list-item
                                        prepend-icon="mdi-account-cancel-outline"
                                        title="Bloquear usuário"
                                        base-color="error"
                                        rounded="lg"
                                        @click="blockOpen = true"
                                    />
                                </v-list>
                            </v-menu>
                        </div>
                    </div>
                    <p v-if="profile.household_description" class="bio">
                        {{ profile.household_description }}
                    </p>
                    <div class="stats">
                        <div>
                            <b>{{ stats.pets }}</b
                            ><span>pets que possui</span>
                        </div>
                        <div>
                            <b>{{ stats.posts }}</b
                            ><span>publicações</span>
                        </div>
                    </div>
                </div>
            </section>
            <v-row
                ><v-col cols="12" md="7"
                    ><PostComposer
                        v-if="canEdit"
                        class="mb-5"
                        :author="profile"
                        :pets="adoptedPets"
                        @published="postPublished" />
                    <v-select
                        v-if="adoptedPets.length"
                        v-model="petPostFilter"
                        :items="adoptedPets"
                        item-title="name"
                        item-value="id"
                        label="Filtrar publicações por pet"
                        clearable
                        density="compact"
                        variant="outlined"
                        class="mb-4" />
                    <ProfilePostCard
                        v-for="post in filteredPosts"
                        :key="post.id"
                        :post="post"
                        :author="profile"
                        :editable="canEdit"
                        @changed="load"
                        @comment="comment"
                        @view="openPost" />
                    <v-card
                        v-if="!posts.length"
                        rounded="xl"
                        class="text-center py-10"
                        ><v-icon size="42" color="primary"
                            >mdi-image-text</v-icon
                        >
                        <h3 class="mt-3">Seu mural começa aqui</h3>
                        <p class="text-medium-emphasis">
                            Publique fotos e memórias com seus pets.
                        </p></v-card
                    >
                    <div ref="feedEnd" class="feed-end py-5 text-center">
                        <v-progress-circular
                            v-if="loadingMore"
                            indeterminate
                            color="primary"
                            size="24"
                        /></div
                ></v-col>
                <v-col cols="12" md="5"
                    ><v-card
                        rounded="xl"
                        class="mb-5 profile-pets"
                        elevation="0"
                    >
                        <div class="d-flex align-center ga-3 pa-5 pb-4">
                            <v-avatar
                                color="primary"
                                variant="tonal"
                                rounded="lg"
                                size="40"
                                ><v-icon icon="mdi-paw"
                            /></v-avatar>
                            <div class="profile-pets-heading">
                                <h2>Companheiros de vida</h2>
                                <p>Os pets de {{ profile.name }}</p>
                            </div>
                            <v-spacer />
                            <v-chip
                                color="primary"
                                variant="tonal"
                                size="small"
                                >{{ adoptedPets.length }}</v-chip
                            >
                        </div>
                        <div
                            v-if="adoptedPets.length"
                            class="profile-pets-list px-5 pb-5"
                        >
                            <router-link
                                v-for="pet in adoptedPets"
                                :key="pet.id"
                                :to="`/pets/${pet.id}?from=profile&profile=${profile.id}`"
                                class="profile-pet-preview"
                                :aria-label="`Ver perfil de ${pet.name}`"
                            >
                                <v-img
                                    v-if="pet.image_url"
                                    :src="pet.image_url"
                                    :alt="pet.name"
                                    width="96"
                                    height="110"
                                    cover
                                    class="profile-pet-photo"
                                >
                                    <template #error
                                        ><div class="profile-pet-placeholder">
                                            <v-icon icon="mdi-paw" /></div
                                    ></template>
                                </v-img>
                                <div
                                    v-else
                                    class="profile-pet-photo profile-pet-placeholder"
                                >
                                    <v-icon icon="mdi-paw" size="30" />
                                </div>
                                <div class="profile-pet-info">
                                    <h3>{{ pet.name }}</h3>
                                    <p>
                                        {{
                                            pet.species === "cat"
                                                ? "Gato"
                                                : "Cachorro"
                                        }}
                                        ·
                                        {{
                                            pet.sex === "female"
                                                ? "Fêmea"
                                                : "Macho"
                                        }}
                                    </p>
                                    <span
                                        v-if="pet.city"
                                        class="profile-pet-city"
                                        ><v-icon
                                            icon="mdi-map-marker-outline"
                                            size="14"
                                        />{{ pet.city }}</span
                                    >
                                    <span class="profile-pet-label">{{
                                        petStatusLabel(pet)
                                    }}</span>
                                </div>
                            </router-link>
                        </div>
                        <p
                            v-else
                            class="px-5 pb-5 text-body-2 text-medium-emphasis"
                        >
                            Ainda não há pets neste perfil.
                        </p>
                        <div v-if="canEdit" class="px-5 pb-5">
                            <v-btn
                                to="/meus-pets"
                                block
                                color="primary"
                                variant="tonal"
                                rounded="lg"
                                prepend-icon="mdi-paw-outline"
                                append-icon="mdi-arrow-right"
                                >Meus pets</v-btn
                            >
                        </div> </v-card
                    ><v-card rounded="xl"
                        ><v-card-title>Sobre o lar</v-card-title
                        ><v-card-text class="text-medium-emphasis"
                            >{{
                                profile.housing_type ||
                                "Tipo de moradia não informado"
                            }}<br />{{
                                profile.has_other_pets
                                    ? "Tem outros animais em casa"
                                    : "Não informou outros animais"
                            }}</v-card-text
                        ></v-card
                    ></v-col
                ></v-row
            >
        </template>
        <ImageViewerDialog
            v-model="viewerOpen"
            :post="viewerPost"
            :posts="viewerPosts"
            :author="profile"
            :title="viewerTitle"
            @comment="comment"
            @load-more="loadMore"
        />
        <ProfileEditDialog
            v-if="canEdit"
            v-model="editOpen"
            :profile="profile"
            :locations="locations"
            :stats="stats"
            @saved="saved"
            @notice="say"
        />
        <v-dialog
            v-model="removeFriendOpen"
            max-width="440"
            :persistent="removingFriend"
        >
            <v-card rounded="xl">
                <div class="d-flex align-center ga-3 pa-6">
                    <v-avatar color="error" variant="tonal" rounded="lg"
                        ><v-icon icon="mdi-account-minus-outline"
                    /></v-avatar>
                    <h2 class="text-h6 font-weight-bold">Remover amizade?</h2>
                </div>
                <v-card-text class="px-6 pb-6"
                    >Deseja remover a amizade com
                    <strong>{{ profile?.name }}</strong
                    >? Para se tornarem amigos novamente, será necessário enviar
                    uma nova solicitação.</v-card-text
                >
                <v-divider />
                <v-card-actions class="pa-4">
                    <v-spacer />
                    <v-btn
                        variant="text"
                        :disabled="removingFriend"
                        @click="removeFriendOpen = false"
                        >Cancelar</v-btn
                    >
                    <v-btn
                        color="error"
                        variant="flat"
                        :loading="removingFriend"
                        @click="confirmRemoveFriend"
                        >Remover amizade</v-btn
                    >
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="blockOpen" max-width="440" :persistent="blocking"
            ><v-card title="Bloquear usuário?"
                ><v-card-text
                    >A amizade será removida e novas interações entre as contas
                    serão impedidas. Você pode desfazer o bloqueio em
                    Privacidade.</v-card-text
                ><v-card-actions
                    ><v-spacer /><v-btn @click="blockOpen = false"
                        >Cancelar</v-btn
                    ><v-btn color="error" :loading="blocking" @click="blockUser"
                        >Bloquear</v-btn
                    ></v-card-actions
                ></v-card
            ></v-dialog
        >
    </v-container>
</template>

<style scoped>
.profile-hero {
    overflow: hidden;
    border: 1px solid rgba(var(--v-theme-primary), 0.17);
    border-radius: 28px;
    background: rgb(var(--v-theme-surface));
}
.banner {
    height: 210px;
    background: linear-gradient(125deg, #0b7f77, #17493f 55%, #0d2522);
    background-size: cover;
    background-position: center;
}
.banner-clickable {
    cursor: zoom-in;
}
.hero-content {
    margin-top: -48px;
}
.avatar {
    border: 5px solid rgb(var(--v-theme-surface));
    background: rgb(var(--v-theme-primary));
    font-size: 2rem;
    font-weight: 800;
}
.heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 16px;
    margin-top: 14px;
}
.heading h1 {
    font-size: clamp(1.75rem, 4vw, 2.45rem);
}
.heading p,
.bio {
    color: rgba(var(--v-theme-on-surface), 0.65);
}
.bio {
    margin-top: 16px;
    max-width: 690px;
}
.stats {
    display: flex;
    gap: 30px;
    margin-top: 24px;
}
.stats div {
    display: grid;
}
.stats b {
    font-size: 1.25rem;
}
.stats span {
    font-size: 0.84rem;
    color: rgba(var(--v-theme-on-surface), 0.6);
}
.post-body {
    white-space: pre-wrap;
}
.comment {
    padding: 8px 10px;
    margin: 7px 0;
    border-radius: 10px;
    background: rgba(var(--v-theme-on-surface), 0.05);
}
@media (max-width: 600px) {
    .banner {
        height: 150px;
    }
    .heading {
        align-items: start;
        flex-direction: column;
    }
    .stats {
        gap: 16px;
    }
}
</style>
<style scoped>
.avatar-clickable {
    cursor: zoom-in;
}
.profile-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    flex-shrink: 0;
}
.profile-pets {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.profile-pets-heading {
    min-width: 0;
}
.profile-pets-heading h2 {
    font-size: 1rem;
    font-weight: 750;
}
.profile-pets-heading p {
    font-size: 0.75rem;
    margin-top: 3px;
    color: rgba(var(--v-theme-on-surface), 0.6);
}
.profile-pets-list {
    display: grid;
    gap: 12px;
}
.profile-pet-preview {
    display: flex;
    gap: 14px;
    align-items: center;
    padding: 10px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    border-radius: 18px;
    color: inherit;
    text-decoration: none;
    transition:
        background 0.15s,
        border-color 0.15s;
}
.profile-pet-preview:hover,
.profile-pet-preview:focus-visible {
    background: rgba(var(--v-theme-primary), 0.05);
    border-color: rgb(var(--v-theme-primary));
}
.profile-pet-photo {
    flex: 0 0 96px;
    width: 96px;
    height: 110px;
    border-radius: 12px;
    overflow: hidden;
}
.profile-pet-placeholder {
    display: grid;
    place-items: center;
    height: 110px;
    background: rgba(var(--v-theme-primary), 0.08);
    color: rgb(var(--v-theme-primary));
}
.profile-pet-info {
    min-width: 0;
}
.profile-pet-info h3 {
    font-size: 1.05rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}
.profile-pet-info p,
.profile-pet-city {
    font-size: 0.72rem;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin-top: 5px;
}
.profile-pet-city {
    display: flex;
    gap: 3px;
    align-items: center;
}
.profile-pet-label {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 600;
    margin-top: 7px;
    padding: 3px 8px;
    border-radius: 20px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.08);
}
.avatar-clickable:hover {
    filter: brightness(0.88);
}
</style>
