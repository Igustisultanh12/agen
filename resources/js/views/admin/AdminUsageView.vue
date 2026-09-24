<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="Usage Logs & Cost Analytics"
            subtitle="Full audit trail of AI model inferences, token consumption, latency, costs, and CSV reporting."
        />

        <!-- Filters & Export -->
        <div class="flex flex-col lg:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#141622] border border-slate-800">
            <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                <input
                    v-model="filters.search"
                    @input="debouncedFetch"
                    type="text"
                    placeholder="Search request ID..."
                    class="px-3.5 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white placeholder-slate-500 w-48 focus:outline-none focus:border-blue-500"
                />
                <select
                    v-model="filters.status"
                    @change="fetchLogs(1)"
                    class="px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                >
                    <option value="">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                    <option value="streaming">Streaming</option>
                </select>
                <input
                    v-model="filters.start_date"
                    @change="fetchLogs(1)"
                    type="date"
                    class="px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                />
                <input
                    v-model="filters.end_date"
                    @change="fetchLogs(1)"
                    type="date"
                    class="px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                />
            </div>

            <button
                @click="downloadCsv"
                class="w-full lg:w-auto px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white text-xs font-semibold shadow-lg shadow-emerald-700/20 transition-all flex items-center justify-center space-x-1.5"
            >
                <span>📥</span>
                <span>Export CSV Report</span>
            </button>
        </div>

        <!-- Usage Logs Table -->
        <div class="rounded-2xl bg-[#141622] border border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#12141e] text-slate-400 border-b border-slate-800 font-medium">
                        <tr>
                            <th class="py-3 px-4">Time</th>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Model / Provider</th>
                            <th class="py-3 px-4 font-mono text-right">Input</th>
                            <th class="py-3 px-4 font-mono text-right">Output</th>
                            <th class="py-3 px-4 font-mono text-right">Total Tokens</th>
                            <th class="py-3 px-4 font-mono text-right">Est. Cost</th>
                            <th class="py-3 px-4 font-mono text-right">Latency</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="py-3 px-4 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-200">{{ log.user?.name || 'N/A' }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">{{ log.user?.email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-200">{{ log.model?.name || 'Unknown Model' }}</div>
                                <div class="text-[10px] text-slate-400">{{ log.provider?.name }}</div>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-400">
                                {{ log.input_tokens.toLocaleString() }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-400">
                                {{ log.output_tokens.toLocaleString() }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-blue-400 font-medium">
                                {{ log.total_tokens.toLocaleString() }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-400 font-medium">
                                ${{ Number(log.estimated_cost).toFixed(4) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-400">
                                {{ log.duration_ms ? `${log.duration_ms}ms` : '-' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                    :class="log.status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'"
                                >
                                    {{ log.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="9" class="py-8 text-center text-slate-500 text-xs">No usage records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="totalPages > 1" class="p-3 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <div>Page {{ currentPage }} of {{ totalPages }} ({{ totalCount }} records)</div>
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
    status: '',
    start_date: '',
    end_date: '',
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
    const res = await api.get('/admin/usage-logs', {
        params: {
            page,
            search: filters.search || undefined,
            status: filters.status || undefined,
            start_date: filters.start_date || undefined,
            end_date: filters.end_date || undefined,
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
    return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
}

function downloadCsv() {
    const token = localStorage.getItem('auth_token');
    const url = `/api/admin/export-csv`;
    // Trigger download with auth header via fetch blob
    fetch(url, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'text/csv',
        },
    })
        .then((res) => res.blob())
        .then((blob) => {
            const blobUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = `ai-usage-report-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
        })
        .catch(() => alert('Failed to download CSV'));
}
</script>
