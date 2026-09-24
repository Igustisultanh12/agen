<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-8">
        <!-- Welcome Header Banner -->
        <div class="p-6 rounded-2xl bg-gradient-to-r from-blue-900/30 via-indigo-900/20 to-purple-900/30 border border-slate-800 flex items-center justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-white tracking-tight">Welcome back, {{ authStore.user?.name }}</h1>
                <p class="text-xs text-slate-400">Select a project, choose a coding agent, and start building in your AI workspace.</p>
            </div>
            <div class="flex items-center space-x-3">
                <router-link
                    to="/projects"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center space-x-1.5"
                >
                    <span>+</span>
                    <span>New Project</span>
                </router-link>
                <button
                    type="button"
                    class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-all"
                    @click="startNewChat"
                >
                    Start New Chat
                </button>
            </div>
        </div>

        <!-- Token Usage & Quota Cards (Requirement 24) -->
        <div v-if="usage" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Today Tokens -->
            <div class="p-5 rounded-xl bg-[#141622] border border-slate-800/80 space-y-2">
                <div class="text-xs text-slate-400 font-medium">Today's Tokens</div>
                <div class="text-2xl font-bold font-mono text-white">{{ usage.today.total_tokens.toLocaleString() }}</div>
                <div class="text-[11px] text-slate-400 font-mono">In: {{ usage.today.input_tokens.toLocaleString() }} | Out: {{ usage.today.output_tokens.toLocaleString() }}</div>
            </div>

            <!-- Monthly Tokens -->
            <div class="p-5 rounded-xl bg-[#141622] border border-slate-800/80 space-y-2">
                <div class="text-xs text-slate-400 font-medium">Monthly Tokens</div>
                <div class="text-2xl font-bold font-mono text-blue-400">{{ (usage.month.total_tokens / 1000000).toFixed(2) }}M</div>
                <div class="text-[11px] text-slate-400 font-mono">Limit: {{ (usage.quota.monthly_token_limit / 1000000).toFixed(0) }}M ({{ usage.quota.monthly_usage_percentage }}%)</div>
            </div>

            <!-- Estimated Cost -->
            <div class="p-5 rounded-xl bg-[#141622] border border-slate-800/80 space-y-2">
                <div class="text-xs text-slate-400 font-medium">Estimated Usage Cost</div>
                <div class="text-2xl font-bold font-mono text-emerald-400">${{ usage.month.estimated_cost.toFixed(3) }}</div>
                <div class="text-[11px] text-slate-400">Today: ${{ usage.today.estimated_cost.toFixed(3) }}</div>
            </div>

            <!-- Total Requests -->
            <div class="p-5 rounded-xl bg-[#141622] border border-slate-800/80 space-y-2">
                <div class="text-xs text-slate-400 font-medium">AI Requests This Month</div>
                <div class="text-2xl font-bold font-mono text-purple-400">{{ usage.month.requests.toLocaleString() }}</div>
                <div class="text-[11px] text-slate-400">Today: {{ usage.today.requests }} requests</div>
            </div>
        </div>

        <!-- Recent Projects Section -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-white">Your Projects</h2>
                <router-link to="/projects" class="text-xs text-blue-400 hover:underline">View All Projects →</router-link>
            </div>

            <div v-if="projectStore.projects.length === 0" class="p-8 rounded-xl bg-[#141622] border border-slate-800 text-center space-y-3">
                <span class="text-3xl">📁</span>
                <p class="text-xs text-slate-400">You don't have any projects yet. Create one to organize your files and conversations.</p>
                <router-link to="/projects" class="inline-block px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-medium">Create First Project</router-link>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div
                    v-for="proj in projectStore.projects.slice(0, 3)"
                    :key="proj.id"
                    class="p-4 rounded-xl bg-[#141622] hover:bg-[#181b28] border border-slate-800/80 cursor-pointer transition-all space-y-2"
                    @click="openProject(proj.id)"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-white truncate">{{ proj.name }}</span>
                        <span class="text-xs text-blue-400">Open ↗</span>
                    </div>
                    <p class="text-xs text-slate-400 line-clamp-2">{{ proj.description || 'No description provided.' }}</p>
                    <div class="pt-2 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>{{ proj.files_count || 0 }} files</span>
                        <span>{{ proj.conversations_count || 0 }} chats</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent AI Requests Table (Requirement 24) -->
        <div v-if="usage && usage.recent_requests.length > 0" class="space-y-3">
            <h2 class="text-base font-semibold text-white">Recent AI Requests</h2>
            <div class="rounded-xl border border-slate-800 bg-[#141622] overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#181b28] text-slate-400 border-b border-slate-800 font-mono text-[11px]">
                        <tr>
                            <th class="p-3">Model</th>
                            <th class="p-3">Provider</th>
                            <th class="p-3">Tokens</th>
                            <th class="p-3">Cost (USD)</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 text-slate-300 font-mono">
                        <tr v-for="req in usage.recent_requests" :key="req.id" class="hover:bg-slate-800/30">
                            <td class="p-3 font-sans font-medium text-white">{{ req.model }}</td>
                            <td class="p-3 text-slate-400">{{ req.provider }}</td>
                            <td class="p-3">{{ req.total_tokens.toLocaleString() }}</td>
                            <td class="p-3 text-emerald-400">${{ req.estimated_cost.toFixed(4) }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px]" :class="req.status === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                                    {{ req.status }}
                                </span>
                            </td>
                            <td class="p-3 text-slate-400">{{ new Date(req.created_at).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useProjectStore } from '../../stores/project';
import { useChatStore } from '../../stores/chat';
import api from '../../api/client';

const router = useRouter();
const authStore = useAuthStore();
const projectStore = useProjectStore();
const chatStore = useChatStore();

const usage = ref<any>(null);

onMounted(async () => {
    await projectStore.fetchProjects();
    try {
        const res = await api.get('/usage');
        usage.value = res.data;
    } catch (err) {
        console.error('Failed to load usage', err);
    }
});

async function startNewChat() {
    await chatStore.createConversation();
    router.push('/workspace');
}

async function openProject(projectId: number) {
    await projectStore.selectProject(projectId);
    router.push('/workspace');
}
</script>
