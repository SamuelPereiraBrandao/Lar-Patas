<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const email = ref(route.query.email || '');
const password = ref('');
const passwordConfirmation = ref('');
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function submit() {
  loading.value = true; error.value = '';
  const response = await fetch('/reset-password', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ email: email.value, token: route.params.token, password: password.value, password_confirmation: passwordConfirmation.value }) });
  loading.value = false;
  const data = await response.json();
  if (!response.ok) { error.value = data.message || 'Não foi possível redefinir a senha.'; return; }
  router.push({ path: '/entrar', query: { reset: 'success' } });
}
</script>

<template>
  <div class="auth-page d-flex align-center justify-center py-10 px-4"><v-card class="auth-card" rounded="xl" elevation="12" width="100%" max-width="510"><div class="auth-banner pa-7 text-center"><div class="auth-icon mx-auto mb-4"><v-icon icon="mdi-shield-key-outline" size="29" /></div><div class="text-overline font-weight-bold">LAR & PATAS</div><h1 class="text-h4 font-weight-bold mt-1">Crie uma nova senha</h1><p class="text-body-2 mt-3 mb-0">Use ao menos 8 caracteres para proteger sua conta.</p></div><v-card-text class="pa-6 pa-md-8"><v-alert v-if="error" type="error" variant="tonal" rounded="lg" class="mb-5">{{ error }}</v-alert><v-form @submit.prevent="submit"><v-text-field v-model="email" label="E-mail" type="email" autocomplete="email" prepend-inner-icon="mdi-email-outline" variant="outlined" /><v-text-field v-model="password" label="Nova senha" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" prepend-inner-icon="mdi-lock-outline" :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'" variant="outlined" @click:append-inner="showPassword = !showPassword" /><v-text-field v-model="passwordConfirmation" label="Confirmar nova senha" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" prepend-inner-icon="mdi-lock-check-outline" variant="outlined" /><v-btn type="submit" :loading="loading" color="primary" size="large" block rounded="lg">Salvar nova senha<v-icon end icon="mdi-check" /></v-btn></v-form></v-card-text></v-card></div>
</template>
