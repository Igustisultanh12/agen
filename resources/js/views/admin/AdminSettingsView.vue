<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="Global System Settings"
            subtitle="Configure platform branding, AI default prompts, monthly cloud spending budgets, cost alert policies, and security limits."
        />

        <!-- Success Alert -->
        <div v-if="successMsg" class="p-4 rounded-xl bg-emerald-950/40 border border-emerald-500/40 text-emerald-300 text-xs flex items-center justify-between">
            <span>{{ successMsg }}</span>
            <button @click="successMsg = ''" class="opacity-75 hover:opacity-100">&times;</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 1. AI Settings Group -->
            <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <span>🤖</span>
                        <span>AI Inference & Prompts</span>
                    </h2>
                    <span class="text-[10px] text-slate-400 uppercase font-mono">ai</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Global System Prompt Prefix</label>
                        <textarea
                            v-model="aiSettings['ai.default_system_prompt']"
                            rows="4"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs font-mono text-white focus:outline-none focus:border-blue-500 leading-relaxed"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Stream Timeout (Seconds)</label>
                            <input
                                v-model="aiSettings['ai.stream_timeout_seconds']"
                                type="number"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Auto Title Generation</label>
                            <select
                                v-model="aiSettings['ai.auto_title_generation']"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            >
                                <option value="true">Enabled</option>
                                <option value="false">Disabled</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            @click="saveGroup('ai', aiSettings)"
                            :disabled="savingGroup === 'ai'"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold disabled:opacity-50"
                        >
                            {{ savingGroup === 'ai' ? 'Saving...' : 'Save AI Settings' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. Cost Budget & Alert Policy (Requirement 34, 73) -->
            <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <span>💵</span>
                        <span>Platform Spending Budget & Alerts</span>
                    </h2>
                    <span class="text-[10px] text-slate-400 uppercase font-mono">quota</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Monthly Platform Budget (USD)</label>
                        <input
                            v-model="quotaSettings['cost.monthly_budget_usd']"
                            type="number"
                            step="10"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-emerald-400 font-bold font-mono focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Warning Alert Threshold (%)</label>
                            <input
                                v-model="quotaSettings['cost.warning_threshold_percent']"
                                type="number"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-amber-400 font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-300 mb-1">Critical Alert Threshold (%)</label>
                            <input
                                v-model="quotaSettings['cost.critical_threshold_percent']"
                                type="number"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-red-400 font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Action When Budget Reaches 100%</label>
                        <select
                            v-model="quotaSettings['cost.limit_reached_action']"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        >
                            <option value="only_free">Allow Free & Local Models Only</option>
                            <option value="only_local">Allow Local Models Only (Ollama / LM Studio)</option>
                            <option value="block_all">Block All Model Inferences</option>
                            <option value="warning_only">Warning Only (Do not block)</option>
                        </select>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            @click="saveGroup('quota', quotaSettings)"
                            :disabled="savingGroup === 'quota'"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold disabled:opacity-50"
                        >
                            {{ savingGroup === 'quota' ? 'Saving...' : 'Save Budget Settings' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3. General Platform Settings -->
            <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <span>⚙️</span>
                        <span>General & Registration</span>
                    </h2>
                    <span class="text-[10px] text-slate-400 uppercase font-mono">general</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Platform Brand Name</label>
                        <input
                            v-model="generalSettings['general.platform_name']"
                            type="text"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Open Public Registration</label>
                        <select
                            v-model="generalSettings['general.allow_registration']"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        >
                            <option value="true">Allowed (Users can self-register)</option>
                            <option value="false">Closed (Admin invitation only)</option>
                        </select>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            @click="saveGroup('general', generalSettings)"
                            :disabled="savingGroup === 'general'"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold disabled:opacity-50"
                        >
                            {{ savingGroup === 'general' ? 'Saving...' : 'Save General Settings' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- 4. Security & Rate Limits -->
            <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-sm font-bold text-white flex items-center space-x-2">
                        <span>🛡️</span>
                        <span>Security & Filesystem Limits</span>
                    </h2>
                    <span class="text-[10px] text-slate-400 uppercase font-mono">security</span>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Global Rate Limit (Requests / Min)</label>
                        <input
                            v-model="securitySettings['security.rate_limit_per_minute']"
                            type="number"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-300 mb-1">Allow Workspace File Uploads</label>
                        <select
                            v-model="securitySettings['security.allow_file_upload']"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        >
                            <option value="true">Allowed (Subject to MIME & path traversal validation)</option>
                            <option value="false">Blocked</option>
                        </select>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            @click="saveGroup('security', securitySettings)"
                            :disabled="savingGroup === 'security'"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold disabled:opacity-50"
                        >
                            {{ savingGroup === 'security' ? 'Saving...' : 'Save Security Settings' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import AdminNav from '../../components/AdminNav.vue';
import api from '../../api/client';

const savingGroup = ref<string | null>(null);
const successMsg = ref('');

const generalSettings = reactive<Record<string, string>>({
    'general.platform_name': 'AI Coding Workspace',
    'general.allow_registration': 'true',
});

const aiSettings = reactive<Record<string, string>>({
    'ai.default_system_prompt': '',
    'ai.stream_timeout_seconds': '120',
    'ai.auto_title_generation': 'true',
});

const quotaSettings = reactive<Record<string, string>>({
    'cost.monthly_budget_usd': '100.00',
    'cost.warning_threshold_percent': '80',
    'cost.critical_threshold_percent': '95',
    'cost.limit_reached_action': 'only_free',
});

const securitySettings = reactive<Record<string, string>>({
    'security.rate_limit_per_minute': '60',
    'security.allow_file_upload': 'true',
});

onMounted(() => {
    fetchSettings();
});

async function fetchSettings() {
    const res = await api.get('/admin/settings');
    const grouped = res.data;

    if (grouped.general) {
        grouped.general.forEach((s: any) => { generalSettings[s.key] = s.value; });
    }
    if (grouped.ai) {
        grouped.ai.forEach((s: any) => { aiSettings[s.key] = s.value; });
    }
    if (grouped.quota) {
        grouped.quota.forEach((s: any) => { quotaSettings[s.key] = s.value; });
    }
    if (grouped.security) {
        grouped.security.forEach((s: any) => { securitySettings[s.key] = s.value; });
    }
}

async function saveGroup(group: string, values: Record<string, string>) {
    savingGroup.value = group;
    successMsg.value = '';
    try {
        await api.post(`/admin/settings/${group}`, { settings: values });
        successMsg.value = `Settings for '${group}' updated successfully.`;
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to update settings');
    } finally {
        savingGroup.value = null;
    }
}
</script>
