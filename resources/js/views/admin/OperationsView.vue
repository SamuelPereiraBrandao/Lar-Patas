<script setup>
import { ref, onMounted } from "vue";
import { request } from "../../stores/requests";
import { notify } from "../../stores/ui";
import AdoptionPickupDialog from "../../components/admin/AdoptionPickupDialog.vue";
import AdoptionCareDialog from "../../components/admin/AdoptionCareDialog.vue";
const deactivateAuthor = ref(false);
const reportsPage = ref(1);
const data = ref({
        pending: [],
        pickups: [],
        followups: [],
        reports: [],
        audit: { data: [] },
    }),
    loading = ref(true),
    tab = ref("pickups"),
    selected = ref(null),
    pickupOpen = ref(false),
    release = ref(false),
    cancelOpen = ref(false),
    careAction = ref("cancel"),
    report = ref(null),
    resolution = ref(""),
    decision = ref("dismissed"),
    saving = ref(false);
async function load(page = 1) {
    loading.value = true;
    try {
        data.value = await request(
            "/api/admin/operations?page=" +
                page +
                "&reports_page=" +
                reportsPage.value,
        );
    } catch (e) {
        notify(e.message, "error");
    } finally {
        loading.value = false;
    }
}
function pickup(item, isRelease = false) {
    selected.value = item;
    release.value = isRelease;
    pickupOpen.value = true;
}
function cancel(item) {
    selected.value = item;
    careAction.value = "cancel";
    cancelOpen.value = true;
}
async function resolve() {
    saving.value = true;
    try {
        await request(`/api/admin/reports/${report.value.id}`, "PATCH", {
            status: decision.value,
            deactivate_author: deactivateAuthor.value,
            resolution: resolution.value,
        });
        report.value = null;
        await load();
        notify("Denúncia analisada.");
    } catch (e) {
        notify(e.message, "error");
    } finally {
        saving.value = false;
    }
}
const date = (value) =>
    value
        ? new Date(value).toLocaleString("pt-BR", {
              dateStyle: "short",
              timeStyle: "short",
          })
        : "";
const today = (item) =>
    new Date(item.pickup_at).toLocaleDateString() ===
    new Date().toLocaleDateString();
