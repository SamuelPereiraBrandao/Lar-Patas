<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { notify } from "../../stores/ui";

const router = useRouter();
const route = useRoute();
const mode = ref("login");
const initialNotice = route.query.verified
    ? "E-mail confirmado. Você já pode entrar."
    : route.query.reset
      ? "Senha redefinida. Entre com sua nova senha."
      : route.query.expired
        ? "Sua sessão expirou. Entre novamente para continuar."
        : "";
const loading = ref(false);
const showPassword = ref(false);
const showConfirmation = ref(false);
const loginForm = reactive({ email: "", password: "", remember: false });
const registerForm = reactive({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    city: "",
    state: "",
    housing_type: null,
    has_other_pets: false,
    household_description: "",
});
const locations = ref([]);
const states = computed(() =>
    locations.value.map((state) => ({
        title: `${state.name} (${state.code})`,
        value: state.code,
    })),
);
const cities = computed(
    () =>
        locations.value
            .find((state) => state.code === registerForm.state)
            ?.cities.map((city) => city.name) || [],
);
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
function translatedError(data) {
    const entries = Object.entries(data.errors || {});
    if (!entries.length)
        return data.message || "Não foi possível concluir a ação.";
    const [field, messages] = entries[0];
    const labels = {
        name: "Nome completo",
        email: "E-mail",
        password: "Senha",
        password_confirmation: "Confirmação de senha",
        state: "Estado (UF)",
        city: "Cidade",
        housing_type: "Tipo de moradia",
    };
    const label = labels[field] || field;
    const message = messages?.[0] || "";
    let text = message.includes("required")
        ? `O campo ${label} é obrigatório.`
        : `Verifique o campo ${label}.`;
    if (entries.length > 1) text += " Revise também os demais campos.";
    return text;
}
async function login() {
    loading.value = true;
    const response = await fetch("/login", {
        method: "POST",
        credentials: "same-origin",
        headers,
        body: JSON.stringify(loginForm),
    });
    loading.value = false;
    if (!response.ok) {
        notify(translatedError(await response.json()), "error");
        return;
    }
    sessionStorage.setItem("after_2fa", route.query.next || "/painel");
    router.push("/confirmar-acesso");
}
async function register() {
    loading.value = true;
    const response = await fetch("/api/register", {
        method: "POST",
        headers,
        body: JSON.stringify(registerForm),
    });
    loading.value = false;
    if (!response.ok) {
        notify(translatedError(await response.json()), "error");
        return;
    }
    notify((await response.json()).message);
    mode.value = "login";
}
watch(
    () => registerForm.state,
    () => {
        if (registerForm.city && !cities.value.includes(registerForm.city))
            registerForm.city = "";
    },
);
onMounted(async () => {
    if (initialNotice) notify(initialNotice);
    const response = await fetch("/api/locations", {
        headers: { Accept: "application/json" },
    });
    if (response.ok) locations.value = (await response.json()).data;
});
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
            <v-card-text class="pa-6 pa-md-8">
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
                    <v-divider class="my-5" />
                    <div class="text-subtitle-2 font-weight-bold mb-3">
                        Sobre seu lar
                    </div>
                    <v-row dense>
                        <v-col cols="12" md="4"
                            ><v-select
                                v-model="registerForm.state"
                                :items="states"
                                label="Estado (UF)"
                                variant="outlined"
                                required
                        /></v-col>
                        <v-col cols="12" md="8"
                            ><v-select
                                v-model="registerForm.city"
                                :items="cities"
                                :disabled="!registerForm.state"
                                label="Cidade"
                                variant="outlined"
                                required
                        /></v-col>
                        <v-col cols="12"
                            ><v-select
                                v-model="registerForm.housing_type"
                                :items="[
                                    'Casa com quintal',
                                    'Casa sem quintal',
                                    'Apartamento',
                                ]"
                                label="Tipo de moradia"
                                variant="outlined"
                                required
                        /></v-col>
                        <v-col cols="12"
                            ><v-checkbox
                                v-model="registerForm.has_other_pets"
                                label="Tenho outros animais em casa"
                                color="primary"
                                hide-details
                        /></v-col>
                        <v-col cols="12"
                            ><v-textarea
                                v-model="registerForm.household_description"
                                label="Conte brevemente sobre seu lar (opcional)"
                                variant="outlined"
                                rows="2"
                                auto-grow
                        /></v-col>
                    </v-row>
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
