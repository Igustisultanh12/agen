<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="AI Providers & Gateways"
            subtitle="Configure Free Claude Code (FCC) upstream gateways, cloud providers (NVIDIA NIM, OpenRouter, Groq, Gemini) and local LLMs (Ollama, LM Studio)."
        />

        <!-- Controls Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#141622] border border-slate-800">
            <div class="text-xs text-slate-400">
                Connected Providers: <span class="font-bold text-white font-mono">{{ providers.length }}</span>
            </div>

            <div class="flex items-center space-x-2 w-full sm:w-auto">
                <button
                    @click="checkAllHealth"
                    :disabled="checkingAll"
                    class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors flex items-center space-x-1.5 disabled:opacity-50"
                >
                    <span :class="{ 'animate-spin': checkingAll }">🔄</span>
                    <span>{{ checkingAll ? 'Pinging All...' : 'Ping All Providers' }}</span>
                </button>
                <button
                    @click="openModal()"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center space-x-1.5"
                >
                    <span>+</span>
                    <span>Add Provider</span>
                </button>
            </div>
        </div>

        <!-- Providers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="provider in providers"
                :key="provider.id"
                class="rounded-2xl bg-[#141622] border border-slate-800 p-6 flex flex-col justify-between space-y-4 hover:border-slate-700 transition-colors"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span class="text-xl">
                                {{ getProviderEmoji(provider.type) }}
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-white leading-tight">{{ provider.name }}</h3>
                                <span class="text-[10px] text-slate-400 font-mono">{{ provider.slug }}</span>
                            </div>
                        </div>

                        <!-- Health Status Badge -->
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-semibold flex items-center space-x-1"
                            :class="{
                                'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': provider.health_status === 'healthy',
                                'bg-amber-500/10 text-amber-400 border border-amber-500/20': provider.health_status === 'warning',
                                'bg-red-500/10 text-red-400 border border-red-500/20': provider.health_status === 'offline' || provider.health_status === 'error',
                                'bg-slate-800 text-slate-400 border border-slate-700': !provider.health_status || provider.health_status === 'unknown',
                            }"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="{
                                'bg-emerald-400': provider.health_status === 'healthy',
                                'bg-amber-400': provider.health_status === 'warning',
                                'bg-red-400': provider.health_status === 'offline' || provider.health_status === 'error',
                                'bg-slate-400': !provider.health_status || provider.health_status === 'unknown',
                            }"></span>
                            <span class="capitalize">{{ provider.health_status || 'unknown' }}</span>
                        </span>
                    </div>

                    <div class="space-y-1.5 text-xs text-slate-400 pt-1">
                        <div class="flex justify-between">
                            <span>Base URL:</span>
                            <span class="font-mono text-slate-300 truncate max-w-[180px]" :title="provider.base_url">
                                {{ provider.base_url }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Models Configured:</span>
                            <span class="font-mono text-white font-medium">{{ provider.models_count || 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Status:</span>
                            <span :class="provider.status === 'active' ? 'text-emerald-400' : 'text-slate-500'" class="capitalize font-medium">
                                {{ provider.status }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Latency:</span>
                            <span class="font-mono text-slate-300">{{ provider.latency_ms ? `${provider.latency_ms} ms` : '-' }}</span>
                        </div>
                    </div>

                    <div v-if="provider.last_error" class="p-2 rounded bg-red-950/20 border border-red-900/30 text-[11px] text-red-400 truncate" :title="provider.last_error">
                        {{ provider.last_error }}
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                    <button
                        @click="checkSingleHealth(provider)"
                        :disabled="checkingId === provider.id"
                        class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs transition-colors disabled:opacity-50"
                    >
                        {{ checkingId === provider.id ? 'Pinging...' : 'Ping Test' }}
                    </button>

                    <div class="flex items-center space-x-2">
                        <button
                            @click="openModal(provider)"
                            class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors"
                        >
                            Edit
                        </button>
                        <button
                            @click="deleteProvider(provider)"
                            class="px-2 py-1 rounded-lg bg-red-950/30 hover:bg-red-900/50 text-red-400 text-xs transition-colors"
                        >
                            &times;
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Provider Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-[#141622] border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white">{{ editingId ? 'Edit AI Provider' : 'Add AI Provider' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form @submit.prevent="submitProvider" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Provider Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g. NVIDIA NIM"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Slug Identifier</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                placeholder="nvidia / openrouter / groq"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Provider Type</label>
                            <select
                                v-model="form.type"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            >
                                <option value="fcc">Free Claude Code (FCC Proxy)</option>
                                <option value="nvidia">NVIDIA NIM</option>
                                <option value="openrouter">OpenRouter</option>
                                <option value="groq">Groq</option>
                                <option value="deepseek">DeepSeek</option>
                                <option value="gemini">Google Gemini</option>
                                <option value="ollama">Ollama (Local)</option>
                                <option value="lmstudio">LM Studio (Local)</option>
                                <option value="openai">OpenAI Compatible</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Priority Order</label>
                            <input
                                v-model.number="form.priority"
                                type="number"
                                required
                                min="1"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Base URL Endpoint</label>
                        <input
                            v-model="form.base_url"
                            type="url"
                            required
                            placeholder="http://127.0.0.1:8082"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">
                            API Key (Stored Encrypted AES-256)
                        </label>
                        <input
                            v-model="form.api_key"
                            type="password"
                            :placeholder="editingId ? 'Leave blank to retain existing key' : 'sk-... or secret key'"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Status</label>
                        <select
                            v-model="form.status"
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        >
                            <option value="active">Active (Available for routing)</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
                            {{ saving ? 'Saving...' : 'Save Provider' }}
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

const providers = ref<any[]>([]);
const showModal = ref(false);
const editingId = ref<number | null>(null);
const saving = ref(false);
const checkingAll = ref(false);
const checkingId = ref<number | null>(null);

const form = reactive({
    name: '',
    slug: '',
    type: 'fcc',
    base_url: 'http://127.0.0.1:8082',
    api_key: '',
    status: 'active',
    priority: 1,
});

onMounted(() => {
    fetchProviders();
});

async function fetchProviders() {
    const res = await api.get('/admin/providers');
    providers.value = res.data;
}

function getProviderEmoji(type: string): string {
    switch (type) {
        case 'nvidia': return '🟢';
        case 'openrouter': return '🌐';
        case 'groq': return '⚡';
        case 'deepseek': return '🐳';
        case 'gemini': return '✨';
        case 'ollama': return '🦙';
        case 'lmstudio': return '🖥️';
        case 'fcc': return '⚙️';
        default: return '🔌';
    }
}

function openModal(provider?: any) {
    if (provider) {
        editingId.value = provider.id;
        Object.assign(form, {
            name: provider.name,
            slug: provider.slug,
            type: provider.type,
            base_url: provider.base_url,
            api_key: '',
            status: provider.status,
            priority: provider.priority || 1,
        });
    } else {
        editingId.value = null;
        Object.assign(form, {
            name: '',
            slug: '',
            type: 'fcc',
            base_url: 'http://127.0.0.1:8082',
            api_key: '',
            status: 'active',
            priority: providers.value.length + 1,
        });
    }
    showModal.value = true;
}

async function submitProvider() {
    saving.value = true;
    try {
        const payload: any = { ...form };
        if (editingId.value && !payload.api_key) {
            delete payload.api_key;
        }

        if (editingId.value) {
            await api.put(`/admin/providers/${editingId.value}`, payload);
        } else {
            await api.post('/admin/providers', payload);
        }
        showModal.value = false;
        await fetchProviders();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to save provider');
    } finally {
        saving.value = false;
    }
}

async function checkSingleHealth(provider: any) {
    checkingId.value = provider.id;
    try {
        await api.post(`/admin/providers/${provider.id}/health`);
        await fetchProviders();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Health check failed');
    } finally {
        checkingId.value = null;
    }
}

async function checkAllHealth() {
    checkingAll.value = true;
    try {
        await api.post('/admin/providers/health-check-all');
        await fetchProviders();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Health check failed');
    } finally {
        checkingAll.value = false;
    }
}

async function deleteProvider(provider: any) {
    if (!confirm(`Delete provider "${provider.name}"? This may affect linked models.`)) return;
    try {
        await api.delete(`/admin/providers/${provider.id}`);
        await fetchProviders();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to delete provider');
    }
}
</script>
