<script setup>
import { computed, onMounted, ref } from "vue";

const filters = defineModel({
    default: () => ({ search: "", species: null, size: null, shelter: null }),
});
const emit = defineEmits(["search"]);
const shelters = ref([]);
const shelterItems = computed(() =>
    shelters.value.map((shelter) => ({
        title: `${shelter.name} â€” ${shelter.district || shelter.city}, ${shelter.state}`,
        value: shelter.id,
    })),
);

onMounted(async () => {
    const response = await fetch("/api/shelters");
    if (response.ok) shelters.value = (await response.json()).data;
});
</script>

<template>
    <v-card rounded="xl" elevation="0" class="border pa-3">
        <v-row dense align="center">
            <v-col cols="12" md="4"
                ><v-text-field
                    v-model="filters.search"
                    label="Busque por nome ou cidade"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    hide-details
                    density="comfortable"
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
            <v-col cols="12" md="2"
                ><v-select
                    v-model="filters.shelter"
                    :items="shelterItems"
                    label="Sede"
                    clearable
                    variant="outlined"
                    hide-details
                    density="comfortable"
            /></v-col>
            <v-col cols="12" md="2"
                ><v-btn
                    color="primary"
                    block
                    height="44"
                    @click="emit('search')"
                    >Filtrar</v-btn
                ></v-col
            >
        </v-row>
    </v-card>
</template>
