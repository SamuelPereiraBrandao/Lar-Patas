<script setup>
import ProfileView from "./ProfileView.vue";

defineProps({ id: String });
</script>

<template><ProfileView :key="id" :user-id="id" /></template>
