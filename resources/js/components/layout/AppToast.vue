<script setup>
import { computed } from "vue";
import { toast } from "../../stores/ui";
const icon = computed(
    () =>
        ({
            success: "mdi-check-circle",
            error: "mdi-alert-circle",
            info: "mdi-information",
        })[toast.value.type] || "mdi-information",
);
</script>
<template>
    <v-snackbar
        v-model="toast.open"
        location="top"
        :timeout="3000"
        class="app-toast"
        ><div class="d-flex align-center ga-3">
            <v-icon :icon="icon" :color="toast.type" /><span
                class="toast-message"
                >{{ toast.message }}</span
            ><v-spacer /><v-btn
                icon="mdi-close"
                size="small"
                variant="text"
                @click="toast.open = false"
            /></div
    ></v-snackbar>
</template>
<style scoped>
.app-toast :deep(.v-snackbar__wrapper) {
    min-width: 320px;
    border: 1px solid rgba(var(--v-theme-primary), 0.28);
    border-radius: 14px;
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 14px 38px rgba(0, 0, 0, 0.18);
}
.app-toast :deep(.v-snackbar__content) {
    padding: 14px 16px;
    color: rgb(var(--v-theme-on-surface));
    font-weight: 600;
}
.toast-message {
    color: rgb(var(--v-theme-on-surface));
    opacity: 1;
}
.app-toast :deep(.v-btn) {
    color: rgb(var(--v-theme-on-surface));
    opacity: 0.85;
}
</style>
