<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import PetCard from "../../components/pets/PetCard.vue";
import { usePetsStore } from "../../stores/pets";
import { isLogged, notify, userAvatar, userName } from "../../stores/ui";
import PostComposer from "../account/profile/PostComposer.vue";
import ImageViewerDialog from "../account/profile/ImageViewerDialog.vue";

const store = usePetsStore();
const posts = ref([]);
const loadingPosts = ref(true);
const loadingMorePosts = ref(false);
const feedPage = ref(1);
const hasMorePosts = ref(false);
const feedEnd = ref(null);
const composerOpen = ref(isLogged.value);
const composerSection = ref(null);
const composerAuthor = ref({ name: userName.value, avatar_url: userAvatar.value });
const composerPets = ref([]);
const viewerOpen = ref(false);
const viewerPost = ref(null);
const profileSummaries = reactive({});
let feedObserver;
const featuredPets = computed(() => store.pets.slice(0, 3));
const welcomeName = computed(() => userName.value?.split(" ")[0] || "você");

function formatDate(value) {
    const minutes = Math.max(0, Math.round((Date.now() - new Date(value).getTime()) / 60000));
    if (minutes < 1) return "Agora";
    if (minutes < 60) return `${minutes} min`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)} h`;
    return new Intl.DateTimeFormat("pt-BR", { dateStyle: "medium" }).format(new Date(value));
}
async function loadFeed(page = 1) {
    const response = await fetch(`/api/community/feed?page=${page}`, { headers: { Accept: "application/json" } });
    if (response.ok) {
        const data = await response.json();
        posts.value = page === 1 ? data.data || [] : [...posts.value, ...(data.data || [])];
        feedPage.value = data.pagination?.current_page || page;
        hasMorePosts.value = Boolean(data.pagination?.has_more);
    }
    loadingPosts.value = false;
}
async function loadMorePosts() {
    if (!hasMorePosts.value || loadingMorePosts.value) return;
    loadingMorePosts.value = true;
    await loadFeed(feedPage.value + 1);
    loadingMorePosts.value = false;
}
async function togglePostLike(post) {
    if (!isLogged.value) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const response = await fetch(`/api/profile/posts/${post.id}/likes`, {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    if (!response.ok) return;
    const data = await response.json();
    post.is_liked = data.liked;
    post.likes_count = data.likes_count;
}
async function loadComposerData() {
    composerAuthor.value = { name: userName.value, avatar_url: userAvatar.value };
    composerPets.value = [];
    const response = await fetch("/api/profile/social", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (!response.ok) return;
    const data = await response.json();
    composerAuthor.value = data.profile || composerAuthor.value;
    composerPets.value = data.adopted_pets || [];
}
async function openComposer() {
    composerOpen.value = true;
    await loadComposerData();
    await nextTick();
    composerSection.value?.scrollIntoView({ behavior: "smooth", block: "center" });
}
function postPublished(post) {
    posts.value.unshift({
        ...post,
        user: post.user || composerAuthor.value,
        comments_count: post.comments_count || 0,
        likes_count: post.likes_count || 0,
        is_liked: Boolean(post.is_liked),
    });
}
function avatarUrl(user) {
    return user?.avatar_url || (user?.avatar_path ? `/storage/${user.avatar_path}` : null);
}
async function loadProfileSummary(userId) {
    if (!userId || profileSummaries[userId]) return;
    const response = await fetch(`/api/users/${userId}/profile`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) profileSummaries[userId] = await response.json();
}
function openPost(post) {
    viewerPost.value = post;
    viewerOpen.value = true;
}
async function comment(post, body) {
    if (!isLogged.value) return;
    try {
        const response = await fetch(`/api/profile/posts/${post.id}/comments`, {
            method: "POST",
            credentials: "same-origin",
            headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "" },
            body: JSON.stringify({ body }),
        });
        const data = await response.json();
        if (!response.ok) return notify(data.message || "Não foi possível comentar.", "error");
        post.comments ||= [];
        post.comments.push(data.comment);
        post.comments_count = post.comments.length;
    } catch {
        notify("Não foi possível comentar. Tente novamente.", "error");
    }
}
onMounted(async () => {
    store.fetchPets();
    await loadFeed();
    if (isLogged.value) loadComposerData();
    await nextTick();
    feedObserver = new IntersectionObserver(
        ([entry]) => entry.isIntersecting && loadMorePosts(),
        { rootMargin: "300px" },
    );
    if (feedEnd.value) feedObserver.observe(feedEnd.value);
});
onBeforeUnmount(() => feedObserver?.disconnect());
</script>

<template>
    <section class="community-hero text-white">
        <v-container class="py-10 py-md-13">
            <v-row align="center">
                <v-col cols="12" md="8" lg="7">
                    <v-chip color="white" variant="flat" class="mb-5 font-weight-bold"><v-icon start icon="mdi-account-group" color="primary" />COMUNIDADE LAR & PATAS</v-chip>
                    <h1 class="text-h3 text-md-h2 font-weight-black mb-4">Pets, histórias e pessoas que cuidam.</h1>
                    <p class="hero-copy text-h6 mb-7">Compartilhe momentos com seu pet, conheça novas pessoas e encontre um amigo que está esperando por um lar.</p>
                    <div class="d-flex flex-wrap ga-3">
                       <v-btn v-if="!isLogged" to="/criar-conta" color="secondary" size="large" rounded="lg">Entrar na comunidade<v-icon end icon="mdi-plus" /></v-btn>
                    </div>
                </v-col>
                <v-col cols="12" md="4" lg="5" class="d-none d-md-flex justify-end">
                    <v-card to="/perfil" class="hero-community-card pa-5" rounded="xl" elevation="0">
                        <div class="d-flex align-center ga-3 mb-4"><v-avatar color="primary" size="46"><v-img v-if="userAvatar" :src="userAvatar" cover /><v-icon v-else icon="mdi-paw" /></v-avatar><div><b>Olá, {{ welcomeName }}</b><div class="text-caption opacity-70">Seu espaço para viver a vida com pets.</div></div></div>
                        <div class="hero-stat-grid"><div><b>{{ posts.length }}</b><span>publicações recentes</span></div><div><b>{{ store.pets.length }}</b><span>pets procurando lar</span></div></div>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </section>

    <v-container class="community-layout py-8 py-md-11">
        <v-row align="start">
            <v-col cols="12" lg="8">
                <div class="d-flex align-center justify-space-between mb-5"><div><div class="section-kicker mb-1">COMUNIDADE</div><h2 class="text-h4 font-weight-bold">Histórias recentes</h2><p class="text-medium-emphasis mt-1">Veja o que as pessoas e seus pets estão vivendo.</p></div><v-btn v-if="isLogged" color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-pencil" @click="openComposer">Publicar</v-btn></div>
                <div v-if="isLogged && composerOpen" ref="composerSection" class="mb-5"><PostComposer :author="composerAuthor" :pets="composerPets" @published="postPublished" /></div>
                <div v-if="loadingPosts" class="text-center py-12"><v-progress-circular indeterminate color="primary" size="38" /></div>
                <div v-else class="feed-list">
                    <v-card v-for="post in posts" :key="post.id" class="feed-post" rounded="xl" elevation="0">
                        <v-card-item class="pb-2"><template #prepend><v-menu open-on-hover :open-on-click="false" location="bottom start" :close-delay="120" @update:model-value="(open) => open && loadProfileSummary(post.user?.id)"><template #activator="{ props: menuProps }"><v-avatar color="primary" class="cursor-pointer" v-bind="menuProps" @click.stop="$router.push(`/perfil/${post.user.id}`)"><v-img v-if="avatarUrl(post.user)" :src="avatarUrl(post.user)" cover /><span v-else>{{ post.user?.name?.[0] }}</span></v-avatar></template><v-card class="profile-preview pa-3" rounded="xl"><div class="d-flex align-center ga-3"><v-avatar size="42" color="primary"><v-img v-if="avatarUrl(profileSummaries[post.user?.id]?.profile || post.user)" :src="avatarUrl(profileSummaries[post.user?.id]?.profile || post.user)" cover /><span v-else>{{ post.user?.name?.[0] }}</span></v-avatar><div><b>{{ post.user?.name }}</b><div class="text-caption">{{ profileSummaries[post.user?.id]?.profile?.city || post.user?.city || "Localidade não informada" }}</div></div></div><div v-if="profileSummaries[post.user?.id]?.stats" class="text-caption mt-2"><b>{{ profileSummaries[post.user.id].stats.interests }}</b> interesses · <b>{{ profileSummaries[post.user.id].stats.adoptions }}</b> adoções</div><v-btn :to="`/perfil/${post.user?.id}`" block size="small" color="primary" variant="tonal" class="mt-3">Ver perfil</v-btn></v-card></v-menu></template><v-menu open-on-hover :open-on-click="false" location="bottom start" :close-delay="120" @update:model-value="(open) => open && loadProfileSummary(post.user?.id)"><template #activator="{ props: menuProps }"><v-card-title class="px-0 text-body-1 font-weight-bold cursor-pointer" v-bind="menuProps" @click.stop="$router.push(`/perfil/${post.user.id}`)">{{ post.user?.name }}</v-card-title></template><v-card class="profile-preview pa-3" rounded="xl"><div class="d-flex align-center ga-3"><v-avatar size="42" color="primary"><v-img v-if="avatarUrl(profileSummaries[post.user?.id]?.profile || post.user)" :src="avatarUrl(profileSummaries[post.user?.id]?.profile || post.user)" cover /></v-avatar><div><b>{{ post.user?.name }}</b><div class="text-caption">{{ profileSummaries[post.user?.id]?.profile?.city || post.user?.city || "Localidade não informada" }}</div></div></div><v-btn :to="`/perfil/${post.user?.id}`" block size="small" color="primary" variant="tonal" class="mt-3">Ver perfil</v-btn></v-card></v-menu><v-card-subtitle class="px-0">{{ post.user?.city || "Comunidade Lar & Patas" }} · {{ formatDate(post.created_at) }}</v-card-subtitle></v-card-item>
                        <v-card-text v-if="post.body" class="pt-1 text-body-1 post-body">{{ post.body }}</v-card-text>
                        <v-carousel v-if="post.gallery_urls?.length" class="post-gallery" height="510" hide-delimiter-background :show-arrows="post.gallery_urls.length > 1 ? 'hover' : false"><v-carousel-item v-for="image in post.gallery_urls" :key="image" :src="image" cover @click="openPost(post)" /></v-carousel>
                        <v-card-actions class="px-4 py-3"><v-chip v-if="post.pet" size="small" color="primary" variant="tonal" prepend-icon="mdi-paw">Com {{ post.pet.name }}</v-chip><v-spacer /><v-btn v-if="isLogged" size="small" variant="text" :color="post.is_liked ? 'error' : undefined" :prepend-icon="post.is_liked ? 'mdi-heart' : 'mdi-heart-outline'" @click="togglePostLike(post)">{{ post.likes_count || 0 }}</v-btn><span class="text-caption text-medium-emphasis"><v-icon size="16" icon="mdi-comment-outline" /> {{ post.comments_count || 0 }} comentários</span><v-btn :to="`/perfil/${post.user.id}`" color="primary" variant="text" size="small">Ver perfil</v-btn></v-card-actions>
                    </v-card>
                    <v-card v-if="!posts.length" class="feed-empty pa-8 text-center" rounded="xl" elevation="0"><v-icon icon="mdi-forum-outline" size="42" color="primary" /><h3 class="mt-3">A comunidade está começando</h3><p class="text-medium-emphasis mt-1">Seja a primeira pessoa a compartilhar uma história com seu pet.</p><v-btn v-if="isLogged" color="primary" class="mt-3" @click="openComposer">Criar publicação</v-btn></v-card>
                    <div ref="feedEnd" class="text-center py-5"><v-progress-circular v-if="loadingMorePosts" indeterminate size="26" color="primary" /><span v-else-if="!hasMorePosts && posts.length" class="text-caption text-medium-emphasis">Você viu todas as publicações por enquanto.</span></div>
                </div>
            </v-col>
            <v-col cols="12" lg="4" id="adocao"><aside class="adoption-sidebar"><v-card class="adoption-callout pa-6 mb-5" rounded="xl" elevation="0"><v-icon icon="mdi-heart-multiple" color="secondary" size="32" /><h2 class="text-h5 font-weight-bold mt-3">Também é lugar de adotar</h2><p class="text-medium-emphasis mt-2">Conheça pets que esperam uma família e acompanhe seus interesses.</p><v-btn to="/pets" color="secondary" block class="mt-4" rounded="lg">Explorar adoções<v-icon end icon="mdi-paw" /></v-btn></v-card><div class="d-flex align-center justify-space-between mb-3 px-1"><h3 class="text-h6 font-weight-bold">Pets em destaque</h3><v-chip size="x-small" color="primary" variant="tonal">{{ store.pets.length }} disponíveis</v-chip></div><v-card v-for="pet in featuredPets" :key="pet.id" class="side-pet mb-3" rounded="xl" elevation="0" :to="`/pets/${pet.id}`"><v-avatar rounded="lg" size="68" color="primary"><v-img v-if="pet.image_url" :src="pet.image_url" cover /><v-icon v-else icon="mdi-paw" /></v-avatar><div class="flex-grow-1"><b>{{ pet.name }}</b><div class="text-caption text-medium-emphasis">{{ pet.city }} · {{ pet.species === "cat" ? "Gato" : "Cachorro" }}</div><v-chip size="x-small" color="success" variant="tonal" class="mt-1">Disponível</v-chip></div><v-icon icon="mdi-chevron-right" color="primary" /></v-card></aside></v-col>
        </v-row>
    </v-container>

    <section class="adoption-strip"><v-container class="py-10 py-md-12"><div class="d-flex flex-wrap align-end justify-space-between ga-3 mb-5"><div><div class="section-kicker mb-1">ADOÇÃO RESPONSÁVEL</div><h2 class="text-h4 font-weight-bold">Encontre seu próximo melhor amigo</h2></div><v-btn to="/pets" color="primary" variant="outlined">Ver todos os pets</v-btn></div><v-row><v-col v-for="pet in featuredPets" :key="pet.id" cols="12" md="4"><PetCard :pet="pet" /></v-col></v-row></v-container></section>
    <ImageViewerDialog v-model="viewerOpen" :post="viewerPost" :posts="posts" :author="viewerPost?.user" @comment="comment" @load-more="loadMorePosts" />
</template>

<style scoped>
.community-hero { background: linear-gradient(110deg, #083e39 0%, #0d766d 56%, #129487 100%); }
.hero-copy { max-width: 680px; color: #d9f2ed; }
.hero-community-card { width: min(100%, 360px); color: rgb(var(--v-theme-on-surface)); background: rgba(255, 255, 255, .94); }
.hero-stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }.hero-stat-grid div { padding: 12px; border-radius: 12px; background: rgba(14, 119, 109, .1); }.hero-stat-grid b { display: block; color: rgb(var(--v-theme-primary)); font-size: 1.25rem; }.hero-stat-grid span { font-size: .72rem; }
.feed-list { display: grid; gap: 16px; }.feed-post, .feed-empty, .side-pet { border: 1px solid rgba(var(--v-theme-primary), .12); background: rgb(var(--v-theme-surface)); }.post-body { white-space: pre-line; line-height: 1.6; }.post-gallery { border-top: 1px solid rgba(var(--v-theme-primary), .08); border-bottom: 1px solid rgba(var(--v-theme-primary), .08); }
.profile-preview { width: 260px; box-shadow: 0 14px 30px rgba(0, 0, 0, .22); }
.adoption-callout { background: linear-gradient(145deg, rgba(var(--v-theme-primary), .13), rgba(var(--v-theme-secondary), .14)); border: 1px solid rgba(var(--v-theme-primary), .16); }.side-pet { display: flex; align-items: center; gap: 12px; padding: 10px; color: inherit; text-decoration: none; }.adoption-strip { background: rgba(var(--v-theme-primary), .045); border-top: 1px solid rgba(var(--v-theme-primary), .1); }
@media (max-width: 959px) { .community-layout { max-width: 760px; } }
</style>
