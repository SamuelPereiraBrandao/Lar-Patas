<script setup>
import { computed, ref } from "vue";
const props = defineProps({
    profile: Object,
    stats: { type: Object, default: () => ({}) },
});
const emit = defineEmits([
    "banner-selected",
    "avatar-selected",
    "remove-banner",
    "remove-avatar",
    "notice",
]);
const bannerInput = ref(null),
    avatarInput = ref(null),
    bannerPreview = ref(""),
    avatarPreview = ref("");
const banner = computed(
    () => bannerPreview.value || props.profile?.banner_url || "",
);
const avatar = computed(
    () => avatarPreview.value || props.profile?.avatar_url || "",
);
function pick(event, type) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type))
        return emit("notice", "Use somente JPG, PNG ou WebP.");
    if (file.size > 5 * 1024 * 1024)
        return emit("notice", "A imagem deve ter no máximo 5 MB.");
    const url = URL.createObjectURL(file);
    if (type === "banner") {
        bannerPreview.value = url;
        emit("banner-selected", file);
    } else {
        avatarPreview.value = url;
        emit("avatar-selected", file);
    }
}
</script>
<template>
    <section class="preview-shell">
        <input
            ref="bannerInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="d-none"
            @change="pick($event, 'banner')"
        />
        <input
            ref="avatarInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="d-none"
            @change="pick($event, 'avatar')"
        />
        <div
            class="preview-banner"
            :style="banner ? { backgroundImage: `url(${banner})` } : {}"
            role="button"
            tabindex="0"
            @click="bannerInput.click()"
            @keydown.enter="bannerInput.click()"
        >
            <div class="edit-hint">
                <v-icon>mdi-image-edit</v-icon> Alterar capa
            </div>
            <v-btn
                v-if="profile?.banner_url"
                icon="mdi-delete"
                size="small"
                color="error"
                class="delete-banner"
                @click.stop="emit('remove-banner')"
            />
        </div>
        <div class="preview-body">
            <div
                class="avatar-slot"
                role="button"
                tabindex="0"
                @click="avatarInput.click()"
                @keydown.enter="avatarInput.click()"
            >
                <v-avatar size="104" color="primary"
                    ><v-img v-if="avatar" :src="avatar" cover /><span
                        v-else
                        class="initials"
                        >{{
                            profile?.name
                                ?.split(" ")
                                .map((x) => x[0])
                                .slice(0, 2)
                                .join("")
                        }}</span
                    ></v-avatar
                ><span class="avatar-edit"
                    ><v-icon size="16">mdi-camera</v-icon></span
                ><v-btn
                    v-if="profile?.avatar_url"
                    icon="mdi-delete"
                    size="x-small"
                    color="error"
                    class="delete-avatar"
                    @click.stop="emit('remove-avatar')"
                />
            </div>
            <div class="preview-copy">
                <h2>{{ profile?.name }}</h2>
                <p>
                    {{
                        profile?.city
                            ? `${profile.city}${profile.state ? `, ${profile.state}` : ""}`
                            : "Perfil de adotante"
                    }}
                </p>
                <div class="mini-stats">
                    <span
                        ><b>{{ stats.interests || 0 }}</b> interesses</span
                    ><span
                        ><b>{{ stats.adoptions || 0 }}</b> adoções</span
                    ><span
                        ><b>{{ stats.posts || 0 }}</b> publicações</span
                    >
                </div>
            </div>
        </div>
    </section>
</template>
<style scoped>
.preview-shell {
    overflow: hidden;
    border: 1px solid rgba(var(--v-theme-primary), 0.35);
    border-radius: 22px;
    background: rgb(var(--v-theme-surface));
}
.preview-banner {
    position: relative;
    height: 190px;
    background: linear-gradient(125deg, #0b7f77, #17493f 55%, #0d2522);
    background-size: cover;
    background-position: center;
    cursor: pointer;
}
.edit-hint {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: white;
    background: rgba(0, 0, 0, 0.22);
    opacity: 0;
    transition: opacity 0.18s;
}
.preview-banner:hover .edit-hint {
    opacity: 1;
}
.delete-banner {
    position: absolute;
    right: 14px;
    top: 14px;
}
.preview-body {
    position: relative;
    min-height: 170px;
    padding: 58px 28px 22px;
}
.avatar-slot {
    position: absolute;
    top: -54px;
    left: 28px;
    cursor: pointer;
}
.avatar-slot :deep(.v-avatar) {
    border: 5px solid rgb(var(--v-theme-surface));
}
.initials {
    font-size: 1.7rem;
    font-weight: 800;
}
.avatar-edit {
    position: absolute;
    right: 1px;
    bottom: 5px;
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
    color: white;
    border: 2px solid rgb(var(--v-theme-surface));
}
.delete-avatar {
    position: absolute;
    top: 0;
    right: -8px;
}
.preview-copy h2 {
    font-size: 1.55rem;
}
.preview-copy p {
    color: rgba(var(--v-theme-on-surface), 0.62);
}
.mini-stats {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}
.mini-stats span {
    display: grid;
    font-size: 0.78rem;
    color: rgba(var(--v-theme-on-surface), 0.62);
}
.mini-stats b {
    font-size: 1rem;
    color: rgb(var(--v-theme-on-surface));
}
</style>
