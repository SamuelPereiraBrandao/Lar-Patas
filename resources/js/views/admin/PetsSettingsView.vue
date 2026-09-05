<script setup>
import { computed, onMounted, ref, watch } from "vue";
import PetEditorDialog from "../../components/pets/PetEditorDialog.vue";
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
    editingPet = ref(null),
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
    petDialog.value = true;
}
function clearFilters() {
    filters.value = { status: null, species: null, shelter: null };
    filterDialog.value = false;
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
        <PetEditorDialog
            v-model="petDialog"
            :pet="editingPet"
            administrative
            :shelter-options="shelterOptions"
            @saved="load"
        />
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
</style>
