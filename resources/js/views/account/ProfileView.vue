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
import { useRoute } from "vue-router";
import { notify, openChat, setSession } from "../../stores/ui";
import ProfileEditDialog from "./profile/ProfileEditDialog.vue";
import ImageViewerDialog from "./profile/ImageViewerDialog.vue";
import ProfilePostCard from "./profile/ProfilePostCard.vue";
import PostComposer from "./profile/PostComposer.vue";
import PetCard from "../../components/pets/PetCard.vue";
import OwnedPetDialog from "./profile/OwnedPetDialog.vue";

const props = defineProps({
    userId: { type: [String, Number], default: null },
});
const route = useRoute();
const friendshipId = ref(null), respondingFriend = ref(false);
const viewerPosts = computed(() => viewerPost.value && !posts.value.some(post => post.id === viewerPost.value.id) ? [viewerPost.value, ...posts.value] : posts.value);
let postRequestVersion = 0;
async function openNotifiedPost() {
    const id = route.query.post;
    const version = ++postRequestVersion;
    if (!id) return;
    try {
        const response = await fetch(`/api/profile/posts/${encodeURIComponent(id)}`, { credentials: "same-origin", headers: { Accept: "application/json" } });
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (version !== postRequestVersion) return;
        viewerOpen.value = false;
        viewerPost.value = result.data;
        await nextTick();
        viewerOpen.value = true;
    } catch {
        if (version === postRequestVersion) notify("Esta postagem não está mais disponível.", "error");
    }
}
watch(() => [route.query.post, route.query.notification], openNotifiedPost);
async function respondFriend(status) {
    respondingFriend.value = true;
    try {
        const response = await fetch(`/api/friend-requests/${friendshipId.value}`, { method: "PATCH", credentials: "same-origin", headers: jsonHeaders, body: JSON.stringify({ status }) });
        if (!response.ok) throw new Error();
        await load();
        window.dispatchEvent(new Event("notifications:read"));
        notify(status === "accepted" ? "Solicitação aceita." : "Solicitação recusada.");
    } catch { notify("Não foi possível responder à solicitação.", "error"); }
    finally { respondingFriend.value = false; }
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
    petDialogOpen = ref(false),
    editingPet = ref(null),
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
function say(message) {
    notify(message, "success");
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
    if (!social.ok) return;
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
function petDeleted(id) {
    adoptedPets.value = adoptedPets.value.filter(pet => pet.id !== id);
    stats.value.pets = adoptedPets.value.length;
    if (petPostFilter.value === id) petPostFilter.value = null;
}
function petSaved(pet) {
    const index = adoptedPets.value.findIndex((item) => item.id === pet.id);
    if (index >= 0) adoptedPets.value.splice(index, 1, pet);
    else adoptedPets.value.unshift(pet);
    stats.value.pets = adoptedPets.value.length;
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
onBeforeUnmount(() => { feedObserver?.disconnect(); window.removeEventListener("pets:changed", load); });
</script>

<template>
    <v-container class="py-8" style="max-width: 1040px">
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
                            variant="tonal"
                            @click="openChat(profile)"
                            >Mensagem</v-btn
                        >
                        <div v-else-if="friendshipStatus === 'received'" class="d-flex ga-2">
                            <v-btn color="primary" :disabled="respondingFriend" @click="respondFriend('accepted')">Aceitar amizade</v-btn>
                            <v-btn variant="tonal" :disabled="respondingFriend" @click="respondFriend('rejected')">Recusar</v-btn>
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
                        <v-btn
                            v-if="!canEdit && friendshipStatus === 'accepted'"
                            color="error"
                            prepend-icon="mdi-account-minus-outline"
                            @click="cancelFriendRequest"
                            >Remover amizade</v-btn
                        >
                    </div>
                    <p v-if="profile.household_description" class="bio">
                        {{ profile.household_description }}
                    </p>
                    <div class="stats">
                        <div><b>{{ stats.pets }}</b><span>pets que possui</span></div>
                        <div>
                            <b>{{ stats.posts }}</b
                            ><span>publicações</span>
                        </div>
                    </div>
                </div>
            </section>
            <v-row
                ><v-col cols="12" md="7"
                    ><PostComposer v-if="canEdit" class="mb-5" :author="profile" :pets="adoptedPets" @published="postPublished" />
                    <v-select v-if="adoptedPets.length" v-model="petPostFilter" :items="adoptedPets" item-title="name" item-value="id" label="Filtrar publicações por pet" clearable density="compact" variant="outlined" class="mb-4" />
                    <ProfilePostCard
                        v-for="post in filteredPosts"
                        :key="post.id"
                        :post="post"
                        :author="profile"
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
                    ><v-card rounded="xl" class="mb-5"
                        ><v-card-title class="d-flex align-center">Pets de {{ profile.name }}<v-spacer /><v-btn v-if="canEdit" size="small" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="editingPet = null; petDialogOpen = true">Adicionar pet</v-btn></v-card-title
                        ><v-card-text
                            ><v-row v-if="adoptedPets.length" class="mt-1">
                                <v-col v-for="pet in adoptedPets" :key="pet.id" cols="12" sm="6" md="12">
                                    <PetCard :pet="pet" family :editable="canEdit && pet.ownership_kind === 'guardian'" :to="`/pets/${pet.id}?from=profile&profile=${profile.id}`" @edit="editingPet = $event; petDialogOpen = true" />
                                </v-col>
                            </v-row>
                            <span v-else class="text-medium-emphasis"
                                >Adicione os pets que fazem parte da sua família.</span
                            ></v-card-text
                        ></v-card
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
        <OwnedPetDialog v-if="canEdit" v-model="petDialogOpen" :owner-name="profile?.name" :city="profile?.city" :state="profile?.state" :locations="locations" :pet="editingPet" :can-delete="editingPet?.owner_id === profile?.id" @saved="petSaved" @deleted="petDeleted" />
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
.avatar-clickable:hover {
    filter: brightness(0.88);
}
</style>
