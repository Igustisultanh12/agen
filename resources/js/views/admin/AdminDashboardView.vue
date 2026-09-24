<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="System Dashboard"
            subtitle="Realtime cluster metrics, token consumption, cost analysis, and model usage."
        />

        <!-- Loading State -->
        <div v-if="adminStore.isLoading && !adminStore.dashboard" class="p-12 text-center text-slate-500 text-sm">
            Loading analytics dashboard...
        </div>

        <template v-else-if="adminStore.dashboard">
            <!-- 4 Primary KPI Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Users -->
                <div class="p-5 rounded-2xl bg-[#141622] border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Total Registered Users</span>
                        <span class="text-blue-400 font-semibold">👥</span>
                    </div>
                    <div class="text-3xl font-bold font-mono text-white">{{ adminStore.dashboard.users.total }}</div>
                    <div class="text-[11px] text-slate-400 flex items-center space-x-2">
                        <span class="text-emerald-400 font-medium">{{ adminStore.dashboard.users.active }} active</span>
                        <span>•</span>
                        <span class="text-amber-400 font-medium">{{ adminStore.dashboard.users.suspended }} suspended</span>
                    </div>
                </div>

                <!-- Total AI Requests -->
                <div class="p-5 rounded-2xl bg-[#141622] border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Requests (This Month)</span>
                        <span class="text-indigo-400 font-semibold">⚡</span>
                    </div>
                    <div class="text-3xl font-bold font-mono text-indigo-400">
                        {{ adminStore.dashboard.requests.this_month.toLocaleString() }}
                    </div>
                    <div class="text-[11px] text-slate-400 flex items-center space-x-2">
                        <span>Today: {{ adminStore.dashboard.requests.today }}</span>
                        <span>•</span>
                        <span class="text-red-400">{{ adminStore.dashboard.requests.failed }} failed</span>
                    </div>
                </div>

                <!-- Total Tokens Processed -->
                <div class="p-5 rounded-2xl bg-[#141622] border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Tokens (This Month)</span>
                        <span class="text-purple-400 font-semibold">🧠</span>
                    </div>
                    <div class="text-3xl font-bold font-mono text-purple-400">
                        {{ (Number(adminStore.dashboard.tokens.this_month || 0) / 1000000).toFixed(2) }}M
                    </div>
                    <div class="text-[11px] text-slate-400">
                        Today: {{ (Number(adminStore.dashboard.tokens.today || 0) / 1000).toFixed(1) }}k tokens
                    </div>
                </div>

                <!-- Total Estimated Cost -->
                <div class="p-5 rounded-2xl bg-[#141622] border border-slate-800 space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Estimated Cost (This Month)</span>
                        <span class="text-emerald-400 font-semibold">💵</span>
                    </div>
                    <div class="text-3xl font-bold font-mono text-emerald-400">
                        ${{ Number(adminStore.dashboard.cost.this_month || 0).toFixed(3) }}
                    </div>
                    <div class="text-[11px] text-slate-400">
                        Today: ${{ Number(adminStore.dashboard.cost.today || 0).toFixed(3) }}
                    </div>
                </div>
            </div>

            <!-- Time-Series Analytics Chart Card (Requirement 7) -->
            <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-white">Daily Token Usage & Request Timeline</h2>
                        <p class="text-xs text-slate-400">Historical trend across all connected AI models</p>
                    </div>

                    <!-- Range Selector -->
                    <div class="flex items-center space-x-1.5 bg-[#0d0f16] p-1 rounded-lg border border-slate-800">
                        <button
                            v-for="r in ranges"
                            :key="r.key"
                            @click="changeRange(r.key)"
                            class="px-2.5 py-1 text-xs rounded transition-colors"
                            :class="activeRange === r.key ? 'bg-blue-600 text-white font-medium' : 'text-slate-400 hover:text-slate-200'"
                        >
                            {{ r.label }}
                        </button>
                    </div>
                </div>

                <!-- Canvas Chart -->
                <div class="h-64 w-full relative">
                    <canvas ref="chartCanvas"></canvas>
                </div>
            </div>

            <!-- Bottom Grid: Top Users & Model / Provider Cost -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Top 5 Active Users (Left 2 cols) -->
                <div class="lg:col-span-2 p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-white">Top Users by Token Consumption</h3>
                        <router-link to="/admin/users" class="text-xs text-blue-400 hover:underline">Manage All Users →</router-link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="py-2 font-medium">User</th>
                                    <th class="py-2 font-medium">Requests</th>
                                    <th class="py-2 font-medium font-mono text-right">Tokens</th>
                                    <th class="py-2 font-medium font-mono text-right">Est. Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <tr v-for="user in adminStore.dashboard.top_users" :key="user.id" class="hover:bg-slate-800/30">
                                    <td class="py-2.5">
                                        <div class="font-medium text-slate-200">{{ user.name }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono">{{ user.email }}</div>
                                    </td>
                                    <td class="py-2.5 text-slate-300 font-mono">{{ user.requests }}</td>
                                    <td class="py-2.5 text-right font-mono text-blue-400 font-medium">
                                        {{ (user.total_tokens / 1000).toFixed(1) }}k
                                    </td>
                                    <td class="py-2.5 text-right font-mono text-emerald-400 font-medium">
                                        ${{ Number(user.estimated_cost).toFixed(3) }}
                                    </td>
                                </tr>
                                <tr v-if="adminStore.dashboard.top_users.length === 0">
                                    <td colspan="4" class="py-4 text-center text-slate-500 text-xs">No user activity recorded yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Provider & Model Distribution (Right 1 col) -->
                <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-5">
                    <h3 class="text-sm font-semibold text-white">Model & Provider Activity</h3>

                    <div class="space-y-4">
                        <div class="text-xs text-slate-400 font-medium uppercase tracking-wider">Top Models</div>
                        <div class="space-y-2.5">
                            <div
                                v-for="m in adminStore.dashboard.cost_by_model"
                                :key="m.name"
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="text-slate-300 truncate max-w-[140px]">{{ m.name }}</span>
                                <div class="text-right font-mono text-slate-400">
                                    <span class="text-white font-medium">{{ (m.total_tokens / 1000).toFixed(0) }}k</span>
                                    <span class="text-[10px] text-emerald-400 ml-1.5">${{ Number(m.estimated_cost).toFixed(3) }}</span>
                                </div>
                            </div>
                            <div v-if="adminStore.dashboard.cost_by_model.length === 0" class="text-xs text-slate-500">
                                No model records yet.
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-800">
                            <div class="text-xs text-slate-400 font-medium uppercase tracking-wider mb-2">Cost by Provider</div>
                            <div class="space-y-2">
                                <div
                                    v-for="p in adminStore.dashboard.cost_by_provider"
                                    :key="p.name"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="text-slate-300 truncate">{{ p.name }}</span>
                                    <span class="font-mono text-emerald-400 font-medium">${{ Number(p.estimated_cost).toFixed(3) }}</span>
                                </div>
                                <div v-if="adminStore.dashboard.cost_by_provider.length === 0" class="text-xs text-slate-500">
                                    No provider records yet.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick, onBeforeUnmount } from 'vue';
