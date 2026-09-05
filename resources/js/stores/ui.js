import { ref } from "vue";

export const theme = ref(localStorage.getItem("theme") || "larEPatas");
export const isLogged = ref(!!localStorage.getItem("authenticated"));
export const userName = ref(localStorage.getItem("user_name") || "");
export const userAvatar = ref(localStorage.getItem("user_avatar") || "");
export const userRoles = ref(
    JSON.parse(localStorage.getItem("user_roles") || "[]"),
);
export const toast = ref({ open: false, message: "", type: "success" });
export const chatTarget = ref(null);
export const isChatOpen = ref(false);

export function notify(message, type = "success") {
    toast.value = { open: true, message, type };
}
export function openChat(user) {
    chatTarget.value = user;
}

export function toggleTheme() {
    theme.value = theme.value === "larEPatas" ? "larEPatasDark" : "larEPatas";
    localStorage.setItem("theme", theme.value);
}
export function setSession(user) {
    isLogged.value = true;
    userName.value = user.name;
    userAvatar.value = user.avatar_url || "";
    userRoles.value = (user.roles || []).map((role) => role.name);
    localStorage.setItem("authenticated", "true");
    localStorage.setItem("user_name", user.name);
    localStorage.setItem("user_avatar", userAvatar.value);
    localStorage.setItem("user_roles", JSON.stringify(userRoles.value));
}
export function clearSession() {
    isLogged.value = false;
    userName.value = "";
    userAvatar.value = "";
    userRoles.value = [];
    localStorage.removeItem("authenticated");
    localStorage.removeItem("user_name");
    localStorage.removeItem("user_avatar");
    localStorage.removeItem("user_roles");
}
