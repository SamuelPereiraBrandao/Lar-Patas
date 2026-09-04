<script setup>
import { computed, onMounted, ref } from "vue";
import PetCard from "../../components/pets/PetCard.vue";
import PetFilters from "../../components/pets/PetFilters.vue";
import { usePetsStore } from "../../stores/pets";
import { isLogged, userRoles } from "../../stores/ui";

const store = usePetsStore();
const filters = ref({ search: "", species: null, size: null, shelter: null });
const ongDestination = computed(() => {
    if (!isLogged.value) return "/entrar";
    if (userRoles.value.includes("admin")) return "/admin/pets";
    if (userRoles.value.includes("donor")) return "/painel/doador";

    return "/painel";
});

onMounted(() => store.fetchPets());

function search() {
    store.fetchPets(filters.value);
}
</script>

<template>
    <section class="hero-gradient text-white">
        <v-container class="hero-content py-14 py-md-20">
            <v-row align="center">
                <v-col cols="12" md="7" lg="6">
                    <v-chip
                        class="mb-5 font-weight-bold"
                        color="white"
                        variant="flat"
                    >
                        <v-icon start icon="mdi-heart" color="secondary" />
                        ADOÇÃO RESPONSÁVEL
                    </v-chip>
                    <h1 class="text-h3 text-md-h2 font-weight-black mb-5">
                        Seu novo melhor amigo está esperando por você.
                    </h1>
                    <p
                        class="text-h6 font-weight-regular text-medium-emphasis mb-8"
                        style="max-width: 570px; color: #e0f4ef !important"
                    >
                        Conheça histórias incríveis e encontre um companheiro
                        para transformar a sua rotina.
                    </p>
                    <div class="d-flex flex-wrap ga-4">
                        <v-btn
                            href="#pets"
                            color="secondary"
                            size="large"
                            rounded="lg"
                            elevation="3"
                        >
                            Ver pets disponíveis
                            <v-icon end icon="mdi-paw" />
                        </v-btn>
                        <v-btn
                            :to="ongDestination"
                            variant="outlined"
                            color="white"
                            size="large"
                            rounded="lg"
                        >
                            Sou uma ONG
                        </v-btn>
                    </div>
                </v-col>
            </v-row>
        </v-container>
    </section>

    <v-container id="pets" class="py-10 py-md-14">
        <v-row class="mb-6" align="end">
            <v-col cols="12" md="8">
                <div class="section-kicker mb-2">ENCONTRE SEU COMPANHEIRO</div>
                <h2 class="text-h4 font-weight-bold">
                    Pets esperando por um lar
                </h2>
                <p class="text-medium-emphasis mt-2">
                    Cada adoção abre espaço para salvar uma nova vida.
                </p>
            </v-col>
            <v-col cols="12" md="4" class="text-md-right">
                <v-chip color="primary" variant="tonal" prepend-icon="mdi-paw">
                    {{ store.pets.length }} pets disponíveis
                </v-chip>
            </v-col>
        </v-row>

        <PetFilters v-model="filters" class="filter-panel" @search="search" />

        <v-row class="mt-5">
            <v-col v-if="store.loading" cols="12" class="text-center pa-12">
                <v-progress-circular indeterminate color="primary" size="42" />
            </v-col>
            <v-col
                v-for="pet in store.pets"
                :key="pet.id"
                cols="12"
                sm="6"
                lg="4"
            >
                <PetCard :pet="pet" />
            </v-col>
            <v-col v-if="!store.loading && !store.pets.length" cols="12">
                <v-alert type="info" variant="tonal" rounded="lg"
                    >Nenhum pet foi encontrado com estes filtros.</v-alert
                >
            </v-col>
        </v-row>
    </v-container>
</template>
