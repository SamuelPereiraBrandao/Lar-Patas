<script setup>
import DateField from "../DateField.vue";
import { computed, ref, watch } from "vue";
import { request } from "../../stores/requests";
import { notify } from "../../stores/ui";
const props = defineProps({ modelValue: Boolean, pet: Object });
const emit = defineEmits(["update:modelValue"]);
const records = ref([]),
    canEdit = ref(false),
    loading = ref(false),
    saving = ref(false),
    adding = ref(false),
    deleting = ref(null);
const page = ref(1);
const perPage = 5;
const pageCount = computed(() =>
    Math.max(1, Math.ceil(records.value.length / perPage)),
);
const visibleRecords = computed(() =>
    records.value.slice((page.value - 1) * perPage, page.value * perPage),
);
const empty = () => ({
    kind: "vaccine",
    title: "",
    performed_at: null,
    due_at: null,
    notes: "",
});
const form = ref(empty());
const dateFields = [
    { key: "performed_at", label: "Data de realização" },
    { key: "due_at", label: "Próxima dose ou retorno" },
];
const kinds = [
    { title: "Vacina", value: "vaccine" },
    { title: "Castração", value: "neutering" },
    { title: "Consulta", value: "consultation" },
    { title: "Cuidados", value: "care" },
];
async function load() {
    loading.value = true;
    try {
        const data = await request(`/api/pets/${props.pet.id}/health`);
        records.value = data.data;
        page.value = Math.min(page.value, pageCount.value);
        canEdit.value = data.can_edit;
    } catch (e) {
        notify(e.message, "error");
    } finally {
        loading.value = false;
    }
}
watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            page.value = 1;
            form.value = empty();
            adding.value = false;
            load();
        }
    },
);
async function save() {
    if (saving.value || !form.value.title.trim()) return;
    saving.value = true;
    try {
        await request(`/api/pets/${props.pet.id}/health`, "POST", form.value);
        adding.value = false;
        form.value = empty();
        await load();
        notify("Registro de saúde salvo.");
    } catch (e) {
        notify(e.message, "error");
    } finally {
        saving.value = false;
    }
}
async function remove() {
    try {
        await request(
            `/api/pets/${props.pet.id}/health/${deleting.value.id}`,
            "DELETE",
        );
        deleting.value = null;
        await load();
    } catch (e) {
        notify(e.message, "error");
    }
}
const date = (value) =>
    value
        ? new Date(value + "T12:00:00").toLocaleDateString("pt-BR")
        : "Não informada";
