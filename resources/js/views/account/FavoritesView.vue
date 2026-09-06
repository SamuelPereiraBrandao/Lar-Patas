<script setup>
import { ref, onMounted } from "vue";
import PetCard from "../../components/pets/PetCard.vue";
import { request } from "../../stores/requests";
import { notify } from "../../stores/ui";
const pets = ref([]),
    searches = ref([]),
    loading = ref(true);
async function load() {
    try {
        const data = await request("/api/favorites");
        pets.value = data.data;
        searches.value = data.searches;
    } catch (e) {
        notify(e.message, "error");
    } finally {
        loading.value = false;
    }
}
async function remove(id) {
    try {
        await request(`/api/saved-searches/${id}`, "DELETE");
        await load();
    } catch (e) {
        notify(e.message, "error");
    }
}
onMounted(load);
</script>
<template>
    <v-container class="py-8"
        ><div class="section-kicker">GUARDADOS COM CARINHO</div>
        <h1 class="text-h4 font-weight-bold">Meus favoritos</h1>
        <p class="text-medium-emphasis mt-2 mb-6">
            Pets e buscas para você revisitar quando quiser.
        </p>
        <v-skeleton-loader v-if="loading" type="card" />
        <template v-else
            ><v-card
                v-if="searches.length"
                class="pa-5 mb-6"
                rounded="xl"
                variant="outlined"
                ><h2 class="text-h6 mb-3">Buscas salvas</h2>
                <div class="d-flex flex-wrap ga-2">
                    <v-chip
                        v-for="search in searches"
                        :key="search.id"
                        :to="{ path: '/pets', query: search.filters }"
                        closable
                        @click:close.prevent="remove(search.id)"
                        prepend-icon="mdi-magnify"
                        >{{ search.name }}</v-chip
                    >
                </div></v-card
            >
            <v-row
                ><v-col
                    v-for="pet in pets.filter((p) => p.is_favorited)"
                    :key="pet.id"
                    cols="12"
                    sm="6"
                    lg="4"
                    ><PetCard
                        :pet="pet"
                        :to="`/pets/${pet.id}?from=favorites`" /></v-col
            ></v-row>
            <v-card
                v-if="!pets.some((p) => p.is_favorited)"
                class="pa-10 text-center"
                rounded="xl"
                variant="tonal"
                ><v-icon
                    icon="mdi-bookmark-outline"
                    size="44"
                    color="primary"
                />
                <h2 class="text-h6 mt-3">
                    Seus próximos encontros começam aqui
                </h2>
                <p class="my-3">Toque no marcador de um pet para guardá-lo.</p>
                <v-btn to="/pets" color="primary">Encontrar pets</v-btn></v-card
            ></template
        ></v-container
    >
</template>
