<script setup>
import { computed, onMounted, ref } from "vue";
import PetEditorDialog from "../../components/pets/PetEditorDialog.vue";
import { notify } from "../../stores/ui";

const pets = ref([]);
const loading = ref(true);
const failed = ref(false);
const editorOpen = ref(false);
const editingPet = ref(null);
const openingEditor = ref(null);
const profile = ref(null);
const locations = ref([]);
const shelterOptions = ref([]);

async function editPet(pet = null) {
    if (openingEditor.value !== null) return;
    openingEditor.value = pet?.id ?? 'new';
    try {
        const endpoints = ["/api/profile/social", "/api/locations"];
        if (pet && pet.ownership_kind !== "guardian") endpoints.push("/api/shelters");
        const responses = await Promise.all(endpoints.map(url => fetch(url, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        })));
        if (responses.some(response => !response.ok)) throw new Error();
        const [social, locality, shelters] = await Promise.all(responses.map(response => response.json()));
        const currentPet = pet ? social.my_pets.find(item => item.id === pet.id) : null;
        if (pet && !currentPet) throw new Error();
        profile.value = social.profile;
        locations.value = locality.data;
        shelterOptions.value = (shelters?.data || []).map(shelter => ({
            title: shelter.name, value: shelter.id, city: shelter.city, state: shelter.state,
        }));
        editingPet.value = currentPet;
        editorOpen.value = true;
    } catch {
        notify("Não foi possível abrir a edição. Tente novamente.", "error");
    } finally {
        openingEditor.value = null;
    }
}

function petChanged() {
    window.dispatchEvent(new Event("pets:changed"));
    load();
}
const availableCount = computed(() => pets.value.filter(pet => pet.status === "available" && pet.ownership_kind !== "guardian").length);
const interestsCount = computed(() => pets.value.reduce((total, pet) => total + (pet.adoptions?.length || 0), 0));

function status(pet) {
    if (pet.ownership_kind === "guardian") return { label: "Pet da família", icon: "mdi-home-heart", color: "primary" };
    return {
        available: { label: "Para adoção", icon: "mdi-heart-outline", color: "primary" },
        in_process: { label: "Em processo de adoção", icon: "mdi-clock-outline", color: "warning" },
        adopted: { label: "Adotado", icon: "mdi-check-circle-outline", color: "success" },
    }[pet.status] || { label: "Em acompanhamento", icon: "mdi-paw", color: "primary" };
}

