<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { useRouter } from "vue-router";
import { setSession } from "../../stores/ui";

const router = useRouter();
const digits = ref(Array(6).fill(""));
const inputs = ref([]);
const error = ref("");
const message = ref("");
const loading = ref(false);
const resending = ref(false);
const secondsLeft = ref(600);
const code = computed(() => digits.value.join(""));
const countdown = computed(
    () =>
        `${String(Math.floor(secondsLeft.value / 60)).padStart(2, "0")}:${String(secondsLeft.value % 60).padStart(2, "0")}`,
);
const expired = computed(() => secondsLeft.value === 0);
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
let timer;

function focus(index) {
    nextTick(() => inputs.value[index]?.focus());
}
function setDigits(start, value) {
    value
        .replace(/\D/g, "")
        .slice(0, 6 - start)
        .split("")
        .forEach((digit, offset) => {
            digits.value[start + offset] = digit;
        });
}
function updateDigit(index, value) {
    const clean = String(value).replace(/\D/g, "");
    if (clean.length > 1) {
        setDigits(index, clean);
        focus(Math.min(index + clean.length, 5));
        return;
    }
    digits.value[index] = clean;
    if (clean && index < 5) focus(index + 1);
}
function handleKeydown(index, event) {
    if (event.key === "Backspace" && !digits.value[index] && index > 0)
        focus(index - 1);
}
async function paste(index, event) {
    setDigits(index, event.clipboardData?.getData("text") || "");
    focus(Math.min(index + 5, 5));
    await nextTick();
    if (code.value.length === 6 && !expired.value) verify();
}

async function verify() {
    error.value = "";
    if (expired.value) {
        error.value =
            "Este código expirou. Reenvie um novo código para continuar.";
        return;
    }
    if (code.value.length !== 6) {
        error.value = "Digite os 6 números do código.";
        return;
    }
    loading.value = true;
    const response = await fetch("/two-factor/verify", {
        method: "POST",
        credentials: "same-origin",
        headers,
        body: JSON.stringify({ code: code.value }),
    });
    loading.value = false;
    if (!response.ok) {
        error.value = (await response.json()).message || "Código inválido.";
        return;
    }
    const payload = await response.json();
    setSession(payload.user);
    window.dispatchEvent(new Event("auth-changed"));
    const destination = sessionStorage.getItem("after_2fa") || "/painel";
    sessionStorage.removeItem("after_2fa");
    router.push(destination);
}
async function resend() {
    resending.value = true;
    message.value = "";
    error.value = "";
    const response = await fetch("/two-factor/resend", {
        method: "POST",
        credentials: "same-origin",
        headers,
    });
    const data = await response.json();
    resending.value = false;
    if (!response.ok) {
        error.value = data.message || "Não foi possível reenviar o código.";
        return;
    }
    digits.value = Array(6).fill("");
    secondsLeft.value = 600;
    message.value = data.message;
    focus(0);
}

onMounted(() => {
    focus(0);
    timer = window.setInterval(() => {
        if (secondsLeft.value > 0) secondsLeft.value -= 1;
    }, 1000);
});
onUnmounted(() => window.clearInterval(timer));
</script>

<template>
    <v-container class="py-16" style="max-width: 540px"
        ><v-card class="pa-8 pa-md-10" rounded="xl" elevation="3"
            ><div class="text-center">
                <span class="brand-mark"
                    ><v-icon icon="mdi-email-check-outline"
                /></span>
                <h1 class="text-h4 font-weight-bold mt-4">
                    Confirme seu acesso
                </h1>
                <p class="text-medium-emphasis mt-3">
                    Enviamos um código de 6 dígitos ao seu e-mail.
                </p>
                <v-chip
                    :color="expired ? 'error' : 'primary'"
                    variant="tonal"
                    class="mt-3"
                    :prepend-icon="
                        expired
                            ? 'mdi-alert-circle-outline'
                            : 'mdi-timer-outline'
                    "
                    >{{
                        expired ? "Código expirado" : `Expira em ${countdown}`
                    }}</v-chip
                >
            </div>
            <v-alert v-if="error" type="error" variant="tonal" class="my-5">{{
                error
            }}</v-alert
            ><v-alert
                v-if="message"
                type="success"
                variant="tonal"
                class="my-5"
                >{{ message }}</v-alert
            ><v-form class="mt-7" @submit.prevent="verify"
                ><div class="d-flex justify-center ga-2 ga-sm-3">
                    <v-text-field
                        v-for="(_, index) in digits"
                        :key="index"
                        :ref="(element) => (inputs[index] = element)"
                        :model-value="digits[index]"
                        :disabled="expired"
                        class="code-box"
                        variant="outlined"
                        hide-details
                        maxlength="1"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        @update:model-value="updateDigit(index, $event)"
                        @keydown="handleKeydown(index, $event)"
                        @paste.prevent="paste(index, $event)"
                    />
                </div>
                <v-btn
                    type="submit"
                    :loading="loading"
                    :disabled="expired"
                    color="primary"
                    size="large"
                    block
                    class="mt-8"
                    >Confirmar e entrar</v-btn
                ></v-form
            >
            <div class="text-center mt-5">
                <v-btn
                    :loading="resending"
                    :disabled="resending"
                    variant="text"
                    color="primary"
                    @click="resend"
                    >Reenviar código</v-btn
                >
            </div></v-card
        ></v-container
    >
</template>

<style scoped>
.code-box {
    max-width: 58px;
}
.code-box :deep(input) {
    font-size: 1.35rem;
    font-weight: 700;
    text-align: center;
    padding-inline: 0;
}
</style>
