<script setup>
import * as Ably from "ably";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { useRouter } from "vue-router";
import {
    chatTarget,
    isChatOpen,
    isLogged,
    notify,
    openChat,
    userAvatar,
    userName,
} from "../../stores/ui";

const drawer = ref(false),
    contacts = ref([]),
    conversation = ref(null),
    messages = ref([]),
    draft = ref(""),
    loading = ref(false),
    messageList = ref(null),
    composer = ref(null),
    latestMessage = ref(null),
    messagesPage = ref(1),
    hasMoreMessages = ref(false),
    loadingOlderMessages = ref(false),
    unreadMessageItems = ref([]),
    sending = ref(false),
    likingMessageIds = ref(new Set());
const router = useRouter();
let unreadRefreshTimer;
let realtime;
const refreshUnreadMessages = () => loadUnreadMessages();
async function refreshRelationship() {
    await loadContacts();
    if (chatTarget.value) await selectContact(chatTarget.value);
}
const csrf =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";
const headers = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-CSRF-TOKEN": csrf,
};
const unreadMessages = computed(() => unreadMessageItems.value.length);
const unreadOtherMessages = computed(
    () =>
        unreadMessageItems.value.filter(
            (item) => item.data?.conversation_id !== conversation.value?.id,
        ).length,
);
const unreadMessageBadge = computed(() =>
    unreadMessages.value
        ? `+${unreadMessages.value > 9 ? "9" : unreadMessages.value}`
        : "",
);
const unreadOtherBadge = computed(() =>
    unreadOtherMessages.value
        ? `+${unreadOtherMessages.value > 9 ? "9" : unreadOtherMessages.value}`
        : "",
);
function unreadForContact(contactId) {
    return unreadMessageItems.value.filter(
        (item) => item.data?.sender_id === contactId,
    ).length;
}
function unreadContactBadge(contactId) {
    const count = unreadForContact(contactId);

    return count ? `+${count > 9 ? "9" : count}` : "";
}
async function loadUnreadMessages() {
    if (!isLogged.value) return;
    const response = await fetch("/api/notifications", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) {
        unreadMessageItems.value = (await response.json()).data.filter(
            (item) => !item.read_at && item.type === "direct_message",
        );
    }
}
async function loadContacts() {
    if (!isLogged.value) return;
    const response = await fetch("/api/chat/contacts", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });
    if (response.ok) contacts.value = (await response.json()).data || [];
}
async function selectContact(contact) {
    loading.value = true;
    realtime?.close();
    const response = await fetch(`/api/chat/users/${contact.id}`, {
        method: "POST",
        credentials: "same-origin",
        headers,
    });
    if (!response.ok) {
        loading.value = false;
        return notify("Não foi possível abrir a conversa.", "error");
    }
    conversation.value = (await response.json()).data;
    await loadMessages();
    await fetch("/api/notifications/read", {
        method: "POST",
        credentials: "same-origin",
        headers,
        body: JSON.stringify({ conversation_id: conversation.value.id }),
    });
    await loadUnreadMessages();
    window.dispatchEvent(new Event("notifications:read"));
    loading.value = false;
    await scrollToLatest(false);
    realtime = new Ably.Realtime({
        authUrl: "/api/realtime/token",
        authMethod: "GET",
    });
    realtime.channels
        .get(`direct:${conversation.value.id}:messages`)
        .subscribe("message:created", async (event) => {
            if (!event.data?.id) return;
            if (messages.value.some((message) => message.id === event.data.id))
                return;

            const pending = messages.value.find(
                (message) =>
                    message._status === "sending" &&
                    message.body === event.data.body &&
                    isMine(event.data),
            );
            if (pending) {
                Object.assign(pending, event.data, { _status: "sent" });
                return;
            }

            messages.value.push(event.data);
            scrollToLatest(true);
            if (!isMine(event.data)) await markConversationRead();
        });
    realtime.channels
        .get(`direct:${conversation.value.id}:messages`)
        .subscribe("messages:read", (event) => {
            const readAt = event.data?.read_at;
            const messageIds = event.data?.message_ids || [];
            messages.value.forEach((message) => {
                if (messageIds.includes(message.id)) message.read_at = readAt;
            });
        });
    realtime.channels
        .get(`direct:${conversation.value.id}:messages`)
        .subscribe("messages:liked", (event) => {
            const message = messages.value.find(
                (item) => item.id === event.data?.message_id,
            );
            if (message) message.likes_count = event.data.likes_count;
        });
}
async function loadMessages(page = 1, prepend = false) {
    if (!conversation.value) return;
    const response = await fetch(
        `/api/chat/conversations/${conversation.value.id}/messages?page=${page}`,
        { credentials: "same-origin", headers: { Accept: "application/json" } },
    );
    if (!response.ok) return;
    const data = await response.json();
    messages.value = prepend
        ? [...(data.data || []), ...messages.value]
        : data.data || [];
    messagesPage.value = data.pagination?.current_page || page;
    hasMoreMessages.value = !!data.pagination?.has_more;
}
async function markConversationRead() {
    if (!conversation.value) return;
    await Promise.all([
        fetch(`/api/chat/conversations/${conversation.value.id}/read`, {
            method: "POST",
            credentials: "same-origin",
            headers,
        }),
        fetch("/api/notifications/read", {
            method: "POST",
            credentials: "same-origin",
            headers,
            body: JSON.stringify({ conversation_id: conversation.value.id }),
        }),
    ]);
    await loadUnreadMessages();
    window.dispatchEvent(new Event("notifications:read"));
}
async function loadOlderMessages() {
    if (!hasMoreMessages.value || loadingOlderMessages.value) return;
    const container = messageList.value;
    loadingOlderMessages.value = true;
    await loadMessages(messagesPage.value + 1, true);
    await nextTick();
    if (container) {
        container.scrollTo({ top: 0, behavior: "smooth" });
    }
    loadingOlderMessages.value = false;
}
async function send() {
    if (!draft.value.trim() || !conversation.value) return;
    const response = await fetch(
        `/api/chat/conversations/${conversation.value.id}/messages`,
        {
            method: "POST",
            credentials: "same-origin",
            headers,
            body: JSON.stringify({ body: draft.value }),
        },
    );
    if (!response.ok)
        return notify("Não foi possível enviar a mensagem.", "error");
    const message = (await response.json()).data;
    if (!messages.value.some((item) => item.id === message.id)) {
        messages.value.push(message);
        scrollToLatest(false);
    }
    draft.value = "";
}
function isMine(message) {
    return message.is_mine || message.user?.name === userName.value;
}
async function requestError(response, fallback) {
    const data = await response.json().catch(() => ({}));

    return data.message || fallback;
}
async function sendOptimistic() {
    if (!draft.value.trim() || !conversation.value || sending.value) return;

    const body = draft.value.trim();
    const clientId = crypto.randomUUID();
    const pendingMessage = {
        id: `pending-${clientId}`,
        _clientId: clientId,
        _status: "sending",
        is_mine: true,
        body,
        created_at: new Date().toISOString(),
        likes_count: 0,
        is_liked: false,
        user: { name: userName.value, avatar_url: userAvatar.value },
    };

    messages.value.push(pendingMessage);
    draft.value = "";
    sending.value = true;
    scrollToLatest(false);

    try {
        const response = await fetch(
            `/api/chat/conversations/${conversation.value.id}/messages`,
            {
                method: "POST",
                credentials: "same-origin",
                headers,
                body: JSON.stringify({ body }),
            },
        );
        if (!response.ok)
            throw new Error(
                await requestError(
                    response,
                    "NÃ£o foi possÃ­vel enviar a mensagem.",
                ),
            );

        const message = (await response.json()).data;
        const pendingIndex = messages.value.findIndex(
            (item) => item._clientId === clientId,
        );
        if (pendingIndex >= 0) {
            messages.value[pendingIndex] = { ...message, _status: "sent" };
        } else if (!messages.value.some((item) => item.id === message.id)) {
            messages.value.push({ ...message, _status: "sent" });
        }
    } catch (error) {
        pendingMessage._status = "failed";
        pendingMessage._error = error.message;
        return;
        notify("Mensagem nÃ£o enviada. Tente novamente.", "error");
    } finally {
        sending.value = false;
        await nextTick();
        composer.value?.focus?.();
    }
}
async function retryMessage(message) {
    if (!message?._clientId || sending.value || !conversation.value) return;

    message._status = "sending";
    sending.value = true;
    try {
        const response = await fetch(
            `/api/chat/conversations/${conversation.value.id}/messages`,
            {
                method: "POST",
                credentials: "same-origin",
                headers,
                body: JSON.stringify({ body: message.body }),
            },
        );
        if (!response.ok)
            throw new Error(
                await requestError(
                    response,
                    "NÃ£o foi possÃ­vel enviar a mensagem.",
                ),
            );

        const savedMessage = (await response.json()).data;
        const index = messages.value.findIndex(
            (item) => item._clientId === message._clientId,
        );
        if (index >= 0) {
            messages.value[index] = { ...savedMessage, _status: "sent" };
        }
    } catch (error) {
        message._status = "failed";
        message._error = error.message;
        return;
        notify(
            "Mensagem ainda nÃ£o enviada. Tente novamente em alguns segundos.",
            "error",
        );
    } finally {
        sending.value = false;
        await nextTick();
        composer.value?.focus?.();
    }
}
async function toggleLike(message) {
    if (
        !message.id ||
        message._status ||
        likingMessageIds.value.has(message.id)
    )
        return;
    likingMessageIds.value.add(message.id);
    const response = await fetch(`/api/chat/messages/${message.id}/likes`, {
        method: "POST",
        credentials: "same-origin",
        headers,
    });
    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        likingMessageIds.value.delete(message.id);
        return notify(
            data.message || "NÃ£o foi possÃ­vel atualizar a curtida.",
            "error",
        );
    }
    const data = await response.json();
    message.is_liked = data.liked;
    message.likes_count = data.likes_count;
    likingMessageIds.value.delete(message.id);
}
async function scrollToLatest(center = false) {
    await nextTick();
    const container = messageList.value;
    const lastMessage = latestMessage.value;
    if (lastMessage) {
        if (!center) {
            container.scrollTo({
                top: container.scrollHeight,
                behavior: "smooth",
            });
            return;
        }
        const offset = lastMessage.offsetTop - container.offsetTop;
        const target =
            offset - (container.clientHeight - lastMessage.clientHeight) / 2;
        container.scrollTo({ top: Math.max(0, target), behavior: "smooth" });
    }
}
function setLatestMessage(element) {
    if (element) latestMessage.value = element;
}
function goToProfile(userId) {
    if (userId) router.push(`/perfil/${userId}`);
}
function time(value) {
    return new Intl.DateTimeFormat("pt-BR", {
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}
function dayKey(value) {
    const date = new Date(value);
    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}
function isNewDay(message, index) {
    return (
        index === 0 ||
        dayKey(message.created_at) !==
            dayKey(messages.value[index - 1].created_at)
    );
}
function dayLabel(value) {
    const parts = new Intl.DateTimeFormat("pt-BR", {
        day: "numeric",
        month: "long",
        weekday: "long",
    }).formatToParts(new Date(value));
    const get = (type) => parts.find((part) => part.type === type)?.value || "";
    return `${get("day")} de ${get("month")}, ${get("weekday")}`;
}
watch(chatTarget, async (target) => {
    if (target) {
        drawer.value = true;
        await selectContact(target);
    }
});
watch(drawer, (value) => {
    isChatOpen.value = value;
    if (!value) chatTarget.value = null;
});
onMounted(() => {
    window.addEventListener("friendship:changed", refreshRelationship);
    loadContacts();
    loadUnreadMessages();
    unreadRefreshTimer = window.setInterval(loadUnreadMessages, 10000);
    window.addEventListener("notifications:received", refreshUnreadMessages);
});
onBeforeUnmount(() => {
    window.removeEventListener("friendship:changed", refreshRelationship);
    realtime?.close();
    window.clearInterval(unreadRefreshTimer);
    window.removeEventListener("notifications:received", refreshUnreadMessages);
});
</script>

<template>
    <template v-if="isLogged">
        <v-badge
            v-show="!drawer"
            :content="unreadMessageBadge"
            :model-value="unreadMessages > 0"
            color="error"
            floating
            class="messages-activator"
            ><v-btn
                icon="mdi-message-text"
                color="surface"
                variant="elevated"
                class="utility-fab"
                aria-label="Abrir mensagens"
                @click="
                    drawer = true;
                    loadContacts();
                "
        /></v-badge>
        <v-navigation-drawer
            v-model="drawer"
            location="right"
            temporary
            width="420"
            class="messages-drawer"
        >
            <div class="d-flex align-center justify-space-between pa-5 pb-3">
                <div>
                    <div class="text-h6 font-weight-bold">Mensagens</div>
                    <div class="text-caption opacity-70">
                        Converse com seus amigos
                    </div>
                </div>
                <v-btn
                    icon="mdi-close"
                    variant="text"
                    @click="drawer = false"
                />
            </div>
            <v-divider />
            <div v-if="!chatTarget" class="contacts-list pa-3">
                <div
                    v-if="!contacts.length"
                    class="text-center py-10 opacity-70"
                >
                    <v-icon icon="mdi-account-group-outline" size="36" />
                    <p class="mt-3">
                        Adicione amigos para começar uma conversa.
                    </p>
                </div>
                <v-list v-else nav
                    ><v-list-item
                        v-for="contact in contacts"
                        :key="contact.id"
                        rounded="lg"
                        @click="openChat(contact)"
                        ><template #prepend
                            ><v-avatar color="primary"
                                ><v-img
                                    v-if="contact.avatar_url"
                                    :src="contact.avatar_url"
                                    cover
                                /><span v-else>{{
                                    contact.name?.[0]
                                }}</span></v-avatar
                            ></template
                        ><v-list-item-title class="font-weight-bold">{{
                            contact.name
                        }}</v-list-item-title
                        ><v-list-item-subtitle>{{
                            contact.city || "Perfil Lar & Patas"
                        }}</v-list-item-subtitle
                        ><template
                            v-if="unreadForContact(contact.id) > 0"
                            #append
                            ><v-chip
                                size="x-small"
                                color="error"
                                label
                                class="font-weight-bold"
                                >{{ unreadContactBadge(contact.id) }}</v-chip
                            ></template
                        ></v-list-item
                    ></v-list
                >
            </div>
            <template v-else
                ><div class="d-flex align-center ga-3 pa-4">
                    <v-badge
                        :content="unreadOtherBadge"
                        :model-value="unreadOtherMessages > 0"
                        color="error"
                        floating
                        ><v-btn
                            icon="mdi-arrow-left"
                            size="small"
                            variant="text"
                            @click="chatTarget = null" /></v-badge
                    ><v-avatar
                        color="primary"
                        class="profile-link"
                        @click="goToProfile(chatTarget.id)"
                        ><v-img
                            v-if="chatTarget.avatar_url"
                            :src="chatTarget.avatar_url"
                            cover
                        /><span v-else>{{
                            chatTarget.name?.[0]
                        }}</span></v-avatar
                    >
                    <div class="font-weight-bold">{{ chatTarget.name }}</div>
                </div>
                <v-divider />
                <div ref="messageList" class="direct-messages pa-4">
                    <div v-if="hasMoreMessages" class="text-center pb-4">
                        <v-btn
                            size="small"
                            variant="tonal"
                            color="primary"
                            :loading="loadingOlderMessages"
                            @click="loadOlderMessages"
                            >Ver mensagens anteriores</v-btn
                        >
                    </div>
                    <div v-if="loadingOlderMessages" class="text-center py-2">
                        <v-progress-circular
                            indeterminate
                            size="20"
                            color="primary"
                        />
                    </div>
                    <div v-if="loading" class="text-center py-8">
                        <v-progress-circular indeterminate color="primary" />
                    </div>
                    <template
                        v-else
                        v-for="(message, index) in messages"
                        :key="message.id"
                    >
                        <div
                            v-if="isNewDay(message, index)"
                            class="day-divider"
                        >
                            <span>{{ dayLabel(message.created_at) }}</span>
                        </div>
                        <div
                            :ref="
                                index === messages.length - 1
                                    ? setLatestMessage
                                    : undefined
                            "
                            class="direct-message"
                            :class="{
                                mine: isMine(message),
                                liked: message.likes_count > 0,
                            }"
                        >
                            <v-avatar
                                size="30"
                                color="primary"
                                class="profile-link"
                                @click="goToProfile(message.user?.id)"
                                ><v-img
                                    v-if="message.user?.avatar_url"
                                    :src="message.user.avatar_url"
                                    cover
                                /><span v-else>{{
                                    message.user?.name?.[0]
                                }}</span></v-avatar
                            >
                            <div class="message-stack">
                                <div
                                    class="message-bubble"
                                    @dblclick.stop.prevent="toggleLike(message)"
                                >
                                    {{ message.body }}
                                </div>
                                <div class="message-meta">
                                    <small v-if="message._status === 'sending'"
                                        >Enviando...</small
                                    >
                                    <small
                                        v-else-if="message._status === 'failed'"
                                        class="message-failed"
                                        >NÃ£o enviada</small
                                    >
                                    <v-tooltip
                                        v-if="message._status === 'failed'"
                                        location="top"
                                    >
                                        <template #activator="{ props }">
                                            <v-icon
                                                v-bind="props"
                                                icon="mdi-close-circle"
                                                size="14"
                                                color="error"
                                                class="failed-icon"
                                            />
                                        </template>
                                        {{
                                            message._error ||
                                            "A mensagem nÃ£o foi enviada."
                                        }}
                                    </v-tooltip>
                                    <v-btn
                                        v-if="message._status === 'failed'"
                                        size="x-small"
                                        variant="text"
                                        color="primary"
                                        class="retry-message"
                                        @click.stop="retryMessage(message)"
                                        >Reenviar</v-btn
                                    >
                                    <small
                                        v-if="
                                            !['sending', 'failed'].includes(
                                                message._status,
                                            )
                                        "
                                        >{{ time(message.created_at) }}</small
                                    >
                                    <v-icon
                                        v-if="
                                            isMine(message) &&
                                            message._status !== 'sending' &&
                                            message._status !== 'failed'
                                        "
                                        :icon="
                                            message.read_at
                                                ? 'mdi-check-all'
                                                : 'mdi-check'
                                        "
                                        size="14"
                                        :color="
                                            message.read_at
                                                ? 'primary'
                                                : undefined
                                        "
                                    />
                                    <v-btn
                                        class="reaction-button"
                                        icon
                                        size="x-small"
                                        variant="text"
                                        :color="
                                            message.is_liked
                                                ? 'error'
                                                : undefined
                                        "
                                        :aria-label="
                                            message.is_liked
                                                ? 'Remover curtida'
                                                : 'Curtir mensagem'
                                        "
                                        @click="toggleLike(message)"
                                    >
                                        <v-icon
                                            :icon="
                                                message.is_liked
                                                    ? 'mdi-heart'
                                                    : 'mdi-heart-outline'
                                            "
                                            size="16"
                                        />
                                    </v-btn>
                                    <small v-if="message.likes_count">{{
                                        message.likes_count
                                    }}</small>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div
                        v-if="!loading && !messages.length"
                        class="text-center py-10 opacity-70"
                    >
                        Envie a primeira mensagem.
                    </div>
                </div>
                <v-alert
                    v-if="conversation && !conversation.can_message"
                    variant="tonal"
                    color="primary"
                    icon="mdi-lock-outline"
                    class="ma-3"
                    rounded="lg"
                >
                    Para continuar conversando, desbloqueie o usuário ou
                    adicione a amizade novamente.
                    <v-btn
                        :to="`/perfil/${chatTarget.id}`"
                        variant="text"
                        size="small"
                        class="mt-2"
                        >Ver perfil</v-btn
                    >
                </v-alert>
                <div v-else class="chat-composer pa-3">
                    <v-text-field
                        ref="composer"
                        v-model="draft"
                        hide-details
                        density="comfortable"
                        placeholder="Escreva uma mensagem"
                        variant="outlined"
                        @keyup.enter="sendOptimistic"
                    /><v-btn
                        icon="mdi-send"
                        color="primary"
                        :disabled="!draft.trim() || sending"
                        :loading="sending"
                        @click="sendOptimistic"
                    /></div
            ></template>
        </v-navigation-drawer>
    </template>
</template>

<style scoped>
.messages-activator {
    position: fixed;
    z-index: 2000;
    top: 18px;
    right: 82px;
}
.utility-fab {
    color: rgb(var(--v-theme-primary)) !important;
    border: 1px solid rgba(var(--v-theme-primary), 0.28);
    box-shadow: 0 5px 16px rgba(5, 38, 34, 0.26) !important;
}
.messages-activator :deep(.v-badge__badge) {
    border: 2px solid rgb(var(--v-theme-surface));
    box-shadow: 0 2px 7px rgba(5, 38, 34, 0.32);
}
.messages-drawer :deep(.v-navigation-drawer__content) {
    display: flex;
    flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: rgba(var(--v-theme-primary), 0.7)
        rgba(var(--v-theme-primary), 0.08);
}
.contacts-list,
.direct-messages {
    overflow-y: auto;
    flex: 1;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: rgba(var(--v-theme-primary), 0.72)
        rgba(var(--v-theme-primary), 0.08);
}
.messages-drawer :deep(.v-navigation-drawer__content::-webkit-scrollbar),
.contacts-list::-webkit-scrollbar,
.direct-messages::-webkit-scrollbar {
    width: 9px;
}
.messages-drawer :deep(.v-navigation-drawer__content::-webkit-scrollbar-track),
.contacts-list::-webkit-scrollbar-track,
.direct-messages::-webkit-scrollbar-track {
    background: rgba(var(--v-theme-primary), 0.08);
    border-radius: 999px;
}
.messages-drawer :deep(.v-navigation-drawer__content::-webkit-scrollbar-thumb),
.contacts-list::-webkit-scrollbar-thumb,
.direct-messages::-webkit-scrollbar-thumb {
    background: rgba(var(--v-theme-primary), 0.62);
    border: 2px solid rgb(var(--v-theme-surface));
    border-radius: 999px;
}
.messages-drawer
    :deep(.v-navigation-drawer__content::-webkit-scrollbar-thumb:hover),
.contacts-list::-webkit-scrollbar-thumb:hover,
.direct-messages::-webkit-scrollbar-thumb:hover {
    background: rgb(var(--v-theme-primary));
}
.direct-message {
    display: flex;
    gap: 8px;
    width: 100%;
    margin-bottom: 16px;
    align-items: flex-start;
}
.day-divider {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 4px 0 16px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.72rem;
    font-weight: 700;
}
.day-divider::before,
.day-divider::after {
    content: "";
    height: 1px;
    flex: 1;
    background: rgba(var(--v-theme-on-surface), 0.12);
}
.direct-message.mine {
    flex-direction: row-reverse;
}
.message-stack {
    display: grid;
    min-width: 0;
    width: fit-content;
    max-width: 74%;
}
.direct-message :deep(.v-avatar) {
    margin-top: 3px;
}
.mine .message-stack {
    justify-items: end;
}
.message-bubble {
    width: fit-content;
    max-width: 100%;
    padding: 9px 12px;
    border-radius: 14px;
    word-break: break-word;
    background: rgba(var(--v-theme-primary), 0.14);
}
.mine .message-bubble {
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
}
.message-meta {
    display: flex;
    min-height: 24px;
    align-items: center;
    gap: 4px;
    width: 100%;
    justify-content: flex-end;
    color: rgba(var(--v-theme-on-surface), 0.7);
}
.mine .message-meta {
    justify-content: flex-end;
}
.reaction-button {
    order: -2;
    margin-right: auto;
}
.reaction-button + small {
    order: -1;
    margin-right: auto;
    margin-left: -2px;
}
.direct-message.liked .reaction-button {
    margin-right: 0;
}
.reaction-button {
    opacity: 0;
    transition: opacity 0.15s ease;
}
.direct-message:hover .reaction-button,
.direct-message.liked .reaction-button,
.reaction-button:focus-visible {
    opacity: 1;
}
.direct-message small {
    padding: 3px 0;
    opacity: 0.65;
    font-size: 0.68rem;
}
.message-failed {
    color: rgb(var(--v-theme-error));
    font-weight: 700;
    opacity: 1 !important;
}
.retry-message {
    min-width: auto;
    padding: 0 3px !important;
    font-size: 0.65rem;
    letter-spacing: 0.02em;
}
.profile-link {
    cursor: pointer;
}
.chat-composer {
    display: flex;
    gap: 8px;
    align-items: center;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
</style>