onMounted(() => load());
</script>
<template>
    <v-container class="py-8"
        ><div class="d-flex flex-wrap justify-space-between ga-3 mb-6">
            <div>
                <div class="section-kicker">ADMINISTRAÇÃO</div>
                <h1 class="text-h4 font-weight-bold">Pendências da equipe</h1>
                <p class="mt-2 text-medium-emphasis">
                    Retiradas, pedidos de ajuda e solicitações que precisam de
                    atenção.
                </p>
            </div>
            <v-btn to="/admin/pets" variant="tonal">Gerenciar pets</v-btn>
        </div>
        <v-row class="mb-5"
            ><v-col
                cols="6"
                md="3"
                v-for="metric in [
                    {
                        label: 'Solicitações na lista',
                        value: data.pending.length,
                    },
                    {
                        label: 'Retiradas hoje',
                        value: data.pickups.filter(today).length,
                    },
                    {
                        label: 'Reagendamentos',
                        value: data.pickups.filter(
                            (p) => p.reschedule_requested_at,
                        ).length,
                    },
                    {
                        label: 'Pedidos de ajuda',
                        value: data.followups.filter(
                            (p) => p.adaptation_status === 'needs_help',
                        ).length,
                    },
                ]"
                :key="metric.label"
                ><v-card
                    class="pa-5"
                    rounded="xl"
                    variant="tonal"
                    color="primary"
                    ><b class="text-h4">{{ metric.value }}</b>
                    <div class="text-body-2 mt-1">
                        {{ metric.label }}
                    </div></v-card
                ></v-col
            ></v-row
        >
        <v-card rounded="xl"
            ><v-tabs v-model="tab" color="primary" show-arrows
                ><v-tab value="pickups">Retiradas</v-tab
                ><v-tab value="pending">Solicitações</v-tab
                ><v-tab value="followups">Adaptação</v-tab
                ><v-tab value="reports">Denúncias</v-tab
                ><v-tab value="audit">Histórico</v-tab></v-tabs
            >
            <v-progress-linear v-if="loading" indeterminate color="primary" />
            <div class="pa-5">
                <template
                    v-if="['pickups', 'pending', 'followups'].includes(tab)"
                    ><p
                        v-if="!data[tab].length"
                        class="pa-6 text-center text-medium-emphasis"
                    >
                        Nenhuma pendência nesta seção.
                    </p>
                    <v-card
                        v-for="item in data[tab]"
                        :key="item.id"
                        variant="outlined"
                        rounded="lg"
                        class="pa-4 mb-3"
                        ><div class="d-flex flex-wrap ga-4 align-center">
                            <v-avatar size="64" rounded="lg" color="primary"
                                ><v-img
                                    v-if="item.pet?.image_url"
                                    :src="item.pet.image_url"
                                    cover /><v-icon v-else icon="mdi-paw"
                            /></v-avatar>
                            <div class="flex-grow-1">
                                <h2 class="text-h6">{{ item.pet?.name }}</h2>
                                <router-link :to="'/perfil/' + item.user_id">{{
                                    item.user?.name
                                }}</router-link>
                                <p class="text-body-2 text-medium-emphasis">
                                    {{ item.user?.city }} {{ item.user?.state }}
                                </p>
                                <p v-if="tab === 'pending'" class="text-body-2">
                                    Solicitação em {{ date(item.created_at) }}
                                </p>
                                <p v-if="item.pickup_at" class="text-body-2">
                                    Retirada: {{ date(item.pickup_at) }}
                                </p>
                            </div>
                            <div class="d-flex flex-wrap ga-2">
                                <v-btn
                                    v-if="
                                        tab === 'pending' &&
                                        item.pet?.status === 'available'
                                    "
                                    color="primary"
                                    @click="pickup(item)"
                                    >Analisar e agendar</v-btn
                                ><template v-if="tab === 'pickups'"
                                    ><v-btn
                                        color="primary"
                                        @click="pickup(item, true)"
                                        >Confirmar entrega</v-btn
                                    ><v-btn
                                        variant="tonal"
                                        @click="pickup(item)"
                                        >Reagendar</v-btn
                                    ><v-btn
                                        variant="text"
                                        color="error"
                                        @click="cancel(item)"
                                        >Cancelar</v-btn
                                    ></template
                                ><v-btn
                                    v-if="tab === 'followups'"
                                    color="primary"
                                    @click="
                                        selected = item;
                                        careAction = 'support';
                                        cancelOpen = true;
                                    "
                                    >Responder acompanhamento</v-btn
                                ><v-btn
                                    :to="'/pets/' + item.pet_id"
                                    variant="text"
                                    >Ver pet</v-btn
                                >
                            </div>
                        </div>
                        <v-alert
                            v-if="item.reschedule_requested_at"
                            class="mt-4"
                            color="warning"
                            variant="tonal"
                            >Nova data solicitada:
                            {{ date(item.requested_pickup_at) }}<br />{{
                                item.reschedule_reason
                            }}</v-alert
                        >
                        <v-alert
                            v-if="tab === 'followups'"
                            class="mt-4"
                            :color="
                                item.adaptation_status === 'needs_help'
                                    ? 'warning'
                                    : 'primary'
                            "
                            variant="tonal"
                            >{{
                                item.adaptation_status === "needs_help"
                                    ? "Precisa de ajuda"
                                    : "Aguardando acompanhamento"
                            }}
                            <p>{{ item.adaptation_notes }}</p></v-alert
                        >
                    </v-card>
                    <p class="text-caption text-medium-emphasis">
                        Até 50 pendências por seção, priorizando as mais
                        antigas.
                    </p></template
                >
                <template v-if="tab === 'reports'"
                    ><p v-if="!data.reports.length" class="pa-6 text-center">
                        Nenhuma denúncia registrada.
                    </p>
                    <v-card
                        v-for="item in data.reports"
                        :key="item.id"
                        rounded="xl"
                        elevation="0"
                        class="report-card mb-5"
                    >
                        <div
                            class="d-flex flex-wrap align-center justify-space-between ga-3 pa-5"
                        >
                            <div>
                                <div class="text-subtitle-1 font-weight-bold">
                                    Denúncia #{{ item.id }}
                                </div>
                                <p class="text-caption text-medium-emphasis">
                                    Enviada por {{ item.reporter_name }} ·
                                    {{
                                        new Date(
                                            item.created_at,
                                        ).toLocaleString("pt-BR")
                                    }}
                                </p>
                            </div>
                            <v-chip
                                color="warning"
                                variant="tonal"
                                size="small"
                            >
                                {{
                                    item.status === "pending"
                                        ? "Aguardando análise"
                                        : item.status === "hidden"
                                          ? "Analisada · Publicação ocultada"
                                          : "Analisada · Denúncia arquivada"
                                }}</v-chip
                            >
                        </div>
                        <v-divider />
                        <div class="pa-5">
                            <div class="report-reason pa-4 mb-5">
                                <div class="text-subtitle-2 mb-1">
                                    <v-icon
                                        icon="mdi-flag-outline"
                                        size="18"
                                        class="mr-1"
                                    />Motivo da denúncia
                                </div>
                                <p class="report-text text-body-2">
                                    {{ item.reason }}
                                </p>
                            </div>
                            <v-row v-if="item.post">
                                <v-col
                                    v-if="item.post.gallery_urls?.length"
                                    cols="12"
                                    md="5"
                                >
                                    <v-carousel
                                        height="300"
                                        :show-arrows="
                                            item.post.gallery_urls.length > 1
                                        "
                                        :hide-delimiters="
                                            item.post.gallery_urls.length < 2
                                        "
                                        class="rounded-lg"
                                    >
                                        <v-carousel-item
                                            v-for="image in item.post
                                                .gallery_urls"
                                            :key="image"
                                            ><v-img
                                                :src="image"
                                                height="300"
                                                contain
                                        /></v-carousel-item>
                                    </v-carousel>
                                </v-col>
                                <v-col
                                    cols="12"
                                    :md="
                                        item.post.gallery_urls?.length ? 7 : 12
                                    "
                                >
                                    <div class="d-flex align-center ga-3 mb-4">
                                        <v-avatar color="primary" size="40"
                                            ><v-img
                                                v-if="
                                                    item.post.user?.avatar_url
                                                "
                                                :src="item.post.user.avatar_url"
                                            /><span v-else>{{
                                                item.post.user?.name?.[0]
                                            }}</span></v-avatar
                                        >
                                        <div>
                                            <b>{{
                                                item.post.user?.name ||
                                                "Autor indisponível"
                                            }}</b>
                                            <p
                                                class="text-caption text-medium-emphasis"
                                            >
                                                Publicação #{{ item.post.id }} ·
                                                {{
                                                    new Date(
                                                        item.post.created_at,
                                                    ).toLocaleString("pt-BR")
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap ga-2 mb-4">
                                        <v-chip
                                            :color="
                                                item.post.hidden_at
                                                    ? 'warning'
                                                    : 'success'
                                            "
                                            size="small"
                                            variant="tonal"
                                            :prepend-icon="
                                                item.post.hidden_at
                                                    ? 'mdi-ghost-outline'
                                                    : 'mdi-eye-outline'
                                            "
                                            >{{
                                                item.post.hidden_at
                                                    ? "Excluída / oculta"
                                                    : "Publicação visível"
                                            }}</v-chip
                                        ><v-chip
                                            v-if="item.post.edited_at"
                                            size="small"
                                            variant="tonal"
                                            prepend-icon="mdi-pencil-outline"
                                            >Texto editado</v-chip
                                        >
                                    </div>
                                    <p class="report-text text-body-1">
                                        {{
                                            item.post.body ||
                                            "Publicação sem texto."
                                        }}
                                    </p>
                                    <p
                                        v-if="item.post.edited_at"
                                        class="text-caption text-medium-emphasis mt-4"
                                    >
                                        Última edição do texto:
                                        {{
                                            new Date(
                                                item.post.edited_at,
                                            ).toLocaleString("pt-BR")
                                        }}
                                    </p>
                                    <p
                                        v-if="item.post.hidden_at"
                                        class="text-caption text-medium-emphasis mt-3"
                                    >
                                        Oculta desde
                                        {{
                                            new Date(
                                                item.post.hidden_at,
                                            ).toLocaleString("pt-BR")
                                        }}. O conteúdo continua preservado para
                                        análise desta denúncia.
                                    </p>
                                </v-col>
                            </v-row>
                            <v-alert v-else variant="tonal" type="info"
                                >A publicação não está mais disponível, mas a
                                denúncia foi preservada.</v-alert
                            >
                        </div>
                        <v-divider />
                        <div v-if="item.status !== 'pending'" class="pa-5">
                            <h3 class="text-subtitle-2 mb-2">
                                Análise concluída
                            </h3>
                            <p class="text-caption text-medium-emphasis mb-2">
                                {{
                                    item.reviewer_name ||
                                    "Equipe administrativa"
                                }}
                                ·
                                {{
                                    new Date(item.updated_at).toLocaleString(
                                        "pt-BR",
                                    )
                                }}
                            </p>
                            <p class="report-text text-body-2">
                                {{
                                    item.resolution ||
                                    "Sem justificativa informada."
                                }}
                            </p>
                        </div>
                        <div v-else class="d-flex justify-end pa-4">
                            <v-btn
                                color="primary"
                                variant="flat"
                                prepend-icon="mdi-shield-search"
                                @click="
                                    report = item;
                                    resolution = '';
                                    decision = 'dismissed';
                                    deactivateAuthor = false;
                                "
                                >Analisar denúncia</v-btn
                            >
                        </div>
                    </v-card>
                </template>
                <v-pagination
                    v-if="
                        tab === 'reports' &&
                        data.reports_pagination?.last_page > 1
                    "
                    v-model="reportsPage"
                    :length="data.reports_pagination.last_page"
                    :total-visible="5"
                    color="primary"
                    @update:model-value="load()"
                />
                <template v-if="tab === 'audit'"
                    ><v-table
                        ><thead>
                            <tr>
                                <th>Quando</th>
                                <th>Responsável</th>
                                <th>Ação</th>
                                <th>Alterações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in data.audit.data" :key="item.id">
                                <td>{{ date(item.created_at) }}</td>
                                <td>{{ item.actor || "Conta removida" }}</td>
                                <td>{{ item.action }} {{ item.resource }}</td>
                                <td>
                                    <span
                                        v-for="(value, key) in item.changes"
                                        :key="key"
                                        >{{ key }}: {{ value }};
                                    </span>
                                </td>
                            </tr>
                        </tbody></v-table
                    ><v-pagination
                        :model-value="data.audit.current_page"
                        :length="data.audit.last_page"
                        @update:model-value="load"
                /></template></div
        ></v-card>
        <AdoptionPickupDialog
            v-model="pickupOpen"
            :pet="selected?.pet"
            :adoption="selected"
            :release="release"
            @saved="load()"
        />
        <AdoptionCareDialog
            v-model="cancelOpen"
            :adoption="selected"
            :action="careAction"
            @saved="load()"
        />
        <v-dialog
            :model-value="!!report"
            max-width="540"
            :persistent="saving"
            @update:model-value="report = null"
            ><v-card title="Analisar denúncia"
                ><v-card-text
                    ><p class="mb-4">{{ report?.reason }}</p>
                    <v-select
                        v-model="decision"
                        :items="[
                            { title: 'Manter publicação', value: 'dismissed' },
                            { title: 'Ocultar publicação', value: 'hidden' },
                        ]"
                        label="Decisão" /><v-checkbox
                        v-model="deactivateAuthor"
                        label="Inativar o autor da publicação"
                        color="error"
                        hide-details
                        class="mb-3" /><v-alert
                        v-if="deactivateAuthor"
                        type="warning"
                        variant="tonal"
                        class="mb-4"
                        >A conta de {{ report?.post?.user?.name }} perderá o
                        acesso e suas sessões serão encerradas ao
                        confirmar.</v-alert
                    ><v-textarea
                        v-model="resolution"
                        label="Justificativa (opcional)"
                        rows="3" /></v-card-text
                ><v-card-actions
                    ><v-spacer /><v-btn
                        @click="report = null"
                        :disabled="saving"
                        >Cancelar</v-btn
                    ><v-btn color="primary" :loading="saving" @click="resolve"
                        >Confirmar decisão</v-btn
                    ></v-card-actions
                ></v-card
            ></v-dialog
        >
    </v-container>
</template>

<style scoped>
.report-card {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.report-reason {
    background: rgba(var(--v-theme-primary), 0.05);
    border-radius: 12px;
}
.report-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
</style>
