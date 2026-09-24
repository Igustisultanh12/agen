<template>
    <div class="border-b border-slate-800 pb-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 text-[11px] font-bold uppercase rounded bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Admin Portal
                    </span>
                    <h1 class="text-xl font-bold text-white tracking-tight">{{ title }}</h1>
                </div>
                <p class="text-xs text-slate-400 mt-1">{{ subtitle }}</p>
            </div>
            <div class="flex items-center space-x-2">
                <router-link
                    to="/workspace"
                    class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition-colors flex items-center space-x-1"
                >
                    <span>⚡</span>
                    <span>Workspace</span>
                </router-link>
            </div>
        </div>

        <!-- Horizontal Nav Tabs -->
        <div class="flex items-center space-x-1 overflow-x-auto pb-1 text-xs scrollbar-thin">
            <router-link
                v-for="item in navItems"
                :key="item.path"
                :to="item.path"
                class="px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition-colors"
                :class="$route.path === item.path ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30 font-semibold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'"
            >
                <span class="mr-1.5">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </router-link>
        </div>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    title: string;
    subtitle: string;
}>();

const navItems = [
    { label: 'Overview', path: '/admin', icon: '📊' },
    { label: 'Users', path: '/admin/users', icon: '👥' },
    { label: 'Plans & Groups', path: '/admin/user-groups', icon: '🏷️' },
    { label: 'Providers', path: '/admin/providers', icon: '🔌' },
    { label: 'Models & Pricing', path: '/admin/models', icon: '🧠' },
    { label: 'Coding Agents', path: '/admin/agents', icon: '🤖' },
    { label: 'Usage Logs', path: '/admin/usage', icon: '📈' },
    { label: 'Audit Trail', path: '/admin/audit-logs', icon: '🛡️' },
    { label: 'Settings', path: '/admin/settings', icon: '⚙️' },
];
</script>
