<script setup>
import { computed } from "vue";

const props = defineProps({
    species: { type: String, default: "dog" },
    height: { type: [Number, String], default: 460 },
    photos: { type: Array, default: () => [] },
});

const fallbackPhotos = computed(() =>
    props.species === "cat"
        ? [
              "https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1495360010541-f48722b34f7d?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1573865526739-10659fec78a5?auto=format&fit=crop&w=1400&q=90",
          ]
        : [
              "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1587300003388-59208cc962cb?auto=format&fit=crop&w=1400&q=90",
              "https://images.unsplash.com/photo-1543466835-00a7907e9de1?auto=format&fit=crop&w=1400&q=90",
          ],
);
const photos = computed(() =>
    props.photos.filter(Boolean).length
        ? props.photos.filter(Boolean)
        : fallbackPhotos.value,
);
</script>

<template>
    <v-carousel
        :height="height"
        show-arrows="hover"
        hide-delimiter-background
        rounded="xl"
        class="pet-gallery"
    >
        <v-carousel-item
            v-for="(photo, index) in photos"
            :key="photo"
            :src="photo"
            cover
        >
            <div class="gallery-caption">
                Foto {{ index + 1 }} de {{ photos.length }}
            </div>
        </v-carousel-item>
    </v-carousel>
</template>

<style scoped>
.pet-gallery {
    border-radius: 24px !important;
    overflow: hidden;
}
.pet-gallery :deep(.v-window__container),
.pet-gallery :deep(.v-carousel-item),
.pet-gallery :deep(.v-img) {
    border-radius: inherit;
}
.gallery-caption {
    position: absolute;
    right: 16px;
    bottom: 16px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(8, 22, 19, 0.72);
    color: white;
    font-size: 0.78rem;
}
</style>
