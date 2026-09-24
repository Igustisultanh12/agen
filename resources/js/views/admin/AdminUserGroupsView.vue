<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="User Groups & Plan Tiers"
            subtitle="Configure tier limits (monthly/daily token limits, max projects, RPM) and assign default plans."
        />

        <div class="flex justify-end">
            <button
                @click="openModal()"
                class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center space-x-1.5"
            >
                <span>+</span>
                <span>Create New Plan</span>
            </button>
        </div>

        <!-- Groups Grid Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div
                v-for="group in groups"
                :key="group.id"
                class="rounded-2xl bg-[#141622] border border-slate-800 p-6 flex flex-col justify-between space-y-4 hover:border-slate-700 transition-colors"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                            :class="group.is_default ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-400'"
                        >
                            {{ group.is_default ? 'Default Plan' : 'Tier' }}
                        </span>
                        <span class="text-xs text-slate-400 font-mono">{{ group.users_count || 0 }} users</span>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-white">{{ group.name }}</h3>
                        <p class="text-xs text-slate-400 mt-1 min-h-[32px]">{{ group.description || 'Standard plan tier' }}</p>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-slate-800 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Monthly Tokens:</span>
                            <span class="font-mono text-blue-400 font-semibold">{{ (group.monthly_token_limit / 1000000).toFixed(0) }}M</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Daily Tokens:</span>
                            <span class="font-mono text-purple-400 font-semibold">{{ (group.daily_token_limit / 1000).toFixed(0) }}k</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Max Projects:</span>
                            <span class="font-mono text-slate-200">{{ group.max_projects }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Rate Limit:</span>
                            <span class="font-mono text-slate-200">{{ group.request_limit_per_minute }} req/min</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-end space-x-2">
                    <button
                        @click="openModal(group)"
                        class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors"
                    >
                        Edit Plan
                    </button>
                    <button
                        v-if="!group.is_default && group.users_count === 0"
                        @click="deleteGroup(group)"
                        class="px-2 py-1.5 rounded-lg bg-red-950/30 hover:bg-red-900/50 text-red-400 text-xs transition-colors"
                        title="Delete"
                    >
                        &times;
                    </button>
                </div>
            </div>
        </div>

        <!-- Create / Edit Plan Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-[#141622] border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white">{{ editingId ? 'Edit Plan' : 'Create New Plan' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form @submit.prevent="submitGroup" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Plan Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Slug Identifier</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Description</label>
                        <input
                            v-model="form.description"
                            type="text"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Monthly Token Limit</label>
                            <input
                                v-model.number="form.monthly_token_limit"
                                type="number"
                                required
                                step="1000000"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Daily Token Limit</label>
                            <input
                                v-model.number="form.daily_token_limit"
                                type="number"
                                required
                                step="50000"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Max Projects</label>
                            <input
                                v-model.number="form.max_projects"
                                type="number"
                                required
                                min="1"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">RPM Limit</label>
                            <input
                                v-model.number="form.request_limit_per_minute"
                                type="number"
                                required
                                min="1"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Concurrent Sessions</label>
                            <input
                                v-model.number="form.concurrent_session_limit"
                                type="number"
                                required
                                min="1"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <input
                            v-model="form.is_default"
                            type="checkbox"
                            id="is_default"
                            class="rounded border-slate-700 text-blue-600 focus:ring-blue-500 bg-[#0d0f16]"
                        />
                        <label for="is_default" class="text-xs text-slate-300">Set as default plan for newly registered users</label>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold disabled:opacity-50"
                        >
                            {{ saving ? 'Saving...' : 'Save Plan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import AdminNav from '../../components/AdminNav.vue';
import api from '../../api/client';

const groups = ref<any[]>([]);
const showModal = ref(false);
const editingId = ref<number | null>(null);
const saving = ref(false);

const form = reactive({
    name: '',
    slug: '',
    description: '',
    monthly_token_limit: 10000000,
    daily_token_limit: 500000,
    weekly_token_limit: 2500000,
    request_limit_per_minute: 20,
    concurrent_session_limit: 2,
    max_projects: 5,
    max_storage_bytes: 104857600,
    max_file_size_bytes: 10485760,
    is_default: false,
});

onMounted(() => {
    fetchGroups();
});

async function fetchGroups() {
    const res = await api.get('/admin/user-groups');
    groups.value = res.data;
}

function openModal(group?: any) {
    if (group) {
        editingId.value = group.id;
        Object.assign(form, {
            name: group.name,
            slug: group.slug,
            description: group.description || '',
            monthly_token_limit: group.monthly_token_limit,
            daily_token_limit: group.daily_token_limit,
            weekly_token_limit: group.weekly_token_limit || 2500000,
            request_limit_per_minute: group.request_limit_per_minute,
            concurrent_session_limit: group.concurrent_session_limit,
            max_projects: group.max_projects,
            max_storage_bytes: group.max_storage_bytes || 104857600,
            max_file_size_bytes: group.max_file_size_bytes || 10485760,
            is_default: !!group.is_default,
        });
    } else {
        editingId.value = null;
        Object.assign(form, {
            name: '',
            slug: '',
            description: '',
            monthly_token_limit: 10000000,
            daily_token_limit: 500000,
            weekly_token_limit: 2500000,
            request_limit_per_minute: 20,
            concurrent_session_limit: 2,
            max_projects: 5,
            max_storage_bytes: 104857600,
            max_file_size_bytes: 10485760,
            is_default: false,
        });
    }
    showModal.value = true;
}

async function submitGroup() {
    saving.value = true;
    try {
        if (editingId.value) {
            await api.put(`/admin/user-groups/${editingId.value}`, form);
        } else {
            await api.post('/admin/user-groups', form);
        }
        showModal.value = false;
        await fetchGroups();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to save user group');
    } finally {
        saving.value = false;
    }
}

async function deleteGroup(group: any) {
    if (!confirm(`Delete plan "${group.name}"?`)) return;
    try {
        await api.delete(`/admin/user-groups/${group.id}`);
        await fetchGroups();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to delete user group');
    }
}
</script>
