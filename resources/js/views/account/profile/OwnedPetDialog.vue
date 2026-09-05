<script setup>
import PetEditorDialog from "../../../components/pets/PetEditorDialog.vue";
defineProps({
    modelValue: Boolean,
    city: String,
    ownerName: String,
    canDelete: Boolean,
    state: String,
    locations: { type: Array, default: () => [] },
    pet: Object,
});
const emit = defineEmits(["update:modelValue", "saved", "deleted"]);
</script>
<template>
    <PetEditorDialog
        :model-value="modelValue"
        :city="city"
        :owner-name="ownerName"
        :state="state"
        :locations="locations"
        :pet="pet"
        :can-delete="canDelete"
        @update:model-value="emit('update:modelValue', $event)"
        @saved="emit('saved', $event)"
        @deleted="emit('deleted', $event)"
    />
</template>
