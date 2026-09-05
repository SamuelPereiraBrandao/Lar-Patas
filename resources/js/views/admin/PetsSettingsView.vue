<script setup>
import { computed, onMounted, ref, watch } from "vue";
import PetEditorDialog from "../../components/pets/PetEditorDialog.vue";
import ShelterManagerDialog from "../../components/admin/ShelterManagerDialog.vue";
import AdoptionPickupDialog from "../../components/admin/AdoptionPickupDialog.vue";
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
const pickupDialog = ref(false);
const pickupPet = ref(null);
const pickupAdoption = ref(null);
const releasing = ref(false);
function openPickup(pet, adoption, release = false) {
    pickupPet.value = pet;
    pickupAdoption.value = adoption;
    releasing.value = release;
    pickupDialog.value = true;
}
function adoptionLabel(adoption) {
    if (adoption.released_at) return "Adoção concluída";
    if (adoption.status === "approved" && adoption.pickup_at) return "Aguardando liberação";
    return { pending: "Interesse recebido", approved: "Aprovado", rejected: "Não selecionado" }[adoption.status] || "Em análise";
}
function canSchedule(pet, adoption) {
    return adoption.status === "pending" && adoption.user && pet.status !== "adopted" && pet.ownership_kind !== "guardian" && !pet.adoptions.some(item => item.status === "approved" && item.pickup_at && !item.released_at);
}
function pickupDate(adoption) {
    return new Date(adoption.pickup_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: adoption.pickup_timezone || 'America/Sao_Paulo' });
}
const headers = [
    { title: "Pet", key: "name" },
    { title: "Sede", key: "shelter" },
    { title: "Situação", key: "status" },
    { title: "Interessados", key: "adoptions_count" },
    { title: "Ações", key: "actions", sortable: false },
    { title: "", key: "data-table-expand" },
];
const shelterOptions = computed(() =>
    shelters.value.map((s) => ({ title: s.name, value: s.id, city: s.city, state: s.state })),
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
                ><v-spacer />
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    rounded="lg"
                    @click="editingPet = null; petDialog = true"
                    >Adicionar pet para adoção</v-btn
                ><v-badge
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
                                <div class="d-flex align-center ga-3 mb-4"><v-icon icon="mdi-account-heart-outline" color="primary" /><div><b>Um novo lar para {{ item.name }}</b><p class="text-caption text-medium-emphasis">Conheça os interessados e acompanhe a retirada.</p></div></div>
                                <div class="applicants-list">
                                    <v-card v-for="a in item.adoptions" :key="a.id" rounded="lg" elevation="0" class="applicant-card pa-4">
                                        <div class="d-flex align-center ga-3">
                                            <v-avatar size="56" color="primary"><v-img v-if="a.user?.avatar_url" :src="a.user.avatar_url" cover /><span v-else>{{ (a.user?.name || a.applicant_name)?.[0] }}</span></v-avatar>
                                            <div class="applicant-info"><router-link v-if="a.user" :to="`/perfil/${a.user.id}`" class="applicant-name">{{ a.user.name }}</router-link><b v-else>{{ a.applicant_name }}</b><p class="text-caption text-medium-emphasis"><v-icon icon="mdi-map-marker-outline" size="14" /> {{ [a.user?.city, a.user?.state].filter(Boolean).join(', ') || 'Localização não informada' }}</p><p class="text-caption text-medium-emphasis">{{ a.user?.email || a.email }}</p></div>
                                        </div>
                                        <div class="applicant-actions">
                                            <v-chip :color="a.released_at ? 'success' : a.status === 'approved' ? 'warning' : 'primary'" size="small" variant="tonal">{{ adoptionLabel(a) }}</v-chip>
                                            <v-btn v-if="canSchedule(item, a)" color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-home-heart" @click="openPickup(item, a)">Aprovar adoção</v-btn>
                                            <v-btn v-if="a.status === 'approved' && a.pickup_at && !a.released_at && item.status !== 'adopted'" color="primary" rounded="lg" prepend-icon="mdi-shield-key-outline" @click="openPickup(item, a, true)">Validar código e liberar</v-btn>
                                        </div>
                                        <div v-if="a.pickup_at" class="applicant-pickup text-body-2"><v-icon icon="mdi-calendar-clock-outline" size="18" /> {{ pickupDate(a) }} · {{ a.pickup_location }}<p v-if="a.pickup_message" class="text-medium-emphasis mt-1">{{ a.pickup_message }}</p></div>
                                    </v-card>
                                </div>
                                <p v-if="!item.adoptions.length" class="text-body-2 text-medium-emphasis">Nenhuma demonstração de interesse ainda.</p>
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
        <AdoptionPickupDialog v-model="pickupDialog" :pet="pickupPet" :adoption="pickupAdoption" :release="releasing" @saved="load" />
        <PetEditorDialog
            v-model="petDialog"
            :pet="editingPet"
            :locations="locations"
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
.applicants-list { display: grid; gap: 12px; }
.applicant-card { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; align-items: center; border: 1px solid rgba(var(--v-theme-on-surface), .08); }
.applicant-info { min-width: 0; overflow-wrap: anywhere; }
.applicant-name { color: inherit; text-decoration: none; font-weight: 700; }
.applicant-name:hover { color: rgb(var(--v-theme-primary)); text-decoration: underline; }
.applicant-actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: flex-end; }
.applicant-pickup { grid-column: 1 / -1; border-top: 1px solid rgba(var(--v-theme-on-surface), .08); padding-top: 12px; overflow-wrap: anywhere; white-space: pre-wrap; }
@media (max-width: 800px) { .applicant-card { grid-template-columns: minmax(0, 1fr); } .applicant-actions { justify-content: flex-start; } }
</style>
