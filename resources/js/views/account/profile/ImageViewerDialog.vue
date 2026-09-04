<script setup>
import { computed, ref, watch } from "vue";
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
const index = ref(0);
const imagePosts = computed(() => props.posts.filter((item) => item.image_url));
const currentPost = computed(() => imagePosts.value[index.value] || props.post);
watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            zoom.value = 1;
            const found = imagePosts.value.findIndex(
                (item) => item.id === props.post?.id,
            );
            index.value = found >= 0 ? found : 0;
        }
    },
);
function submit() {
    if (!draft.value.trim() || !currentPost.value?.id) return;
    emit("comment", currentPost.value, draft.value);
    draft.value = "";
}
function previous() {
    index.value =
        (index.value - 1 + imagePosts.value.length) % imagePosts.value.length;
    zoom.value = 1;
}
function next() {
    if (index.value === imagePosts.value.length - 1) return emit("load-more");
    index.value += 1;
    zoom.value = 1;
}
</script>
<template>
    <v-dialog
        :model-value="modelValue"
        max-width="1100"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card rounded="xl"
            ><v-row no-gutters class="viewer-row">
                <v-col cols="12" md="7" class="image-pane"
                    ><v-img
                        v-if="currentPost"
                        :src="currentPost.image_url"
                        cover
                        :style="{ transform: `scale(${zoom})` }"
                    />
                    <v-btn
                        v-if="imagePosts.length > 1"
                        class="carousel-arrow previous"
                        icon="mdi-chevron-left"
                        @click="previous"
                    />
                    <v-btn
                        v-if="imagePosts.length > 1"
                        class="carousel-arrow next"
                        icon="mdi-chevron-right"
                        @click="next"
                    />
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
                <v-col cols="12" md="5" class="comments-pane"
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
                                    v-if="item.user.avatar_url"
                                    :src="item.user.avatar_url"
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
                        <v-btn
                            icon="mdi-send"
                            color="primary"
                            @click="submit"
                        /><v-textarea
                            v-model="draft"
                            hide-details
                            rows="2"
                            auto-grow
                            variant="outlined"
                            label="Escreva um comentário"
                            @keydown.ctrl.enter.prevent="submit"
                        /></div
                ></v-col> </v-row
        ></v-card>
    </v-dialog>
</template>
<style scoped>
.image-pane {
    position: relative;
    min-height: 520px;
    padding: 14px;
    overflow: hidden;
    background: transparent;
}
.viewer-row {
    gap: 16px;
    margin: 0;
    padding: 16px;
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
.previous {
    left: 28px;
}
.next {
    right: 28px;
}
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
    min-height: 520px;
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
    max-height: 360px;
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
        min-height: 380px;
    }
}
</style>
