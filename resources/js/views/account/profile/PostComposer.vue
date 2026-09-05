<script setup>
import { computed, onBeforeUnmount, ref } from "vue";
import { useRouter } from "vue-router";
import { notify } from "../../../stores/ui";

const props = defineProps({
    author: { type: Object, default: () => ({}) },
    pets: { type: Array, default: () => [] },
});
const emit = defineEmits(["published"]);
const router = useRouter();

const body = ref("");
const petId = ref(null);
const images = ref([]);
const posting = ref(false);
const imageInput = ref(null);
const initials = computed(() =>
    (props.author?.name || "A")
        .split(" ")
        .map((word) => word[0])
        .slice(0, 2)
        .join("")
        .toUpperCase(),
);
const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

function selectImage(event) {
    const selected = Array.from(event.target.files || []);
    const available = 5 - images.value.length;
    if (!selected.length || available < 1) return;
    if (selected.length > available) notify(`Você pode adicionar até 5 fotos. Foram adicionadas ${available}.`, "info");
    images.value.push(...selected.slice(0, available).map((file) => ({ file, url: URL.createObjectURL(file) })));
    event.target.value = "";
}
function removeImage(index) {
    const [image] = images.value.splice(index, 1);
    if (image) URL.revokeObjectURL(image.url);
}
function clearImages() {
    images.value.forEach((image) => URL.revokeObjectURL(image.url));
    images.value = [];
}
async function publish() {
    posting.value = true;
    try {
        const form = new FormData();
        form.append("body", body.value);
        if (petId.value) form.append("pet_id", petId.value);
        images.value.forEach((image) => form.append("images[]", image.file));
        const response = await fetch("/api/profile/posts", {
            method: "POST",
            credentials: "same-origin",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": csrf },
            body: form,
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "Não foi possível publicar.");
        emit("published", data.post);
        body.value = "";
        petId.value = null;
        clearImages();
        notify("Publicação criada.", "success");
    } catch (error) {
        notify(error.message || "Não foi possível publicar.", "error");
    } finally {
        posting.value = false;
    }
}
onBeforeUnmount(clearImages);
</script>

<template>
    <v-card class="post-composer" rounded="xl" elevation="0">
        <div class="composer-top pa-5 pb-4">
            <div class="d-flex align-center ga-3">
                <v-avatar size="46" color="primary" class="composer-avatar cursor-pointer" @click="router.push('/perfil')">
                    <v-img v-if="author?.avatar_url" :src="author.avatar_url" cover />
                    <span v-else>{{ initials }}</span>
                </v-avatar>
                <div class="cursor-pointer" @click="router.push('/perfil')">
                    <div class="text-body-1 font-weight-bold">Criar publicação</div>
                    <div class="text-caption text-medium-emphasis">Compartilhe um momento com a comunidade.</div>
                </div>
                <v-chip class="ml-auto" size="small" color="primary" variant="tonal" prepend-icon="mdi-earth">Público</v-chip>
            </div>
            <v-textarea v-model="body" class="composer-field mt-5" label="O que está acontecendo por aí?" placeholder="Compartilhe uma história, uma conquista ou um momento especial..." variant="outlined" hide-details rows="6" auto-grow counter="2000" maxlength="2000" />
            <div v-if="images.length" class="preview-grid mt-4" :class="`photos-${images.length}`">
                <div v-for="(image, index) in images" :key="image.url" class="image-preview">
                    <v-img :src="image.url" height="180" cover />
                    <v-btn icon="mdi-close" size="small" color="surface" class="remove-image" @click="removeImage(index)" />
                </div>
            </div>
        </div>
        <v-divider />
        <div class="composer-bottom pa-4 d-flex flex-wrap align-center ga-2">
            <v-btn color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-image-outline" :disabled="images.length >= 5" @click="imageInput?.click()">{{ images.length ? `Adicionar foto (${images.length}/5)` : "Adicionar foto" }}</v-btn>
            <input ref="imageInput" class="d-none" type="file" accept="image/jpeg,image/png,image/webp" multiple @change="selectImage" />
            <v-select v-if="pets.length" v-model="petId" :items="pets" item-title="name" item-value="id" label="Marcar um pet" variant="outlined" density="compact" hide-details clearable class="pet-picker" />
            <v-spacer />
            <v-btn color="primary" rounded="lg" :loading="posting" :disabled="!body.trim() && !images.length" prepend-icon="mdi-send" @click="publish">Publicar</v-btn>
        </div>
    </v-card>
</template>

<style scoped>
.post-composer { overflow: hidden; border: 1px solid rgba(var(--v-theme-primary), .15); box-shadow: 0 12px 28px rgba(6, 54, 50, .09); background: linear-gradient(145deg, rgb(var(--v-theme-surface)) 0%, rgba(var(--v-theme-primary), .035) 100%); }
.composer-top { background: linear-gradient(180deg, rgba(var(--v-theme-primary), .06), transparent 55%); }.composer-avatar { border: 3px solid rgba(var(--v-theme-primary), .16); }.composer-field :deep(.v-field) { background: rgb(var(--v-theme-surface)); border-radius: 14px; }.composer-field :deep(textarea) { min-height: 170px; padding: 12px 16px; color: rgb(var(--v-theme-on-surface)) !important; font-size: 1rem; line-height: 1.6; }.composer-field :deep(textarea::placeholder) { color: rgba(var(--v-theme-on-surface), .62) !important; opacity: 1; }.composer-field :deep(.v-label) { color: rgba(var(--v-theme-on-surface), .76); }.preview-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }.preview-grid.photos-1 { grid-template-columns: 1fr; }.image-preview { position: relative; overflow: hidden; border-radius: 16px; border: 1px solid rgba(var(--v-theme-primary), .16); }.remove-image { position: absolute; top: 10px; right: 10px; box-shadow: 0 3px 10px rgba(0,0,0,.22); }.composer-bottom { background: rgba(var(--v-theme-surface), .72); }.pet-picker { min-width: 190px; max-width: 250px; }
@media (max-width: 560px) { .pet-picker { width: 100%; max-width: none; }.composer-bottom .v-spacer { display: none; } }
</style>
