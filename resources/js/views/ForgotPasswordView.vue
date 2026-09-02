<script setup>
import { ref } from 'vue';

const email = ref('');
const loading = ref(false);
const message = ref('');
const error = ref('');
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function submit() {
  loading.value = true; error.value = ''; message.value = '';
  const response = await fetch('/forgot-password', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ email: email.value }) });
  loading.value = false;
  const data = await response.json();
  if (!response.ok) { error.value = data.message || 'Não foi possível enviar o link.'; return; }
  message.value = data.message;
}
</script>

<template>
  <div class="auth-page d-flex align-center justify-center py-10 px-4"><v-card class="auth-card" rounded="xl" elevation="12" width="100%" max-width="510"><div class="auth-banner pa-7 text-center"><div class="auth-icon mx-auto mb-4"><v-icon icon="mdi-lock-reset" size="29" /></div><div class="text-overline font-weight-bold">LAR & PATAS</div><h1 class="text-h4 font-weight-bold mt-1">Redefinir senha</h1><p class="text-body-2 mt-3 mb-0">Enviaremos um link seguro para o seu e-mail.</p></div><v-card-text class="pa-6 pa-md-8"><v-alert v-if="message" type="success" variant="tonal" rounded="lg" class="mb-5">{{ message }}</v-alert><v-alert v-if="error" type="error" variant="tonal" rounded="lg" class="mb-5">{{ error }}</v-alert><v-form @submit.prevent="submit"><v-text-field v-model="email" label="E-mail cadastrado" type="email" autocomplete="email" prepend-inner-icon="mdi-email-outline" variant="outlined" /><v-btn type="submit" :loading="loading" color="primary" size="large" block rounded="lg">Enviar link de redefinição<v-icon end icon="mdi-send" /></v-btn></v-form><div class="text-center mt-6"><v-btn to="/entrar" variant="text" color="primary" prepend-icon="mdi-arrow-left">Voltar para entrar</v-btn></div></v-card-text></v-card></div>
</template>
