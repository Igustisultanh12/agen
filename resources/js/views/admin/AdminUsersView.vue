<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="User Management"
            subtitle="Manage user accounts, assign plans, adjust token quotas, and monitor user statuses."
        />

        <!-- Controls Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#141622] border border-slate-800">
            <div class="flex items-center space-x-3 w-full sm:w-auto">
                <input
                    v-model="searchQuery"
                    @input="debouncedSearch"
                    type="text"
                    placeholder="Search user name or email..."
                    class="px-3.5 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-100 placeholder-slate-500 w-full sm:w-64 focus:outline-none focus:border-blue-500"
                />
                <select
                    v-model="selectedRole"
                    @change="fetchUsers"
                    class="px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                >
                    <option value="">All Roles</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button
                @click="openCreateModal"
                class="w-full sm:w-auto px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center justify-center space-x-1.5"
            >
                <span>+</span>
                <span>Create New User</span>
            </button>
        </div>

        <!-- Users Table -->
        <div class="rounded-2xl bg-[#141622] border border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#12141e] text-slate-400 border-b border-slate-800 font-medium">
                        <tr>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Group / Plan</th>
                            <th class="py-3 px-4">Monthly Quota</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-200">{{ user.name }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">{{ user.email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                    :class="user.role === 'admin' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-slate-800 text-slate-400'"
                                >
                                    {{ user.role }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] bg-blue-500/10 text-blue-400 border border-blue-500/20 font-medium">
                                    {{ user.user_group?.name || 'Free' }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div v-if="user.quota" class="space-y-1">
                                    <div class="flex items-center justify-between text-[11px] font-mono">
                                        <span class="text-slate-400">{{ (user.quota.monthly_used_tokens / 1000000).toFixed(2) }}M</span>
                                        <span class="text-slate-500">/ {{ (user.quota.monthly_token_limit / 1000000).toFixed(0) }}M</span>
                                    </div>
                                    <div class="w-24 h-1.5 bg-slate-800 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-blue-500"
                                            :style="{ width: `${Math.min(100, (user.quota.monthly_used_tokens / (user.quota.monthly_token_limit || 1)) * 100)}%` }"
                                        ></div>
                                    </div>
                                </div>
                                <span v-else class="text-slate-500 text-[11px]">-</span>
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                    :class="user.status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'"
                                >
                                    {{ user.status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right space-x-1.5">
                                <button
                                    @click="openQuotaModal(user)"
                                    class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] transition-colors"
                                    title="Edit Quota"
                                >
                                    Quota
                                </button>
                                <button
                                    @click="toggleSuspension(user)"
                                    class="px-2.5 py-1 rounded text-[11px] transition-colors"
                                    :class="user.status === 'active' ? 'bg-amber-950/40 hover:bg-amber-900/50 text-amber-300' : 'bg-emerald-950/40 hover:bg-emerald-900/50 text-emerald-300'"
                                >
                                    {{ user.status === 'active' ? 'Suspend' : 'Activate' }}
                                </button>
                                <button
                                    v-if="user.role !== 'admin'"
                                    @click="deleteUser(user)"
                                    class="px-2 py-1 rounded bg-red-950/30 hover:bg-red-900/50 text-red-400 text-[11px] transition-colors"
                                    title="Delete"
                                >
                                    &times;
                                </button>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="6" class="py-8 text-center text-slate-500 text-xs">No users found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create User Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-[#141622] border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white">Create New User</h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form @submit.prevent="submitCreateUser" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Full Name</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Email</label>
                        <input
                            v-model="createForm.email"
                            type="email"
                            required
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Password</label>
                        <input
                            v-model="createForm.password"
                            type="password"
                            required
                            minlength="8"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Role</label>
                            <select
                                v-model="createForm.role"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            >
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">User Group / Plan</label>
                            <select
                                v-model="createForm.user_group_id"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            >
                                <option v-for="g in userGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold disabled:opacity-50"
                        >
                            {{ saving ? 'Creating...' : 'Create User' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quota Adjust Modal -->
        <div v-if="showQuotaModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-[#141622] border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white">Adjust User Quota</h3>
                    <button @click="showQuotaModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <div class="text-xs text-slate-400 font-mono">
                    User: <span class="text-white">{{ targetUser?.name }}</span> ({{ targetUser?.email }})
                </div>

                <form @submit.prevent="submitQuota" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Monthly Token Limit</label>
                        <input
                            v-model.number="quotaForm.monthly_token_limit"
                            type="number"
                            required
                            step="100000"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Daily Token Limit</label>
                        <input
                            v-model.number="quotaForm.daily_token_limit"
                            type="number"
                            required
                            step="10000"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Add Bonus Tokens (Immediate Credit)</label>
                        <input
                            v-model.number="quotaForm.add_bonus_tokens"
                            type="number"
                            step="10000"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div class="flex justify-between items-center pt-3 border-t border-slate-800">
                        <button
                            type="button"
                            @click="resetUserQuota"
                            class="px-3 py-1.5 rounded-lg bg-red-950/40 text-red-400 hover:bg-red-900/40 text-xs font-medium"
                        >
                            Reset Usage
                        </button>
                        <div class="flex space-x-2">
                            <button
                                type="button"
                                @click="showQuotaModal = false"
                                class="px-4 py-2 rounded-lg bg-slate-800 text-slate-300 text-xs font-medium"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold disabled:opacity-50"
                            >
                                {{ saving ? 'Saving...' : 'Save Limits' }}
                            </button>
                        </div>
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

const users = ref<any[]>([]);
const userGroups = ref<any[]>([]);
const searchQuery = ref('');
const selectedRole = ref('');
const showCreateModal = ref(false);
const showQuotaModal = ref(false);
const targetUser = ref<any>(null);
const saving = ref(false);

const createForm = reactive({
    name: '',
    email: '',
    password: '',
    role: 'user',
    user_group_id: 1,
});

const quotaForm = reactive({
    monthly_token_limit: 10000000,
    daily_token_limit: 500000,
    add_bonus_tokens: 0,
});

let debounceTimer: any = null;
function debouncedSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchUsers();
    }, 300);
}

onMounted(async () => {
    await fetchUserGroups();
    await fetchUsers();
});

async function fetchUsers() {
    const res = await api.get('/admin/users', {
        params: {
            search: searchQuery.value || undefined,
            role: selectedRole.value || undefined,
        },
    });
    users.value = res.data.data || res.data;
}

async function fetchUserGroups() {
    const res = await api.get('/admin/user-groups');
    userGroups.value = res.data;
    if (userGroups.value.length > 0) {
        createForm.user_group_id = userGroups.value[0].id;
    }
}

function openCreateModal() {
    createForm.name = '';
    createForm.email = '';
    createForm.password = '';
    createForm.role = 'user';
    showCreateModal.value = true;
}

async function submitCreateUser() {
    saving.value = true;
    try {
        await api.post('/admin/users', createForm);
        showCreateModal.value = false;
        await fetchUsers();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to create user');
    } finally {
        saving.value = false;
    }
}

function openQuotaModal(user: any) {
    targetUser.value = user;
    quotaForm.monthly_token_limit = user.quota?.monthly_token_limit || 10000000;
    quotaForm.daily_token_limit = user.quota?.daily_token_limit || 500000;
    quotaForm.add_bonus_tokens = 0;
    showQuotaModal.value = true;
}

async function submitQuota() {
    if (!targetUser.value) return;
    saving.value = true;
    try {
        await api.post(`/admin/users/${targetUser.value.id}/quota`, quotaForm);
        showQuotaModal.value = false;
        await fetchUsers();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to update quota');
    } finally {
        saving.value = false;
    }
}

async function resetUserQuota() {
    if (!targetUser.value) return;
    if (!confirm(`Reset token usage count for ${targetUser.value.name}?`)) return;
    try {
        await api.post(`/admin/users/${targetUser.value.id}/reset-quota`);
        showQuotaModal.value = false;
        await fetchUsers();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to reset quota');
    }
}

async function toggleSuspension(user: any) {
    try {
        await api.post(`/admin/users/${user.id}/suspend`);
        await fetchUsers();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to change status');
    }
}

async function deleteUser(user: any) {
    if (!confirm(`Are you sure you want to permanently delete user "${user.name}"? This cannot be undone.`)) return;
    try {
        await api.delete(`/admin/users/${user.id}`);
        await fetchUsers();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to delete user');
    }
}
</script>
