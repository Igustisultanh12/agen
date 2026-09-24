<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <div>
            <h1 class="text-xl font-bold text-white tracking-tight">Token Usage & Estimated Cost</h1>
            <p class="text-xs text-slate-400">Detailed accounting of your AI token usage, quota limits, and estimated costs across all conversations.</p>
        </div>

        <div v-if="usage" class="space-y-6">
            <!-- Quota Limit Status Card -->
            <div class="p-6 rounded-xl bg-[#141622] border border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Monthly Quota Consumption</h2>
                        <p class="text-xs text-slate-400 font-mono">
                            {{ usage.quota.monthly_used_tokens.toLocaleString() }} / {{ usage.quota.monthly_token_limit.toLocaleString() }} tokens consumed
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-xl font-bold font-mono text-blue-400">{{ usage.quota.monthly_usage_percentage }}%</span>
                        <div class="text-[11px] text-slate-500 font-mono">{{ usage.quota.monthly_remaining_tokens.toLocaleString() }} tokens remaining</div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="w-full h-2.5 bg-slate-800 rounded-full overflow-hidden">
                    <div
                        class="h-full transition-all duration-500"
                        :class="usage.quota.monthly_usage_percentage > 90 ? 'bg-red-500' : (usage.quota.monthly_usage_percentage > 75 ? 'bg-amber-500' : 'bg-blue-500')"
                        :style="{ width: `${Math.min(100, usage.quota.monthly_usage_percentage)}%` }"
                    ></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-slate-800/80 text-xs">
                    <div>
                        <span class="text-slate-400">Daily Limit:</span>
                        <span class="text-white font-mono ml-2">{{ usage.quota.daily_token_limit.toLocaleString() }} tokens</span>
                    </div>
                    <div>
                        <span class="text-slate-400">Daily Used:</span>
                        <span class="text-white font-mono ml-2">{{ usage.quota.daily_used_tokens.toLocaleString() }} tokens</span>
                    </div>
                    <div>
                        <span class="text-slate-400">Estimated Total Cost:</span>
                        <span class="text-emerald-400 font-mono font-medium ml-2">${{ Number(usage.quota.total_cost_usd || 0).toFixed(4) }} USD</span>
                    </div>
                </div>
            </div>

            <!-- Historical Requests List -->
            <div class="space-y-3">
                <h2 class="text-base font-semibold text-white">Request History</h2>
                <div class="rounded-xl border border-slate-800 bg-[#141622] overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-[#181b28] text-slate-400 border-b border-slate-800 font-mono text-[11px]">
                            <tr>
                                <th class="p-3">Model</th>
                                <th class="p-3">Provider</th>
                                <th class="p-3">Input</th>
                                <th class="p-3">Output</th>
                                <th class="p-3">Total Tokens</th>
                                <th class="p-3">Estimated Cost</th>
                                <th class="p-3">Duration</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-300 font-mono">
                            <tr v-for="req in usage.recent_requests" :key="req.id" class="hover:bg-slate-800/30">
                                <td class="p-3 font-sans font-medium text-white">{{ req.model }}</td>
                                <td class="p-3 text-slate-400">{{ req.provider }}</td>
                                <td class="p-3">{{ req.input_tokens.toLocaleString() }}</td>
                                <td class="p-3">{{ req.output_tokens.toLocaleString() }}</td>
                                <td class="p-3">{{ req.total_tokens.toLocaleString() }}</td>
                                <td class="p-3 text-emerald-400">${{ Number(req.estimated_cost || 0).toFixed(4) }}</td>
                                <td class="p-3 text-slate-400">{{ req.duration_ms }}ms</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px]" :class="req.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                                        {{ req.status }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-400">{{ new Date(req.created_at).toLocaleString() }}</td>
                            </tr>
                            <tr v-if="usage.recent_requests.length === 0">
                                <td colspan="9" class="p-6 text-center text-slate-500">No AI requests logged yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../api/client';

const usage = ref<any>(null);

onMounted(async () => {
    try {
        const res = await api.get('/usage');
        usage.value = res.data;
    } catch (err) {
        console.error('Failed to load usage', err);
    }
});
</script>
