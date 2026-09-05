<script setup>
import { computed, reactive, ref, watch } from "vue";
import ProfilePhotoPreview from "./ProfilePhotoPreview.vue";
const props = defineProps({
        modelValue: Boolean,
        profile: Object,
        locations: { type: Array, default: () => [] },
        stats: { type: Object, default: () => ({}) },
    }),
    emit = defineEmits(["update:modelValue", "saved", "notice"]);
const tab = ref("details"),
    saving = ref(false),
    avatarFile = ref(null),
    bannerFile = ref(null),
    form = reactive({
        name: "",
        phone: "",
        city: "",
        state: "",
        birth_date: "",
        housing_type: null,
        has_other_pets: false,
        household_description: "",
    });
const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") || "",
    jsonHeaders = {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf,
    },
    formHeaders = { Accept: "application/json", "X-CSRF-TOKEN": csrf };
const states = computed(() =>
        props.locations.map((x) => ({
            title: `${x.name} (${x.code})`,
            value: x.code,
        })),
    ),
    cities = computed(
        () =>
            props.locations
                .find((x) => x.code === form.state)
                ?.cities.map((x) => x.name) || [],
    );
function format(v) {
    const d = String(v || "")
        .replace(/\D/g, "")
        .slice(0, 11);
    return d.length > 10
        ? `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`
        : d.length > 6
          ? `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`
          : d.length > 2
            ? `(${d.slice(0, 2)}) ${d.slice(2)}`
            : d
              ? `(${d}`
              : "";
}
watch(
    () => props.profile,
    (v) => {
        if (v)
            Object.assign(form, v, {
                birth_date: v.birth_date?.slice(0, 10) || "",
            });
    },
    { immediate: true },
);
watch(
    () => form.phone,
    (v) => {
        const n = format(v);
        if (n !== v) form.phone = n;
    },
);
watch(
    () => form.state,
    () => {
        if (
            props.locations.length &&
            form.city &&
            !cities.value.includes(form.city)
        ) {
            form.city = "";
        }
    },
);
async function upload(url, key, file) {
    if (!file) return null;
    const fd = new FormData();
    fd.append(key, file);
    const r = await fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: formHeaders,
            body: fd,
        }),
        d = await r.json();
    if (!r.ok) throw Error(d.message || "Falha ao enviar imagem.");
    return d.user;
}
async function remove(kind) {
    const r = await fetch(`/api/profile/${kind}`, {
            method: "DELETE",
            credentials: "same-origin",
            headers: formHeaders,
        }),
        d = await r.json();
    if (!r.ok) return emit("notice", d.message || "Falha ao remover.");
    emit("saved", d.user);
    emit("notice", "Imagem removida.");
}
async function save() {
    saving.value = true;
    try {
        const r = await fetch("/api/profile", {
                method: "PUT",
                credentials: "same-origin",
                headers: jsonHeaders,
                body: JSON.stringify(form),
            }),
            d = await r.json();
        if (!r.ok) throw Error(d.message || "Falha ao salvar.");
        let user =
            (await upload("/api/profile/avatar", "avatar", avatarFile.value)) ||
            d.user;
        user =
            (await upload("/api/profile/banner", "banner", bannerFile.value)) ||
            user;
        emit("saved", user);
        emit("update:modelValue", false);
        emit("notice", "Perfil atualizado.");
    } catch (e) {
        emit("notice", e.message);
    } finally {
        saving.value = false;
    }
}
</script>
<template>
    <v-dialog
        :model-value="modelValue"
        max-width="800"
        scrollable
        @update:model-value="emit('update:modelValue', $event)"
        ><v-card rounded="xl"
            ><v-card-title class="pa-6 pb-2">Editar meu perfil</v-card-title
            ><v-tabs v-model="tab" color="primary" grow
                ><v-tab value="details">Informações</v-tab
                ><v-tab value="photos">Fotos do perfil</v-tab></v-tabs
            ><v-divider /><v-card-text class="pa-6"
                ><v-window v-model="tab"
                    ><v-window-item value="details"
                        ><v-row
                            ><v-col cols="12" md="6"
                                ><v-text-field
                                    v-model="form.name"
                                    label="Nome completo"
                                    variant="outlined" /></v-col
                            ><v-col cols="12" md="6"
                                ><v-text-field
                                    v-model="form.phone"
                                    label="Telefone"
                                    variant="outlined" /></v-col
                            ><v-col cols="12" md="4"
                                ><v-select
                                    v-model="form.state"
                                    :items="states"
                                    label="Estado (UF)"
                                    variant="outlined" /></v-col
                            ><v-col cols="12" md="8"
                                ><v-select
                                    v-model="form.city"
                                    :items="cities"
                                    label="Cidade"
                                    :disabled="!form.state"
                                    variant="outlined" /></v-col
                            ><v-col cols="12" md="6"
                                ><v-text-field
                                    v-model="form.birth_date"
                                    label="Data de nascimento"
                                    type="date"
                                    variant="outlined" /></v-col
                            ><v-col cols="12" md="6"
                                ><v-select
                                    v-model="form.housing_type"
                                    :items="[
                                        'Casa com quintal',
                                        'Casa sem quintal',
                                        'Apartamento',
                                    ]"
                                    label="Tipo de moradia"
                                    variant="outlined" /></v-col
                            ><v-col cols="12"
                                ><v-checkbox
                                    v-model="form.has_other_pets"
                                    label="Tenho outros animais em casa"
                                    color="primary" /></v-col
                            ><v-col cols="12"
                                ><v-textarea
                                    v-model="form.household_description"
                                    label="Uma pequena bio sobre seu lar"
                                    variant="outlined"
                                    rows="4" /></v-col></v-row></v-window-item
                    ><v-window-item value="photos"
                        ><p class="text-medium-emphasis mb-5">
                            Clique diretamente na capa ou no avatar para
                            selecionar uma imagem. A página real só muda quando
                            salvar.
                        </p>
                        <ProfilePhotoPreview
                            :profile="profile"
                            :stats="stats"
                            @banner-selected="bannerFile = $event"
                            @avatar-selected="avatarFile = $event"
                            @remove-banner="remove('banner')"
                            @remove-avatar="remove('avatar')"
                            @notice="
                                emit('notice', $event)
                            " /></v-window-item></v-window></v-card-text
            ><v-card-actions class="pa-6 pt-0"
                ><v-spacer /><v-btn @click="emit('update:modelValue', false)"
                    >Cancelar</v-btn
                ><v-btn color="primary" :loading="saving" @click="save"
                    >Salvar alterações</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
</template>
