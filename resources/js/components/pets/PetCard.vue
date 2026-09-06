<script setup>
import FavoriteButton from "./FavoriteButton.vue";
import { petStatusLabel } from "../../stores/pets";
import { computed } from "vue";
import { useRouter } from "vue-router";

const props = defineProps({
    pet: { type: Object, required: true },
    family: Boolean,
    editable: Boolean,
    to: String,
});
const emit = defineEmits(["edit"]);
const destination = computed(() => props.to || `/pets/${props.pet.id}`);
const router = useRouter();
const activeInterest = computed(
    () =>
        props.pet.status !== "adopted" &&
        props.pet.ownership_kind !== "guardian" &&
        props.pet.is_interested,
);
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
const publishedAt = computed(() => {
    if (!props.pet.created_at) return "Data não informada";

    return new Intl.DateTimeFormat("pt-BR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(props.pet.created_at));
});
</script>

<template>
    <v-card
        class="pet-card h-100 cursor-pointer"
        rounded="xl"
        elevation="0"
        @click="router.push(destination)"
    >
        <v-img
            :src="image"
            class="pet-image flex-grow-0"
            :aspect-ratio="1.5"
            cover
        >
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
            <div class="d-flex align-start ga-3">
                <div class="pet-heading">
                    <v-card-title
                        class="pet-name px-0 text-h5 font-weight-bold"
                        >{{ pet.name }}</v-card-title
                    >
                    <div class="pet-meta d-flex flex-wrap ga-2 mt-1">
                        <span>{{
                            pet.age_label || "Idade não informada"
                        }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{
                            sizeLabel
                                ? `Porte ${sizeLabel}`
                                : "Porte não informado"
                        }}</span>
                    </div>
                </div>
                <FavoriteButton v-if="!family" :pet="pet" />
            </div>
            <div class="mt-3">
                <v-chip
                    v-if="family"
                    class="pet-status"
                    color="primary"
                    size="small"
                    variant="tonal"
                    >{{ petStatusLabel(pet) }}</v-chip
                >
                <v-chip
                    v-else-if="pet.status !== 'adopted' && activeInterest"
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
                    >{{ petStatusLabel(pet) }}</v-chip
                >
            </div>
            <div class="pet-meta mt-4">
                <v-icon size="17" icon="mdi-map-marker-outline" />{{ pet.city
                }}{{ family && pet.state ? `, ${pet.state}` : "" }}
            </div>
            <div v-if="!family" class="pet-meta mt-2">
                <v-icon size="17" icon="mdi-home-city-outline" />
                {{ pet.shelter?.name || "Sede não informada" }}
            </div>
            <div v-if="!family" class="pet-meta mt-2">
                <v-icon size="16" icon="mdi-calendar-clock-outline" />
                Publicado em {{ publishedAt }}
            </div>
            <v-chip
                v-if="
                    !family && pet.status !== 'adopted' && pet.adoptions_count
                "
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
        <v-card-actions v-if="family" class="px-4 pb-4 pt-2 ga-2">
            <v-btn
                :to="destination"
                color="primary"
                variant="flat"
                rounded="lg"
                size="large"
                class="flex-grow-1"
                @click.stop
            >
                Ver pet<v-icon end icon="mdi-arrow-right" />
            </v-btn>
            <v-btn
                v-if="editable"
                icon="mdi-cog-outline"
                color="primary"
                variant="tonal"
                rounded="lg"
                :title="`Configurar ${pet.name}`"
                :aria-label="`Configurar ${pet.name}`"
                @click.stop="emit('edit', pet)"
            />
        </v-card-actions>
        <v-card-actions v-else class="px-4 pb-4 pt-2">
            <v-btn
                :to="activeInterest ? '/painel' : `/pets/${pet.id}`"
                :color="activeInterest ? 'secondary' : 'primary'"
                variant="flat"
                block
                rounded="lg"
                size="large"
                @click.stop
            >
                {{
                    activeInterest
                        ? "Acompanhar meu interesse"
                        : `Conhecer ${pet.name}`
                }}
                <v-icon
                    end
                    :icon="activeInterest ? 'mdi-heart' : 'mdi-arrow-right'"
                />
            </v-btn>
        </v-card-actions>
    </v-card>
</template>

<style scoped>
.pet-card {
    display: flex;
    flex-direction: column;
}
.pet-heading {
    flex: 1;
    min-width: 0;
}
.pet-name {
    white-space: normal;
    overflow-wrap: anywhere;
}
.pet-card :deep(.v-card-actions) {
    margin-top: auto;
}
.pet-card :deep(.v-card-item) {
    flex-shrink: 0;
}
</style>
