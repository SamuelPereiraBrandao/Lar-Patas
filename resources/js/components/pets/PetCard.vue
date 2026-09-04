<script setup>
import { computed } from "vue";
import { useRouter } from "vue-router";

const props = defineProps({ pet: { type: Object, required: true } });
const router = useRouter();
const image = computed(
    () =>
        props.pet.image_url ||
        (props.pet.species === "cat"
            ? "https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=900&q=85"
            : "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=85"),
);
const speciesLabel = computed(() =>
    props.pet.species === "cat" ? "Gato" : "Cachorro",
);
const sizeLabel = computed(
    () =>
        ({ small: "pequeno", medium: "médio", large: "grande" })[
            props.pet.size
        ] || props.pet.size,
);
</script>

<template>
    <v-card
        class="pet-card h-100 cursor-pointer"
        rounded="xl"
        elevation="0"
        @click="router.push(`/pets/${pet.id}`)"
    >
        <v-img :src="image" class="pet-image" height="245" cover>
            <div class="d-flex justify-space-between pa-3">
                <v-chip
                    size="small"
                    color="white"
                    variant="flat"
                    class="font-weight-bold"
                    ><v-icon start size="16" icon="mdi-paw" color="primary" />{{
                        speciesLabel
                    }}</v-chip
                >
                <v-chip
                    v-if="pet.sex"
                    size="small"
                    color="white"
                    variant="flat"
                    class="font-weight-bold"
                    >{{ pet.sex === "female" ? "Fêmea" : "Macho" }}</v-chip
                >
            </div>
        </v-img>
        <v-card-item class="pt-5">
            <div class="d-flex align-start justify-space-between ga-3">
                <div>
                    <v-card-title class="px-0 text-h5 font-weight-bold">{{
                        pet.name
                    }}</v-card-title>
                    <div class="pet-meta mt-1">
                        <v-icon size="16" icon="mdi-cake-variant-outline" />{{
                            pet.age_label
                        }}<span class="mx-1">â€¢</span>Porte {{ sizeLabel }}
                    </div>
                </div>
                <v-chip
                    v-if="pet.is_interested"
                    class="pet-status"
                    color="secondary"
                    size="small"
                    variant="flat"
                    prepend-icon="mdi-heart"
                    >Interesse marcado</v-chip
                >
                <v-chip
                    v-else
                    class="pet-status"
                    color="success"
                    size="small"
                    variant="tonal"
                    >Disponível</v-chip
                >
            </div>
            <div class="pet-meta mt-4">
                <v-icon size="17" icon="mdi-map-marker-outline" />{{ pet.city }}
            </div>
            <v-chip
                v-if="pet.adoptions_count"
                class="mt-3"
                color="secondary"
                size="small"
                variant="tonal"
                prepend-icon="mdi-account-heart-outline"
            >
                {{ pet.adoptions_count }}
                {{
                    pet.adoptions_count === 1
                        ? "pessoa interessada"
                        : "pessoas interessadas"
                }}
            </v-chip>
        </v-card-item>
        <v-card-actions class="px-4 pb-4 pt-2">
            <v-btn
                :to="pet.is_interested ? '/painel' : `/pets/${pet.id}`"
                :color="pet.is_interested ? 'secondary' : 'primary'"
                variant="flat"
                block
                rounded="lg"
                size="large"
                @click.stop
            >
                {{
                    pet.is_interested
                        ? "Acompanhar meu interesse"
                        : `Conhecer ${pet.name}`
                }}
                <v-icon
                    end
                    :icon="pet.is_interested ? 'mdi-heart' : 'mdi-arrow-right'"
                />
            </v-btn>
        </v-card-actions>
    </v-card>
</template>
