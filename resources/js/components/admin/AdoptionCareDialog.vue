<script setup>
import DateField from "../DateField.vue";
import { ref, watch } from "vue";
import { request } from "../../stores/requests";
import { notify } from "../../stores/ui";
const props = defineProps({
    modelValue: Boolean,
    adoption: Object,
    action: String,
});
const emit = defineEmits(["update:modelValue", "saved"]);
const reason = ref(""),
    date = ref(""),
    status = ref("well"),
    notes = ref(""),
    saving = ref(false),
    support = ref(""),
    resolved = ref(false);
watch(
    () => props.modelValue,
    () => {
        reason.value = "";
        date.value = "";
        support.value = "";
        resolved.value = false;
        notes.value = props.adoption?.adaptation_notes || "";
        status.value = props.adoption?.adaptation_status || "well";
    },
);
async function save() {
    saving.value = true;
    try {
        await request(`/api/adoptions/${props.adoption.id}/care`, "PATCH", {
            action: props.action,
            reason: reason.value || null,
            requested_pickup_at: date.value
                ? new Date(date.value).toISOString()
                : null,
            adaptation_status: status.value,
            adaptation_notes: notes.value,
            support_message: support.value,
            resolved: resolved.value,
        });
        notify(
            props.action === "reschedule"
                ? "Pedido enviado. A equipe confirmará a nova data."
                : "Atualização salva.",
        );
        emit("saved");
        emit("update:modelValue", false);
    } catch (e) {
        notify(e.message, "error");
    } finally {
        saving.value = false;
    }
}
</script>
<template>
    <v-dialog
        :model-value="modelValue"
        max-width="560"
        :persistent="saving"
        @update:model-value="emit('update:modelValue', $event)"
        ><v-card rounded="xl"
            ><v-card-title>{{
                action === "support"
                    ? "Responder acompanhamento"
                    : action === "cancel"
                      ? "Cancelar solicitação"
                      : action === "reschedule"
                        ? "Pedir reagendamento"
                        : "Como está a adaptação?"
            }}</v-card-title
            ><v-card-text
                ><h3 class="text-h6 mb-4">{{ adoption?.pet?.name }}</h3>
                <template v-if="action === 'support'"
                    ><p class="mb-4">{{ adoption?.adaptation_notes }}</p>
                    <v-textarea
                        v-model="support"
                        label="Resposta para o adotante"
                        maxlength="2000"
                        rows="4" /><v-checkbox
                        v-model="resolved"
                        label="Marcar pedido de ajuda como atendido"
                /></template>
                <template v-else-if="action === 'followup'"
                    ><v-select
                        v-model="status"
                        label="Como vocês estão?"
                        :items="[
                            { title: 'Tudo bem', value: 'well' },
                            { title: 'Em adaptação', value: 'adjusting' },
                            {
                                title: 'Preciso de ajuda da equipe',
                                value: 'needs_help',
                            },
                        ]" /><v-textarea
                        v-model="notes"
                        label="Conte como estão os primeiros dias"
                        maxlength="2000"
                        rows="4"
                /></template>
                <template v-else
                    ><v-alert
                        class="mb-4"
                        variant="tonal"
                        :color="action === 'cancel' ? 'warning' : 'info'"
                        >{{
                            action === "cancel"
                                ? "A reserva será encerrada e o pet poderá ser adotado por outra pessoa."
                                : "A data atual permanece válida até a equipe confirmar o reagendamento."
                        }}</v-alert
                    ><DateField
                        v-if="action === 'reschedule'"
                        v-model="date"
                        datetime
                        label="Data e horário sugeridos" /><v-textarea
                        v-model="reason"
                        label="Motivo (pelo menos 10 caracteres)"
                        rows="3"
                        maxlength="2000"
                /></template> </v-card-text
            ><v-card-actions
                ><v-spacer /><v-btn
                    :disabled="saving"
                    @click="emit('update:modelValue', false)"
                    >Voltar</v-btn
                ><v-btn
                    :color="action === 'cancel' ? 'error' : 'primary'"
                    variant="flat"
                    :loading="saving"
                    @click="save"
                    >{{
                        action === "cancel"
                            ? "Confirmar cancelamento"
                            : "Enviar"
                    }}</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
</template>