</script>
<template>
    <v-dialog
        :model-value="modelValue"
        max-width="760"
        scrollable
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card rounded="xl">
            <div class="d-flex align-center ga-3 pa-6">
                <v-avatar color="primary" variant="tonal" rounded="lg" size="48"
                    ><v-icon icon="mdi-medical-bag"
                /></v-avatar>
                <div class="health-heading">
                    <h2 class="text-h6 font-weight-bold">
                        Saúde de {{ pet?.name }}
                    </h2>
                    <span class="text-caption text-medium-emphasis"
                        ><v-icon icon="mdi-lock-outline" size="14" /> Ficha
                        privada de saúde</span
                    >
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    size="small"
                    aria-label="Fechar ficha de saúde"
                    @click="emit('update:modelValue', false)"
                />
            </div>
            <v-divider />
            <v-card-text class="pa-6">
                <p class="text-body-2 text-medium-emphasis mb-5">
                    Vacinas, consultas e cuidados em um só lugar. Registre as
                    orientações da equipe veterinária.
                </p>
                <v-progress-linear
                    v-if="loading"
                    indeterminate
                    color="primary"
                    class="mb-4"
                />
                <template v-else>
                    <v-form
                        v-if="adding"
                        class="health-form pa-4 pa-sm-5 mb-6"
                        @submit.prevent="save"
                    >
                        <h3 class="text-subtitle-1 font-weight-bold mb-5">
                            Novo registro
                        </h3>
                        <v-row>
                            <v-col cols="12" sm="4"
                                ><v-select
                                    v-model="form.kind"
                                    :items="kinds"
                                    label="Tipo de cuidado"
                                    hide-details
                            /></v-col>
                            <v-col cols="12" sm="8"
                                ><v-text-field
                                    v-model="form.title"
                                    label="Nome da vacina ou cuidado"
                                    maxlength="120"
                                    hide-details
                            /></v-col>
                            <v-col
                                v-for="field in dateFields"
                                :key="field.key"
                                cols="12"
                                sm="6"
                            >
                                <DateField
                                    v-model="form[field.key]"
                                    :label="field.label"
                                    hide-details
                                />
                            </v-col>
                            <v-col cols="12"
                                ><v-textarea
                                    v-model="form.notes"
                                    label="Orientações e observações"
                                    placeholder="Dose, profissional responsável e cuidados recomendados…"
                                    rows="3"
                                    auto-grow
                                    maxlength="2000"
                                    hide-details
                            /></v-col>
                        </v-row>
                        <div class="d-flex flex-wrap justify-end ga-2 mt-5">
                            <v-btn
                                variant="text"
                                :disabled="saving"
                                @click="adding = false"
                                >Cancelar</v-btn
                            >
                            <v-btn
                                type="submit"
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-check"
                                :loading="saving"
                                :disabled="!form.title.trim()"
                                >Salvar registro</v-btn
                            >
                        </div>
                    </v-form>
                    <div
                        class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4"
                    >
                        <h3 class="text-subtitle-1 font-weight-bold">
                            Histórico
                            <span class="text-body-2 text-medium-emphasis"
                                >({{ records.length }})</span
                            >
                        </h3>
                        <v-btn
                            v-if="canEdit && !adding"
                            color="primary"
                            variant="tonal"
                            prepend-icon="mdi-plus"
                            @click="
                                form = empty();
                                adding = true;
                            "
                            >Adicionar registro</v-btn
                        >
                    </div>
                    <div
                        v-if="!records.length"
                        class="health-empty text-center pa-8"
                    >
                        <v-avatar
                            color="primary"
                            variant="tonal"
                            size="56"
                            class="mb-3"
                            ><v-icon
                                icon="mdi-clipboard-heart-outline"
                                size="28"
                        /></v-avatar>
                        <h3 class="text-subtitle-1 font-weight-bold">
                            Os cuidados começam aqui
                        </h3>
                        <p class="text-body-2 text-medium-emphasis mt-1">
                            Nenhum registro de saúde cadastrado.
                        </p>
                    </div>
                    <v-card
                        v-for="record in visibleRecords"
                        :key="record.id"
                        elevation="0"
                        rounded="lg"
                        class="health-record pa-4 pa-sm-5 mb-3"
                    >
                        <div class="d-flex align-start ga-3">
                            <div class="health-heading">
                                <v-chip
                                    size="small"
                                    color="primary"
                                    variant="tonal"
                                    class="mb-2"
                                    >{{
                                        kinds.find(
                                            (k) => k.value === record.kind,
                                        )?.title
                                    }}</v-chip
                                >
                                <h3 class="text-subtitle-1 font-weight-bold">
                                    {{ record.title }}
                                </h3>
                            </div>
                            <v-btn
                                v-if="canEdit"
                                icon="mdi-delete-outline"
                                variant="text"
                                color="error"
                                size="small"
                                aria-label="Excluir registro"
                                @click="deleting = record"
                            />
                        </div>
                        <div class="d-flex flex-wrap ga-4 mt-4">
                            <div class="health-date">
                                <v-icon
                                    icon="mdi-calendar-check-outline"
                                    color="primary"
                                    size="20"
                                />
                                <div>
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Realizado em
                                    </div>
                                    <span class="text-body-2">{{
                                        date(record.performed_at)
                                    }}</span>
                                </div>
                            </div>
                            <div v-if="record.due_at" class="health-date">
                                <v-icon
                                    icon="mdi-calendar-clock-outline"
                                    color="primary"
                                    size="20"
                                />
                                <div>
                                    <div
                                        class="text-caption text-medium-emphasis"
                                    >
                                        Próxima dose ou retorno
                                    </div>
                                    <span class="text-body-2">{{
                                        date(record.due_at)
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <p
                            v-if="record.notes"
                            class="health-notes text-body-2 text-medium-emphasis mt-4 pt-4"
                        >
                            {{ record.notes }}
                        </p>
                    </v-card>
                </template>
            </v-card-text>
            <v-divider />
            <v-card-actions class="px-6 py-4">
                <div
                    v-if="!loading && records.length"
                    class="d-flex flex-wrap align-center ga-2"
                >
                    <span
                        class="text-caption text-medium-emphasis"
                        aria-live="polite"
                    >
                        {{ (page - 1) * perPage + 1 }}–{{
                            Math.min(page * perPage, records.length)
                        }}
                        de {{ records.length }} registros
                    </span>
                    <v-pagination
                        v-if="pageCount > 1"
                        v-model="page"
                        :length="pageCount"
                        :total-visible="3"
                        size="small"
                        color="primary"
                        rounded="lg"
                        aria-label="Páginas do histórico de saúde"
                    />
                </div>
                <v-spacer /><v-btn
                    variant="text"
                    color="primary"
                    @click="emit('update:modelValue', false)"
                    >Fechar</v-btn
                ></v-card-actions
            >
        </v-card>
    </v-dialog>
    <v-dialog
        :model-value="!!deleting"
        max-width="420"
        @update:model-value="deleting = null"
    >
        <v-card title="Excluir registro?"
            ><v-card-text
                >O registro {{ deleting?.title }} será removido da
                ficha.</v-card-text
            ><v-card-actions
                ><v-spacer /><v-btn variant="text" @click="deleting = null"
                    >Cancelar</v-btn
                ><v-btn color="error" variant="tonal" @click="remove"
                    >Excluir</v-btn
                ></v-card-actions
            ></v-card
        >
    </v-dialog>
</template>
<style scoped>
.health-heading {
    flex: 1;
    min-width: 0;
    overflow-wrap: anywhere;
}
.health-form,
.health-empty {
    background: rgba(var(--v-theme-primary), 0.04);
    border: 1px solid rgba(var(--v-theme-primary), 0.15);
    border-radius: 16px;
}
.health-record {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.health-date {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 190px;
}
.health-notes {
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
</style>
