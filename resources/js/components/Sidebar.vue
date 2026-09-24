<template>
    <aside
        :class="[
            'flex flex-col bg-white border-r border-[#E2E8F0] transition-all duration-300 z-20 select-none shadow-sm',
            isCollapsed ? 'w-18' : 'w-72'
        ]"
    >
        <!-- Top App Brand (SISFOPERS KC style) -->
        <div class="pt-6 pb-4 px-6 flex items-center justify-between border-b border-[#E2E8F0]">
            <div v-if="!isCollapsed" class="flex flex-col select-none overflow-hidden">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-[#2563EB]/10 flex items-center justify-center font-black text-sm text-[#2563EB] select-none shadow-sm shrink-0">
                        AG
                    </div>
                    <div class="text-xl font-extrabold tracking-tight text-slate-900 truncate">
                        ANTIGRAVITY<span class="text-[#2563EB]">.</span>
                    </div>
                </div>
                <span class="text-[9px] font-bold tracking-wider text-[#94A3B8] uppercase mt-1.5 truncate">
                    AI CODING WORKSPACE • SISFOPERS KC
                </span>
            </div>
            <div v-else class="mx-auto">
                <div class="w-8 h-8 rounded-xl bg-[#2563EB]/10 flex items-center justify-center font-black text-sm text-[#2563EB]">
                    AG
                </div>
            </div>

            <button
                type="button"
                class="p-1.5 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
                @click="isCollapsed = !isCollapsed"
                title="Toggle sidebar"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- New Chat Button -->
        <div class="p-4">
            <button
                type="button"
                class="w-full flex items-center justify-center space-x-2 py-3 px-4 rounded-2xl bg-[#2563EB] hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm shadow-blue-500/20"
                @click="handleNewChat"
            >
                <span class="text-base font-bold">+</span>
                <span v-if="!isCollapsed">Buat Chat Baru</span>
            </button>
        </div>

        <!-- Navigation Links (SISFOPERS KC styled) -->
        <nav class="flex-1 overflow-y-auto px-4 py-1 space-y-1.5">
            <router-link
                to="/workspace"
                class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] transition duration-150"
                active-class="bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]"
                :class="$route.path === '/workspace' ? '' : 'text-[#64748B] hover:text-slate-800 hover:bg-slate-50 font-medium'"
            >
                <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span v-if="!isCollapsed">Workspace AI</span>
            </router-link>

            <router-link
                to="/dashboard"
                class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] transition duration-150"
                active-class="bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]"
                :class="$route.path === '/dashboard' ? '' : 'text-[#64748B] hover:text-slate-800 hover:bg-slate-50 font-medium'"
            >
                <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span v-if="!isCollapsed">Dashboard</span>
            </router-link>

            <router-link
                to="/projects"
                class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] transition duration-150"
                active-class="bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]"
                :class="$route.path === '/projects' ? '' : 'text-[#64748B] hover:text-slate-800 hover:bg-slate-50 font-medium'"
            >
                <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
                <span v-if="!isCollapsed">Project & Repos</span>
            </router-link>

            <router-link
                to="/usage"
                class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] transition duration-150"
                active-class="bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]"
                :class="$route.path === '/usage' ? '' : 'text-[#64748B] hover:text-slate-800 hover:bg-slate-50 font-medium'"
            >
                <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span v-if="!isCollapsed">Usage & Kuota</span>
            </router-link>

            <router-link
                to="/api-keys"
                class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] transition duration-150"
                active-class="bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]"
                :class="$route.path === '/api-keys' ? '' : 'text-[#64748B] hover:text-slate-800 hover:bg-slate-50 font-medium'"
            >
                <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                <span v-if="!isCollapsed">API Keys</span>
            </router-link>

            <router-link
                to="/settings"
                class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] transition duration-150"
                active-class="bg-[#2563EB]/5 text-[#2563EB] font-bold shadow-sm shadow-blue-500/[0.02]"
                :class="$route.path === '/settings' ? '' : 'text-[#64748B] hover:text-slate-800 hover:bg-slate-50 font-medium'"
            >
                <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <circle cx="12" cy="12" r="3" />
                </svg>
                <span v-if="!isCollapsed">Pengaturan</span>
            </router-link>

            <!-- Admin Nav Section (SISFOPERS KC styled) -->
            <div v-if="authStore.isAdmin" class="pt-4 border-t border-slate-100 mt-3">
                <div v-if="!isCollapsed" class="px-4 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    Administrator
                </div>
                <router-link
                    to="/admin"
                    class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-[14px] transition duration-150"
                    active-class="bg-[#2563EB]/5 text-[#2563EB] font-bold"
                    :class="$route.path.startsWith('/admin') ? 'bg-[#2563EB]/5 text-[#2563EB] font-bold' : 'text-[#64748B] hover:text-slate-800 hover:bg-slate-50 font-medium'"
                >
                    <svg class="w-5 h-5 opacity-80 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span v-if="!isCollapsed">Admin Control</span>
                </router-link>
            </div>

            <!-- Recent Conversations List -->
            <div v-if="!isCollapsed && chatStore.conversations.length > 0" class="pt-4 border-t border-slate-100 mt-3">
                <div class="px-4 pb-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    Riwayat Chat
                </div>
                <div class="space-y-1 max-h-48 overflow-y-auto px-1">
                    <button
                        v-for="conv in chatStore.conversations.slice(0, 10)"
                        :key="conv.id"
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-colors text-left"
                        :class="chatStore.currentConversation?.id === conv.id ? 'bg-[#2563EB]/10 text-[#2563EB] font-semibold' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800'"
                        @click="selectChat(conv.id)"
                    >
                        <span class="truncate">{{ conv.title }}</span>
                    </button>
                </div>
            </div>
        </nav>

        <!-- Quota Progress Card (SISFOPERS KC style) -->
        <div v-if="!isCollapsed && authStore.quota" class="p-3.5 mx-4 my-2 rounded-2xl bg-slate-50 border border-slate-200/80 shadow-sm">
            <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5 font-medium">
                <span>Kuota Bulanan</span>
                <span class="font-bold text-[#2563EB]">{{ authStore.quota.monthly_usage_percentage }}%</span>
            </div>
            <div class="w-full h-2 bg-slate-200/80 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="authStore.quota.monthly_usage_percentage > 90 ? 'bg-red-500' : (authStore.quota.monthly_usage_percentage > 75 ? 'bg-amber-500' : 'bg-[#2563EB]')"
                    :style="{ width: `${Math.min(100, authStore.quota.monthly_usage_percentage)}%` }"
                ></div>
            </div>
            <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1.5 font-mono">
                <span>{{ (authStore.quota.monthly_used_tokens / 1000000).toFixed(2) }}M</span>
                <span>/ {{ (authStore.quota.monthly_token_limit / 1000000).toFixed(0) }}M tokens</span>
            </div>
        </div>

        <!-- User profile footer (SISFOPERS KC style) -->
        <div class="p-4 border-t border-[#E2E8F0] bg-slate-50/50">
            <div v-if="!isCollapsed" class="flex items-center gap-3 mb-2 p-1.5 rounded-xl hover:bg-slate-100/80 transition cursor-pointer">
                <div class="w-10 h-10 rounded-xl bg-[#2563EB]/10 flex items-center justify-center font-bold text-xs text-[#2563EB] uppercase select-none shrink-0">
                    {{ authStore.user?.name ? authStore.user.name.substring(0, 2).toUpperCase() : 'AG' }}
                </div>
                <div class="text-xs truncate flex-1" v-if="authStore.user">
                    <p class="font-bold text-slate-800 truncate">
                        {{ authStore.user.name }}
                    </p>
                    <p class="text-slate-400 truncate text-[11px] mt-0.5">
                        {{ authStore.user.email }}
                    </p>
                </div>
            </div>

            <button
                type="button"
                @click="handleLogout"
                class="w-full py-2 px-3 text-center text-xs text-[#EF4444] hover:bg-red-50 rounded-xl font-bold border border-transparent hover:border-red-100/50 transition duration-150 cursor-pointer"
            >
                Keluar Sistem
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
