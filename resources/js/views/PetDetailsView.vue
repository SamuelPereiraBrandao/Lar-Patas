<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import PetGallery from '../components/pets/PetGallery.vue';

const props = defineProps({ id: String });
const route = useRoute();
const pet = ref(null);
const interestSnackbar = ref(route.query.interest === 'success');
const shelterLocation = computed(() => pet.value?.shelter ? `${pet.value.shelter.district || pet.value.shelter.city}, ${pet.value.shelter.city} - ${pet.value.shelter.state}` : 'Sede não informada');
const interestedLabel = computed(() => `${pet.value?.adoptions_count || 0} ${pet.value?.adoptions_count === 1 ? 'pessoa interessada' : 'pessoas interessadas'}`);
function latestInterest(value) { return value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : ''; }
onMounted(async () => { pet.value = (await (await fetch(`/api/pets/${props.id}`)).json()).data; });
</script>

<template>
  <v-snackbar v-model="interestSnackbar" color="success" :timeout="3000" location="top" rounded="lg">Interesse marcado! Você pode acompanhá-lo na sua Visão geral.<template #actions><v-btn variant="text" @click="interestSnackbar = false">Fechar</v-btn></template></v-snackbar>
  <v-container v-if="pet" class="py-10">
    <v-btn to="/" variant="text" prepend-icon="mdi-arrow-left">Voltar aos pets</v-btn>
    <v-row class="mt-3">
      <v-col cols="12" md="7"><PetGallery :species="pet.species" /><v-card v-if="pet.shelter" class="mt-5 pa-5" rounded="xl" variant="tonal" color="primary"><div class="d-flex align-center ga-3"><v-avatar color="primary" variant="flat"><v-icon icon="mdi-home-heart" /></v-avatar><div><div class="font-weight-bold">{{ pet.shelter.name }}</div><div class="text-body-2">Sede de acolhimento: {{ shelterLocation }}</div></div></div></v-card></v-col>
      <v-col cols="12" md="5">
        <v-chip color="success">Disponível para adoção</v-chip><v-chip v-if="pet.is_interested" color="secondary" class="ml-2" prepend-icon="mdi-heart">Seu interesse está marcado</v-chip>
        <h1 class="text-h3 font-weight-black mt-4">{{ pet.name }}</h1><p class="text-h6 text-medium-emphasis">{{ pet.species === 'cat' ? 'Gato' : 'Cachorro' }} · {{ pet.age_label }} · Porte {{ pet.size }}</p>
        <v-divider class="my-6" /><p class="text-body-1">{{ pet.description }}</p>
        <v-list class="bg-transparent mt-4"><v-list-item prepend-icon="mdi-map-marker" :title="pet.city" subtitle="Localização informada do pet" /><v-list-item v-if="pet.shelter" prepend-icon="mdi-home-map-marker" :title="shelterLocation" :subtitle="`Sede de acolhimento: ${pet.shelter.name}`" /><v-list-item prepend-icon="mdi-paw" :title="pet.temperament" subtitle="Temperamento" /><v-list-item prepend-icon="mdi-shield-check" title="Adoção com análise de perfil" subtitle="A ONG entrará em contato para agendar uma visita" /></v-list>
        <v-card v-if="pet.adoptions_count" class="pa-4 mb-4" rounded="lg" variant="tonal" color="secondary"><div class="d-flex align-center ga-3"><v-avatar color="secondary" size="42"><v-icon icon="mdi-account-heart-outline" /></v-avatar><div><div class="font-weight-bold">{{ interestedLabel }}</div><div class="text-caption">{{ pet.adoptions_count === 1 ? 'Uma pessoa já demonstrou interesse.' : 'Outras famílias também estão conhecendo este pet.' }}</div><div v-if="pet.latest_interest_at" class="text-caption mt-1">Último interesse registrado em {{ latestInterest(pet.latest_interest_at) }}.</div></div></div></v-card>
        <v-alert v-if="pet.is_interested" type="success" variant="tonal" class="mb-4" rounded="lg">Você já demonstrou interesse por {{ pet.name }}. Acompanhe atualizações, visitas e a fila no seu painel.</v-alert>
        <v-btn v-if="pet.is_interested" to="/painel" color="secondary" size="large" block rounded="lg" prepend-icon="mdi-heart">Acompanhar meu interesse</v-btn><v-btn v-else :to="`/adotar/${pet.id}`" color="primary" size="large" block rounded="lg">Quero adotar {{ pet.name }}</v-btn>
      </v-col>
    </v-row>
  </v-container>
  <v-container v-else class="py-16 text-center"><v-progress-circular indeterminate color="primary" /></v-container>
</template>