import AdminNav from '../../components/AdminNav.vue';
import { useAdminStore } from '../../stores/admin';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const adminStore = useAdminStore();
const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: Chart | null = null;

const activeRange = ref('30d');
const ranges = [
    { key: 'today', label: 'Today' },
    { key: '7d', label: '7 Days' },
    { key: '30d', label: '30 Days' },
    { key: 'this_month', label: 'This Month' },
];

onMounted(async () => {
    await adminStore.fetchDashboard();
    await loadAnalytics();
});

onBeforeUnmount(() => {
    if (chartInstance) {
        chartInstance.destroy();
    }
});

async function changeRange(range: string) {
    activeRange.value = range;
    await loadAnalytics();
}

async function loadAnalytics() {
    await adminStore.fetchAnalytics(activeRange.value);
    await nextTick();
    renderChart();
}

function renderChart() {
    if (!chartCanvas.value) return;
    if (chartInstance) {
        chartInstance.destroy();
    }

    const timeline = adminStore.analytics?.timeline || [];
    const labels = timeline.map((item: any) => item.date_label);
    const tokens = timeline.map((item: any) => item.total_tokens);
    const requests = timeline.map((item: any) => item.requests);

    chartInstance = new Chart(chartCanvas.value, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Total Tokens',
                    data: tokens,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'y',
                },
                {
                    label: 'Requests',
                    data: requests,
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    borderDash: [4, 4],
                    tension: 0.2,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#94a3b8',
                        font: { size: 11 },
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: 'rgba(51, 65, 85, 0.3)' },
                    ticks: { color: '#64748b', font: { size: 10 } },
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { color: 'rgba(51, 65, 85, 0.3)' },
                    ticks: {
                        color: '#64748b',
                        font: { size: 10 },
                        callback: (v) => `${Number(v) / 1000}k`,
                    },
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#10b981', font: { size: 10 } },
                },
            },
        },
    });
}
</script>
