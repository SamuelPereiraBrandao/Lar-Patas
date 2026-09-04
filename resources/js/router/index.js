import { createRouter, createWebHistory } from "vue-router";
import HomeView from "../views/public/HomeView.vue";
import PetDetailsView from "../views/pets/PetDetailsView.vue";
import AdoptionFormView from "../views/pets/AdoptionFormView.vue";
import LoginView from "../views/auth/LoginView.vue";
import TwoFactorView from "../views/auth/TwoFactorView.vue";
import DashboardView from "../views/dashboards/AdopterDashboardView.vue";
import ProfileView from "../views/account/ProfileView.vue";
import AdminPetsView from "../views/admin/PetsSettingsView.vue";
import AdminUsersView from "../views/admin/UsersSettingsView.vue";
import ForgotPasswordView from "../views/auth/ForgotPasswordView.vue";
import ResetPasswordView from "../views/auth/ResetPasswordView.vue";
import DonorDashboardView from "../views/dashboards/DonorDashboardView.vue";
import NotFoundView from "../views/public/NotFoundView.vue";
import { clearSession } from "../stores/ui";

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: "/", component: HomeView },
        { path: "/pets/:id", component: PetDetailsView, props: true },
        {
            path: "/adotar/:id",
            component: AdoptionFormView,
            props: true,
            meta: { requiresAuth: true },
        },
        { path: "/entrar", component: LoginView },
        { path: "/esqueci-minha-senha", component: ForgotPasswordView },
        {
            path: "/redefinir-senha/:token",
            component: ResetPasswordView,
            props: true,
        },
        { path: "/confirmar-acesso", component: TwoFactorView },
        {
            path: "/painel",
            component: DashboardView,
            meta: { requiresAuth: true },
        },
        {
            path: "/painel/doador",
            component: DonorDashboardView,
            meta: { requiresAuth: true },
        },
        {
            path: "/perfil",
            component: ProfileView,
            meta: { requiresAuth: true },
        },
        {
            path: "/perfil/:id",
            redirect: "/perfil",
            meta: { requiresAuth: true },
        },
        {
            path: "/admin/pets",
            component: AdminPetsView,
            meta: { requiresAuth: true },
        },
        {
            path: "/admin/usuarios",
            component: AdminUsersView,
            meta: { requiresAuth: true },
        },
        { path: "/admin/sedes", redirect: "/admin/pets" },
        { path: "/:pathMatch(.*)*", component: NotFoundView },
    ],
});

router.beforeEach(async (to) => {
    if (to.meta.requiresAuth && !localStorage.getItem("authenticated")) {
        return { path: "/entrar", query: { next: to.fullPath } };
    }

    if (to.meta.requiresAuth) {
        const response = await fetch("/auth/user", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });
        if (!response.ok) {
            clearSession();

            return {
                path: "/entrar",
                query: { next: to.fullPath, expired: "1" },
            };
        }
    }
});

export default router;
