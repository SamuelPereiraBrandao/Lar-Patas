import { ref } from 'vue';

export const theme = ref(localStorage.getItem('theme') || 'larEPatas');
export const isLogged = ref(!!localStorage.getItem('authenticated'));
export const userName = ref(localStorage.getItem('user_name') || '');
export const userRoles = ref(JSON.parse(localStorage.getItem('user_roles') || '[]'));

export function toggleTheme() { theme.value = theme.value === 'larEPatas' ? 'larEPatasDark' : 'larEPatas'; localStorage.setItem('theme', theme.value); }
export function setSession(user) { isLogged.value = true; userName.value = user.name; userRoles.value = (user.roles || []).map((role) => role.name); localStorage.setItem('authenticated', 'true'); localStorage.setItem('user_name', user.name); localStorage.setItem('user_roles', JSON.stringify(userRoles.value)); }
export function clearSession() { isLogged.value = false; userName.value = ''; userRoles.value = []; localStorage.removeItem('authenticated'); localStorage.removeItem('user_name'); localStorage.removeItem('user_roles'); }
