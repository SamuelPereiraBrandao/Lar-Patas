<script setup>
import { computed, onMounted, ref } from "vue";
import { isLogged, notify } from "../../stores/ui";
import { request } from "../../stores/requests";

const filters = defineModel({
    default: () => ({
        search: "",
        species: null,
        size: null,
        shelter: null,
        favorites: false,
    }),
});
defineProps({
    savedSearches: { type: Array, default: () => [] },
    loadingSearches: Boolean,
    loading: Boolean,
});
const emit = defineEmits(["search", "save", "apply-saved"]);
const shelters = ref([]);
const shelterItems = computed(() =>
    shelters.value.map((shelter) => ({
        title: `${shelter.name} — ${shelter.district || shelter.city}, ${shelter.state}`,
        value: shelter.id,
    })),
);
function clearFilters() {
    filters.value = {
        search: "",
        species: null,
        size: null,
        shelter: null,
        favorites: false,
    };
    emit("search");
}
function toggleFavorites() {
    filters.value.favorites = !filters.value.favorites;
    emit("search");
}
onMounted(async () => {
    try {
        shelters.value = (await request("/api/shelters")).data;
    } catch (error) {
        notify(error.message, "error");
    }
});
</script>

<template>
    <v-card rounded="xl" elevation="0" class="border pet-filters">
        <v-form class="pa-5 pa-md-6" @submit.prevent="emit('search')">
            <div class="d-flex align-center ga-3 mb-6">
                <v-avatar color="primary" variant="tonal" rounded="lg" size="44"
                    ><v-icon icon="mdi-tune-variant"
                /></v-avatar>
                <div>
                    <h3 class="text-subtitle-1 font-weight-bold">
                        Encontre seu companheiro
                    </h3>
                    <p class="text-body-2 text-medium-emphasis mb-0">
                        Combine os filtros para encontrar o pet que procura.
                    </p>
                </div>
            </div>
            <v-row dense>
                <v-col cols="12" md="4"
                    ><v-text-field
                        v-model="filters.search"
                        label="Nome ou cidade"
                        placeholder="Quem você quer encontrar?"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        hide-details
                        density="comfortable"
                        clearable
                /></v-col>
                <v-col cols="6" md="2"
                    ><v-select
                        v-model="filters.species"
                        :items="['Cachorro', 'Gato']"
                        label="Espécie"
                        clearable
                        variant="outlined"
                        hide-details
                        density="comfortable"
                /></v-col>
                <v-col cols="6" md="2"
                    ><v-select
                        v-model="filters.size"
                        :items="[
                            { title: 'Pequeno', value: 'small' },
                            { title: 'Médio', value: 'medium' },
                            { title: 'Grande', value: 'large' },
                        ]"
                        label="Porte"
                        clearable
                        variant="outlined"
                        hide-details
                        density="comfortable"
                /></v-col>
                <v-col cols="12" md="4"
                    ><v-select
                        v-model="filters.shelter"
                        :items="shelterItems"
                        label="Sede de acolhimento"
                        clearable
                        variant="outlined"
                        hide-details
                        density="comfortable"
                /></v-col>
            </v-row>
            <div class="filter-actions mt-5">
                <v-btn
                    v-if="isLogged"
                    :variant="filters.favorites ? 'tonal' : 'text'"
                    color="primary"
                    :prepend-icon="
                        filters.favorites
                            ? 'mdi-bookmark-check'
                            : 'mdi-bookmark-outline'
                    "
                    :aria-pressed="Boolean(filters.favorites)"
                    @click="toggleFavorites"
                    >Somente favoritos</v-btn
                >
                <div class="d-flex flex-wrap ga-2 ml-auto">
                    <v-btn variant="text" @click="clearFilters">Limpar</v-btn>
                    <v-btn
                        v-if="isLogged"
                        variant="outlined"
                        prepend-icon="mdi-bookmark-plus-outline"
                        @click="emit('save')"
                        >Salvar busca</v-btn
                    >
                    <v-btn
                        type="submit"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-magnify"
                        :loading="loading"
                        >Buscar pets</v-btn
                    >
                </div>
            </div>
        </v-form>
        <div v-if="isLogged" class="saved-searches px-5 px-md-6 py-4">
            <div class="d-flex align-center justify-space-between ga-3 mb-2">
                <span class="text-body-2 font-weight-bold"
                    ><v-icon
                        icon="mdi-bookmark-multiple-outline"
                        size="18"
                        class="mr-2"
                    />Buscas salvas</span
                >
                <v-btn
                    v-if="savedSearches.length"
                    to="/favoritos"
                    size="small"
                    variant="text"
                    color="primary"
                    >Gerenciar</v-btn
                >
            </div>
            <v-progress-linear
                v-if="loadingSearches"
                indeterminate
                color="primary"
                aria-label="Carregando buscas salvas"
            />
            <div v-else-if="savedSearches.length" class="d-flex flex-wrap ga-2">
                <v-chip
                    v-for="saved in savedSearches"
                    :key="saved.id"
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-magnify"
                    class="saved-search-chip"
                    @click="emit('apply-saved', saved)"
                    >{{ saved.name }}</v-chip
                >
            </div>
            <p v-else class="text-body-2 text-medium-emphasis mb-0">
                Salve uma busca para repetir seus filtros favoritos com um
                toque.
            </p>
        </div>
    </v-card>
</template>

<style scoped>
.filter-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.saved-searches {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-primary), 0.035);
}
.saved-search-chip {
    max-width: 100%;
}
.saved-search-chip :deep(.v-chip__content) {
    white-space: normal;
    overflow-wrap: anywhere;
}
.saved-search-chip {
    height: auto;
    min-height: 32px;
    padding-top: 6px;
    padding-bottom: 6px;
}
</style>
