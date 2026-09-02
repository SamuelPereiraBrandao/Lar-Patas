<script setup>
import { onMounted, ref } from 'vue';

const shelters = ref([]);
const loading = ref(true);
const saving = ref(false);
const dialog = ref(false);
const form = ref({ name: '', district: '', city: 'São Paulo', state: 'SP', address: '', active: true });
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function load() {
  loading.value = true;
  const response = await fetch('/api/admin/shelters', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (response.ok) shelters.value = (await response.json()).data;
  loading.value = false;
}

async function save() {
  saving.value = true;
  const response = await fetch('/api/admin/shelters', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(form.value) });
  saving.value = false;
  if (response.ok) { dialog.value = false; form.value = { name: '', district: '', city: 'São Paulo', state: 'SP', address: '', active: true }; await load(); }
}

onMounted(load);
</script>

<template>
  <v-container class="py-10">
    <div class="d-flex flex-wrap align-center justify-space-between mb-8"><div><div class="section-kicker">CONFIGURAÇÕES</div><h1 class="text-h4 font-weight-bold">Sedes de acolhimento</h1><p class="text-medium-emphasis mt-2">Cadastre as unidades da ONG e acompanhe os pets vinculados a cada uma.</p></div><v-btn color="primary" prepend-icon="mdi-plus" @click="dialog = true">Cadastrar sede</v-btn></div>
    <v-row><v-col v-if="loading" cols="12" class="text-center pa-12"><v-progress-circular indeterminate color="primary" /></v-col><v-col v-for="shelter in shelters" :key="shelter.id" cols="12" md="6" lg="4"><v-card class="pa-5 h-100" rounded="xl"><div class="d-flex justify-space-between"><v-avatar color="primary" variant="tonal"><v-icon icon="mdi-home-heart" /></v-avatar><v-chip :color="shelter.active ? 'success' : 'default'" size="small">{{ shelter.active ? 'Ativa' : 'Inativa' }}</v-chip></div><h2 class="text-h6 font-weight-bold mt-4">{{ shelter.name }}</h2><p class="text-medium-emphasis mt-1">{{ shelter.district ? `${shelter.district}, ` : '' }}{{ shelter.city }} - {{ shelter.state }}</p><p v-if="shelter.address" class="text-caption mt-3">{{ shelter.address }}</p><v-divider class="my-4" /><v-chip color="secondary" variant="tonal" prepend-icon="mdi-paw">{{ shelter.pets_count }} {{ shelter.pets_count === 1 ? 'pet vinculado' : 'pets vinculados' }}</v-chip></v-card></v-col><v-col v-if="!loading && !shelters.length" cols="12"><v-alert type="info" variant="tonal">Nenhuma sede cadastrada.</v-alert></v-col></v-row>
    <v-dialog v-model="dialog" max-width="620"><v-card rounded="xl"><v-card-title class="pa-6">Cadastrar sede</v-card-title><v-card-text><v-row><v-col cols="12"><v-text-field v-model="form.name" label="Nome da sede" variant="outlined" /></v-col><v-col cols="12" md="5"><v-text-field v-model="form.district" label="Bairro" variant="outlined" /></v-col><v-col cols="12" md="5"><v-text-field v-model="form.city" label="Cidade" variant="outlined" /></v-col><v-col cols="12" md="2"><v-text-field v-model="form.state" label="UF" variant="outlined" maxlength="2" /></v-col><v-col cols="12"><v-text-field v-model="form.address" label="Endereço / referência" variant="outlined" /></v-col></v-row></v-card-text><v-card-actions class="pa-6 pt-0"><v-spacer /><v-btn variant="text" @click="dialog = false">Cancelar</v-btn><v-btn :loading="saving" color="primary" @click="save">Salvar sede</v-btn></v-card-actions></v-card></v-dialog>
  </v-container>
</template>
