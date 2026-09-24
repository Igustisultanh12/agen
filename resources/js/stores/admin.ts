import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api/client';

export const useAdminStore = defineStore('admin', () => {
    const dashboard = ref<any>(null);
    const analytics = ref<any>(null);
    const users = ref<any[]>([]);
    const userGroups = ref<any[]>([]);
    const providers = ref<any[]>([]);
    const models = ref<any[]>([]);
    const agents = ref<any[]>([]);
    const usageLogs = ref<any[]>([]);
    const auditLogs = ref<any[]>([]);
    const settings = ref<any>({});
    const isLoading = ref<boolean>(false);

    async function fetchDashboard() {
        isLoading.value = true;
        try {
            const res = await api.get('/admin/dashboard');
            dashboard.value = res.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchAnalytics(range = '30d') {
        const res = await api.get('/admin/analytics', { params: { range } });
        analytics.value = res.data;
    }

    async function fetchUsers(params = {}) {
        const res = await api.get('/admin/users', { params });
        users.value = res.data.data || res.data;
        return res.data;
    }

    async function fetchUserGroups() {
        const res = await api.get('/admin/user-groups');
        userGroups.value = res.data;
    }

    async function fetchProviders() {
        const res = await api.get('/admin/providers');
        providers.value = res.data;
    }

    async function fetchModels() {
        const res = await api.get('/admin/models');
        models.value = res.data;
    }

    async function fetchAgents() {
        const res = await api.get('/admin/agents');
        agents.value = res.data;
    }

    async function fetchUsageLogs(params = {}) {
        const res = await api.get('/admin/usage-logs', { params });
        usageLogs.value = res.data.data || res.data;
        return res.data;
    }

    async function fetchAuditLogs(params = {}) {
        const res = await api.get('/admin/audit-logs', { params });
        auditLogs.value = res.data.data || res.data;
        return res.data;
    }

    async function fetchSettings() {
        const res = await api.get('/admin/settings');
        settings.value = res.data;
    }

    async function checkProviderHealth(providerId: number) {
        const res = await api.post(`/admin/providers/${providerId}/health`);
        await fetchProviders();
        return res.data;
    }

    async function toggleModelStatus(modelId: number) {
        const res = await api.post(`/admin/models/${modelId}/toggle`);
        await fetchModels();
        return res.data;
    }

    async function toggleUserSuspension(userId: number) {
        const res = await api.post(`/admin/users/${userId}/suspend`);
        await fetchUsers();
        return res.data;
    }

    async function updateUserQuota(userId: number, limits: { monthly_token_limit: number; daily_token_limit: number; add_bonus_tokens?: number }) {
        const res = await api.post(`/admin/users/${userId}/quota`, limits);
        await fetchUsers();
        return res.data;
    }

    return {
        dashboard,
        analytics,
        users,
        userGroups,
        providers,
        models,
        agents,
        usageLogs,
        auditLogs,
        settings,
        isLoading,
        fetchDashboard,
        fetchAnalytics,
        fetchUsers,
        fetchUserGroups,
        fetchProviders,
        fetchModels,
        fetchAgents,
        fetchUsageLogs,
        fetchAuditLogs,
        fetchSettings,
        checkProviderHealth,
        toggleModelStatus,
        toggleUserSuspension,
        updateUserQuota,
    };
});
