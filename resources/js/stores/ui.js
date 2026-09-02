import { ref } from 'vue';
export const theme = ref(localStorage.getItem('theme') || 'larEPatas');
export const isLogged = ref(!!localStorage.getItem('authenticated'));
export const userName = ref(localStorage.getItem('user_name') || '');
export function toggleTheme() { theme.value = theme.value === 'larEPatas' ? 'larEPatasDark' : 'larEPatas'; localStorage.setItem('theme', theme.value); }
export function setSession(user) { isLogged.value = true; userName.value = user.name; localStorage.setItem('authenticated', 'true'); localStorage.setItem('user_name', user.name); }
export function clearSession() { isLogged.value = false; userName.value = ''; localStorage.removeItem('authenticated'); localStorage.removeItem('user_name'); }
