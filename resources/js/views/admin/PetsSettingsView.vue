<script setup>
import { computed, onMounted, ref, watch } from "vue";
import ShelterManagerDialog from "../../components/admin/ShelterManagerDialog.vue";
import { notify } from "../../stores/ui";

const pets = ref([]),
    shelters = ref([]),
    locations = ref([]),
    search = ref(""),
    searchOpen = ref(false),
    filterDialog = ref(false),
    optionsMenu = ref(false),
    shelterDialog = ref(false),
    shelterManager = ref(false),
    petDialog = ref(false),
    petTab = ref("details"),
    editingPet = ref(null),
    petForm = ref({}),
    pendingPhotos = ref([]),
    removedPhotoPaths = ref([]),
    coverPhotoPath = ref(null),
    saving = ref(false),
    filters = ref({ status: null, species: null, shelter: null });
const headers = [
    { title: "Pet", key: "name" },
    { title: "Sede", key: "shelter" },
    { title: "Situação", key: "status" },
    { title: "Interessados", key: "adoptions_count" },
    { title: "Ações", key: "actions", sortable: false },
    { title: "", key: "data-table-expand" },
];
const shelterOptions = computed(() =>
    shelters.value.map((s) => ({ title: s.name, value: s.id })),
);
const activeFilters = computed(
    () => Object.values(filters.value).filter(Boolean).length,
);
const samplePhotos = (species) =>
    species === "cat"
        ? [
              "https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1495360010541-f48722b34f7d?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=1400&q=90",
          ]
        : [
              "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=1400&q=90",
          ];
