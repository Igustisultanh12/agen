import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        redirect: '/workspace',
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('../views/auth/RegisterView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/workspace',
        name: 'workspace',
        component: () => import('../views/workspace/WorkspaceView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('../views/user/UserDashboardView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/projects',
        name: 'projects',
        component: () => import('../views/user/ProjectsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/usage',
        name: 'usage',
        component: () => import('../views/user/UserUsageView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/api-keys',
        name: 'api-keys',
        component: () => import('../views/user/ApiKeysView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('../views/user/UserSettingsView.vue'),
        meta: { requiresAuth: true },
    },

    // Admin Routes
    {
        path: '/admin',
        name: 'admin-dashboard',
        component: () => import('../views/admin/AdminDashboardView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/users',
        name: 'admin-users',
        component: () => import('../views/admin/AdminUsersView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/user-groups',
        name: 'admin-user-groups',
        component: () => import('../views/admin/AdminUserGroupsView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/providers',
        name: 'admin-providers',
        component: () => import('../views/admin/AdminProvidersView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/models',
        name: 'admin-models',
        component: () => import('../views/admin/AdminModelsView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/agents',
        name: 'admin-agents',
        component: () => import('../views/admin/AdminAgentsView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/usage',
        name: 'admin-usage',
        component: () => import('../views/admin/AdminUsageView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/audit-logs',
        name: 'admin-audit-logs',
        component: () => import('../views/admin/AdminAuditLogsView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/settings',
        name: 'admin-settings',
        component: () => import('../views/admin/AdminSettingsView.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    if (authStore.token && !authStore.user) {
        await authStore.fetchMe();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return next({ name: 'login' });
    }

    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return next({ name: 'workspace' });
    }

    if (to.meta.requiresAdmin && !authStore.isAdmin) {
        return next({ name: 'workspace' });
    }

    next();
});

export default router;
