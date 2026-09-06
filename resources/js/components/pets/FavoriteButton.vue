<script setup>
import { ref } from "vue";
import { isLogged, notify } from "../../stores/ui";
import { request } from "../../stores/requests";
const props = defineProps({ pet: Object });
const busy = ref(false);
async function toggle() {
    if (!isLogged.value)
        return notify("Entre na sua conta para salvar favoritos.", "info");
    if (busy.value) return;
    busy.value = true;
    try {
        const data = await request(
            `/api/pets/${props.pet.id}/favorite`,
            props.pet.is_favorited ? "DELETE" : "PUT",
        );
        props.pet.is_favorited = data.is_favorited;
    } catch (error) {
        notify(error.message, "error");
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <v-btn
        :icon="pet.is_favorited ? 'mdi-bookmark' : 'mdi-bookmark-outline'"
        :aria-label="
            pet.is_favorited ? 'Remover dos favoritos' : 'Salvar nos favoritos'
        "
        :title="
            pet.is_favorited ? 'Remover dos favoritos' : 'Salvar nos favoritos'
        "
        :loading="busy"
        color="primary"
        variant="tonal"
        size="small"
        @click.stop="toggle"
    />
</template>
