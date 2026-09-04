<script setup>
import { computed, ref } from "vue";
const props = defineProps({ post: Object, author: Object });
const emit = defineEmits(["comment"]);
const expanded = ref(false),
    viewer = ref(false),
    draft = ref("");
const comments = computed(() =>
    expanded.value ? props.post.comments : props.post.comments.slice(0, 3),
);
function submit() {
    if (!draft.value.trim()) return;
    emit("comment", props.post, draft.value);
    draft.value = "";
}
</script>
<template>
    <v-card rounded="xl" class="mb-5 post-card"
        ><v-card-text class="pa-5"
            ><div class="d-flex align-center ga-3 mb-4">
                <v-avatar size="44" color="primary"
                    ><v-img
                        v-if="author?.avatar_url"
                        :src="author.avatar_url"
                        cover
                    /><span v-else>{{ author?.name?.[0] }}</span></v-avatar
                >
                <div>
                    <a class="author" :href="`/perfil/${author?.id}`">{{
                        author?.name
                    }}</a
                    ><v-chip
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
            </div>
            <p v-if="post.body" class="post-body">{{ post.body }}</p>
            <v-chip
                v-if="post.pet"
                color="primary"
                variant="tonal"
                class="mb-4"
                prepend-icon="mdi-paw"
                >Com {{ post.pet.name }}</v-chip
            ><v-img
                v-if="post.image_url"
                :src="post.image_url"
                class="post-image mb-4"
                height="340"
                cover
                @click="viewer = true" />
            <div class="comments">
                <div v-for="item in comments" :key="item.id" class="comment">
                    <v-avatar size="32" color="primary"
                        ><v-img
                            v-if="item.user.avatar_url"
                            :src="item.user.avatar_url"
                            cover
                        /><span v-else>{{
                            item.user.name?.[0]
                        }}</span></v-avatar
                    >
                    <div class="comment-content">
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
                            >
                        </div>
                        <span>{{ item.body }}</span>
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
            <div class="d-flex ga-2 mt-3">
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
                    variant="text"
                    @click="submit"
                /></div></v-card-text></v-card
    ><v-dialog v-model="viewer" max-width="1100"
        ><v-card rounded="xl"
            ><v-row no-gutters
                ><v-col cols="12" md="7" class="viewer-image"
                    ><v-img
                        :src="post.image_url"
                        max-height="78vh"
                        contain /></v-col
                ><v-col cols="12" md="5"
                    ><div class="pa-5">
                        <div class="d-flex align-center ga-3 mb-4">
                            <v-avatar size="38" color="primary"
                                ><v-img
                                    v-if="author?.avatar_url"
                                    :src="author.avatar_url"
                                    cover
                            /></v-avatar>
                            <div>
                                <b>{{ author?.name }}</b>
                                <div class="text-caption">
                                    {{
                                        new Date(
                                            post.created_at,
                                        ).toLocaleDateString("pt-BR")
                                    }}
                                </div>
                            </div>
                            <v-spacer /><v-btn
                                icon="mdi-close"
                                variant="text"
                                @click="viewer = false"
                            />
                        </div>
                        <p v-if="post.body" class="post-body">
                            {{ post.body }}
                        </p>
                        <v-divider class="my-4" />
                        <div class="viewer-comments">
                            <p
                                v-if="!post.comments.length"
                                class="text-medium-emphasis"
                            >
                                Ainda não há comentários nesta publicação.
                            </p>
                            <div
                                v-for="item in post.comments"
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
                                <div class="comment-content">
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
                                        >
                                    </div>
                                    <span>{{ item.body }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="viewer-compose">
                            <v-btn
                                icon="mdi-send"
                                color="primary"
                                size="large"
                                @click="submit"
                            /><v-textarea
                                v-model="draft"
                                hide-details
                                rows="2"
                                auto-grow
                                variant="outlined"
                                label="Escreva um comentário"
                                @keydown.ctrl.enter.prevent="submit"
                            />
                        </div></div></v-col></v-row></v-card
    ></v-dialog>
</template>
<style scoped>
.author {
    color: inherit;
    font-weight: 800;
    text-decoration: none;
}
.author:hover {
    text-decoration: underline;
}
.post-body {
    white-space: pre-wrap;
}
.post-image {
    cursor: zoom-in;
    border-radius: 14px;
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
.comment-content > div {
    display: flex;
    align-items: center;
    gap: 7px;
}
.comment small {
    font-size: 0.7rem;
    color: rgba(var(--v-theme-on-surface), 0.55);
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
