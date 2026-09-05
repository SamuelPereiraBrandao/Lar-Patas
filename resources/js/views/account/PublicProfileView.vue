<script setup>
import ProfileView from "./ProfileView.vue";

defineProps({ id: String });
</script>

<template><ProfileView :user-id="id" /></template>
