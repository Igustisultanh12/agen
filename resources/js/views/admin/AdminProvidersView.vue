<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#F8FAFC] space-y-6">
        <AdminNav
            title="AI Providers & Gateways"
            subtitle="Koneksi native langsung ke cloud LLM (NVIDIA NIM, OpenRouter, Groq, DeepSeek, Gemini) dan model lokal (Ollama, LM Studio) — 100% PHP Laravel."
        />

        <!-- Controls Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 rounded-2xl bg-white border border-[#E2E8F0] shadow-xs">
            <div class="flex items-center space-x-3 text-xs text-slate-500">
                <span>Provider Aktif: <strong class="text-slate-900 font-bold font-mono">{{ providers.length }}</strong></span>
                <span class="text-slate-300">|</span>
                <span class="text-[#2563EB] font-bold">⚡ Gateway Laravel Native AI Engine</span>
            </div>

            <div class="flex items-center space-x-2 w-full sm:w-auto">
                <button
                    @click="checkAllHealth"
                    :disabled="checkingAll"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all flex items-center space-x-1.5 disabled:opacity-50 cursor-pointer shadow-xs"
                >
                    <span :class="{ 'animate-spin': checkingAll }">🔄</span>
                    <span>{{ checkingAll ? 'Menguji Semua...' : 'Ping Semua Provider' }}</span>
                </button>
                <button
                    @click="openModal()"
                    class="px-4 py-2 rounded-xl bg-[#2563EB] hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition-all flex items-center space-x-1.5 cursor-pointer"
                >
                    <span>+</span>
                    <span>Tambah Provider</span>
                </button>
            </div>
        </div>

        <!-- Providers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="provider in providers"
                :key="provider.id"
                class="rounded-2xl bg-white border border-[#E2E8F0] p-6 flex flex-col justify-between space-y-4 hover:shadow-md transition-shadow shadow-xs"
            >
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">
                                {{ getProviderEmoji(provider.type) }}
                            </span>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 leading-tight">{{ provider.name }}</h3>
                                <span class="text-[10px] text-slate-400 font-mono">{{ provider.slug }}</span>
                            </div>
                        </div>

                        <!-- Health Status Badge -->
                        <span
                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold flex items-center space-x-1"
                            :class="{
                                'bg-emerald-50 text-emerald-600 border border-emerald-200': provider.health_status === 'healthy',
                                'bg-amber-50 text-amber-600 border border-amber-200': provider.health_status === 'warning',
                                'bg-red-50 text-red-600 border border-red-200': provider.health_status === 'offline' || provider.health_status === 'error',
                                'bg-slate-100 text-slate-500 border border-slate-200': !provider.health_status || provider.health_status === 'unknown',
                            }"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="{
                                'bg-emerald-500': provider.health_status === 'healthy',
                                'bg-amber-500': provider.health_status === 'warning',
                                'bg-red-500': provider.health_status === 'offline' || provider.health_status === 'error',
                                'bg-slate-400': !provider.health_status || provider.health_status === 'unknown',
                            }"></span>
                            <span class="capitalize">{{ provider.health_status || 'unknown' }}</span>
                        </span>
                    </div>

                    <div class="space-y-2 text-xs text-slate-500 pt-1">
                        <div class="flex justify-between">
                            <span>Endpoint:</span>
                            <span class="font-mono text-slate-700 truncate max-w-[180px] font-medium" :title="provider.base_url">
                                {{ provider.base_url }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tipe:</span>
                            <span class="font-mono text-slate-700 capitalize font-medium">{{ provider.type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Jumlah Model:</span>
                            <span class="font-mono text-slate-900 font-bold">{{ provider.models_count || 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Status:</span>
                            <span :class="provider.status === 'active' ? 'text-emerald-600 font-bold' : 'text-slate-400'" class="capitalize">
                                {{ provider.status }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Latensi:</span>
                            <span class="font-mono text-slate-800 font-semibold">{{ provider.latency_ms ? `${provider.latency_ms} ms` : '-' }}</span>
                        </div>
                    </div>

                    <div v-if="provider.last_error" class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-800 truncate" :title="provider.last_error">
                        {{ provider.last_error }}
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <button
                        @click="checkSingleHealth(provider)"
                        :disabled="checkingId === provider.id"
                        class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors disabled:opacity-50 flex items-center space-x-1.5 cursor-pointer shadow-xs"
                    >
                        <span v-if="checkingId === provider.id" class="animate-spin">🔄</span>
                        <span>{{ checkingId === provider.id ? 'Menguji...' : 'Ping Test' }}</span>
                    </button>

                    <div class="flex items-center space-x-2">
                        <button
                            @click="openModal(provider)"
                            class="px-3 py-1.5 rounded-xl bg-[#2563EB]/10 hover:bg-[#2563EB]/20 text-[#2563EB] text-xs font-bold transition-colors cursor-pointer"
                        >
                            Edit
                        </button>
                        <button
                            @click="deleteProvider(provider)"
                            class="px-2.5 py-1.5 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold transition-colors cursor-pointer"
                        >
                            &times;
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Provider Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white border border-[#E2E8F0] rounded-3xl w-full max-w-lg p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900">{{ editingId ? 'Edit AI Provider' : 'Tambah AI Provider' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 font-bold p-1">&times;</button>
                </div>

                <form @submit.prevent="submitProvider" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Provider</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Contoh: NVIDIA NIM"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Slug Identifier</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                placeholder="nvidia_nim / open_router / groq"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 font-mono focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tipe Provider</label>
                            <select
                                v-model="form.type"
                                @change="handleTypeChange"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                            >
                                <option value="nvidia_nim">NVIDIA NIM (Gratis & Cepat)</option>
                                <option value="open_router">OpenRouter (Multi-model)</option>
                                <option value="groq">Groq (Ultra-fast LPU)</option>
                                <option value="deepseek">DeepSeek (V3 / R1)</option>
                                <option value="gemini">Google Gemini (Direct)</option>
                                <option value="ollama">Ollama (Lokal Offline)</option>
                                <option value="lmstudio">LM Studio (Lokal)</option>
                                <option value="openai">OpenAI Compatible</option>
                                <option value="fcc">FCC Proxy (Legacy)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Prioritas Rute</label>
                            <input
                                v-model.number="form.priority"
                                type="number"
                                required
                                min="1"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 font-mono focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Base URL Endpoint</label>
                        <input
                            v-model="form.base_url"
                            type="url"
                            required
                            placeholder="https://integrate.api.nvidia.com/v1"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 font-mono focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            API Key (Tersimpan Terenkripsi AES-256)
                        </label>
                        <input
                            v-model="form.api_key"
                            type="password"
                            :placeholder="editingId ? 'Kosongkan jika tidak ingin mengubah key' : 'nvapi-... atau sk-... atau gsk_...'"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 font-mono focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                        <select
                            v-model="form.status"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-900 focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                        >
                            <option value="active">Active (Tersedia untuk rute AI)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="px-5 py-2.5 rounded-xl bg-[#2563EB] hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20 disabled:opacity-50 cursor-pointer"
                        >
                            {{ saving ? 'Menyimpan...' : 'Simpan Provider' }}
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
    type: 'nvidia_nim',
    base_url: 'https://integrate.api.nvidia.com/v1',
    api_key: '',
    status: 'active',
    priority: 1,
});

const defaultEndpoints: Record<string, { name: string; slug: string; url: string }> = {
    nvidia_nim: { name: 'NVIDIA NIM', slug: 'nvidia_nim', url: 'https://integrate.api.nvidia.com/v1' },
    open_router: { name: 'OpenRouter', slug: 'open_router', url: 'https://openrouter.ai/api/v1' },
    groq: { name: 'Groq', slug: 'groq', url: 'https://api.groq.com/openai/v1' },
    deepseek: { name: 'DeepSeek', slug: 'deepseek', url: 'https://api.deepseek.com' },
    gemini: { name: 'Google Gemini', slug: 'gemini', url: 'https://generativelanguage.googleapis.com/v1beta/openai/' },
    ollama: { name: 'Ollama (Local)', slug: 'ollama', url: 'http://localhost:11434' },
    lmstudio: { name: 'LM Studio (Local)', slug: 'lmstudio', url: 'http://localhost:1234/v1' },
    openai: { name: 'OpenAI', slug: 'openai', url: 'https://api.openai.com/v1' },
    fcc: { name: 'Free Claude Code Proxy', slug: 'fcc', url: 'http://127.0.0.1:8082' },
};

onMounted(() => {
    fetchProviders();
});

async function fetchProviders() {
    const res = await api.get('/admin/providers');
    providers.value = res.data;
}

function handleTypeChange() {
    if (!editingId.value && defaultEndpoints[form.type]) {
        const def = defaultEndpoints[form.type];
        if (!form.name || Object.values(defaultEndpoints).some(d => d.name === form.name)) {
            form.name = def.name;
        }
        if (!form.slug || Object.values(defaultEndpoints).some(d => d.slug === form.slug)) {
            form.slug = def.slug;
        }
        form.base_url = def.url;
    }
}

function getProviderEmoji(type: string): string {
    switch (type) {
        case 'nvidia':
        case 'nvidia_nim': return '🟢';
        case 'openrouter':
        case 'open_router': return '🌐';
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
            name: 'NVIDIA NIM',
            slug: 'nvidia_nim',
            type: 'nvidia_nim',
            base_url: 'https://integrate.api.nvidia.com/v1',
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
