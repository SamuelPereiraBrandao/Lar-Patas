<script setup>
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const router = useRouter();
const route = useRoute();
const mode = ref("login");
const error = ref("");
const success = ref(
    route.query.verified
        ? "E-mail confirmado. Você já pode entrar."
        : route.query.reset
          ? "Senha redefinida. Entre com sua nova senha."
          : route.query.expired
            ? "Sua sessão expirou. Entre novamente para continuar."
            : "",
);
const loading = ref(false);
const showPassword = ref(false);
const showConfirmation = ref(false);
const loginForm = reactive({ email: "", password: "", remember: false });
const registerForm = reactive({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});
const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";
const headers = {
    "Content-Type": "application/json",
    Accept: "application/json",
    "X-CSRF-TOKEN": csrf,
    "X-Requested-With": "XMLHttpRequest",
};
const title = computed(() =>
    mode.value === "login" ? "Boas-vindas de volta" : "Crie seu acesso",
);
async function login() {
    error.value = "";
    loading.value = true;
    const response = await fetch("/login", {
        method: "POST",
        credentials: "same-origin",
        headers,
        body: JSON.stringify(loginForm),
    });
    loading.value = false;
    if (!response.ok) {
        error.value =
            (await response.json()).message || "Não foi possível entrar.";
        return;
    }
    sessionStorage.setItem("after_2fa", route.query.next || "/painel");
    router.push("/confirmar-acesso");
}
async function register() {
    error.value = "";
    success.value = "";
    loading.value = true;
    const response = await fetch("/api/register", {
        method: "POST",
        headers,
        body: JSON.stringify(registerForm),
    });
    loading.value = false;
    if (!response.ok) {
        error.value =
            (await response.json()).message ||
            "Não foi possível criar o cadastro.";
        return;
    }
    success.value = (await response.json()).message;
    mode.value = "login";
}
</script>

<template>
    <div class="auth-page d-flex align-center justify-center py-10 px-4">
        <v-card
            class="auth-card"
            rounded="xl"
            elevation="12"
            width="100%"
            max-width="510"
            ><div class="auth-banner pa-7 text-center">
                <div class="auth-icon mx-auto mb-4">
                    <v-icon icon="mdi-paw" size="29" />
                </div>
                <div class="text-overline font-weight-bold">LAR & PATAS</div>
                <h1 class="text-h4 font-weight-bold mt-1">{{ title }}</h1>
                <p class="text-body-2 mt-3 mb-0">
                    {{
                        mode === "login"
                            ? "Entre para acompanhar suas adoéées e seus pets."
                            : "Crie seu perfil e encontre o companheiro ideal."
                    }}
                </p>
            </div>
            <v-card-text class="pa-6 pa-md-8"
                ><v-alert
                    v-if="success"
                    type="success"
                    variant="tonal"
                    rounded="lg"
                    class="mb-5"
                    >{{ success }}</v-alert
                ><v-alert
                    v-if="error"
                    type="error"
                    variant="tonal"
                    rounded="lg"
                    class="mb-5"
                    >{{ error }}</v-alert
                >
                <v-form v-if="mode === 'login'" @submit.prevent="login"
                    ><v-text-field
                        v-model="loginForm.email"
                        label="E-mail"
                        type="email"
                        autocomplete="email"
                        prepend-inner-icon="mdi-email-outline"
                        variant="outlined" /><v-text-field
                        v-model="loginForm.password"
                        label="Senha"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        prepend-inner-icon="mdi-lock-outline"
                        :append-inner-icon="
                            showPassword
                                ? 'mdi-eye-off-outline'
                                : 'mdi-eye-outline'
                        "
                        variant="outlined"
                        @click:append-inner="showPassword = !showPassword" />
                    <div class="d-flex align-center justify-space-between mb-4">
                        <v-checkbox
                            v-model="loginForm.remember"
                            label="Lembrar meu acesso"
                            density="compact"
                            color="primary"
                            hide-details
                        /><v-btn
                            to="/esqueci-minha-senha"
                            variant="text"
                            size="small"
                            color="primary"
                            >Esqueci minha senha</v-btn
                        >
                    </div>
                    <v-btn
                        type="submit"
                        :loading="loading"
                        color="primary"
                        size="large"
                        block
                        rounded="lg"
                        >Continuar<v-icon end icon="mdi-arrow-right" /></v-btn
                ></v-form>
                <v-form v-else autocomplete="off" @submit.prevent="register"
                    ><v-text-field
                        v-model="registerForm.name"
                        label="Nome completo"
                        autocomplete="name"
                        prepend-inner-icon="mdi-account-outline"
                        variant="outlined" /><v-text-field
                        v-model="registerForm.email"
                        label="E-mail"
                        type="email"
                        autocomplete="email"
                        prepend-inner-icon="mdi-email-outline"
                        variant="outlined" /><v-text-field
                        v-model="registerForm.password"
                        label="Senha"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        prepend-inner-icon="mdi-lock-outline"
                        :append-inner-icon="
                            showPassword
                                ? 'mdi-eye-off-outline'
                                : 'mdi-eye-outline'
                        "
                        variant="outlined"
                        @click:append-inner="
                            showPassword = !showPassword
                        " /><v-text-field
                        v-model="registerForm.password_confirmation"
                        label="Confirmar senha"
                        :type="showConfirmation ? 'text' : 'password'"
                        autocomplete="new-password"
                        prepend-inner-icon="mdi-lock-check-outline"
                        :append-inner-icon="
                            showConfirmation
                                ? 'mdi-eye-off-outline'
                                : 'mdi-eye-outline'
                        "
                        variant="outlined"
                        @click:append-inner="
                            showConfirmation = !showConfirmation
                        " />
                    <div class="text-caption text-medium-emphasis mb-5">
                        Use ao menos 8 caracteres para criar sua senha.
                    </div>
                    <v-btn
                        type="submit"
                        :loading="loading"
                        color="primary"
                        size="large"
                        block
                        rounded="lg"
                        >Criar conta<v-icon
                            end
                            icon="mdi-account-plus-outline" /></v-btn
                ></v-form>
                <v-divider class="my-6" />
                <div class="text-center text-body-2">
                    {{
                        mode === "login"
                            ? "Ainda não tem uma conta?"
                            : "Jé possui uma conta?"
                    }}
                    <v-btn
                        variant="text"
                        size="small"
                        color="primary"
                        @click="mode = mode === 'login' ? 'register' : 'login'"
                        >{{
                            mode === "login" ? "Criar agora" : "Entrar"
                        }}</v-btn
                    >
                </div>
            </v-card-text></v-card
        >
    </div>
</template>
