<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <AdminNav
            title="AI Models & Pricing Matrix"
            subtitle="Register models, configure context windows, set token pricing (per 1M tokens), toggle active status, and configure fallback chains."
        />

        <!-- Controls Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-[#141622] border border-slate-800">
            <div class="flex items-center space-x-3 w-full sm:w-auto">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search model name or ID..."
                    class="px-3.5 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-100 placeholder-slate-500 w-full sm:w-64 focus:outline-none focus:border-blue-500"
                />
                <select
                    v-model="selectedProvider"
                    class="px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-slate-200 focus:outline-none focus:border-blue-500"
                >
                    <option value="">All Providers</option>
                    <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>

            <button
                @click="openModal()"
                class="w-full sm:w-auto px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center justify-center space-x-1.5"
            >
                <span>+</span>
                <span>Register Model</span>
            </button>
        </div>

        <!-- Models Table -->
        <div class="rounded-2xl bg-[#141622] border border-slate-800 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#12141e] text-slate-400 border-b border-slate-800 font-medium">
                        <tr>
                            <th class="py-3 px-4">Model</th>
                            <th class="py-3 px-4">Provider</th>
                            <th class="py-3 px-4">Context Window</th>
                            <th class="py-3 px-4">Pricing / 1M Tokens</th>
                            <th class="py-3 px-4">Fallbacks</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <tr v-for="model in filteredModels" :key="model.id" class="hover:bg-slate-800/30 transition-colors">
                            <td class="py-3 px-4">
                                <div class="flex items-center space-x-2">
                                    <div class="font-medium text-slate-200">{{ model.name }}</div>
                                    <span v-if="model.is_default" class="px-1.5 py-0.5 rounded text-[9px] bg-blue-500/20 text-blue-400 border border-blue-500/30 uppercase font-bold">
                                        Default
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-500 font-mono">{{ model.provider_model_id }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] bg-slate-800 text-slate-300 font-mono">
                                    {{ model.provider?.name || 'Unknown' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-300">
                                {{ (model.context_window / 1024).toFixed(0) }}k tokens
                            </td>
                            <td class="py-3 px-4 font-mono text-xs">
                                <div class="text-slate-300">
                                    In: <span class="text-emerald-400">${{ model.active_pricing?.input_price_per_1m ?? '0.00' }}</span>
                                </div>
                                <div class="text-slate-400 text-[10px]">
                                    Out: <span class="text-emerald-400">${{ model.active_pricing?.output_price_per_1m ?? '0.00' }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <div v-if="model.fallback_models && model.fallback_models.length > 0" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="fb in model.fallback_models"
                                        :key="fb.id"
                                        class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-950/40 text-indigo-300 border border-indigo-800/40"
                                    >
                                        {{ fb.fallback_model?.name || fb.fallback_model_id }}
                                    </span>
                                </div>
                                <span v-else class="text-slate-500 text-[10px]">None</span>
                            </td>
                            <td class="py-3 px-4">
                                <button
                                    @click="toggleStatus(model)"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold cursor-pointer transition-colors"
                                    :class="model.status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700'"
                                >
                                    {{ model.status }}
                                </button>
                            </td>
                            <td class="py-3 px-4 text-right space-x-1.5">
                                <button
                                    @click="openModal(model)"
                                    class="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] transition-colors"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteModel(model)"
                                    class="px-2 py-1 rounded bg-red-950/30 hover:bg-red-900/50 text-red-400 text-[11px] transition-colors"
                                >
                                    &times;
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredModels.length === 0">
                            <td colspan="7" class="py-8 text-center text-slate-500 text-xs">No models found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit Model Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-[#141622] border border-slate-800 rounded-2xl w-full max-w-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white">{{ editingId ? 'Edit AI Model' : 'Register AI Model' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-white">&times;</button>
                </div>

                <form @submit.prevent="submitModel" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Provider</label>
                            <select
                                v-model="form.provider_id"
                                required
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            >
                                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Display Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g. Claude 3.5 Sonnet"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Slug Identifier</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                placeholder="claude-3-5-sonnet"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Provider Model ID (FCC Wire Name)</label>
                            <input
                                v-model="form.provider_model_id"
                                type="text"
                                required
                                placeholder="anthropic/claude-3.5-sonnet"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Context Window</label>
                            <input
                                v-model.number="form.context_window"
                                type="number"
                                required
                                step="1024"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Max Output Tokens</label>
                            <input
                                v-model.number="form.max_tokens"
                                type="number"
                                required
                                step="1024"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Category</label>
                            <select
                                v-model="form.category"
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                            >
                                <option value="free">Free</option>
                                <option value="paid">Paid</option>
                                <option value="local">Local</option>
                            </select>
                        </div>
                    </div>

                    <!-- Pricing Inputs (Per 1M tokens) -->
                    <div class="p-3 rounded-xl bg-[#0d0f16] border border-slate-800 space-y-2">
                        <div class="text-xs font-medium text-slate-300">Model Pricing ($ / 1 Million Tokens)</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] text-slate-400 mb-1">Input Price / 1M</label>
                                <input
                                    v-model.number="form.pricing.input_price_per_1m"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-1.5 rounded-lg bg-[#141622] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] text-slate-400 mb-1">Output Price / 1M</label>
                                <input
                                    v-model.number="form.pricing.output_price_per_1m"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-1.5 rounded-lg bg-[#141622] border border-slate-700 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Fallback chain selector (Requirement 13) -->
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Fallback Model Chain (Optional)</label>
                        <select
                            v-model="form.fallback_model_ids"
                            multiple
                            class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500 h-24"
                        >
                            <option
                                v-for="m in models.filter(item => item.id !== editingId)"
                                :key="m.id"
                                :value="m.id"
                            >
                                {{ m.name }} ({{ m.provider?.name }})
                            </option>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">Hold Ctrl/Cmd to select multiple fallback models in priority order.</p>
                    </div>

                    <div class="flex items-center space-x-4 pt-1">
                        <label class="flex items-center space-x-2 text-xs text-slate-300">
                            <input v-model="form.is_default" type="checkbox" class="rounded bg-[#0d0f16] border-slate-700 text-blue-600" />
                            <span>System Default Model</span>
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
                            {{ saving ? 'Saving...' : 'Save Model' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import AdminNav from '../../components/AdminNav.vue';
import api from '../../api/client';

const models = ref<any[]>([]);
const providers = ref<any[]>([]);
const searchQuery = ref('');
const selectedProvider = ref('');
const showModal = ref(false);
const editingId = ref<number | null>(null);
const saving = ref(false);

const form = reactive({
    provider_id: 1,
    name: '',
    slug: '',
    provider_model_id: '',
    description: '',
    context_window: 128000,
    max_tokens: 4096,
    category: 'free',
    status: 'active',
    visibility: 'all',
    is_default: false,
    pricing: {
        input_price_per_1m: 0.0,
        output_price_per_1m: 0.0,
    },
    fallback_model_ids: [] as number[],
});

onMounted(async () => {
    await fetchProviders();
    await fetchModels();
});

async function fetchProviders() {
    const res = await api.get('/admin/providers');
    providers.value = res.data;
    if (providers.value.length > 0 && !form.provider_id) {
        form.provider_id = providers.value[0].id;
    }
}

async function fetchModels() {
    const res = await api.get('/admin/models');
    models.value = res.data;
}

const filteredModels = computed(() => {
    return models.value.filter((m) => {
        const matchesQuery = !searchQuery.value ||
            m.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            m.provider_model_id.toLowerCase().includes(searchQuery.value.toLowerCase());
        const matchesProvider = !selectedProvider.value || m.provider_id === Number(selectedProvider.value);
        return matchesQuery && matchesProvider;
    });
});

function openModal(model?: any) {
    if (model) {
        editingId.value = model.id;
        Object.assign(form, {
            provider_id: model.provider_id,
            name: model.name,
            slug: model.slug,
            provider_model_id: model.provider_model_id,
            description: model.description || '',
            context_window: model.context_window,
            max_tokens: model.max_tokens,
            category: model.category,
            status: model.status,
            visibility: model.visibility || 'all',
            is_default: !!model.is_default,
            pricing: {
                input_price_per_1m: model.active_pricing?.input_price_per_1m || 0.0,
                output_price_per_1m: model.active_pricing?.output_price_per_1m || 0.0,
            },
            fallback_model_ids: model.fallback_models ? model.fallback_models.map((f: any) => f.fallback_model_id) : [],
        });
    } else {
        editingId.value = null;
        Object.assign(form, {
            provider_id: providers.value[0]?.id || 1,
            name: '',
            slug: '',
            provider_model_id: '',
            description: '',
            context_window: 128000,
            max_tokens: 4096,
            category: 'free',
            status: 'active',
            visibility: 'all',
            is_default: false,
            pricing: {
                input_price_per_1m: 0.0,
                output_price_per_1m: 0.0,
            },
            fallback_model_ids: [],
        });
    }
    showModal.value = true;
}

async function submitModel() {
    saving.value = true;
    try {
        if (editingId.value) {
            await api.put(`/admin/models/${editingId.value}`, form);
        } else {
            await api.post('/admin/models', form);
        }
        showModal.value = false;
        await fetchModels();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to save model');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(model: any) {
    try {
        await api.post(`/admin/models/${model.id}/toggle`);
        await fetchModels();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to toggle status');
    }
}

async function deleteModel(model: any) {
    if (!confirm(`Delete model "${model.name}"?`)) return;
    try {
        await api.delete(`/admin/models/${model.id}`);
        await fetchModels();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Failed to delete model');
    }
}
</script>
