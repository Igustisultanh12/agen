import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api/client';

export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user';
    status: 'active' | 'suspended';
    avatar_url?: string | null;
    user_group?: { id: number; name: string; slug: string } | null;
    custom_instructions?: string | null;
    preferences?: any;
}

export interface Quota {
    monthly_token_limit: number;
    monthly_used_tokens: number;
    monthly_remaining_tokens: number;
    monthly_usage_percentage: number;
    daily_token_limit: number;
    daily_used_tokens: number;
    daily_remaining_tokens: number;
    daily_usage_percentage: number;
    total_cost_usd: number;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const quota = ref<Quota | null>(null);
    const token = ref<string | null>(localStorage.getItem('auth_token'));
    const isLoading = ref<boolean>(false);

    const isAuthenticated = computed(() => !!token.value);
    const isAdmin = computed(() => user.value?.role === 'admin');

    async function login(credentials: { email: string; password: string }) {
        isLoading.value = true;
        try {
            const res = await api.post('/auth/login', credentials);
            token.value = res.data.token;
            user.value = res.data.user;
            localStorage.setItem('auth_token', res.data.token);
            await fetchMe();
            return res.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function register(data: { name: string; email: string; password: string; password_confirmation: string }) {
        isLoading.value = true;
        try {
            const res = await api.post('/auth/register', data);
            token.value = res.data.token;
            user.value = res.data.user;
            localStorage.setItem('auth_token', res.data.token);
            await fetchMe();
            return res.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchMe() {
        if (!token.value) return;
        try {
            const res = await api.get('/auth/me');
            user.value = res.data.user;
            quota.value = res.data.quota;
        } catch {
            logout();
        }
    }

    async function logout() {
        try {
            if (token.value) {
                await api.post('/auth/logout');
            }
        } catch {
            // ignore network failure on logout
        } finally {
            user.value = null;
            quota.value = null;
            token.value = null;
            localStorage.removeItem('auth_token');
        }
    }

    async function updateProfile(data: { name: string; custom_instructions?: string }) {
        const res = await api.put('/auth/profile', data);
        if (user.value) {
            user.value.name = res.data.user.name;
            user.value.custom_instructions = res.data.user.custom_instructions;
        }
        return res.data;
    }

    return {
        user,
        quota,
        token,
        isLoading,
        isAuthenticated,
        isAdmin,
        login,
        register,
        fetchMe,
        logout,
        updateProfile,
    };
});