function photoList(value) {
    if (Array.isArray(value)) return value;
    if (typeof value === "string") {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return value ? [value] : [];
        }
    }
    return value && typeof value === "object" ? Object.values(value) : [];
}
const galleryPhotos = computed(() => {
    const paths = [
        editingPet.value?.image_path,
        ...photoList(editingPet.value?.gallery_paths),
    ].filter(Boolean);
    const urls = [
        editingPet.value?.image_url,
        ...photoList(editingPet.value?.gallery_urls),
    ].filter(Boolean);
    const stored = paths
        .map((path, index) => ({ path, url: urls[index] }))
        .filter((photo) => !removedPhotoPaths.value.includes(photo.path));
    if (coverPhotoPath.value) {
        const cover = stored.find(
            (photo) => photo.path === coverPhotoPath.value,
        );
        if (cover)
            return [
                cover,
                ...stored.filter((photo) => photo.path !== cover.path),
                ...pendingPhotos.value.map((photo) => ({
                    url: URL.createObjectURL(photo),
                })),
            ].map((photo) => photo.url);
    }
    return [
        ...(stored.length
            ? stored.map((photo) => photo.url)
            : samplePhotos(editingPet.value?.species)),
        ...pendingPhotos.value.map((photo) => URL.createObjectURL(photo)),
    ];
});
const filteredPets = computed(() =>
    pets.value.filter((pet) => {
        const term = search.value.toLowerCase();
        return (
            (!term ||
                [pet.name, pet.city, pet.shelter?.name]
                    .filter(Boolean)
                    .join(" ")
                    .toLowerCase()
                    .includes(term)) &&
            (!filters.value.status || pet.status === filters.value.status) &&
            (!filters.value.species || pet.species === filters.value.species) &&
            (!filters.value.shelter || pet.shelter_id === filters.value.shelter)
        );
    }),
);
async function load() {
    const [p, s, l] = await Promise.all([
        fetch("/api/admin/pets", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        }),
        fetch("/api/admin/shelters", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        }),
        fetch("/api/locations", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        }),
    ]);
    if (p.ok) pets.value = (await p.json()).data;
    if (s.ok) shelters.value = (await s.json()).data;
    if (l.ok) locations.value = (await l.json()).data;
}
function editPet(pet) {
    editingPet.value = pet;
    pendingPhotos.value = [];
    removedPhotoPaths.value = [];
    coverPhotoPath.value = null;
    petTab.value = "details";
    petForm.value = {
        name: pet.name,
        species: pet.species,
        breed: pet.breed || "",
        size: pet.size,
        sex: pet.sex,
        city: pet.city,
        shelter_id: pet.shelter_id,
        temperament: pet.temperament,
        description: pet.description,
        status: pet.status,
    };
    petDialog.value = true;
}
function clearFilters() {
    filters.value = { status: null, species: null, shelter: null };
    filterDialog.value = false;
}
function pickPhoto() {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/jpeg,image/png,image/webp";
    input.multiple = true;
    input.onchange = () => {
        pendingPhotos.value.push(
            ...Array.from(input.files || []).slice(
                0,
                8 - pendingPhotos.value.length,
            ),
        );
    };
    input.click();
}
function removePhoto(index) {
    const paths = [
        editingPet.value?.image_path,
        ...photoList(editingPet.value?.gallery_paths),
    ].filter(Boolean);
    const availablePaths = paths.filter(
        (path) => !removedPhotoPaths.value.includes(path),
    );
    if (index < availablePaths.length) {
        if (availablePaths.length <= 1) {
            window.alert("O pet precisa manter pelo menos uma foto.");
            return;
        }
        removedPhotoPaths.value.push(availablePaths[index]);
    } else pendingPhotos.value.splice(index - availablePaths.length, 1);
    petTab.value = "details";
    setTimeout(() => {
        petTab.value = "gallery";
    }, 0);
}
function makeCover(index) {
    const paths = [
        editingPet.value?.image_path,
        ...photoList(editingPet.value?.gallery_paths),
    ].filter(Boolean);
    if (paths[index]) coverPhotoPath.value = paths[index];
}
async function savePet() {
    if (!editingPet.value) return;
    saving.value = true;
    const form = new FormData();
    Object.entries(petForm.value).forEach(([key, value]) =>
        form.append(key, value ?? ""),
    );
    pendingPhotos.value.forEach((photo) => form.append("photos[]", photo));
    removedPhotoPaths.value.forEach((path) =>
        form.append("removed_photo_paths[]", path),
    );
    if (coverPhotoPath.value)
        form.append("cover_photo_path", coverPhotoPath.value);
    form.append("_method", "PUT");
    const response = await fetch(`/api/pets/${editingPet.value.id}`, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN":
                document.querySelector('meta[name="csrf-token"]')?.content ||
                "",
        },
        body: form,
    });
    saving.value = false;
    if (response.ok) {
        petDialog.value = false;
        await load();
        notify("Configurações do pet atualizadas.");
    } else {
        notify(
            (await response.json().catch(() => ({}))).message ||
                `Erro ${response.status} ao salvar.`,
            "error",
        );
    }
}
onMounted(() => {
    load();
});
watch(shelterDialog, (open) => {
    if (open) {
        shelterDialog.value = false;
        shelterManager.value = true;
    }
});
</script>
<template>
    <v-container class="py-10"
        ><div class="section-kicker">ADMINISTRAÇÃO</div>
        <h1 class="text-h4 font-weight-bold">Configurações de pets</h1>
        <p class="text-medium-emphasis mt-2 mb-7">
            Gerencie pets, interessados e as sedes de acolhimento em um único
            lugar.
        </p>
        <v-card rounded="xl" class="overflow-hidden"
            ><div class="d-flex align-center ga-2 pa-4 flex-wrap">
                <v-btn
                    icon="mdi-magnify"
                    variant="text"
                    color="primary"
                    @click="searchOpen = !searchOpen"
                /><v-expand-x-transition
                    ><v-text-field
                        v-if="searchOpen"
                        v-model="search"
                        class="admin-search"
                        label="Buscar pet, cidade ou sede"
                        density="compact"
                        hide-details
                        variant="outlined"
                        clearable /></v-expand-x-transition
                ><v-spacer /><v-badge
                    :content="activeFilters"
                    :model-value="activeFilters > 0"
                    color="error"
                    ><v-btn
                        prepend-icon="mdi-filter-variant"
                        :variant="activeFilters ? 'tonal' : 'outlined'"
                        color="primary"
                        @click="filterDialog = true"
                        >Filtros</v-btn
                    ></v-badge
                ><v-menu v-model="optionsMenu"
                    ><template #activator="{ props }"
                        ><v-btn
                            v-bind="props"
                            prepend-icon="mdi-dots-horizontal"
                            variant="outlined"
                            >Opções</v-btn
                        ></template
                    ><v-list
                        ><v-list-item
                            prepend-icon="mdi-home-city-outline"
                            title="Gerenciar sedes"
                            @click="
                                shelterDialog = true;
                                optionsMenu = false;
                            " /><v-list-item
                            prepend-icon="mdi-refresh"
                            title="Atualizar tabela"
                            @click="
                                load();
                                optionsMenu = false;
                            " /></v-list
                ></v-menu>
            </div>
            <v-data-table
                :headers="headers"
                :items="filteredPets"
                item-value="id"
                show-expand
                hover
                ><template #item.name="{ item }"
                    ><div class="d-flex align-center ga-3">
                        <v-avatar color="primary" variant="tonal" size="36"
                            ><v-icon icon="mdi-paw"
                        /></v-avatar>
                        <div>
                            <b>{{ item.name }}</b>
                            <div class="text-caption">
                                {{ item.city }} ·
                                {{
                                    item.species === "cat" ? "Gato" : "Cachorro"
                                }}
                            </div>
                        </div>
                    </div></template
                ><template #item.shelter="{ item }"
                    ><v-chip size="small" variant="tonal">{{
                        item.shelter?.name || "Sem sede"
                    }}</v-chip></template
                ><template #item.status="{ item }"
                    ><v-chip
                        size="small"
                        :color="
                            item.status === 'available'
                                ? 'success'
                                : item.status === 'adopted'
                                  ? 'secondary'
                                  : 'warning'
                        "
                        >{{
                            item.status === "available"
                                ? "Disponível"
                                : item.status === "adopted"
                                  ? "Adotado"
                                  : "Em processo"
                        }}</v-chip
                    ></template
                ><template #item.adoptions_count="{ item }"
                    ><v-chip color="secondary" size="small" variant="tonal"
                        >{{ item.adoptions_count }} interessados</v-chip
                    ></template
                ><template #item.actions="{ item }"
                    ><v-btn
                        icon="mdi-cog-outline"
                        color="primary"
                        size="small"
                        variant="tonal"
                        @click="editPet(item)" /></template
                ><template #expanded-row="{ columns, item }"
                    ><tr>
                        <td :colspan="columns.length" class="pa-0">
                            <div class="pa-5 expanded-pet">
                                <b>Interessados em {{ item.name }}</b
                                ><v-table density="compact"
                                    ><tbody>
                                        <tr
                                            v-for="a in item.adoptions"
                                            :key="a.id"
                                        >
                                            <td>
                                                {{
                                                    a.user?.name ||
                                                    a.applicant_name
                                                }}
                                            </td>
                                            <td>
                                                {{ a.user?.email || a.email }}
                                            </td>
                                            <td>{{ a.status }}</td>
                                        </tr>
                                        <tr v-if="!item.adoptions.length">
                                            <td class="text-medium-emphasis">
                                                Nenhuma demonstração de
                                                interesse ainda.
                                            </td>
                                        </tr>
                                    </tbody></v-table
                                >
                            </div>
                        </td>
                    </tr></template
                ></v-data-table
            ></v-card
        >
        <v-dialog v-model="filterDialog" max-width="560"
            ><v-card rounded="xl"
                ><v-card-title>Filtros de pets</v-card-title
                ><v-card-text
                    ><v-select
                        v-model="filters.status"
                        :items="[
                            { title: 'Disponível', value: 'available' },
                            { title: 'Em processo', value: 'in_process' },
                            { title: 'Adotado', value: 'adopted' },
                        ]"
                        label="Situação"
                        clearable
                        variant="outlined" /><v-select
                        v-model="filters.species"
                        :items="[
                            { title: 'Cachorro', value: 'dog' },
                            { title: 'Gato', value: 'cat' },
                        ]"
                        label="Espécie"
                        clearable
                        variant="outlined" /><v-select
                        v-model="filters.shelter"
                        :items="shelterOptions"
                        label="Sede"
                        clearable
                        variant="outlined" /></v-card-text
                ><v-card-actions
                    ><v-btn @click="clearFilters">Limpar</v-btn
                    ><v-spacer /><v-btn
                        color="primary"
                        @click="filterDialog = false"
                        >Aplicar</v-btn
                    ></v-card-actions
                ></v-card
            ></v-dialog
        >
        <v-dialog v-model="petDialog" max-width="900" scrollable
            ><v-card rounded="xl"
                ><v-card-title class="pa-6 pb-3 d-flex align-center"
                    ><v-avatar color="primary" variant="tonal" class="mr-3"
                        ><v-icon icon="mdi-paw"
                    /></v-avatar>
                    <div>
                        Configurar {{ editingPet?.name }}
                        <div
                            class="text-caption text-medium-emphasis font-weight-regular"
                        >
                            Organize os dados e as fotos do pet.
                        </div>
                    </div></v-card-title
                ><v-tabs v-model="petTab" color="primary" grow
                    ><v-tab value="details" prepend-icon="mdi-text-box-outline"
                        >Dados do pet</v-tab
                    ><v-tab
                        value="gallery"
                        prepend-icon="mdi-image-multiple-outline"
                        >Galeria de fotos</v-tab
                    ></v-tabs
                ><v-divider /><v-card-text class="pa-6"
                    ><div>
                        <div v-show="petTab === 'details'">
                            <v-row
                                ><v-col cols="12" md="7"
                                    ><v-text-field
                                        v-model="petForm.name"
                                        label="Nome"
                                        variant="outlined" /><v-text-field
                                        v-model="petForm.breed"
                                        label="Raça / bio curta"
                                        variant="outlined" /><v-textarea
                                        v-model="petForm.description"
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
                                        v-model="petForm.species"
                                        :items="[
                                            { title: 'Cachorro', value: 'dog' },
                                            { title: 'Gato', value: 'cat' },
                                        ]"
                                        label="Espécie"
                                        variant="outlined" /></v-col
                                ><v-col cols="6" md="4"
                                    ><v-select
                                        v-model="petForm.sex"
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
                                        v-model="petForm.size"
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
                                ><v-col cols="12" md="6"
                                    ><v-text-field
                                        v-model="petForm.city"
                                        label="Cidade"
                                        variant="outlined" /></v-col
                                ><v-col cols="12" md="6"
                                    ><v-select
                                        v-model="petForm.shelter_id"
                                        :items="shelterOptions"
                                        label="Sede"
                                        variant="outlined" /></v-col
                                ><v-col cols="12" md="6"
                                    ><v-text-field
                                        v-model="petForm.temperament"
                                        label="Temperamento"
                                        variant="outlined" /></v-col
                                ><v-col cols="12" md="6"
                                    ><v-select
                                        v-model="petForm.status"
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
                        <div v-show="petTab === 'gallery'">
                            <div
                                class="d-flex align-center justify-space-between mb-4"
                            >
                                <div>
                                    <div class="text-h6">
                                        Fotos do {{ editingPet?.name }}
                                    </div>
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        A primeira foto é a capa.
                                    </div>
                                </div>
                                <v-btn
                                    color="primary"
                                    prepend-icon="mdi-image-plus"
                                    @click="pickPhoto"
                                    >Adicionar fotos</v-btn
                                >
                            </div>
                            <v-row dense
                                ><v-col
                                    v-for="(photo, index) in galleryPhotos"
                                    :key="photo"
                                    cols="6"
                                    md="4"
                                    ><v-card class="photo-card" rounded="xl"
                                        ><v-img :src="photo" height="165" cover
                                            ><div class="photo-actions">
                                                <v-chip
                                                    v-if="index === 0"
                                                    color="primary"
                                                    size="small"
                                                    prepend-icon="mdi-star"
                                                    >Foto de capa</v-chip
                                                ><v-spacer /><v-btn
                                                    v-if="index !== 0"
                                                    icon="mdi-star-outline"
                                                    size="small"
                                                    color="white"
                                                    variant="flat"
                                                    @click="makeCover(index)"
                                                /><v-btn
                                                    icon="mdi-delete-outline"
                                                    size="small"
                                                    color="error"
                                                    variant="flat"
                                                    @click="removePhoto(index)"
                                                /></div
                                        ></v-img>
                                        <div class="px-3 py-2 text-caption">
                                            {{
                                                index === 0
                                                    ? "Capa atual"
                                                    : "Foto cadastrada"
                                            }}
                                        </div></v-card
                                    ></v-col
                                ></v-row
                            >
                        </div>
                    </div></v-card-text
                ><v-card-actions class="pa-6 pt-0"
                    ><v-spacer /><v-btn
                        variant="text"
                        @click="petDialog = false"
                        >Cancelar</v-btn
                    ><v-btn
                        color="primary"
                        prepend-icon="mdi-content-save-outline"
                        :loading="saving"
                        @click="savePet"
                        >Salvar alterações</v-btn
                    ></v-card-actions
                ></v-card
            ></v-dialog
        >
        <ShelterManagerDialog v-model="shelterManager" :locations="locations" />
    </v-container>
</template>
<style scoped>
.admin-search {
    max-width: 310px;
}
.expanded-pet {
    background: rgba(22, 112, 99, 0.06);
}
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
