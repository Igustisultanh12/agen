<template>
    <aside
        :class="[
            'flex flex-col bg-[#12141c] border-r border-slate-800 transition-all duration-300 z-20 select-none',
            isCollapsed ? 'w-16' : 'w-64'
        ]"
    >
        <!-- Top App Brand -->
        <div class="h-14 flex items-center justify-between px-4 border-b border-slate-800/80">
            <div v-if="!isCollapsed" class="flex items-center space-x-2.5 overflow-hidden">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 via-indigo-500 to-purple-500 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <span class="text-white font-bold text-sm">AG</span>
                </div>
                <div class="truncate">
                    <div class="text-sm font-semibold text-slate-100 tracking-tight">Antigravity</div>
                    <div class="text-[10px] text-slate-400 font-mono">FCC Engine</div>
                </div>
            </div>
            <div v-else class="mx-auto">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 via-indigo-500 to-purple-500 flex items-center justify-center">
                    <span class="text-white font-bold text-sm">AG</span>
                </div>
            </div>

            <button
                type="button"
                class="p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition-colors"
                @click="isCollapsed = !isCollapsed"
                title="Toggle sidebar"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- New Chat Button -->
        <div class="p-3">
            <button
                type="button"
                class="w-full flex items-center justify-center space-x-2 py-2 px-3 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium transition-all shadow-md shadow-blue-600/20"
                @click="handleNewChat"
            >
                <span>+</span>
                <span v-if="!isCollapsed">New Chat</span>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 overflow-y-auto px-2 space-y-1">
            <router-link
                to="/workspace"
                class="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
                active-class="bg-slate-800 text-blue-400 font-medium"
            >
                <span class="text-base">⚡</span>
                <span v-if="!isCollapsed">Workspace</span>
            </router-link>

            <router-link
                to="/dashboard"
                class="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
                active-class="bg-slate-800 text-blue-400 font-medium"
            >
                <span class="text-base">📊</span>
                <span v-if="!isCollapsed">Dashboard</span>
            </router-link>

            <router-link
                to="/projects"
                class="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
                active-class="bg-slate-800 text-blue-400 font-medium"
            >
                <span class="text-base">📁</span>
                <span v-if="!isCollapsed">Projects</span>
            </router-link>

            <router-link
                to="/usage"
                class="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
                active-class="bg-slate-800 text-blue-400 font-medium"
            >
                <span class="text-base">📈</span>
                <span v-if="!isCollapsed">Usage & Quota</span>
            </router-link>

            <router-link
                to="/api-keys"
                class="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
                active-class="bg-slate-800 text-blue-400 font-medium"
            >
                <span class="text-base">🔑</span>
                <span v-if="!isCollapsed">API Keys</span>
            </router-link>

            <router-link
                to="/settings"
                class="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
                active-class="bg-slate-800 text-blue-400 font-medium"
            >
                <span class="text-base">⚙️</span>
                <span v-if="!isCollapsed">Settings</span>
            </router-link>

            <!-- Admin Nav Section (Requirement 42) -->
            <div v-if="authStore.isAdmin" class="pt-3">
                <div v-if="!isCollapsed" class="px-3 pb-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                    Administrator
                </div>
                <router-link
                    to="/admin"
                    class="flex items-center space-x-3 px-3 py-2 rounded-lg text-sm text-amber-400 hover:bg-amber-950/40 hover:text-amber-300 transition-colors"
                    active-class="bg-amber-950/60 text-amber-300 font-medium border border-amber-500/20"
                >
                    <span class="text-base">🛡️</span>
                    <span v-if="!isCollapsed">Admin Control</span>
                </router-link>
            </div>

            <!-- Recent Conversations List -->
            <div v-if="!isCollapsed && chatStore.conversations.length > 0" class="pt-3">
                <div class="px-3 pb-1 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                    Recent Chats
                </div>
                <div class="space-y-0.5 max-h-48 overflow-y-auto">
                    <button
                        v-for="conv in chatStore.conversations.slice(0, 10)"
                        :key="conv.id"
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-1.5 rounded-md text-xs transition-colors text-left"
                        :class="chatStore.currentConversation?.id === conv.id ? 'bg-slate-800 text-blue-400 font-medium' : 'text-slate-400 hover:bg-slate-800/40 hover:text-slate-200'"
                        @click="selectChat(conv.id)"
                    >
                        <span class="truncate">{{ conv.title }}</span>
                    </button>
                </div>
            </div>
        </nav>

        <!-- Quota Progress Bar (Requirement 10) -->
        <div v-if="!isCollapsed && authStore.quota" class="p-3 mx-2 my-2 rounded-xl bg-[#161922] border border-slate-800">
            <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
                <span>Monthly Quota</span>
                <span class="font-mono text-slate-200 font-medium">{{ authStore.quota.monthly_usage_percentage }}%</span>
            </div>
            <div class="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
                <div
                    class="h-full transition-all duration-500"
                    :class="authStore.quota.monthly_usage_percentage > 90 ? 'bg-red-500' : (authStore.quota.monthly_usage_percentage > 75 ? 'bg-amber-500' : 'bg-blue-500')"
                    :style="{ width: `${Math.min(100, authStore.quota.monthly_usage_percentage)}%` }"
                ></div>
            </div>
            <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1 font-mono">
                <span>{{ (authStore.quota.monthly_used_tokens / 1000000).toFixed(2) }}M</span>
                <span>/ {{ (authStore.quota.monthly_token_limit / 1000000).toFixed(0) }}M tokens</span>
            </div>
        </div>

        <!-- User profile footer -->
        <div class="p-3 border-t border-slate-800 flex items-center justify-between">
            <div v-if="!isCollapsed" class="flex items-center space-x-2.5 overflow-hidden">
                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-300 uppercase">
                    {{ authStore.user?.name.charAt(0) || 'U' }}
                </div>
                <div class="truncate">
                    <div class="text-xs font-medium text-slate-200 truncate">{{ authStore.user?.name }}</div>
                    <div class="text-[10px] text-slate-400 truncate">{{ authStore.user?.role?.toUpperCase() }}</div>
                </div>
            </div>

            <button
                type="button"
                class="p-1.5 rounded hover:bg-red-500/20 text-slate-400 hover:text-red-400 transition-colors"
                title="Logout"
                @click="handleLogout"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </button>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useChatStore } from '../stores/chat';

const router = useRouter();
const authStore = useAuthStore();
const chatStore = useChatStore();

const isCollapsed = ref(false);

async function handleNewChat() {
    await chatStore.createConversation();
    router.push('/workspace');
}

async function selectChat(convId: number) {
    await chatStore.selectConversation(convId);
    router.push('/workspace');
}

async function handleLogout() {
    await authStore.logout();
    router.push('/login');
}
</script>
