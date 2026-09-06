<script setup>
import { computed, reactive, ref } from "vue";
import { userAvatar, userName } from "../../../stores/ui";
import { notify } from "../../../stores/ui";
import { request } from "../../../stores/requests";
const props = defineProps({ post: Object, author: Object, editable: Boolean });
const emit = defineEmits(["comment", "view", "changed"]);
const editOpen = ref(false),
    deleteOpen = ref(false),
    savingPost = ref(false),
    editedBody = ref("");
async function changePost(remove = false) {
    if (savingPost.value) return;
    savingPost.value = true;
    try {
        await request(
            `/api/profile/posts/${props.post.id}`,
            remove ? "DELETE" : "PATCH",
            remove ? undefined : { body: editedBody.value },
        );
        editOpen.value = false;
        deleteOpen.value = false;
        emit("changed");
        notify(remove ? "Publicação excluída." : "Texto atualizado.");
    } catch (error) {
        notify(error.message, "error");
    } finally {
        savingPost.value = false;
    }
}
const expanded = ref(false),
    draft = ref("");
const summaries = reactive({});
const comments = computed(() =>
    expanded.value ? props.post.comments : props.post.comments.slice(0, 3),
);
function submit() {
    if (props.post.hidden_at) return;
    if (!draft.value.trim()) return;
    emit("comment", props.post, draft.value);
    draft.value = "";
}
function avatarUrl(user) {
    return (
        user?.avatar_url ||
        (user?.avatar_path ? `/storage/${user.avatar_path}` : null)
    );
}
async function loadSummary(userId) {
    if (!userId || summaries[userId]) return;
    const response = await fetch(`/api/users/${userId}/profile`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) summaries[userId] = await response.json();
}
async function toggleLike() {
    if (props.post.hidden_at) return;
    const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || "";
    const response = await fetch(`/api/profile/posts/${props.post.id}/likes`, {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    if (!response.ok) return;
    const data = await response.json();
    props.post.is_liked = data.liked;
    props.post.likes_count = data.likes_count;
}
</script>
<template>
    <v-card
        rounded="xl"
        class="mb-5 post-card"
        :class="{ 'post-frozen': post.hidden_at }"
        ><v-card-text class="pa-5" :inert="Boolean(post.hidden_at)"
            ><div class="d-flex align-center ga-3 mb-4">
                <v-avatar size="44" color="primary"
                    ><v-img
                        v-if="author?.avatar_url"
                        :src="author.avatar_url"
                        cover
                    /><span v-else>{{ author?.name?.[0] }}</span></v-avatar
                >
                <div @mouseenter="loadSummary(author?.id)">
                    <span class="author-wrap">
                        <a class="author" :href="`/perfil/${author?.id}`">{{
                            author?.name
                        }}</a>
                        <v-card class="profile-popover pa-3" rounded="xl">
                            <div class="d-flex align-center ga-3">
                                <v-avatar size="42" color="primary"
                                    ><v-img
                                        v-if="
                                            avatarUrl(
                                                summaries[author?.id]?.profile,
                                            )
                                        "
                                        :src="
                                            avatarUrl(
                                                summaries[author?.id]?.profile,
                                            )
                                        "
                                        cover
                                    /><span v-else>{{
                                        author?.name?.[0]
                                    }}</span></v-avatar
                                >
                                <div>
                                    <b>{{ author?.name }}</b>
                                    <div class="text-caption">
                                        {{
                                            summaries[author?.id]?.profile
                                                .city ||
                                            "Localidade não informada"
                                        }}{{
                                            summaries[author?.id]?.profile.state
                                                ? ` · ${summaries[author?.id].profile.state}`
                                                : ""
                                        }}
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="summaries[author?.id]"
                                class="text-caption mt-2"
                            >
                                <b>{{
                                    summaries[author?.id].stats.interests
                                }}</b>
                                interesses ·
                                <b>{{
                                    summaries[author?.id].stats.adoptions
                                }}</b>
                                adoções
                            </div>
                            <v-btn
                                :to="`/perfil/${author?.id}`"
                                block
                                size="small"
                                color="primary"
                                variant="tonal"
                                class="mt-3"
                                >Ver perfil</v-btn
                            >
                        </v-card>
                    </span>
                    <v-chip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        class="ml-2"
                        >Autor</v-chip
                    >
                    <div class="text-caption text-medium-emphasis">
                        {{
                            new Date(post.created_at).toLocaleDateString(
                                "pt-BR",
                                {
                                    day: "2-digit",
                                    month: "long",
                                    year: "numeric",
                                },
                            )
                        }}
                    </div>
                </div>
                <div
                    v-if="editable && !post.hidden_at"
                    class="ml-auto flex-shrink-0"
                >
                    <v-menu location="bottom end">
                        <template #activator="{ props: menuProps }"
                            ><v-btn
                                v-bind="menuProps"
                                icon="mdi-dots-horizontal"
                                size="small"
                                variant="text"
                                aria-label="Opções da publicação"
                        /></template>
                        <v-list rounded="lg" density="compact">
                            <v-list-item
                                title="Editar texto"
                                prepend-icon="mdi-pencil-outline"
                                @click="
                                    editedBody = post.body || '';
                                    editOpen = true;
                                "
                            />
                            <v-list-item
                                title="Excluir publicação"
                                prepend-icon="mdi-delete-outline"
                                base-color="error"
                                @click="deleteOpen = true"
                            />
                        </v-list>
                    </v-menu>
                </div>
            </div>
            <v-alert
                v-if="post.hidden_at"
                variant="tonal"
                color="warning"
                icon="mdi-ghost-outline"
                class="mb-4"
                density="compact"
                >Publicação excluída · Visível apenas para
                administradores.</v-alert
            >
            <p v-if="post.body" class="post-body">{{ post.body }}</p>
            <v-chip
                v-if="post.pet"
                color="primary"
                variant="tonal"
                class="mb-4"
                prepend-icon="mdi-paw"
                >Com {{ post.pet.name }}</v-chip
            ><v-carousel
                v-if="post.gallery_urls?.length"
                class="post-gallery mb-4"
                height="340"
                hide-delimiter-background
                :show-arrows="post.gallery_urls.length > 1 ? 'hover' : false"
                ><v-carousel-item
                    v-for="image in post.gallery_urls"
                    :key="image"
                    :src="image"
                    cover
                    @click="!post.hidden_at && emit('view', post)"
            /></v-carousel>
            <div class="post-actions mb-3">
                <v-btn
                    size="small"
                    variant="text"
                    :color="post.is_liked ? 'error' : undefined"
                    :prepend-icon="
                        post.is_liked ? 'mdi-heart' : 'mdi-heart-outline'
                    "
                    @click="toggleLike"
                    :disabled="Boolean(post.hidden_at)"
                    >{{ post.likes_count || 0 }}
                    {{ post.likes_count === 1 ? "curtida" : "curtidas" }}</v-btn
                >
            </div>
            <div class="comments">
                <div v-for="item in comments" :key="item.id" class="comment">
                    <v-avatar size="32" color="primary"
                        ><v-img
                            v-if="avatarUrl(item.user)"
                            :src="avatarUrl(item.user)"
                            cover
                        /><span v-else>{{
                            item.user.name?.[0]
                        }}</span></v-avatar
                    >
                    <div
                        class="comment-content"
                        @mouseenter="loadSummary(item.user.id)"
                    >
                        <div class="comment-meta">
                            <span class="author-wrap"
                                ><a
                                    class="author"
                                    :href="`/perfil/${item.user.id}`"
                                    >{{ item.user.name }}</a
                                ><v-card
                                    class="profile-popover pa-3"
                                    rounded="xl"
                                    ><div class="d-flex align-center ga-3">
                                        <v-avatar size="42" color="primary"
                                            ><v-img
                                                v-if="
                                                    avatarUrl(
                                                        summaries[item.user.id]
                                                            ?.profile,
                                                    )
                                                "
                                                :src="
                                                    avatarUrl(
                                                        summaries[item.user.id]
                                                            ?.profile,
                                                    )
                                                "
                                                cover
                                            /><span v-else>{{
                                                item.user.name?.[0]
                                            }}</span></v-avatar
                                        >
                                        <div>
                                            <b>{{ item.user.name }}</b>
                                            <div class="text-caption">
                                                {{
                                                    summaries[item.user.id]
                                                        ?.profile.city ||
                                                    "Localidade não informada"
                                                }}{{
                                                    summaries[item.user.id]
                                                        ?.profile.state
                                                        ? ` · ${summaries[item.user.id].profile.state}`
                                                        : ""
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="summaries[item.user.id]"
                                        class="text-caption mt-2"
                                    >
                                        <b>{{
                                            summaries[item.user.id].stats
                                                .interests
                                        }}</b>
                                        interesses ·
                                        <b>{{
                                            summaries[item.user.id].stats
                                                .adoptions
                                        }}</b>
                                        adoções
                                    </div>
                                    <v-btn
                                        :to="`/perfil/${item.user.id}`"
                                        block
                                        size="small"
                                        color="primary"
                                        variant="tonal"
                                        class="mt-3"
                                        >Ver perfil</v-btn
                                    ></v-card
                                ></span
                            ><small
                                >{{
                                    new Date(
                                        item.created_at,
                                    ).toLocaleDateString("pt-BR")
                                }}
                                ·
                                {{
                                    new Date(
                                        item.created_at,
                                    ).toLocaleTimeString("pt-BR", {
                                        hour: "2-digit",
                                        minute: "2-digit",
                                    })
                                }}</small
                            >
                        </div>
                        <span class="comment-body">{{ item.body }}</span>
                    </div>
                </div>
            </div>
            <v-btn
                v-if="post.comments.length > 3"
                variant="text"
                size="small"
                color="primary"
                class="mt-1"
                @click="expanded = !expanded"
                >{{
                    expanded
                        ? "Mostrar menos"
                        : `Ver mais ${post.comments.length - 3} comentários`
                }}</v-btn
            >
            <div v-if="!post.hidden_at" class="comment-composer">
                <v-avatar size="38" color="primary">
                    <v-img v-if="userAvatar" :src="userAvatar" cover />
                    <span v-else>{{ userName?.[0] || "A" }}</span>
                </v-avatar>
                <v-text-field
                    v-model="draft"
                    hide-details
                    density="compact"
                    variant="outlined"
                    label="Escreva um comentário"
                    @keydown.enter.prevent="submit"
                /><v-btn
                    icon="mdi-send"
                    color="primary"
                    variant="tonal"
                    @click="submit"
                /></div></v-card-text
    ></v-card>
    <v-dialog v-model="editOpen" max-width="560" :persistent="savingPost"
        ><v-card title="Editar publicação"
            ><v-card-text
                ><v-textarea
                    v-model="editedBody"
                    label="Texto da publicação"
                    maxlength="2000"
                    counter
                    auto-grow /></v-card-text
            ><v-card-actions
                ><v-spacer /><v-btn
                    :disabled="savingPost"
                    @click="editOpen = false"
                    >Cancelar</v-btn
                ><v-btn
                    color="primary"
                    variant="flat"
                    :loading="savingPost"
                    :disabled="!editedBody.trim()"
                    @click="changePost()"
                    >Salvar texto</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
    <v-dialog v-model="deleteOpen" max-width="440" :persistent="savingPost"
        ><v-card title="Excluir publicação?"
            ><v-card-text
                >A publicação ficará oculta para os usuários. Administradores
                ainda poderão consultá-la.</v-card-text
            ><v-card-actions
                ><v-spacer /><v-btn
                    :disabled="savingPost"
                    @click="deleteOpen = false"
                    >Cancelar</v-btn
                ><v-btn
                    color="error"
                    variant="flat"
                    :loading="savingPost"
                    @click="changePost(true)"
                    >Excluir publicação</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
</template>
<style scoped>
.post-frozen {
    filter: grayscale(1);
    background: rgba(var(--v-theme-on-surface), 0.06);
    opacity: 0.75;
}
.author {
    color: inherit;
    font-weight: 800;
    text-decoration: none;
}
.author:hover {
    text-decoration: underline;
}
.author-wrap {
    position: relative;
    display: inline-flex;
}
.profile-popover {
    display: none;
    position: absolute;
    z-index: 8;
    top: calc(100% + 8px);
    left: 0;
    width: 260px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, 0.28);
}
.author-wrap:hover .profile-popover,
.profile-popover:hover {
    display: block;
}
.post-body {
    white-space: pre-wrap;
}
.post-gallery {
    cursor: zoom-in;
    border-radius: 14px;
    overflow: hidden;
}
.post-actions {
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    padding-top: 8px;
}
.comments {
    display: grid;
    gap: 7px;
}
.comment {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 10px;
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.05);
}
.comment-content {
    display: grid;
    gap: 4px;
    min-width: 0;
}
.comment-meta {
    display: flex;
    align-items: center;
    gap: 7px;
}
.comment-body {
    line-height: 1.45;
}
.comment small {
    font-size: 0.7rem;
    color: rgba(var(--v-theme-on-surface), 0.55);
}
.comment-composer {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}
.comment-composer .v-text-field {
    flex: 1;
}
.viewer-image {
    display: grid;
    min-height: 480px;
    place-items: center;
    background: transparent;
}
.viewer-image :deep(.v-img) {
    width: 100%;
    height: 100%;
}
.viewer-comments {
    display: grid;
    gap: 8px;
    max-height: 38vh;
    overflow: auto;
}
.viewer-compose {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.viewer-compose .v-btn {
    flex: 0 0 auto;
}
.viewer-compose .v-textarea {
    flex: 1;
}
</style>
