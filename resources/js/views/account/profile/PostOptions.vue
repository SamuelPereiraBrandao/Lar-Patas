<script setup>
import { ref } from "vue";
import { request } from "../../../stores/requests";
import { notify } from "../../../stores/ui";
const props = defineProps({ post: Object });
const emit = defineEmits(["changed"]);
const editOpen = ref(false),
    deleteOpen = ref(false),
    savingPost = ref(false),
    editedBody = ref("");
async function changePost(remove = false) {
    if (savingPost.value) return;
    savingPost.value = true;
    try {
        await request(
            `/api/profile/posts/${props.post.id}`,
            remove ? "DELETE" : "PATCH",
            remove ? undefined : { body: editedBody.value },
        );
        editOpen.value = false;
        deleteOpen.value = false;
        emit("changed");
        notify(remove ? "Publicação excluída." : "Texto atualizado.");
    } catch (error) {
        notify(error.message, "error");
    } finally {
        savingPost.value = false;
    }
}
</script>
<template>
    <v-menu location="bottom end">
        <template #activator="{ props: menuProps }"
            ><v-btn
                v-bind="menuProps"
                icon="mdi-dots-horizontal"
                size="small"
                variant="text"
                aria-label="Opções da publicação"
        /></template>
        <v-list rounded="lg" density="compact">
            <v-list-item
                title="Editar texto"
                prepend-icon="mdi-pencil-outline"
                @click="
                    editedBody = post.body || '';
                    editOpen = true;
                "
            />
            <v-list-item
                title="Excluir publicação"
                prepend-icon="mdi-delete-outline"
                base-color="error"
                @click="deleteOpen = true"
            />
        </v-list>
    </v-menu>
    <v-dialog v-model="editOpen" max-width="560" :persistent="savingPost"
        ><v-card title="Editar publicação"
            ><v-card-text
                ><v-textarea
                    v-model="editedBody"
                    label="Texto da publicação"
                    maxlength="2000"
                    counter
                    auto-grow /></v-card-text
            ><v-card-actions
                ><v-spacer /><v-btn
                    :disabled="savingPost"
                    @click="editOpen = false"
                    >Cancelar</v-btn
                ><v-btn
                    color="primary"
                    variant="flat"
                    :loading="savingPost"
                    :disabled="!editedBody.trim()"
                    @click="changePost()"
                    >Salvar texto</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
    <v-dialog v-model="deleteOpen" max-width="440" :persistent="savingPost"
        ><v-card title="Excluir publicação?"
            ><v-card-text
                >A publicação ficará oculta para os usuários. Administradores
                ainda poderão consultá-la.</v-card-text
            ><v-card-actions
                ><v-spacer /><v-btn
                    :disabled="savingPost"
                    @click="deleteOpen = false"
                    >Cancelar</v-btn
                ><v-btn
                    color="error"
                    variant="flat"
                    :loading="savingPost"
                    @click="changePost(true)"
                    >Excluir publicação</v-btn
                ></v-card-actions
            ></v-card
        ></v-dialog
    >
</template>
