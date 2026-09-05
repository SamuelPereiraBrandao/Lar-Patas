<script setup>
import { computed, ref, watch } from "vue";
import { notify } from "../../stores/ui";

const open = defineModel({ default: false });
const props = defineProps({
    locations: { type: Array, default: () => [] },
});
const shelters = ref([]);
const search = ref("");
const createDialog = ref(false);
const editingShelter = ref(null);
const confirmDialog = ref(false);
const statusTarget = ref(null);
const saving = ref(false);
const form = ref({
    name: "",
    district: "",
    city: "São Paulo",
    state: "SP",
    address: "",
    active: true,
});
const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";
const headers = [
    { title: "Sede", key: "name" },
    { title: "Localidade", key: "city" },
    { title: "Pets", key: "pets_count" },
    { title: "Status", key: "active" },
    { title: "Ações", key: "actions", sortable: false },
];
const states = computed(() =>
    props.locations.map((location) => ({
        title: `${location.name} (${location.code})`,
        value: location.code,
    })),
);
const cities = computed(
    () =>
        props.locations
            .find((location) => location.code === form.value.state)
            ?.cities.map((city) => city.name) || [],
);
const filtered = computed(() =>
    shelters.value.filter((shelter) =>
        `${shelter.name} ${shelter.district || ""} ${shelter.city}`
            .toLowerCase()
            .includes(search.value.toLowerCase()),
    ),
);
async function load() {
    const response = await fetch("/api/admin/shelters", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) shelters.value = (await response.json()).data;
}
async function save() {
    saving.value = true;
    const editing = editingShelter.value;
    const response = await fetch(
        editing ? `/api/admin/shelters/${editing.id}` : "/api/admin/shelters",
        {
            method: editing ? "PUT" : "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrf,
            },
            body: JSON.stringify(form.value),
        },
    );
    saving.value = false;
    if (response.ok) {
        createDialog.value = false;
        resetForm();
        load();
        notify(
            editing
                ? "Sede atualizada com sucesso."
                : "Sede cadastrada com sucesso.",
        );
    } else {
        notify(
            editing
                ? "Não foi possível atualizar a sede."
                : "Não foi possível cadastrar a sede.",
            "error",
        );
    }
}
function resetForm() {
    editingShelter.value = null;
    form.value = {
        name: "",
        district: "",
        city: "São Paulo",
        state: "SP",
        address: "",
        active: true,
    };
}
function createShelter() {
    resetForm();
    createDialog.value = true;
}
function editShelter(shelter) {
    editingShelter.value = shelter;
    form.value = {
        name: shelter.name || "",
        district: shelter.district || "",
        city: shelter.city || "",
        state: shelter.state || "",
        address: shelter.address || "",
        active: shelter.active,
    };
    createDialog.value = true;
}
function askToggle(shelter) {
    statusTarget.value = shelter;
    confirmDialog.value = true;
}
async function toggle() {
    const shelter = statusTarget.value;
    if (!shelter) return;
    const response = await fetch(`/api/admin/shelters/${shelter.id}/status`, {
        method: "PUT",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf,
        },
        body: JSON.stringify({ active: !shelter.active }),
    });
    if (response.ok) {
        confirmDialog.value = false;
        notify(
            shelter.active
                ? "Sede desativada com sucesso."
                : "Sede ativada com sucesso.",
        );
        load();
    } else {
        notify("Não foi possível alterar o status da sede.", "error");
    }
}
watch(open, (value) => {
    if (value) load();
});
watch(
    () => form.value.state,
    () => {
        if (
            props.locations.length &&
            form.value.city &&
            !cities.value.includes(form.value.city)
        ) {
            form.value.city = "";
        }
    },
);
</script>

