<script setup>
import { onMounted, ref } from "vue";
import PetCard from "../../components/pets/PetCard.vue";
import PetFilters from "../../components/pets/PetFilters.vue";
import { usePetsStore } from "../../stores/pets";

const store = usePetsStore();
const filters = ref({ search: "", species: null, size: null, shelter: null });

function search() {
    store.fetchPets(filters.value);
}

onMounted(search);
</script>

<template>
    <section class="explore-hero text-white">
        <v-container class="py-10 py-md-13">
            <v-chip color="white" variant="flat" class="mb-5 font-weight-bold">
                <v-icon start icon="mdi-paw" color="primary" />
                ADOÇÃO RESPONSÁVEL
            </v-chip>
            <h1 class="text-h3 text-md-h2 font-weight-black">Encontre seu próximo melhor amigo</h1>
            <p class="text-h6 explore-copy mt-4 mb-0">Use os filtros para descobrir pets que combinam com sua rotina, sua casa e seu coração.</p>
        </v-container>
    </section>

    <v-container class="py-8 py-md-11">
        <div class="d-flex flex-wrap align-end justify-space-between ga-3 mb-6">
            <div>
                <div class="section-kicker mb-1">PETS PARA ADOÇÃO</div>
                <h2 class="text-h4 font-weight-bold">Todos os pets disponíveis</h2>
                <p class="text-medium-emphasis mt-1">Cada adoção abre espaço para salvar uma nova vida.</p>
            </div>
            <v-chip color="primary" variant="tonal" prepend-icon="mdi-paw">{{ store.pets.length }} pets encontrados</v-chip>
        </div>

        <PetFilters v-model="filters" class="filter-panel" @search="search" />

        <v-row class="mt-5">
            <v-col v-if="store.loading" cols="12" class="text-center pa-12"><v-progress-circular indeterminate color="primary" size="42" /></v-col>
            <v-col v-for="pet in store.pets" :key="pet.id" cols="12" sm="6" lg="4"><PetCard :pet="pet" /></v-col>
            <v-col v-if="!store.loading && !store.pets.length" cols="12"><v-alert type="info" variant="tonal" rounded="lg">Nenhum pet foi encontrado com estes filtros.</v-alert></v-col>
        </v-row>
    </v-container>
</template>

<style scoped>
.explore-hero { background: linear-gradient(110deg, #063f3a, #0d8177); }
.explore-copy { max-width: 680px; color: #d8f4ef; }
</style>
