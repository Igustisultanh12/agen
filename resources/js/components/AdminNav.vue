<template>
    <div class="border-b border-[#E2E8F0] pb-5 mb-6 select-none">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <div class="flex items-center space-x-2.5">
                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase rounded-lg bg-[#2563EB]/10 text-[#2563EB] border border-[#2563EB]/20 tracking-wider">
                        Admin Portal
                    </span>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ title }}</h1>
                </div>
                <p class="text-xs text-slate-500 mt-1.5">{{ subtitle }}</p>
            </div>
            <div class="flex items-center space-x-2">
                <router-link
                    to="/workspace"
                    class="px-4 py-2 rounded-xl bg-white border border-[#E2E8F0] hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all shadow-xs flex items-center space-x-2"
                >
                    <span>⚡</span>
                    <span>Kembali ke Workspace</span>
                </router-link>
            </div>
        </div>

        <!-- Horizontal Nav Tabs (SISFOPERS KC style) -->
        <div class="flex items-center space-x-1.5 overflow-x-auto pb-1 text-xs scrollbar-thin">
            <router-link
                v-for="item in navItems"
                :key="item.path"
                :to="item.path"
                class="px-3.5 py-2 rounded-xl font-bold whitespace-nowrap transition-all shadow-xs"
                :class="$route.path === item.path ? 'bg-[#2563EB] text-white shadow-blue-500/20' : 'bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-[#E2E8F0]'"
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
    { label: 'AI Providers', path: '/admin/providers', icon: '🔌' },
    { label: 'Models & Pricing', path: '/admin/models', icon: '🧠' },
    { label: 'Coding Agents', path: '/admin/agents', icon: '🤖' },
    { label: 'Usage Logs', path: '/admin/usage', icon: '📈' },
    { label: 'Audit Trail', path: '/admin/audit-logs', icon: '🛡️' },
    { label: 'Settings', path: '/admin/settings', icon: '⚙️' },
];
</script>
