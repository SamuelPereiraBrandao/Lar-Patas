<script setup>
import { ref, onMounted } from "vue";
import { isLogged } from "../../stores/ui";
import { request } from "../../stores/requests";
const actions = ref([]);
onMounted(async () => {
    if (!isLogged.value) return;
    try {
        const [adoptions, notifications] = await Promise.all([
            request("/api/dashboards/receiver"),
            request("/api/notifications"),
        ]);
        for (const item of adoptions.data) {
            if (item.status === "approved" && !item.released_at)
                actions.value.push({
                    key: "pickup" + item.id,
                    title: "Retirada de " + item.pet.name,
                    body: item.reschedule_requested_at
                        ? "Reagendamento aguardando a equipe."
                        : "Confira o local, a data e seu código de retirada.",
                    to: "/painel",
                    icon: "mdi-calendar-check",
                });
            else if (item.released_at && !item.followup_completed_at)
                actions.value.push({
                    key: "care" + item.id,
                    title: "Como está " + item.pet.name + "?",
                    body: "Conte à equipe como está a adaptação.",
                    to: "/painel",
                    icon: "mdi-heart-outline",
                });
        }
        const invites = notifications.data.filter(
            (n) =>
                !n.read_at &&
                (n.friend_request?.status === "pending" ||
                    n.pet_request_status === "pending"),
        );
        for (const invite of invites) {
            const pet = invite.pet_request_status === "pending";
            actions.value.push({
                key: "invite" + invite.id,
                title: invite.title,
                body: "Abra para aceitar ou recusar o convite.",
                to: pet
                    ? "/pets/" + invite.data.pet_id
                    : "/perfil/" + invite.friend_request.sender.id,
                icon: "mdi-account-plus-outline",
            });
        }
    } catch {
        /* The feed remains usable when pending actions cannot be loaded. */
    }
});
</script>
<template>
    <v-container v-if="actions.length" class="pb-0"
        ><v-card rounded="xl" variant="tonal" color="primary" class="pa-5"
            ><div class="d-flex justify-space-between align-center mb-3">
                <h2 class="text-h6">Sua atenção faz a diferença</h2>
                <v-chip size="small">{{ actions.length }}</v-chip>
            </div>
            <v-list bg-color="transparent"
                ><v-list-item
                    v-for="action in actions.slice(0, 3)"
                    :key="action.key"
                    :title="action.title"
                    :subtitle="action.body"
                    :prepend-icon="action.icon"
                    :to="action.to"
                    append-icon="mdi-arrow-right"
                    rounded="lg" /></v-list></v-card
    ></v-container>
</template>
