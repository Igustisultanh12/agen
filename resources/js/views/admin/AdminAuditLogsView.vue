<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="Security & System Audit Trail"
            subtitle="Immutable activity logs tracking user logins, password updates, quota changes, provider configurations, and administrative actions."
        />

        <!-- Filter bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#141622] border border-slate-800">
            <div class="flex items-center space-x-3 w-full sm:w-auto">
                <input
                    v-model="filters.search"
                    @input="debouncedFetch"
                    type="text"
                    placeholder="Search action or IP..."
                    class="px-3.5 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white placeholder-slate-500 w-full sm:w-64 focus:outline-none focus:border-blue-500"
                />
                <select
                    v-model="filters.action"
                    @change="fetchLogs(1)"
                    class="px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                >
                    <option value="">All Actions</option>
                    <option value="login">Login</option>
                    <option value="logout">Logout</option>
                    <option value="register">Register</option>
                    <option value="update_profile">Update Profile</option>
                    <option value="change_password">Change Password</option>
                    <option value="update_quota">Update Quota</option>
                    <option value="suspend_user">Suspend User</option>
                    <option value="create_provider">Create Provider</option>
                </select>
            </div>
            <div class="text-xs text-slate-400 font-mono">
                Total Logs: {{ totalCount }}
            </div>
        </div>

        <!-- Audit Table -->
        <div class="rounded-2xl bg-[#141622] border border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#12141e] text-slate-400 border-b border-slate-800 font-medium">
                        <tr>
                            <th class="py-3 px-4">Timestamp</th>
                            <th class="py-3 px-4">Operator</th>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Target Resource</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4">Payload / Context</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="py-3 px-4 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-200">{{ log.user?.name || 'System / Guest' }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">{{ log.user?.email || '-' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] bg-slate-800 font-mono text-amber-300 font-medium border border-slate-700">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-300 text-[11px]">
                                <span v-if="log.resource_type">{{ log.resource_type }} #{{ log.resource_id }}</span>
                                <span v-else class="text-slate-500">-</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-400 text-[11px]">
                                {{ log.ip_address || '127.0.0.1' }}
                            </td>
                            <td class="py-3 px-4">
                                <pre v-if="log.payload" class="text-[10px] text-slate-400 font-mono max-w-xs truncate bg-[#0d0f16] p-1 rounded border border-slate-800">{{ JSON.stringify(log.payload) }}</pre>
                                <span v-else class="text-slate-500 text-[10px]">-</span>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="6" class="py-8 text-center text-slate-500 text-xs">No audit logs recorded.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="totalPages > 1" class="p-3 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <div>Page {{ currentPage }} of {{ totalPages }}</div>
                <div class="flex items-center space-x-1.5">
                    <button
                        :disabled="currentPage <= 1"
                        @click="fetchLogs(currentPage - 1)"
                        class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 disabled:opacity-40"
                    >
                        Prev
                    </button>
                    <button
                        :disabled="currentPage >= totalPages"
                        @click="fetchLogs(currentPage + 1)"
                        class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 disabled:opacity-40"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import AdminNav from '../../components/AdminNav.vue';
import api from '../../api/client';

const logs = ref<any[]>([]);
const currentPage = ref(1);
const totalPages = ref(1);
const totalCount = ref(0);

const filters = reactive({
    search: '',
    action: '',
});

let timer: any = null;
function debouncedFetch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        fetchLogs(1);
    }, 300);
}

onMounted(() => {
    fetchLogs(1);
});

async function fetchLogs(page = 1) {
    currentPage.value = page;
    const res = await api.get('/admin/audit-logs', {
        params: {
            page,
            search: filters.search || undefined,
            action: filters.action || undefined,
        },
    });

    const paginator = res.data;
    logs.value = paginator.data || [];
    currentPage.value = paginator.current_page || 1;
    totalPages.value = paginator.last_page || 1;
    totalCount.value = paginator.total || logs.value.length;
}

function formatDate(iso: string): string {
    if (!iso) return '-';
    const d = new Date(iso);
    return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
}
</script>
