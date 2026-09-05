<script setup>
import { computed, ref, watch } from "vue";
import { isLogged } from "../../../stores/ui";
const props = defineProps({
    modelValue: Boolean,
    post: Object,
    posts: { type: Array, default: () => [] },
    author: Object,
    title: String,
});
const emit = defineEmits(["update:modelValue", "comment", "load-more"]);
const zoom = ref(1);
const draft = ref("");
const postIndex = ref(0);
const photoIndex = ref(0);
const imagePosts = computed(() =>
    props.posts.filter((item) => item.id === props.post?.id || item.image_url || item.gallery_urls?.length),
);
const currentPost = computed(
    () => imagePosts.value[postIndex.value] || props.post,
);
const currentImages = computed(() =>
    currentPost.value?.gallery_urls?.length
        ? currentPost.value.gallery_urls
        : currentPost.value?.image_url
          ? [currentPost.value.image_url]
          : [],
);
const currentImage = computed(() => currentImages.value[photoIndex.value]);
watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            zoom.value = 1;
            const found = imagePosts.value.findIndex(
                (item) => item.id === props.post?.id,
            );
            postIndex.value = found >= 0 ? found : 0;
            photoIndex.value = 0;
        }
    },
);
function submit() {
    if (!draft.value.trim() || !currentPost.value?.id) return;
    emit("comment", currentPost.value, draft.value);
    draft.value = "";
}
function avatarUrl(user) {
    return (
        user?.avatar_url ||
        (user?.avatar_path ? `/storage/${user.avatar_path}` : null)
    );
}
function previousPost() {
    postIndex.value =
        (postIndex.value - 1 + imagePosts.value.length) % imagePosts.value.length;
    photoIndex.value = 0;
    zoom.value = 1;
}
function nextPost() {
    if (postIndex.value === imagePosts.value.length - 1) return emit("load-more");
    postIndex.value += 1;
    photoIndex.value = 0;
    zoom.value = 1;
}
function previousPhoto() {
    photoIndex.value =
        (photoIndex.value - 1 + currentImages.value.length) % currentImages.value.length;
    zoom.value = 1;
}
function nextPhoto() {
    photoIndex.value = (photoIndex.value + 1) % currentImages.value.length;
    zoom.value = 1;
}
async function toggleLike() {
    if (!isLogged.value || !currentPost.value?.id) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    const response = await fetch(`/api/profile/posts/${currentPost.value.id}/likes`, {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
    });
    if (!response.ok) return;
    const data = await response.json();
    currentPost.value.is_liked = data.liked;
    currentPost.value.likes_count = data.likes_count;
}
</script>
<template>
    <v-dialog
        :model-value="modelValue"
        max-width="1100"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <div class="viewer-shell"><v-btn v-if="imagePosts.length > 1" class="post-arrow post-previous" icon="mdi-chevron-left" @click="previousPost" /><v-card rounded="xl"
            ><v-row no-gutters class="viewer-row">
                <v-col v-if="currentImages.length" cols="12" md="7" class="image-pane"
                    ><v-img
                        v-if="currentPost"
                        :src="currentImage"
                        cover
                        :style="{ transform: `scale(${zoom})` }"
                    />
                    <v-btn
                        v-if="currentImages.length > 1"
                        class="carousel-arrow photo-previous"
                        icon="mdi-chevron-left"
                        @click="previousPhoto"
                    />
                    <v-btn
                        v-if="currentImages.length > 1"
                        class="carousel-arrow photo-next"
                        icon="mdi-chevron-right"
                        @click="nextPhoto"
                    />
                    <div v-if="currentImages.length > 1" class="photo-counter">Foto {{ photoIndex + 1 }} de {{ currentImages.length }}</div>
                    <div class="zoom-bar">
                        <v-icon>mdi-magnify-minus</v-icon
                        ><v-slider
                            v-model="zoom"
                            min="1"
                            max="3"
                            step=".1"
                            hide-details
                            class="mx-3"
                        /><v-icon>mdi-magnify-plus</v-icon>
                    </div></v-col
                >
                <v-col cols="12" :md="currentImages.length ? 5 : 12" class="comments-pane"
                    ><div class="d-flex align-center ga-3 mb-4">
                        <v-avatar size="40" color="primary"
                            ><v-img
                                v-if="author?.avatar_url"
                                :src="author.avatar_url"
                                cover
                        /></v-avatar>
                        <div>
                            <b>{{ author?.name }}</b>
                            <div class="text-caption">
                                {{ title || "Imagem do perfil" }}
                            </div>
                        </div>
                        <v-spacer /><v-btn
                            icon="mdi-close"
                            variant="text"
                            @click="emit('update:modelValue', false)"
                        />
                    </div>
                    <p v-if="currentPost?.body" class="post-text">
                        {{ currentPost.body }}
                    </p>
                    <v-btn v-if="isLogged" size="small" variant="tonal" :color="currentPost?.is_liked ? 'error' : 'primary'" :prepend-icon="currentPost?.is_liked ? 'mdi-heart' : 'mdi-heart-outline'" @click="toggleLike">{{ currentPost?.likes_count || 0 }} {{ currentPost?.likes_count === 1 ? 'curtida' : 'curtidas' }}</v-btn>
                    <v-divider class="my-4" />
                    <div class="comments-list">
                        <p
                            v-if="!currentPost?.comments?.length"
                            class="text-medium-emphasis"
                        >
                            Ainda não há comentários nesta publicação.
                        </p>
                        <div
                            v-for="item in currentPost?.comments || []"
                            :key="item.id"
                            class="comment"
                        >
                            <v-avatar size="32" color="primary"
                                ><v-img
                                    v-if="avatarUrl(item.user)"
                                    :src="avatarUrl(item.user)"
                                    cover
                                /><span v-else>{{
                                    item.user.name?.[0]
                                }}</span></v-avatar
                            >
                            <div>
                                <b>{{ item.user.name }}</b
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
                                ><span>{{ item.body }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="compose">
                        <v-textarea
                            v-model="draft"
                            hide-details
                            rows="2"
                            auto-grow
                            variant="outlined"
                            label="Escreva um comentário"
                            @keydown.ctrl.enter.prevent="submit"
                        /><v-btn
                            icon="mdi-send"
                            color="primary"
                            @click="submit"
                        /></div
                ></v-col> </v-row
        ></v-card><v-btn v-if="imagePosts.length > 1" class="post-arrow post-next" icon="mdi-chevron-right" @click="nextPost" /></div>
    </v-dialog>
</template>
<style scoped>
.image-pane {
    position: relative;
    height: clamp(480px, 72vh, 620px);
    min-height: 0;
    padding: 14px;
    overflow: hidden;
    background: transparent;
}
.viewer-row {
    gap: 16px;
    margin: 0;
    padding: 16px;
    align-items: stretch;
}
.viewer-row > .v-col {
    padding: 0;
}
.viewer-row > .image-pane {
    flex: 7 1 0;
    width: auto;
    max-width: none;
}
.viewer-row > .comments-pane {
    flex: 5 1 0;
    width: auto;
    max-width: none;
}
.image-pane :deep(.v-img) {
    width: 100%;
    height: 100%;
    border-radius: 14px;
    transition: transform 0.18s ease;
}
.carousel-arrow {
    position: absolute;
    top: 50%;
    z-index: 2;
    transform: translateY(-50%);
    background: rgba(var(--v-theme-surface), 0.88);
}
.photo-previous {
    left: 28px;
}
.photo-next {
    right: 28px;
}
.photo-counter { position: absolute; top: 28px; left: 50%; transform: translateX(-50%); z-index: 2; padding: 6px 11px; border-radius: 999px; color: rgb(var(--v-theme-on-primary)); background: rgba(0, 0, 0, .55); font-size: .75rem; font-weight: 700; }.viewer-shell { position: relative; }.post-arrow { position: absolute; top: 50%; z-index: 3; transform: translateY(-50%); background: rgb(var(--v-theme-surface)); box-shadow: 0 8px 22px rgba(0, 0, 0, .22); }.post-previous { left: -26px; }.post-next { right: -26px; }
.zoom-bar {
    position: absolute;
    left: 18px;
    right: 18px;
    bottom: 18px;
    display: flex;
    align-items: center;
    padding: 10px 16px;
    border-radius: 12px;
    background: rgba(var(--v-theme-surface), 0.9);
}
.comments-pane {
    display: flex;
    height: clamp(480px, 72vh, 620px);
    min-height: 0;
    flex-direction: column;
    padding: 30px 28px;
}
.post-text {
    margin-block: 6px 14px;
    white-space: pre-wrap;
}
.comments-list {
    display: grid;
    flex: 1;
    align-content: start;
    gap: 10px;
    min-height: 0;
    max-height: none;
    overflow: auto;
}
.comment {
    align-self: start;
    height: fit-content;
    display: flex;
    gap: 9px;
    padding: 11px;
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.05);
}
.comment > div {
    display: grid;
    gap: 4px;
}
.comment small {
    font-size: 0.7rem;
    color: rgba(var(--v-theme-on-surface), 0.55);
}
.compose {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.compose .v-textarea {
    flex: 1;
}
@media (max-width: 959px) {
    .viewer-row {
        display: block;
        padding: 10px;
    }
    .viewer-row > .image-pane,
    .viewer-row > .comments-pane {
        width: 100%;
        max-width: 100%;
    }
    .viewer-row > .comments-pane {
        margin-top: 12px;
    }
    .image-pane,
    .comments-pane {
        height: auto;
        min-height: 380px;
    }
    .post-previous { left: 8px; }.post-next { right: 8px; }
}
</style>