async function load() {
    loading.value = true;
    failed.value = false;
    try {
        const response = await fetch("/api/dashboards/donor", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });
        if (!response.ok) throw new Error();
        pets.value = (await response.json()).data;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <v-container class="donor-page py-8 py-md-10">
        <header class="page-header">
            <div>
                <div class="eyebrow mb-2">MEUS COMPANHEIROS</div>
                <h1>Meus pets</h1>
                <p class="text-medium-emphasis mt-2">Cada pet, uma história. Acompanhe por aqui os próximos passos.</p>
            </div>
            <v-btn color="primary" rounded="lg" prepend-icon="mdi-plus" :loading="openingEditor === 'new'" :disabled="openingEditor !== null && openingEditor !== 'new'" @click="editPet()">Adicionar pet</v-btn>
        </header>

        <div v-if="!loading && !failed && pets.length" class="overview" aria-label="Resumo dos pets">
            <div class="overview-item">
                <v-avatar color="primary" variant="tonal" rounded="lg"><v-icon icon="mdi-paw" /></v-avatar>
                <div><strong>{{ pets.length }}</strong><span>{{ pets.length === 1 ? 'pet cadastrado' : 'pets cadastrados' }}</span></div>
            </div>
            <div class="overview-item">
                <v-avatar color="primary" variant="tonal" rounded="lg"><v-icon icon="mdi-home-search-outline" /></v-avatar>
                <div><strong>{{ availableCount }}</strong><span>para adoção</span></div>
            </div>
            <div class="overview-item">
                <v-avatar color="primary" variant="tonal" rounded="lg"><v-icon icon="mdi-account-heart-outline" /></v-avatar>
                <div><strong>{{ interestsCount }}</strong><span>{{ interestsCount === 1 ? 'interesse recebido' : 'interesses recebidos' }}</span></div>
            </div>
        </div>

        <div v-if="loading" class="pets-grid" role="status" aria-label="Carregando pets">
            <v-skeleton-loader v-for="index in 2" :key="index" type="image, article, actions" class="rounded-xl" />
        </div>
        <v-alert v-else-if="failed" type="error" variant="tonal" rounded="lg">
            Não foi possível carregar seus pets.
            <v-btn variant="text" class="ml-2" @click="load">Tentar novamente</v-btn>
        </v-alert>
        <template v-else-if="pets.length">
            <div class="d-flex align-center ga-3 mb-5">
                <h2 class="text-h6 font-weight-bold">Seus pets e acompanhamentos</h2>
                <v-chip size="small" variant="tonal" color="primary">{{ pets.length }}</v-chip>
            </div>
            <div class="pets-grid">
                <v-card v-for="pet in pets" :key="pet.id" class="donor-pet" rounded="xl" elevation="0" tag="article">
                    <router-link :to="`/pets/${pet.id}?from=my-pets`" class="pet-photo" :aria-label="`Ver perfil de ${pet.name}`">
                        <v-img v-if="pet.image_url" :src="pet.image_url" :alt="pet.name" height="230" cover>
                            <template #error><div class="photo-fallback"><v-icon icon="mdi-paw" size="56" /></div></template>
                        </v-img>
                        <div v-else class="photo-fallback"><v-icon icon="mdi-paw" size="56" /><span>Foto ainda não adicionada</span></div>
                        <v-chip class="photo-status" :color="status(pet).color" variant="flat" :prepend-icon="status(pet).icon" size="small">{{ status(pet).label }}</v-chip>
                    </router-link>
                    <div class="pet-body">
                        <div class="d-flex align-start justify-space-between ga-3">
                            <div>
                                <h3>{{ pet.name }}</h3>
                                <p class="text-body-2 text-medium-emphasis mt-1">{{ pet.species === 'cat' ? 'Gato' : 'Cachorro' }}<template v-if="pet.age_label"> · {{ pet.age_label }}</template></p>
                            </div>
                            <v-icon :icon="pet.species === 'cat' ? 'mdi-cat' : 'mdi-dog'" color="primary" size="28" />
                        </div>
                        <p class="pet-location text-body-2 text-medium-emphasis"><v-icon icon="mdi-map-marker-outline" size="18" />{{ [pet.city, pet.state].filter(Boolean).join(', ') || 'Localização não informada' }}</p>

                        <div v-if="pet.status === 'adopted' || pet.ownership_kind === 'guardian'" class="pet-progress">
                            <v-icon icon="mdi-home-heart" color="primary" size="22" />
                            <div><b>{{ pet.ownership_kind === 'guardian' ? 'Parte da família' : 'Um lar encontrado' }}</b><p>Este pet já tem um lar.</p></div>
                        </div>
                        <div v-else class="pet-progress">
                            <v-icon icon="mdi-clipboard-clock-outline" color="primary" size="22" />
                            <div><b>{{ pet.queue_position ? `Posição ${pet.queue_position} na fila` : 'Aguardando triagem' }}</b><p>{{ pet.queue_position ? 'Acompanhe as atualizações da equipe.' : 'A equipe ainda vai definir o atendimento.' }}</p></div>
                        </div>
                        <div v-if="pet.triage_notes" class="team-note"><span>RECADO DA EQUIPE</span><p>{{ pet.triage_notes }}</p></div>
                        <div class="pet-footer">
                            <span class="interest-count"><v-icon icon="mdi-account-heart-outline" size="19" />{{ pet.adoptions?.length || 0 }} {{ pet.adoptions?.length === 1 ? 'interessado' : 'interessados' }}</span>
                            <div class="d-flex flex-wrap ga-2">
                                <v-btn color="primary" variant="text" rounded="lg" prepend-icon="mdi-pencil-outline" :aria-label="`Editar ${pet.name}`" :loading="openingEditor === pet.id" :disabled="openingEditor !== null && openingEditor !== pet.id" @click="editPet(pet)">Editar</v-btn>
                                <v-btn :to="`/pets/${pet.id}?from=my-pets`" color="primary" variant="tonal" rounded="lg" append-icon="mdi-arrow-right">Ver pet</v-btn>
                            </div>
                        </div>
                    </div>
                </v-card>
            </div>
        </template>
        <div v-else class="empty-state">
            <v-avatar size="76" color="primary" variant="tonal"><v-icon icon="mdi-paw" size="38" /></v-avatar>
            <h2 class="text-h5 font-weight-bold mt-5">Sua história começa com um pet</h2>
            <p class="text-medium-emphasis mt-2 mb-6">Os pets que você cadastrar aparecerão aqui para acompanhar cada etapa.</p>
            <v-btn color="primary" rounded="lg" prepend-icon="mdi-plus" :loading="openingEditor === 'new'" @click="editPet()">Adicionar meu primeiro pet</v-btn>
        </div>
        <PetEditorDialog
            v-model="editorOpen"
            :pet="editingPet"
            :owner-name="profile?.name"
            :city="profile?.city"
            :state="profile?.state"
            :locations="locations"
            :administrative="!!editingPet && editingPet.ownership_kind !== 'guardian'"
            :shelter-options="shelterOptions"
            :can-delete="editingPet?.owner_id === profile?.id"
            @saved="petChanged"
            @deleted="petChanged"
        />
    </v-container>
</template>

<style scoped>
.donor-page { max-width: 1200px; }
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 28px; }
.eyebrow { color: rgb(var(--v-theme-primary)); font-size: .7rem; font-weight: 800; letter-spacing: .14em; }
h1 { font-size: clamp(2rem, 4vw, 2.75rem); line-height: 1.15; letter-spacing: -.035em; }
.overview { display: grid; grid-template-columns: repeat(3, 1fr); padding: 22px 0; margin-bottom: 30px; border-block: 1px solid rgba(var(--v-theme-on-surface), .09); }
.overview-item { display: flex; align-items: center; gap: 14px; padding-inline: 24px; }
.overview-item:first-child { padding-left: 0; }
.overview-item + .overview-item { border-left: 1px solid rgba(var(--v-theme-on-surface), .09); }
.overview-item strong { display: block; font-size: 1.6rem; line-height: 1.15; }
.overview-item span { font-size: .8rem; color: rgba(var(--v-theme-on-surface), .6); }
.pets-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
.donor-pet { overflow: hidden; border: 1px solid rgba(var(--v-theme-on-surface), .08); }
.pet-photo { display: block; position: relative; text-decoration: none; }
.photo-fallback { height: 230px; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 12px; background: rgba(var(--v-theme-primary), .08); color: rgb(var(--v-theme-primary)); }
.photo-fallback span { font-size: .8rem; }
.photo-status { position: absolute; left: 18px; bottom: 16px; }
.pet-body { padding: 24px; }
h3 { font-size: 1.5rem; line-height: 1.2; overflow-wrap: anywhere; }
.pet-location { display: flex; align-items: center; gap: 6px; margin-top: 14px; }
.pet-progress { display: flex; align-items: center; gap: 12px; padding: 16px; margin-top: 20px; border-radius: 14px; background: rgba(var(--v-theme-primary), .06); }
.pet-progress b { font-size: .85rem; }
.pet-progress p { margin-top: 3px; font-size: .78rem; color: rgba(var(--v-theme-on-surface), .65); }
.team-note { margin-top: 18px; font-size: .85rem; overflow-wrap: anywhere; }
.team-note span { font-size: .65rem; font-weight: 800; letter-spacing: .08em; color: rgba(var(--v-theme-on-surface), .6); }
.team-note p { margin-top: 5px; white-space: pre-wrap; }
.pet-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 22px; }
.interest-count { display: flex; align-items: center; gap: 7px; font-size: .8rem; color: rgba(var(--v-theme-on-surface), .65); }
.empty-state { text-align: center; padding: 56px 24px; border: 1px dashed rgba(var(--v-theme-primary), .25); border-radius: 24px; }
@media (max-width: 700px) {
    .page-header { align-items: flex-start; flex-direction: column; gap: 18px; }
    .overview-item { padding-inline: 12px; gap: 0; }
    .overview-item .v-avatar { display: none; }
    .overview-item span { font-size: .72rem; }
    .pets-grid { grid-template-columns: 1fr; }
    .pet-body { padding: 20px; }
}
</style>