<template>
    <v-dialog v-model="open" max-width="1080"
        ><v-card rounded="xl"
            ><v-card-title class="pa-6 d-flex align-center"
                ><div>
                    <div class="text-h5 font-weight-bold">
                        Sedes de acolhimento
                    </div>
                    <div class="text-body-2 text-medium-emphasis mt-1">
                        Gerencie as unidades e os pets vinculados.
                    </div>
                </div>
                <v-spacer /><v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    @click="createShelter"
                    >Cadastrar sede</v-btn
                ></v-card-title
            ><v-card-text class="px-6 pb-6"
                ><v-text-field
                    v-model="search"
                    label="Procurar sede por nome"
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="comfortable"
                    clearable
                    class="mb-4" /><v-card rounded="lg" variant="outlined"
                    ><v-data-table
                        :headers="headers"
                        :items="filtered"
                        item-value="id"
                        hover
                        ><template #item.name="{ item }"
                            ><div class="font-weight-bold">{{ item.name }}</div>
                            <div class="text-caption">
                                {{ item.address || "Sem endereço informado" }}
                            </div></template
                        ><template #item.city="{ item }"
                            >{{ item.district ? `${item.district}, ` : ""
                            }}{{ item.city }} - {{ item.state }}</template
                        ><template #item.pets_count="{ item }"
                            ><v-chip
                                color="secondary"
                                size="small"
                                variant="tonal"
                                >{{ item.pets_count }} pets</v-chip
                            ></template
                        ><template #item.active="{ item }"
                            ><v-chip
                                :color="item.active ? 'success' : 'error'"
                                size="small"
                                >{{
                                    item.active ? "Ativa" : "Desativada"
                                }}</v-chip
                            ></template
                        ><template #item.actions="{ item }"
                            ><div class="d-flex ga-1 justify-end">
                                <v-btn
                                    icon="mdi-pencil-outline"
                                    color="primary"
                                    variant="tonal"
                                    size="small"
                                    @click="editShelter(item)"
                                /><v-btn
                                    :icon="
                                        item.active
                                            ? 'mdi-account-off-outline'
                                            : 'mdi-account-check-outline'
                                    "
                                    :color="item.active ? 'error' : 'success'"
                                    variant="tonal"
                                    size="small"
                                    @click="askToggle(item)"
                                /></div></template></v-data-table></v-card></v-card-text
            ><v-card-actions class="px-6 pb-6"
                ><v-spacer /><v-btn variant="text" @click="open = false"
                    >Fechar</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
    <v-dialog v-model="createDialog" max-width="620"
        ><v-card rounded="xl"
            ><v-card-title class="pa-6">{{
                editingShelter ? "Editar sede" : "Cadastrar nova sede"
            }}</v-card-title
            ><v-card-text
                ><v-row
                    ><v-col cols="12"
                        ><v-text-field
                            v-model="form.name"
                            label="Nome da sede"
                            variant="outlined" /></v-col
                    ><v-col cols="12" md="5"
                        ><v-text-field
                            v-model="form.district"
                            label="Bairro"
                            variant="outlined" /></v-col
                    ><v-col cols="12" md="3"
                        ><v-select
                            v-model="form.state"
                            :items="states"
                            label="Estado (UF)"
                            variant="outlined" /></v-col
                    ><v-col cols="12" md="4"
                        ><v-select
                            v-model="form.city"
                            :items="cities"
                            label="Cidade"
                            :disabled="!form.state"
                            variant="outlined" /></v-col
                    ><v-col cols="12"
                        ><v-text-field
                            v-model="form.address"
                            label="Endereço / referência"
                            variant="outlined" /></v-col></v-row></v-card-text
            ><v-card-actions class="pa-6 pt-0"
                ><v-spacer /><v-btn variant="text" @click="createDialog = false"
                    >Cancelar</v-btn
                ><v-btn :loading="saving" color="primary" @click="save">{{
                    editingShelter ? "Salvar alterações" : "Salvar sede"
                }}</v-btn></v-card-actions
            ></v-card
        ></v-dialog
    >
    <v-dialog v-model="confirmDialog" max-width="480"
        ><v-card rounded="xl"
            ><v-card-title class="pa-6">{{
                statusTarget?.active ? "Desativar sede?" : "Ativar sede?"
            }}</v-card-title
            ><v-card-text
                >Tem certeza que deseja
                {{ statusTarget?.active ? "desativar" : "ativar" }} a sede
                <strong>{{ statusTarget?.name }}</strong
                >?</v-card-text
            ><v-card-actions class="pa-6 pt-0"
                ><v-spacer /><v-btn
                    variant="text"
                    @click="confirmDialog = false"
                    >Cancelar</v-btn
                ><v-btn
                    :color="statusTarget?.active ? 'error' : 'success'"
                    @click="toggle"
                    >{{
                        statusTarget?.active ? "Desativar sede" : "Ativar sede"
                    }}</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
</template>
