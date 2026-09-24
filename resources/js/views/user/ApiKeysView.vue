<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white tracking-tight">API Keys</h1>
                <p class="text-xs text-slate-400">Create and manage API keys to connect external tools, IDE plugins, or scripts to the workspace REST API.</p>
            </div>
            <button
                type="button"
                class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center space-x-1.5"
                @click="showCreateModal = true"
            >
                <span>+</span>
                <span>Generate New Key</span>
            </button>
        </div>

        <!-- Newly Generated Key Alert Box (Shown only once) -->
        <div v-if="newGeneratedKey" class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-400">Save Your Secret API Key</span>
                <button class="text-xs text-slate-400 hover:text-white" @click="newGeneratedKey = ''">Dismiss</button>
            </div>
            <p class="text-xs text-slate-300">Please copy this API key now. For security purposes, you will not be able to view it again.</p>
            <div class="flex items-center space-x-2">
                <input
                    type="text"
                    readonly
                    :value="newGeneratedKey"
                    class="w-full px-3 py-2 rounded-lg bg-[#11131a] border border-amber-500/40 text-xs font-mono text-amber-300 select-all"
                />
                <button
                    class="px-3 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold text-xs transition-colors flex-shrink-0"
                    @click="copyNewKey"
                >
                    {{ copyBtnText }}
                </button>
            </div>
        </div>

        <!-- Keys Table -->
        <div class="rounded-xl border border-slate-800 bg-[#141622] overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#181b28] text-slate-400 border-b border-slate-800 font-mono text-[11px]">
                    <tr>
                        <th class="p-3">Key Name</th>
                        <th class="p-3">Key Preview</th>
                        <th class="p-3">Permissions</th>
                        <th class="p-3">Last Used</th>
                        <th class="p-3">Expires At</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    <tr v-for="key in apiKeys" :key="key.id" class="hover:bg-slate-800/30">
                        <td class="p-3 font-medium text-white">{{ key.name }}</td>
                        <td class="p-3 font-mono text-slate-400">{{ key.key_preview }}</td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="p in key.permissions" :key="p" class="px-1.5 py-0.5 rounded bg-slate-800 text-[10px] text-slate-300 font-mono">
                                    {{ p }}
                                </span>
                            </div>
                        </td>
                        <td class="p-3 font-mono text-slate-400">{{ key.last_used_at ? new Date(key.last_used_at).toLocaleDateString() : 'Never' }}</td>
                        <td class="p-3 font-mono text-slate-400">{{ key.expires_at ? new Date(key.expires_at).toLocaleDateString() : 'No expiry' }}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px]" :class="key.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                                {{ key.is_active ? 'Active' : 'Revoked' }}
                            </span>
                        </td>
                        <td class="p-3 text-right space-x-2">
                            <button
                                v-if="key.is_active"
                                type="button"
                                class="text-xs text-blue-400 hover:underline"
                                @click="rotateKey(key.id)"
                            >
                                Rotate
                            </button>
                            <button
                                v-if="key.is_active"
                                type="button"
                                class="text-xs text-red-400 hover:underline"
                                @click="revokeKey(key.id)"
                            >
                                Revoke
                            </button>
                        </td>
                    </tr>
                    <tr v-if="apiKeys.length === 0">
                        <td colspan="7" class="p-8 text-center text-slate-500">No API keys created yet. Generate one above to access the REST API.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Key Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showCreateModal = false">
            <div class="w-full max-w-md bg-[#161924] border border-slate-800 rounded-xl p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white">Generate API Key</h3>
                    <button class="text-slate-400 hover:text-white" @click="showCreateModal = false">✕</button>
                </div>

                <form class="space-y-4" @submit.prevent="handleCreateKey">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Key Name / Label</label>
                        <input
                            v-model="keyName"
                            type="text"
                            required
                            placeholder="e.g. VS Code Extension, CI/CD Pipeline"
                            class="w-full px-3 py-2 rounded-lg bg-[#11131a] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Expiration</label>
                        <select
                            v-model="keyExpiry"
                            class="w-full px-3 py-2 rounded-lg bg-[#11131a] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                        >
                            <option :value="30">30 Days</option>
                            <option :value="90">90 Days</option>
                            <option :value="365">1 Year</option>
                            <option :value="0">Never Expire</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" class="px-3 py-1.5 rounded bg-slate-800 text-xs text-slate-300" @click="showCreateModal = false">Cancel</button>
                        <button type="submit" class="px-3 py-1.5 rounded bg-blue-600 text-xs text-white font-medium hover:bg-blue-500">Generate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../api/client';

const apiKeys = ref<any[]>([]);
const showCreateModal = ref(false);
const keyName = ref('');
const keyExpiry = ref(90);
const newGeneratedKey = ref('');
const copyBtnText = ref('Copy Key');

onMounted(async () => {
    await fetchKeys();
});

async function fetchKeys() {
    const res = await api.get('/api-keys');
    apiKeys.value = res.data;
}

async function handleCreateKey() {
    if (!keyName.value.trim()) return;
    const res = await api.post('/api-keys', {
        name: keyName.value.trim(),
        expires_days: keyExpiry.value > 0 ? keyExpiry.value : null,
    });
    newGeneratedKey.value = res.data.plain_text_key;
    keyName.value = '';
    showCreateModal.value = false;
    await fetchKeys();
}

async function revokeKey(keyId: number) {
    if (confirm('Revoke this API key? Applications using it will immediately be rejected.')) {
        await api.post(`/api-keys/${keyId}/revoke`);
        await fetchKeys();
    }
}

async function rotateKey(keyId: number) {
    if (confirm('Rotate this API key? The old key will immediately be revoked.')) {
        const res = await api.post(`/api-keys/${keyId}/rotate`);
        newGeneratedKey.value = res.data.plain_text_key;
        await fetchKeys();
    }
}

function copyNewKey() {
    navigator.clipboard.writeText(newGeneratedKey.value).then(() => {
        copyBtnText.value = 'Copied!';
        setTimeout(() => copyBtnText.value = 'Copy Key', 2000);
    });
}
</script>
