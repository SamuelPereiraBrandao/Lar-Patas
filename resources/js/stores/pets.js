import { defineStore } from "pinia";
import { ref } from "vue";
export function petStatusLabel(pet) {
    if (pet?.ownership_kind === "guardian") return "Pet da família";
    return (
        {
            available: "Disponível para adoção",
            in_process: "Em processo de adoção",
            adopted: "Adotado pela plataforma",
        }[pet?.status] || "Situação não informada"
    );
}
export const usePetsStore = defineStore("pets", () => {
    const pets = ref([]),
        loading = ref(false);
    async function fetchPets(filters = {}) {
        loading.value = true;
        try {
            const p = new URLSearchParams(
                Object.entries(filters).filter(([, v]) => v),
            );
            const r = await fetch(`/api/pets?${p}`);
            pets.value = (await r.json()).data || [];
        } finally {
            loading.value = false;
        }
    }
    return { pets, loading, fetchPets };
});
