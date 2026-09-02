<script setup>
import { onMounted, ref } from 'vue';
import PetGallery from '../components/pets/PetGallery.vue';

const interests = ref([]);
const loading = ref(true);
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function load() {
  loading.value = true;
  const response = await fetch('/api/dashboards/receiver', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (response.ok) interests.value = (await response.json()).data;
  loading.value = false;
}

async function remove(interest) {
  await fetch(`/api/adoptions/${interest.id}`, { method: 'DELETE', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
  await load();
}

function peopleLabel(total) { return `${total} ${total === 1 ? 'pessoa interessada' : 'pessoas interessadas'}`; }
function visitsLabel(total) { return `${total} ${total === 1 ? 'visita prevista' : 'visitas previstas'}`; }

onMounted(load);
</script>

<template>
  <v-container class="py-10">
    <div class="d-flex flex-wrap align-center justify-space-between mb-8">
      <div><div class="section-kicker">MEUS INTERESSES</div><h1 class="text-h4 font-weight-bold">Pets que quero conhecer</h1><p class="text-medium-emphasis mt-2">Acompanhe pessoas interessadas, a fila e possíveis visitas.</p></div>
      <v-btn to="/" color="primary" prepend-icon="mdi-paw">Encontrar mais pets</v-btn>
    </div>
    <v-row>
      <v-col v-if="loading" cols="12" class="text-center pa-12"><v-progress-circular indeterminate color="primary" /></v-col>
      <v-col v-for="interest in interests" :key="interest.id" cols="12" md="6" lg="4">
        <v-card rounded="xl" class="h-100 overflow-hidden">
          <PetGallery :species="interest.pet.species" :height="205" />
          <v-card-item class="pt-5"><v-card-title>{{ interest.pet.name }}</v-card-title><v-card-subtitle>{{ interest.pet.city }} · {{ interest.pet.age_label }}</v-card-subtitle></v-card-item>
          <v-card-text>
            <v-alert color="secondary" variant="tonal" density="compact" class="mb-3" icon="mdi-heart">Seu interesse está marcado</v-alert>
            <div class="d-flex flex-wrap ga-2"><v-chip color="primary" variant="tonal" size="small" prepend-icon="mdi-account-group">{{ peopleLabel(interest.pet.adoptions_count) }} <span class="ml-1">(inclui você)</span></v-chip><v-chip variant="tonal" size="small" prepend-icon="mdi-calendar-clock">{{ visitsLabel(interest.pet.visits_count) }}</v-chip></div>
            <p class="text-caption mt-4">Status da solicitação: <strong>{{ interest.status }}</strong></p>
          </v-card-text>
          <v-card-actions class="pa-4"><v-btn :to="`/pets/${interest.pet.id}`" variant="text" color="primary">Ver perfil</v-btn><v-spacer /><v-btn color="error" variant="text" @click="remove(interest)">Remover interesse</v-btn></v-card-actions>
        </v-card>
      </v-col>
      <v-col v-if="!loading && !interests.length" cols="12"><v-card class="pa-10 text-center" rounded="xl"><v-icon icon="mdi-heart-outline" size="44" color="primary" /><h2 class="text-h6 mt-4">Você ainda não marcou nenhum pet</h2><v-btn to="/" color="primary" class="mt-4">Explorar pets</v-btn></v-card></v-col>
    </v-row>
  </v-container>
</template>
