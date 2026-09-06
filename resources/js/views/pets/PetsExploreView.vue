<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { request } from "../../stores/requests";
import { isLogged, notify } from "../../stores/ui";
import PetCard from "../../components/pets/PetCard.vue";
import PetFilters from "../../components/pets/PetFilters.vue";
import { usePetsStore } from "../../stores/pets";

const store = usePetsStore();
const filters = ref({
    search: "",
    species: null,
    size: null,
    shelter: null,
    favorites: false,
});
const visiblePets = computed(() =>
    filters.value.favorites
        ? store.pets.filter((pet) => pet.is_favorited)
        : store.pets,
);
const route = useRoute();
for (const key of Object.keys(filters.value)) {
    if (typeof route.query[key] === "string")
        filters.value[key] =
            key === "favorites"
                ? ["true", "1"].includes(route.query[key])
                : key === "shelter"
                  ? Number(route.query[key])
                  : route.query[key];
}
const saveOpen = ref(false),
    searchName = ref(""),
    savingSearch = ref(false);
const savedSearches = ref([]);
const loadingSearches = ref(false);
function applySavedSearch(saved) {
    const values = saved.filters || {};
    filters.value = {
        search: values.search || "",
        species:
            { dog: "Cachorro", cat: "Gato" }[values.species] ||
            values.species ||
            null,
        size:
            { Pequeno: "small", Médio: "medium", Grande: "large" }[
                values.size
            ] ||
            values.size ||
            null,
        shelter: values.shelter ? Number(values.shelter) : null,
        favorites: [true, 1, "1", "true"].includes(values.favorites),
    };
    search();
}
async function loadSavedSearches() {
    if (!isLogged.value) return;
    loadingSearches.value = true;
    try {
        savedSearches.value = (await request("/api/favorites")).searches || [];
    } catch (error) {
        notify(error.message, "error");
    } finally {
        loadingSearches.value = false;
    }
}
async function saveSearch() {
    if (savingSearch.value || !searchName.value.trim()) return;
    savingSearch.value = true;
    try {
        const result = await request("/api/saved-searches", "POST", {
            name: searchName.value,
            filters: filters.value,
        });
        savedSearches.value.unshift(result.data);
        saveOpen.value = false;
        notify("Busca salva em Favoritos e buscas.");
    } catch (error) {
        notify(error.message, "error");
    } finally {
        savingSearch.value = false;
    }
}

function search() {
    store.fetchPets(filters.value);
}

onMounted(search);
onMounted(loadSavedSearches);
</script>

<template>
    <v-container class="py-8 py-md-11">
        <div class="d-flex flex-wrap align-end justify-space-between ga-3 mb-6">
            <div>
                <div class="section-kicker mb-1">PETS PARA ADOÇÃO</div>
                <h1 class="text-h4 font-weight-bold">
                    {{
                        filters.favorites
                            ? "Meus pets favoritos"
                            : "Todos os pets disponíveis"
                    }}
                </h1>
                <p class="text-medium-emphasis mt-1">
                    Cada adoção abre espaço para salvar uma nova vida.
                </p>
            </div>
            <v-chip color="primary" variant="tonal" prepend-icon="mdi-paw"
                >{{ visiblePets.length }} pets encontrados</v-chip
            >
        </div>

        <PetFilters
            v-model="filters"
            class="filter-panel"
            :saved-searches="savedSearches"
            :loading-searches="loadingSearches"
            :loading="store.loading"
            @search="search"
            @apply-saved="applySavedSearch"
            @save="
                searchName = filters.search || 'Minha busca';
                saveOpen = true;
            "
        />
        <v-dialog v-model="saveOpen" max-width="440"
            ><v-card title="Salvar busca"
                ><v-card-text
                    ><v-text-field
                        v-model="searchName"
                        label="Nome da busca"
                        maxlength="80"
                        @keydown.enter="saveSearch" /></v-card-text
                ><v-card-actions
                    ><v-spacer /><v-btn @click="saveOpen = false"
                        >Cancelar</v-btn
                    ><v-btn
                        color="primary"
                        :loading="savingSearch"
                        :disabled="!searchName.trim()"
                        @click="saveSearch"
                        >Salvar</v-btn
                    ></v-card-actions
                ></v-card
            ></v-dialog
        >

        <v-row class="mt-5">
            <v-col v-if="store.loading" cols="12" class="text-center pa-12"
                ><v-progress-circular indeterminate color="primary" size="42"
            /></v-col>
            <v-col
                v-for="pet in visiblePets"
                :key="pet.id"
                cols="12"
                sm="6"
                lg="4"
                ><PetCard :pet="pet"
            /></v-col>
            <v-col v-if="!store.loading && !visiblePets.length" cols="12"
                ><v-alert type="info" variant="tonal" rounded="lg"
                    >Nenhum pet foi encontrado com estes filtros.</v-alert
                ></v-col
            >
        </v-row>
    </v-container>
</template>
