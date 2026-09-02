import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import PetDetailsView from '../views/PetDetailsView.vue';
import AdoptionFormView from '../views/AdoptionFormView.vue';
import LoginView from '../views/LoginView.vue';
import TwoFactorView from '../views/TwoFactorView.vue';
import DashboardView from '../views/DashboardView.vue';
import ProfileView from '../views/ProfileView.vue';
import AdminPetsView from '../views/AdminPetsView.vue';
import AdminUsersView from '../views/AdminUsersView.vue';
import AdminSheltersView from '../views/AdminSheltersView.vue';
import ForgotPasswordView from '../views/ForgotPasswordView.vue';
import ResetPasswordView from '../views/ResetPasswordView.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: HomeView },
        { path: '/pets/:id', component: PetDetailsView, props: true },
        { path: '/adotar/:id', component: AdoptionFormView, props: true, meta: { requiresAuth: true } },
        { path: '/entrar', component: LoginView },
        { path: '/esqueci-minha-senha', component: ForgotPasswordView },
        { path: '/redefinir-senha/:token', component: ResetPasswordView, props: true },
        { path: '/confirmar-acesso', component: TwoFactorView },
        { path: '/painel', component: DashboardView, meta: { requiresAuth: true } },
        { path: '/perfil', component: ProfileView, meta: { requiresAuth: true } },
        { path: '/admin/pets', component: AdminPetsView, meta: { requiresAuth: true } },
        { path: '/admin/usuarios', component: AdminUsersView, meta: { requiresAuth: true } },
        { path: '/admin/sedes', component: AdminSheltersView, meta: { requiresAuth: true } },
    ],
});

router.beforeEach((to) => {
    if (to.meta.requiresAuth && !localStorage.getItem('authenticated')) {
        return { path: '/entrar', query: { next: to.fullPath } };
    }
});

export default router;
