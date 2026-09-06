<script setup>
import { ref } from "vue";
const props = defineProps({ user: Object });
const emit = defineEmits(["navigate"]);
const summary = ref(null),
    loading = ref(false);
async function load(open) {
    if (!open || !props.user?.id || loading.value || summary.value) return;
    loading.value = true;
    try {
        const response = await fetch(`/api/users/${props.user.id}/profile`, {
            headers: { Accept: "application/json" },
        });
        if (response.ok) summary.value = await response.json();
    } finally {
        loading.value = false;
    }
}
</script>
<template>
    <v-menu
        open-on-hover
        :open-on-click="false"
        :close-delay="150"
        location="bottom start"
        @update:model-value="load"
    >
        <template #activator="{ props: menuProps }"
            ><router-link
                v-bind="menuProps"
                :to="`/perfil/${user?.id}`"
                class="profile-link"
                @click="emit('navigate')"
                >{{ user?.name }}</router-link
            ></template
        >
        <v-card width="290" rounded="xl" class="pa-4">
            <div class="d-flex align-center ga-3">
                <v-avatar color="primary" size="44"
                    ><v-img
                        v-if="user?.avatar_url"
                        :src="user.avatar_url"
                    /><span v-else>{{ user?.name?.[0] }}</span></v-avatar
                >
                <div>
                    <b>{{ user?.name }}</b>
                    <p class="text-caption text-medium-emphasis">
                        {{
                            [summary?.profile?.city, summary?.profile?.state]
                                .filter(Boolean)
                                .join(", ")
                        }}
                    </p>
                </div>
            </div>
            <v-progress-linear
                v-if="loading"
                indeterminate
                color="primary"
                class="mt-3"
            />
            <div v-if="summary" class="d-flex ga-4 text-body-2 mt-4">
                <span
                    ><b>{{ summary.stats.pets }}</b> pets</span
                ><span
                    ><b>{{ summary.stats.posts }}</b> publicações</span
                >
            </div>
            <v-btn
                :to="`/perfil/${user?.id}`"
                block
                variant="tonal"
                color="primary"
                class="mt-4"
                @click="emit('navigate')"
                >Ver perfil</v-btn
            >
        </v-card>
    </v-menu>
</template>
<style scoped>
.profile-link {
    color: inherit;
    font-weight: 700;
    text-decoration: none;
}
.profile-link:hover {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
}
</style>
