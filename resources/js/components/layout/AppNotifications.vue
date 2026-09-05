<script setup>
import * as Ably from "ably";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { isChatOpen, isLogged, notify } from "../../stores/ui";

const router = useRouter();
const open = ref(false);
const filter = ref("all");
const items = ref([]);
const unread = ref(0);
const loading = ref(false);
const respondingPet = ref(null);
let refreshTimer;
let realtime;
const realtimeChannel = ref("");
const refreshNotifications = () => loadNotifications();

const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";
const headers = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-CSRF-TOKEN": csrf,
};
const generalItems = computed(() =>
    items.value.filter((item) => item.type !== "direct_message"),
);
const visibleItems = computed(() =>
    filter.value === "unread"
        ? generalItems.value.filter((item) => !item.read_at)
        : generalItems.value,
);
const generalUnread = computed(
    () => generalItems.value.filter((item) => !item.read_at).length,
);
const badgeUnread = computed(() => generalUnread.value);
const unreadBadge = computed(() =>
    badgeUnread.value
        ? `+${badgeUnread.value > 9 ? "9" : badgeUnread.value}`
        : "",
);

function formatDate(value) {
    if (!value) return "Agora";
    const minutes = Math.max(
        0,
        Math.round((Date.now() - new Date(value).getTime()) / 60000),
    );
    if (minutes < 1) return "Agora";
    if (minutes < 60) return `${minutes} min`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)} h`;
    return new Intl.DateTimeFormat("pt-BR", { dateStyle: "short" }).format(
        new Date(value),
    );
}
function avatar(item) {
    return (
        item.friend_request?.sender?.avatar_url || item.actor?.avatar_url || ""
    );
}
async function loadNotifications() {
    if (!isLogged.value) return;
    loading.value = true;
    const response = await fetch("/api/notifications", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) {
        const data = await response.json();
        items.value = data.data || [];
        unread.value = data.unread || 0;
        realtimeChannel.value = data.realtime_channel || "";
    }
    loading.value = false;
}
async function markRead() {
    if (!unread.value) return;
    await fetch("/api/notifications/read", {
        method: "POST",
        credentials: "same-origin",
        headers,
        body: JSON.stringify({ except_types: ["direct_message"] }),
    });
    items.value = items.value.map((item) => ({
        ...item,
        read_at:
            item.type === "direct_message"
                ? item.read_at
                : item.read_at || new Date().toISOString(),
    }));
    unread.value = items.value.filter((item) => !item.read_at).length;
}
async function respond(item, status) {
    const request = item.friend_request;
    if (!request || request.status !== "pending") return;
    const response = await fetch(`/api/friend-requests/${request.id}`, {
        method: "PATCH",
        credentials: "same-origin",
        headers,
        body: JSON.stringify({ status }),
    });
    if (!response.ok)
        return notify("Não foi possível atualizar a solicitação.", "error");
    request.status = status;
    notify(
        status === "accepted"
            ? "Solicitação de amizade aceita."
            : "Solicitação recusada.",
    );
}
function goToProfile(item) {
    const sender = item.friend_request?.sender || item.actor;
    if (!sender) return;
    open.value = false;
    router.push(`/perfil/${sender.id}`);
}
function openNotification(item) {
    open.value = false;
    if (item.data?.post_id) {
        router.push({
            path: "/perfil",
            query: {
                post: String(item.data.post_id),
                notification: String(item.id),
            },
        });
    } else if (item.data?.pet_id) {
        router.push(`/pets/${item.data.pet_id}`);
    } else {
        goToProfile(item);
    }
}
async function respondPet(item, accept) {
    respondingPet.value = item.id;
    try {
        const response = await fetch(
            `/api/profile/pets/${item.data.pet_id}/owner-request`,
            {
                method: "PATCH",
                credentials: "same-origin",
                headers,
                body: JSON.stringify({ accept }),
            },
        );
        if (!response.ok) throw new Error();
        await loadNotifications();
        window.dispatchEvent(new Event("pets:changed"));
        notify(
            accept
                ? "Convite aceito! O pet agora aparece no seu perfil."
                : "Convite recusado.",
        );
    } catch {
        notify(
            "Não foi possível responder ao convite. Atualize as notificações e tente novamente.",
            "error",
        );
    } finally {
        respondingPet.value = null;
    }
}
watch(open, async (value) => {
    if (value) {
        await loadNotifications();
        markRead();
    }
});
onMounted(async () => {
    await loadNotifications();
    refreshTimer = window.setInterval(loadNotifications, 10000);
    window.addEventListener("notifications:read", refreshNotifications);
    if (!realtimeChannel.value) return;
    realtime = new Ably.Realtime({
        authUrl: "/api/realtime/token",
        authMethod: "GET",
    });
    realtime.channels
        .get(realtimeChannel.value)
        .subscribe("message:created", async () => {
            await loadNotifications();
            window.dispatchEvent(new Event("notifications:received"));
        });
});
onBeforeUnmount(() => {
    window.clearInterval(refreshTimer);
    realtime?.close();
    window.removeEventListener("notifications:read", refreshNotifications);
});
</script>

<template>
    <v-menu
        v-if="isLogged"
        v-model="open"
        location="bottom end"
        :close-on-content-click="false"
        max-width="420"
    >
        <template #activator="{ props }">
            <div
                class="notifications-activator"
                :class="{ 'notifications-activator--in-chat': isChatOpen }"
            >
                <v-badge
                    :content="unreadBadge"
                    :model-value="badgeUnread > 0"
                    color="error"
                    floating
                >
                    <v-btn
                        v-bind="props"
                        icon="mdi-bell"
                        color="surface"
                        variant="elevated"
                        class="utility-fab"
                        aria-label="Abrir notificações"
                    />
                </v-badge>
            </div>
        </template>
        <v-card class="notifications-panel" rounded="xl" elevation="12">
            <div
                class="d-flex align-center justify-space-between px-5 pt-5 pb-3"
            >
                <div>
                    <div class="text-h6 font-weight-bold">Notificações</div>
                    <div class="text-caption opacity-70">
                        Seu histórico recente
                    </div>
                </div>
                <v-btn
                    icon="mdi-refresh"
                    variant="text"
                    size="small"
                    :loading="loading"
                    @click="loadNotifications"
                />
            </div>
            <div class="px-5 pb-3 d-flex ga-2">
                <v-btn
                    size="small"
                    rounded="pill"
                    :variant="filter === 'all' ? 'flat' : 'tonal'"
                    color="primary"
                    @click="filter = 'all'"
                    >Todas</v-btn
                >
                <v-btn
                    size="small"
                    rounded="pill"
                    :variant="filter === 'unread' ? 'flat' : 'tonal'"
                    color="primary"
                    @click="filter = 'unread'"
                    >Não lidas</v-btn
                >
            </div>
            <v-divider />
            <div class="notifications-list">
                <div v-if="loading && !items.length" class="py-8 text-center">
                    <v-progress-circular indeterminate color="primary" />
                </div>
                <div
                    v-else-if="!visibleItems.length"
                    class="empty-notifications pa-8 text-center"
                >
                    <v-icon
                        icon="mdi-bell-check-outline"
                        size="34"
                        color="primary"
                    />
                    <div class="font-weight-medium mt-2">
                        Nenhuma notificação por aqui.
                    </div>
                </div>
                <article
                    v-for="item in visibleItems"
                    :key="item.id"
                    class="notification-item"
                    role="link"
                    tabindex="0"
                    @click="openNotification(item)"
                    @keydown.enter.self.prevent="openNotification(item)"
                    :class="{ unread: !item.read_at }"
                >
                    <v-avatar
                        size="46"
                        color="primary"
                        class="mt-1"
                        @click.stop="goToProfile(item)"
                        ><v-img
                            v-if="avatar(item)"
                            :src="avatar(item)"
                            cover /><v-icon
                            v-else
                            :icon="
                                item.type === 'friend_request'
                                    ? 'mdi-account-plus-outline'
                                    : 'mdi-bell-outline'
                            "
                    /></v-avatar>
                    <div class="notification-copy">
                        <button
                            type="button"
                            class="notification-link"
                            @click.stop="openNotification(item)"
                        >
                            <b>{{ item.title }}</b
                            ><span v-if="item.body">{{ item.body }}</span>
                        </button>
                        <small>{{ formatDate(item.created_at) }}</small>
                        <div
                            v-if="
                                item.type === 'pet_caretaker_request' &&
                                item.pet_request_status === 'pending'
                            "
                            class="d-flex ga-2 mt-2"
                        >
                            <v-btn
                                size="small"
                                color="primary"
                                variant="flat"
                                :disabled="respondingPet !== null"
                                @click.stop="respondPet(item, true)"
                                >Aceitar</v-btn
                            >
                            <v-btn
                                size="small"
                                variant="tonal"
                                :disabled="respondingPet !== null"
                                @click.stop="respondPet(item, false)"
                                >Recusar</v-btn
                            >
                        </div>
                        <div
                            v-if="
                                item.type === 'friend_request' &&
                                item.friend_request?.status === 'pending'
                            "
                            class="d-flex ga-2 mt-2"
                        >
                            <v-btn
                                size="small"
                                color="primary"
                                variant="flat"
                                @click.stop="respond(item, 'accepted')"
                                >Aceitar</v-btn
                            ><v-btn
                                size="small"
                                variant="tonal"
                                @click.stop="respond(item, 'rejected')"
                                >Recusar</v-btn
                            >
                        </div>
                    </div>
                    <span
                        v-if="!item.read_at"
                        class="unread-dot"
                        aria-label="Não lida"
                    />
                </article>
            </div>
            <v-divider />
            <div class="pa-3 text-center">
                <v-btn variant="text" color="primary" @click="filter = 'all'"
                    >Ver todo o histórico</v-btn
                >
            </div>
        </v-card>
    </v-menu>
</template>

<style scoped>
.notifications-activator {
    position: fixed;
    z-index: 2000;
    top: 18px;
    right: 22px;
}
.notifications-activator--in-chat {
    top: 20px;
    right: 68px;
}
.utility-fab {
    color: rgb(var(--v-theme-primary)) !important;
    border: 1px solid rgba(var(--v-theme-primary), 0.28);
    box-shadow: 0 5px 16px rgba(5, 38, 34, 0.26) !important;
}
.notifications-activator :deep(.v-badge__badge) {
    border: 2px solid rgb(var(--v-theme-surface));
    box-shadow: 0 2px 7px rgba(5, 38, 34, 0.32);
}
.notifications-panel {
    width: min(420px, calc(100vw - 24px));
    background: rgb(var(--v-theme-surface));
}
.notifications-list {
    max-height: min(60vh, 520px);
    overflow-y: auto;
}
.notification-item {
    position: relative;
    display: flex;
    gap: 12px;
    padding: 13px 18px;
    cursor: default;
}
.notification-item:hover,
.notification-item.unread {
    background: rgba(var(--v-theme-primary), 0.08);
}
.notification-copy {
    min-width: 0;
    flex: 1;
}
.notification-link {
    display: grid;
    gap: 2px;
    width: 100%;
    padding: 0;
    color: inherit;
    text-align: left;
    background: transparent;
    border: 0;
    cursor: pointer;
}
.notification-link span {
    font-size: 0.875rem;
    opacity: 0.8;
}
.notification-copy small {
    color: rgb(var(--v-theme-primary));
    font-weight: 700;
}
.unread-dot {
    align-self: center;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: rgb(var(--v-theme-primary));
}
</style>
