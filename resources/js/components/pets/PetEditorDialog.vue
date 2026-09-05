<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { notify } from "../../stores/ui";
const props = defineProps({
    modelValue: Boolean,
    city: String,
    state: String,
    ownerName: String,
    canDelete: Boolean,
    locations: { type: Array, default: () => [] },
    pet: Object,
    administrative: Boolean,
    shelterOptions: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue", "saved", "deleted"]);
const deleting = ref(false),
    deleteDialog = ref(false);
async function deletePet() {
    if (!props.pet || !props.canDelete) return;
    deleting.value = true;
    try {
        const response = await fetch(`/api/profile/pets/${props.pet.id}`, {
            method: "DELETE",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN":
                    document.querySelector('meta[name="csrf-token"]')
                        ?.content || "",
            },
        });
        if (!response.ok) throw new Error();
        deleteDialog.value = false;
        emit("deleted", props.pet.id);
        emit("update:modelValue", false);
        notify("Pet excluído.");
    } catch {
        notify("Não foi possível excluir o pet.", "error");
    } finally {
        deleting.value = false;
    }
}
const tab = ref("details"),
    saving = ref(false),
    input = ref(null);
const gallery = ref([]),
    removedPaths = ref([]);
const form = reactive({});
const ownerOptions = ref([]),
    ownerSearch = ref(""),
    loadingOwners = ref(false);
let ownerSearchTimer;
let ownerSearchVersion = 0;
async function loadOwners() {
    const version = ++ownerSearchVersion;
    loadingOwners.value = true;
    try {
        const response = await fetch(
            `/api/profile/pet-owners?search=${encodeURIComponent(ownerSearch.value || "")}`,
            {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            },
        );
        if (!response.ok) throw new Error();
        const result = await response.json();
        if (version !== ownerSearchVersion) return;
        const selected = ownerOptions.value.filter((owner) =>
            form.owner_ids?.includes(owner.id),
        );
        ownerOptions.value = [
            ...new Map(
                [...selected, ...result.data].map((owner) => [owner.id, owner]),
            ).values(),
        ];
    } catch {
        if (version === ownerSearchVersion)
            notify(
                "Não foi possível buscar os donos. Tente novamente.",
                "error",
            );
    } finally {
        if (version === ownerSearchVersion) loadingOwners.value = false;
    }
}
watch(ownerSearch, () => {
    clearTimeout(ownerSearchTimer);
    if (props.modelValue && !props.administrative)
        ownerSearchTimer = setTimeout(loadOwners, 250);
});
onBeforeUnmount(() => {
    clearTimeout(ownerSearchTimer);
    ownerSearchVersion++;
});
const states = computed(() =>
    props.locations.map((location) => ({
        title: `${location.name} (${location.code})`,
        value: location.code,
    })),
);
const cities = computed(
    () =>
        props.locations
            .find((location) => location.code === form.state)
            ?.cities.map((city) => city.name) || [],
);
const pendingOwners = computed(() => (props.pet?.caretakers || []).filter(
    owner => owner.pivot?.status === "pending" && form.owner_ids?.includes(owner.id),
));
function useShelterLocation() {
    const shelter = props.shelterOptions.find(shelter => shelter.value === form.shelter_id);
    if (props.administrative && shelter) {
        form.city = shelter.city || "";
        form.state = shelter.state || "";
    }
}
function useOwnerLocation(value) {
    if (value) {
        form.city = props.city || "";
        form.state = props.state || "";
    }
}
const opts = [
    "Brincalhão",
    "Calmo",
    "Carinhoso",
    "Curioso",
    "Energético",
    "Sociável",
    "Tímido",
    "Protetor",
];

function clearPhotos() {
    gallery.value.forEach((photo) => {
        if (photo.file) URL.revokeObjectURL(photo.url);
    });
    gallery.value = [];
}
function photoList(value) {
    if (Array.isArray(value)) return value;
    if (typeof value === "string") {
        try {
            return photoList(JSON.parse(value));
        } catch {
            return value ? [value] : [];
        }
    }
    return value && typeof value === "object" ? Object.values(value) : [];
}
function reset() {
    clearPhotos();
    removedPaths.value = [];
    tab.value = "details";
    deleteDialog.value = false;
    const pet = props.pet;
    Object.assign(form, {
        name: pet?.name || "",
        owner_ids: (pet?.caretakers || []).map((owner) => owner.id),
        species: pet?.species || "dog",
        breed: pet?.breed || "",
        size: pet?.size || "medium",
        sex: pet?.sex || "male",
        city: pet?.city || props.city || "",
        state:
            pet?.state || (pet?.city === props.city ? props.state : "") || "",
        lives_with_owner: pet
            ? Boolean(pet.lives_with_owner)
            : Boolean(props.city && props.state),
        temperament: (pet?.temperament || "").split(", ").filter(Boolean),
        description: pet?.description || "",
        shelter_id: pet?.shelter_id || null,
        status: pet?.status || "available",
    });
    useShelterLocation();
    if (!props.administrative) {
        useOwnerLocation(form.lives_with_owner);
        ownerOptions.value = pet?.caretakers || [];
        ownerSearch.value = "";
        loadOwners();
    }
    const paths = [pet?.image_path, ...photoList(pet?.gallery_paths)].filter(
        Boolean,
    );
    const urls = [pet?.image_url, ...photoList(pet?.gallery_urls)].filter(
        Boolean,
    );
    gallery.value = paths.map((path, index) => ({
        key: path,
        path,
        url: urls[index],
    }));
}
watch(
    () => [props.modelValue, props.pet],
    () => {
        if (props.modelValue) reset();
        else clearPhotos();
    },
    { immediate: true },
);
onBeforeUnmount(clearPhotos);
function makeCover(index) {
    gallery.value.unshift(...gallery.value.splice(index, 1));
}
function removePhoto(index) {
    if (gallery.value.length === 1)
        return notify("O pet precisa manter pelo menos uma foto.", "error");
    const [photo] = gallery.value.splice(index, 1);
    if (photo.file) URL.revokeObjectURL(photo.url);
    else removedPaths.value.push(photo.path);
}
function add(event) {
    const selected = Array.from(event.target.files || []);
    event.target.value = "";
    if (
        selected.some(
            (file) =>
                !["image/jpeg", "image/png", "image/webp"].includes(
                    file.type,
                ) || file.size > 5 * 1024 * 1024,
        )
    )
        return notify(
            "Escolha fotos JPG, PNG ou WebP de at\u00e9 5 MB.",
            "error",
        );
    const available = Math.max(0, 10 - gallery.value.length);
    if (selected.length > available)
        notify("O limite da galeria \u00e9 de 10 fotos.", "error");
    selected.slice(0, available).forEach((file) => {
        const url = URL.createObjectURL(file);
        gallery.value.push({ key: url, url, file });
    });
}
async function save() {
    if (!props.pet && !gallery.value.length) {
        tab.value = "gallery";
        notify("Adicione pelo menos uma foto para criar o pet.", "error");
        return;
    }
    saving.value = true;
    try {
        const data = new FormData();
        Object.entries(form).forEach(([key, value]) => {
            if (key === "owner_ids") {
                if (!props.administrative)
                    data.append(key, JSON.stringify(value));
                return;
            }
            if (
                props.administrative &&
                ["lives_with_owner"].includes(key)
            )
                return;
            if (!props.administrative && ["shelter_id", "status"].includes(key))
                return;
            data.append(
                key,
                typeof value === "boolean"
                    ? value
                        ? "1"
                        : "0"
                    : Array.isArray(value)
                      ? value.join(", ")
                      : (value ?? ""),
            );
        });
        gallery.value
            .filter((photo) => photo.file)
            .forEach((photo) => data.append("photos[]", photo.file));
        removedPaths.value.forEach((path) =>
            data.append("removed_photo_paths[]", path),
        );
        if (gallery.value[0]?.file) data.append("cover_photo_index", "0");
        else if (gallery.value[0]?.path)
            data.append("cover_photo_path", gallery.value[0].path);
        if (props.pet) data.append("_method", "PUT");
        const endpoint = props.administrative
            ? "/api/pets"
            : "/api/profile/pets";
        const response = await fetch(
            props.pet ? endpoint + "/" + props.pet.id : endpoint,
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN":
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content || "",
                },
                body: data,
            },
        );
        const result = await response.json();
        if (!response.ok)
            return notify(result.message || "Erro ao salvar.", "error");
        emit("saved", result.data);
        emit("update:modelValue", false);
    } catch {
        notify("Erro ao salvar. Tente novamente.", "error");
    } finally {
        saving.value = false;
    }
}
</script>
<template>
    <v-dialog
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
        max-width="900"
        scrollable
        ><v-card rounded="xl"
            ><v-card-title class="pa-6 pb-3 d-flex align-center"
                ><v-avatar color="primary" variant="tonal" class="mr-3"
                    ><v-icon icon="mdi-paw"
                /></v-avatar>
                <div>
                    {{ pet ? `Configurar ${pet.name}` : "Adicionar pet" }}
                    <div
                        class="text-caption text-medium-emphasis font-weight-regular"
                    >
                        Organize os dados e as fotos do pet.
                    </div>
                </div></v-card-title
            ><v-tabs v-model="tab" color="primary" grow
                ><v-tab value="details" prepend-icon="mdi-text-box-outline"
                    >Dados do pet</v-tab
                ><v-tab
                    value="gallery"
                    prepend-icon="mdi-image-multiple-outline"
                    >Galeria de fotos</v-tab
                ></v-tabs
            ><v-divider /><v-card-text class="pa-6"
                ><div>
                    <div v-show="tab === 'details'">
                        <v-row
                            ><v-col cols="12" md="7"
                                ><v-text-field
                                    v-model="form.name"
                                    label="Nome"
                                    variant="outlined" /><v-text-field
                                    v-model="form.breed"
                                    label="Raça / bio curta"
                                    variant="outlined" /><v-textarea
                                    v-model="form.description"
                                    label="Descrição / história"
                                    rows="5"
                                    variant="outlined" /></v-col
                            ><v-col cols="12" md="5"
                                ><v-card
                                    class="pa-4 h-100"
                                    rounded="lg"
                                    variant="tonal"
                                    ><b>Como este pet será apresentado</b>
                                    <p
                                        class="text-caption text-medium-emphasis mt-2"
                                    >
                                        Uma boa história ajuda a ONG e as
                                        famílias a conhecerem o pet antes da
                                        visita.
                                    </p></v-card
                                ></v-col
                            ><v-col cols="6" md="4"
                                ><v-select
                                    v-model="form.species"
                                    :items="[
                                        { title: 'Cachorro', value: 'dog' },
                                        { title: 'Gato', value: 'cat' },
                                    ]"
                                    label="Espécie"
                                    variant="outlined" /></v-col
                            ><v-col cols="6" md="4"
                                ><v-select
                                    v-model="form.sex"
                                    :items="[
                                        { title: 'Macho', value: 'male' },
                                        {
                                            title: 'Fêmea',
                                            value: 'female',
                                        },
                                    ]"
                                    label="Sexo"
                                    variant="outlined" /></v-col
                            ><v-col cols="12" md="4"
                                ><v-select
                                    v-model="form.size"
                                    :items="[
                                        {
                                            title: 'Pequeno',
                                            value: 'small',
                                        },
                                        {
                                            title: 'Médio',
                                            value: 'medium',
                                        },
                                        { title: 'Grande', value: 'large' },
                                    ]"
                                    label="Porte"
                                    variant="outlined" /></v-col
                            ><v-col v-if="!administrative" cols="12">
                                <div class="text-subtitle-2 mb-2">Donos</div>
                                <v-chip
                                    color="primary"
                                    variant="tonal"
                                    prepend-icon="mdi-account-heart"
                                    class="mb-3"
                                    >{{
                                        pet?.owner?.name || ownerName || "Você"
                                    }}</v-chip
                                >
                                <v-autocomplete
                                    v-model="form.owner_ids"
                                    v-model:search="ownerSearch"
                                    :items="ownerOptions"
                                    item-title="name"
                                    item-value="id"
                                    label="Adicionar donos"
                                    placeholder="Buscar pessoas pelo nome"
                                    multiple
                                    chips
                                    closable-chips
                                    no-filter
                                    :loading="loadingOwners"
                                    variant="outlined"
                                    hint="As pessoas receberão um convite. Após aceitarem, poderão editar o pet e ele aparecerá no perfil delas."
                                    persistent-hint
                                    no-data-text="Nenhuma pessoa encontrada"
                                >
                                    <template #item="{ props: itemProps, item }"
                                        ><v-list-item
                                            v-bind="itemProps"
                                            :subtitle="
                                                [item.raw.city, item.raw.state]
                                                    .filter(Boolean)
                                                    .join(', ')
                                            "
                                    /></template>
                                </v-autocomplete>
                                <p v-for="owner in pendingOwners" :key="owner.id" class="text-caption mt-2 text-medium-emphasis">
                                    Solicitação feita — aguardando confirmação de {{ owner.name }}.
                                </p>
                            </v-col>
                            <v-col v-if="!administrative" cols="12">
                                <v-checkbox
                                    v-model="form.lives_with_owner"
                                    label="Mora comigo"
                                    color="primary"
                                    hide-details
                                    @update:model-value="useOwnerLocation"
                                />
                                <p
                                    v-if="form.lives_with_owner"
                                    class="text-caption text-medium-emphasis"
                                >
                                    Usar minha localização: {{ form.city }} /
                                    {{ form.state }}
                                </p>
                            </v-col>
                            <v-col v-if="administrative" cols="12">
                                <v-select
                                    v-model="form.shelter_id"
                                    :items="[{ title: 'Nenhuma', value: null }, ...shelterOptions]"
                                    label="Sede"
                                    @update:model-value="useShelterLocation"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col
                                v-if="administrative || !form.lives_with_owner"
                                cols="12"
                                md="4"
                            >
                                <v-select
                                    v-model="form.state"
                                    :items="states"
                                    :disabled="administrative && !!form.shelter_id"
                                    label="Estado (UF)"
                                    variant="outlined"
                                    @update:model-value="form.city = ''"
                                />
                            </v-col>
                            <v-col
                                v-if="administrative || !form.lives_with_owner"
                                cols="12"
                                md="8"
                            >
                                <v-select
                                    v-model="form.city"
                                    :items="cities"
                                    label="Cidade"
                                    :disabled="!form.state || (administrative && !!form.shelter_id)"
                                    variant="outlined"
                                />
                            </v-col>
                            <v-col cols="12" md="6"
                                ><v-select
                                    v-model="form.temperament"
                                    :items="opts"
                                    label="Temperamento"
                                    multiple
                                    chips
                                    closable-chips
                                    variant="outlined" /></v-col
                            ><v-col v-if="administrative" cols="12" md="6"
                                ><v-select
                                    v-model="form.status"
                                    :items="[
                                        {
                                            title: 'Disponível',
                                            value: 'available',
                                        },
                                        {
                                            title: 'Em processo',
                                            value: 'in_process',
                                        },
                                        {
                                            title: 'Adotado',
                                            value: 'adopted',
                                        },
                                    ]"
                                    label="Situação"
                                    variant="outlined" /></v-col
                        ></v-row>
                    </div>
                    <div v-show="tab === 'gallery'">
                        <div
                            class="d-flex align-center justify-space-between mb-4"
                        >
                            <div>
                                <div class="text-h6">
                                    Fotos do
                                    {{ pet?.name || form.name || "pet" }}
                                </div>
                                <div class="text-caption text-medium-emphasis">
                                    A primeira foto é a capa.
                                </div>
                            </div>
                            <v-btn
                                color="primary"
                                prepend-icon="mdi-image-plus"
                                :disabled="gallery.length >= 10"
                                @click="input?.click()"
                                >Adicionar fotos</v-btn
                            >
                        </div>
                        <input
                            ref="input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="d-none"
                            @change="add"
                        />
                        <v-card
                            v-if="!gallery.length"
                            rounded="xl"
                            variant="tonal"
                            class="pa-8 text-center"
                            ><v-icon
                                icon="mdi-image-multiple-outline"
                                size="40"
                                color="primary"
                            />
                            <div class="text-subtitle-1 mt-3">
                                Adicione as fotos do seu pet
                            </div>
                            <p class="text-caption text-medium-emphasis">
                                A primeira foto será a capa. Escolha até 10
                                fotos de até 5 MB cada.
                            </p></v-card
                        >
                        <v-row v-else dense
                            ><v-col
                                v-for="(photo, index) in gallery"
                                :key="photo.key"
                                cols="6"
                                md="4"
                                ><v-card class="photo-card" rounded="xl"
                                    ><v-img :src="photo.url" height="165" cover
                                        ><div class="photo-actions">
                                            <v-chip
                                                v-if="index === 0"
                                                color="primary"
                                                variant="flat"
                                                size="small"
                                                prepend-icon="mdi-star"
                                                >Foto de capa</v-chip
                                            ><v-spacer /><v-btn
                                                v-if="index !== 0"
                                                icon="mdi-star-outline"
                                                size="small"
                                                color="white"
                                                variant="flat"
                                                title="Definir como capa"
                                                aria-label="Definir como capa"
                                                @click="makeCover(index)"
                                            /><v-btn
                                                icon="mdi-delete-outline"
                                                size="small"
                                                color="error"
                                                variant="flat"
                                                title="Remover foto"
                                                aria-label="Remover foto"
                                                @click="removePhoto(index)"
                                            /></div
                                    ></v-img>
                                    <div class="px-3 py-2 text-caption">
                                        {{
                                            index === 0
                                                ? "Capa atual"
                                                : photo.file
                                                  ? "Nova foto"
                                                  : "Foto cadastrada"
                                        }}
                                    </div></v-card
                                ></v-col
                            ></v-row
                        >
                    </div>
                </div></v-card-text
            ><v-card-actions class="pa-6 pt-0 flex-wrap"
                ><v-btn
                    v-if="pet && canDelete"
                    color="error"
                    prepend-icon="mdi-delete-outline"
                    :disabled="saving"
                    @click="deleteDialog = true"
                    >Excluir pet</v-btn
                ><v-spacer /><v-btn
                    variant="text"
                    @click="emit('update:modelValue', false)"
                    >Cancelar</v-btn
                ><v-btn
                    color="primary"
                    prepend-icon="mdi-content-save-outline"
                    :loading="saving"
                    @click="save"
                    >Salvar alterações</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
    <v-dialog v-model="deleteDialog" max-width="440" :persistent="deleting">
        <v-card rounded="xl" title="Excluir pet?">
            <v-card-text
                >Excluir {{ pet?.name }} remove o pet dos perfis de todos os
                donos. Esta ação não pode ser desfeita.</v-card-text
            >
            <v-card-actions class="pa-4"
                ><v-spacer /><v-btn
                    :disabled="deleting"
                    @click="deleteDialog = false"
                    >Cancelar</v-btn
                ><v-btn
                    color="error"
                    variant="flat"
                    :loading="deleting"
                    @click="deletePet"
                    >Excluir pet</v-btn
                ></v-card-actions
            >
        </v-card>
    </v-dialog>
</template>
<style scoped>
.photo-card {
    overflow: hidden;
    border: 1px solid rgba(var(--v-theme-primary), 0.15);
}
.photo-actions {
    height: 100%;
    display: flex;
    align-items: start;
    gap: 6px;
    padding: 10px;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.45), transparent 42%);
}
</style>
