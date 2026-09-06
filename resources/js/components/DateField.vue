<script setup>
import { computed, ref } from "vue";
defineOptions({ inheritAttrs: false });
const model = defineModel({ default: null });
const props = defineProps({ label: String, datetime: Boolean });
const open = ref(false);
const selected = ref(null);
const hour = ref("09");
const minute = ref("00");
const hours = Array.from({ length: 24 }, (_, n) => String(n).padStart(2, "0"));
const minutes = Array.from({ length: 60 }, (_, n) =>
    String(n).padStart(2, "0"),
);
const display = computed(() => {
    if (!model.value) return "";
    const [day, time] = model.value.split("T");
    return (
        day.split("-").reverse().join("/") +
        (props.datetime && time ? ` ${time.slice(0, 5)}` : "")
    );
});
function show() {
    const [day, time] = (model.value || "").split("T");
    selected.value = day ? new Date(`${day}T12:00:00`) : null;
    [hour.value, minute.value] = time
        ? time.slice(0, 5).split(":")
        : ["09", "00"];
    open.value = true;
}
function confirm() {
    if (!selected.value) return;
    const day = new Date(selected.value);
    const value = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, "0")}-${String(day.getDate()).padStart(2, "0")}`;
    model.value = props.datetime
        ? `${value}T${hour.value}:${minute.value}`
        : value;
    open.value = false;
}
</script>
<template>
    <v-text-field
        v-bind="$attrs"
        :model-value="display"
        :label="label"
        readonly
        clearable
        prepend-inner-icon="mdi-calendar-month-outline"
        placeholder="Selecione uma data"
        @click="show"
        @keydown.enter.prevent="show"
        @keydown.space.prevent="show"
        @click:clear.stop="model = null"
    />
    <v-dialog v-model="open" max-width="380">
        <v-card rounded="xl">
            <div class="d-flex align-center ga-2 px-5 pt-5 pb-2">
                <h2 class="text-subtitle-1 font-weight-bold">
                    {{ label || "Selecione uma data" }}
                </h2>
                <v-spacer /><v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    aria-label="Fechar calendário"
                    @click="open = false"
                />
            </div>
            <v-date-picker
                v-model="selected"
                locale="pt-BR"
                color="primary"
                hide-header
                title="Selecione a data"
                :first-day-of-week="1"
                width="100%"
            />
            <div v-if="datetime" class="px-5 pb-3">
                <p class="text-body-2 font-weight-medium mb-3">Horário</p>
                <div class="d-flex ga-3">
                    <v-select
                        v-model="hour"
                        :items="hours"
                        label="Hora"
                        hide-details
                    /><v-select
                        v-model="minute"
                        :items="minutes"
                        label="Minuto"
                        hide-details
                    />
                </div>
            </div>
            <v-divider />
            <v-card-actions class="pa-4"
                ><v-btn variant="text" @click="open = false">Cancelar</v-btn
                ><v-spacer /><v-btn
                    color="primary"
                    variant="flat"
                    :disabled="!selected"
                    @click="confirm"
                    >Confirmar</v-btn
                ></v-card-actions
            >
        </v-card>
    </v-dialog>
</template>
