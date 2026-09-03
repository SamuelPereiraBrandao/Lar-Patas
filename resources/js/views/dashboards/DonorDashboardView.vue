<script setup>
import { onMounted, ref } from 'vue';

const pets = ref([]);
const loading = ref(true);
onMounted(async () => { const response = await fetch('/api/dashboards/donor', { credentials: 'same-origin', headers: { Accept: 'application/json' } }); if (response.ok) pets.value = (await response.json()).data; loading.value = false; });
</script>

<template>
  <v-container class="py-10"><div class="section-kicker">ÁREA DO DOADOR</div><h1 class="text-h4 font-weight-bold">Meus pets e fila de atendimento</h1><p class="text-medium-emphasis mt-2 mb-7">Acompanhe a triagem e as pessoas interessadas nos pets que você cadastrou.</p><v-row><v-col v-if="loading" cols="12" class="text-center pa-12"><v-progress-circular indeterminate color="primary" /></v-col><v-col v-for="pet in pets" :key="pet.id" cols="12" md="6"><v-card rounded="xl" class="pa-5"><div class="d-flex justify-space-between align-start"><div><h2 class="text-h6 font-weight-bold">{{ pet.name }}</h2><p class="text-caption mt-1">Fila: {{ pet.queue_position || 'Aguardando definição' }}</p></div><v-chip color="primary" variant="tonal">{{ pet.adoptions.length }} interessados</v-chip></div><v-divider class="my-4" /><p class="text-body-2"><strong>Status:</strong> {{ pet.status }}</p><p v-if="pet.triage_notes" class="text-body-2 mt-2"><strong>Equipe:</strong> {{ pet.triage_notes }}</p><v-btn :to="`/pets/${pet.id}`" variant="text" color="primary" class="mt-3">Ver perfil do pet</v-btn></v-card></v-col><v-col v-if="!loading && !pets.length" cols="12"><v-alert type="info" variant="tonal">Você ainda não cadastrou pets para doação.</v-alert></v-col></v-row></v-container>
</template>
