<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
} from "vue";
import { notify, openChat, setSession } from "../../stores/ui";
import ProfileEditDialog from "./profile/ProfileEditDialog.vue";
import ImageViewerDialog from "./profile/ImageViewerDialog.vue";
import ProfilePostCard from "./profile/ProfilePostCard.vue";

const props = defineProps({
    userId: { type: [String, Number], default: null },
});

const profile = ref(null),
    posts = ref([]),
    adoptedPets = ref([]),
    stats = ref({ interests: 0, adoptions: 0, posts: 0 }),
    locations = ref([]),
    editOpen = ref(false),
    viewerOpen = ref(false),
    viewerPost = ref(null),
    viewerTitle = ref(""),
    posting = ref(false),
    postFile = ref(null),
    postImageName = ref(""),
    page = ref(1),
    hasMore = ref(false),
    loadingMore = ref(false),
    canEdit = ref(!props.userId),
    friendRequestSent = ref(false),
    friendshipStatus = ref(null),
    feedEnd = ref(null);
let feedObserver;
const newPost = reactive({ body: "", pet_id: null });
const commentDrafts = reactive({});
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
function filePicked(value) {
    postFile.value = Array.isArray(value)
        ? value[0]
        : value?.target?.files?.[0] || value || null;
    postImageName.value = postFile.value?.name || "";
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
async function publish() {
    posting.value = true;
    try {
        const body = new FormData();
        body.append("body", newPost.body);
        if (newPost.pet_id) body.append("pet_id", newPost.pet_id);
        if (postFile.value) body.append("image", postFile.value);
        const response = await fetch("/api/profile/posts", {
            method: "POST",
            credentials: "same-origin",
            headers: formHeaders,
            body,
        });
        const data = await response.json();
        if (!response.ok)
            throw new Error(data.message || "Não foi possível publicar.");
        posts.value.unshift(data.post);
        stats.value.posts++;
        newPost.body = "";
        newPost.pet_id = null;
        postFile.value = null;
        postImageName.value = "";
        say("Publicação criada.");
    } catch (error) {
        say(error.message);
    } finally {
        posting.value = false;
    }
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
    await load();
    await nextTick();
    feedObserver = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) loadMore();
        },
        { rootMargin: "240px" },
    );
    if (feedEnd.value) feedObserver.observe(feedEnd.value);
});
onBeforeUnmount(() => feedObserver?.disconnect());
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
                        <div>
                            <b>{{ stats.interests }}</b
                            ><span>interesses</span>
                        </div>
                        <div>
                            <b>{{ stats.adoptions }}</b
                            ><span>adoções</span>
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
                    ><v-card v-if="canEdit" rounded="xl" class="mb-5"
                        ><v-card-text class="pa-5"
                            ><b>Compartilhe uma novidade</b
                            ><v-textarea
                                v-model="newPost.body"
                                class="mt-3"
                                hide-details
                                variant="outlined"
                                label="Como está a vida por aí?"
                                rows="3"
                                auto-grow
                            />
                            <div
                                class="d-flex flex-wrap align-center ga-3 mt-4"
                            >
                                <v-select
                                    v-model="newPost.pet_id"
                                    :items="adoptedPets"
                                    item-title="name"
                                    item-value="id"
                                    label="Mencionar pet adotado"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    style="min-width: 210px; max-width: 280px"
                                    clearable
                                /><v-btn
                                    variant="tonal"
                                    color="primary"
                                    prepend-icon="mdi-image"
                                    @click="$refs.postImage.click()"
                                    >{{
                                        postImageName || "Adicionar foto"
                                    }}</v-btn
                                ><input
                                    ref="postImage"
                                    class="d-none"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="filePicked"
                                /><v-spacer /><v-btn
                                    color="primary"
                                    :loading="posting"
                                    @click="publish"
                                    >Publicar</v-btn
                                >
                            </div></v-card-text
                        ></v-card
                    >
                    <ProfilePostCard
                        v-for="post in posts"
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
                        ><v-card-title
                            >Pets que fazem parte da história</v-card-title
                        ><v-card-text
                            ><div v-if="adoptedPets.length" class="d-grid ga-3">
                                <div
                                    v-for="pet in adoptedPets"
                                    :key="pet.id"
                                    class="d-flex align-center ga-3"
                                >
                                    <v-avatar size="52" rounded="lg"
                                        ><v-img
                                            :src="pet.image_url" /></v-avatar
                                    ><b>{{ pet.name }}</b>
                                </div>
                            </div>
                            <span v-else class="text-medium-emphasis"
                                >Pets adotados aparecerão aqui para serem
                                marcados nas publicações.</span
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
            :posts="posts"
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
