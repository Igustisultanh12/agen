<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="Coding Agents Management"
            subtitle="Manage autonomous AI coding agent harnesses (Claude Code, OpenCode, Codex, Pi, Cline, Hermes, DeepSeek Harness, Grok Build, Muse Code, Aider)."
        />

        <div class="flex justify-end">
            <button
                @click="openModal()"
                class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center space-x-1.5"
            >
                <span>+</span>
                <span>Add Coding Agent</span>
            </button>
        </div>

        <!-- Agents Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="agent in agents"
                :key="agent.id"
                class="rounded-2xl bg-[#141622] border border-slate-800 p-6 flex flex-col justify-between space-y-4 hover:border-slate-700 transition-colors"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2.5">
                            <span class="text-2xl">{{ getAgentIcon(agent.slug) }}</span>
                            <div>
                                <div class="flex items-center space-x-1.5">
                                    <h3 class="text-base font-bold text-white leading-tight">{{ agent.name }}</h3>
                                    <span v-if="agent.is_default" class="px-1.5 py-0.5 rounded text-[9px] bg-blue-500/20 text-blue-400 border border-blue-500/30 uppercase font-bold">
                                        Default
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-400 font-mono">{{ agent.slug }}</span>
                            </div>
                        </div>

                        <button
                            @click="toggleStatus(agent)"
                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold transition-colors"
                            :class="agent.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500 border border-slate-700'"
                        >
                            {{ agent.is_active ? 'Active' : 'Disabled' }}
                        </button>
                    </div>

                    <p class="text-xs text-slate-400 min-h-[36px] leading-relaxed">
                        {{ agent.description || 'Specialized AI coding harness' }}
                    </p>

                    <div class="pt-2 border-t border-slate-800/80 space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Harness Type:</span>
                            <span class="font-mono text-slate-300 font-medium">{{ agent.harness_type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Assigned Plans:</span>
                            <span v-if="agent.user_groups && agent.user_groups.length > 0" class="text-blue-400 font-medium truncate max-w-[150px]">
                                {{ agent.user_groups.map((g: any) => g.name).join(', ') }}
                            </span>
                            <span v-else class="text-slate-500">All Plans</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-end space-x-2">
                    <button
                        @click="openModal(agent)"
                        class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors"
                    >
                        Edit Harness
                    </button>
                </div>
            </div>
        </div>

        <!-- Add / Edit Agent Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-[#141622] border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white">{{ editingId ? 'Edit Agent Harness' : 'Add Coding Agent' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form @submit.prevent="submitAgent" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Agent Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Claude Code"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Slug Identifier</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                placeholder="claude-code"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Harness Type</label>
                        <select
                            v-model="form.harness_type"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        >
                            <option value="fcc_claude_code">Free Claude Code (FCC) Harness</option>
                            <option value="codex">Codex Interactive</option>
                            <option value="opencode">OpenCode Architecture</option>
                            <option value="pi">Pi Reasoning Agent</option>
                            <option value="cline">Cline Autonomous Harness</option>
                            <option value="hermes">Hermes Agent</option>
                            <option value="deepseek_harness">DeepSeek Harness</option>
                            <option value="grok_build">Grok Build Harness</option>
                            <option value="aider">Aider Terminal Emulation</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-4 pt-1">
                        <label class="flex items-center space-x-2 text-xs text-slate-300">
                            <input v-model="form.is_active" type="checkbox" class="rounded bg-[#0d0f16] border-slate-700 text-blue-600" />
                            <span>Active</span>
                        </label>
                        <label class="flex items-center space-x-2 text-xs text-slate-300">
                            <input v-model="form.is_default" type="checkbox" class="rounded bg-[#0d0f16] border-slate-700 text-blue-600" />
                            <span>Default Selection</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-800">
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
                            {{ saving ? 'Saving...' : 'Save Agent' }}
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

const agents = ref<any[]>([]);
const showModal = ref(false);
const editingId = ref<number | null>(null);
const saving = ref(false);

const form = reactive({
    name: '',
    slug: '',
    description: '',
    harness_type: 'fcc_claude_code',
    is_active: true,
    is_default: false,
});

onMounted(() => {
    fetchAgents();
});

async function fetchAgents() {
    const res = await api.get('/admin/agents');
    agents.value = res.data;
}

function getAgentIcon(slug: string): string {
    switch (slug) {
        case 'claude-code': return '🤖';
        case 'codex': return '💻';
        case 'opencode': return '🧩';
        case 'pi': return 'π';
        case 'cline': return '🛠️';
        case 'hermes': return '⚡';
        case 'deepseek-harness': return '🐳';
        case 'grok-build': return '🔨';
        case 'muse-code': return '🎨';
        case 'aider': return '🚀';
        default: return '🤖';
    }
}

function openModal(agent?: any) {
    if (agent) {
        editingId.value = agent.id;
        Object.assign(form, {
            name: agent.name,
            slug: agent.slug,
            description: agent.description || '',
            harness_type: agent.harness_type || 'fcc_claude_code',
            is_active: !!agent.is_active,
            is_default: !!agent.is_default,
        });
    } else {
        editingId.value = null;
        Object.assign(form, {
            name: '',
            slug: '',
            description: '',
            harness_type: 'fcc_claude_code',
            is_active: true,
            is_default: false,
        });
    }
    showModal.value = true;
}

async function submitAgent() {
    saving.value = true;
    try {
        if (editingId.value) {
            await api.put(`/admin/agents/${editingId.value}`, form);
        } else {
            await api.post('/admin/agents', form);
        }
        showModal.value = false;
        await fetchAgents();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to save agent');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(agent: any) {
    try {
        await api.post(`/admin/agents/${agent.id}/toggle`);
        await fetchAgents();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to update agent status');
    }
}
</script>
