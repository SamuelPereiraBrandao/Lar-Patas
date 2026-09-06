<script setup>
import DateField from "../DateField.vue";
import { computed, ref, watch } from "vue";
import { notify } from "../../stores/ui";

const props = defineProps({
    modelValue: Boolean,
    pet: Object,
    adoption: Object,
    release: Boolean,
});
const emit = defineEmits(["update:modelValue", "saved"]);
const pickupAt = ref("");
const location = ref("");
const message = ref("");
const code = ref("");
const saving = ref(false);
const errors = ref({});
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
const person = computed(
    () => props.adoption?.user?.name || props.adoption?.applicant_name,
);
watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        errors.value = {};
        code.value = "";
        pickupAt.value = "";
        const shelter = props.pet?.shelter;
        location.value = shelter
            ? [
                  shelter.name,
                  shelter.address,
                  shelter.district,
                  shelter.city,
                  shelter.state,
              ]
                  .filter(Boolean)
                  .join(", ")
            : "";
        if (props.adoption?.requested_pickup_at || props.adoption?.pickup_at) {
            const date = new Date(
                props.adoption.requested_pickup_at || props.adoption.pickup_at,
            );
            date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
            pickupAt.value = date.toISOString().slice(0, 16);
        }
        location.value = props.adoption?.pickup_location || location.value;
        message.value = `Olá, ${person.value}! Estamos esperando você para buscar ${props.pet?.name}. Apresente seu código de verificação à equipe na chegada.`;
    },
);

async function submit() {
    if (saving.value) return;
    errors.value = {};
    if (props.release) {
        if (!/^\d{6}$/.test(code.value))
            errors.value.code = ["Informe os seis dígitos do código."];
    } else {
        if (!pickupAt.value || new Date(pickupAt.value).getTime() <= Date.now())
            errors.value.pickup_at = ["Escolha uma data e horário futuros."];
        if (!location.value.trim())
            errors.value.pickup_location = ["Informe o local de retirada."];
        if (!message.value.trim())
            errors.value.pickup_message = [
                "Escreva uma mensagem para o adotante.",
            ];
    }
    if (Object.keys(errors.value).length) return;
    saving.value = true;
    try {
        const response = await fetch(
            `/api/adoptions/${props.adoption.id}/${props.release ? "release" : "pickup"}`,
            {
                method:
                    !props.release && props.adoption?.status === "approved"
                        ? "PATCH"
                        : "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content || "",
                },
                body: JSON.stringify(
                    props.release
                        ? { code: code.value }
                        : {
                              pickup_at: new Date(pickupAt.value).toISOString(),
                              pickup_timezone: timezone,
                              pickup_location: location.value,
                              pickup_message: message.value,
                          },
                ),
            },
        );
        const data = await response.json();
        if (!response.ok) {
            errors.value = data.errors || {};
            notify(
                response.status === 429
                    ? "Muitas tentativas. Aguarde um minuto antes de tentar novamente."
                    : data.message ||
                          "Não foi possível concluir. Tente novamente.",
                "error",
            );
            return;
        }
        notify(
            props.release
                ? "Entrega confirmada! O pet e a publicação de adoção já estão no perfil do adotante."
                : "Retirada agendada! A notificação foi criada e o e-mail será enviado.",
        );
        emit("update:modelValue", false);
        emit("saved");
    } catch {
        notify(
            "Não foi possível concluir. Verifique sua conexão e tente novamente.",
            "error",
        );
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="620"
        scrollable
        :persistent="saving"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card rounded="xl">
            <v-card-title class="pa-6 pb-2 text-wrap">{{
                release
                    ? "Confirmar entrega e tornar dono"
                    : "Aprovar adoção e agendar retirada"
            }}</v-card-title>
            <v-card-text class="px-6">
                <div
                    class="d-flex align-center ga-4 pa-4 rounded-lg bg-surface-variant mb-5"
                >
                    <v-avatar size="64" rounded="lg" color="primary"
                        ><v-img
                            v-if="pet?.image_url"
                            :src="pet.image_url"
                            cover /><v-icon v-else icon="mdi-paw"
                    /></v-avatar>
                    <div>
                        <b>{{ pet?.name }}</b>
                        <p class="text-body-2">Futuro dono: {{ person }}</p>
                    </div>
                </div>
                <template v-if="release">
                    <v-alert
                        variant="tonal"
                        color="primary"
                        class="mb-5"
                        icon="mdi-shield-check-outline"
                        >Confira o código apresentado pelo adotante na retirada.
                        Ao confirmar, {{ pet?.name }} passará para o perfil de
                        {{ person }} e uma publicação de adoção será
                        criada.</v-alert
                    >
                    <v-text-field
                        v-model="code"
                        label="Código de verificação"
                        placeholder="000000"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="6"
                        variant="outlined"
                        :error-messages="errors.code"
                        @keydown.enter.prevent="submit"
                    />
                </template>
                <template v-else>
                    <p class="text-body-2 text-medium-emphasis mb-5">
                        O pet ficará aguardando liberação. A propriedade só será
                        transferida após a conferência do código pela equipe.
                    </p>
                    <DateField
                        v-model="pickupAt"
                        datetime
                        label="Data e horário da retirada"
                        variant="outlined"
                        :hint="`Fuso horário: ${timezone}`"
                        persistent-hint
                        :error-messages="errors.pickup_at"
                        class="mb-3"
                    />
                    <v-textarea
                        v-model="location"
                        label="Local de retirada"
                        rows="2"
                        auto-grow
                        maxlength="500"
                        variant="outlined"
                        :error-messages="errors.pickup_location"
                    />
                    <v-textarea
                        v-model="message"
                        label="Mensagem para o adotante"
                        rows="4"
                        maxlength="2000"
                        counter
                        variant="outlined"
                        :error-messages="errors.pickup_message"
                    />
                    <p class="text-caption text-medium-emphasis">
                        A data, o local, a mensagem e o código serão enviados
                        por e-mail e notificação.
                    </p>
                </template>
            </v-card-text>
            <v-card-actions class="pa-6 pt-3 d-flex flex-wrap ga-2">
                <v-spacer /><v-btn
                    :disabled="saving"
                    @click="emit('update:modelValue', false)"
                    >Cancelar</v-btn
                >
                <v-btn
                    color="primary"
                    variant="flat"
                    rounded="lg"
                    :loading="saving"
                    :prepend-icon="
                        release
                            ? 'mdi-check-circle-outline'
                            : 'mdi-calendar-check-outline'
                    "
                    @click="submit"
                    >{{
                        release ? "Confirmar entrega" : "Confirmar agendamento"
                    }}</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
