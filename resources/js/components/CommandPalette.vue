<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-start justify-center pt-20 bg-black/60 backdrop-blur-sm" @click.self="close">
        <div class="w-full max-w-xl bg-[#161922] border border-slate-700/60 rounded-xl shadow-2xl overflow-hidden flex flex-col">
            <!-- Search bar -->
            <div class="flex items-center px-4 py-3 border-b border-slate-800">
                <svg class="w-5 h-5 text-slate-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    ref="inputRef"
                    v-model="query"
                    type="text"
                    placeholder="Type a command or search (e.g. New Chat, Project, Settings)..."
                    class="w-full bg-transparent text-sm text-slate-200 placeholder-slate-500 focus:outline-none"
                    @keydown.down.prevent="navigate(1)"
                    @keydown.up.prevent="navigate(-1)"
                    @keydown.enter.prevent="selectCurrent"
                    @keydown.esc.prevent="close"
                />
                <span class="text-xs text-slate-500 bg-slate-800 px-2 py-0.5 rounded border border-slate-700">ESC</span>
            </div>

            <!-- Command items -->
            <div class="max-h-80 overflow-y-auto p-2 space-y-1">
                <button
                    v-for="(cmd, index) in filteredCommands"
                    :key="cmd.id"
                    :class="[
                        'w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm transition-colors text-left',
                        selectedIndex === index ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30' : 'text-slate-300 hover:bg-slate-800/60'
                    ]"
                    @click="execute(cmd)"
                >
                    <div class="flex items-center space-x-3">
                        <span class="text-slate-400">{{ cmd.icon }}</span>
                        <span>{{ cmd.title }}</span>
                    </div>
                    <span class="text-xs text-slate-500 font-mono">{{ cmd.shortcut }}</span>
                </button>

                <div v-if="filteredCommands.length === 0" class="text-center py-6 text-sm text-slate-500">
                    No commands match "{{ query }}"
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useChatStore } from '../stores/chat';

const router = useRouter();
const chatStore = useChatStore();

const isOpen = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

const commands = [
    { id: 'new-chat', title: 'New Chat', icon: '💬', shortcut: 'Ctrl+N', action: () => { chatStore.createConversation(); router.push('/workspace'); } },
    { id: 'workspace', title: 'Open AI Workspace', icon: '⚡', shortcut: 'Alt+W', action: () => router.push('/workspace') },
    { id: 'projects', title: 'Open Projects', icon: '📁', shortcut: 'Alt+P', action: () => router.push('/projects') },
    { id: 'usage', title: 'View Token Usage & Quota', icon: '📊', shortcut: 'Alt+U', action: () => router.push('/usage') },
    { id: 'api-keys', title: 'Manage API Keys', icon: '🔑', shortcut: 'Alt+K', action: () => router.push('/api-keys') },
    { id: 'settings', title: 'User Settings & Instructions', icon: '⚙️', shortcut: 'Alt+S', action: () => router.push('/settings') },
    { id: 'admin', title: 'Open Admin Dashboard', icon: '🛡️', shortcut: 'Alt+A', action: () => router.push('/admin') },
];

const filteredCommands = computed(() => {
    if (!query.value.trim()) return commands;
    const q = query.value.toLowerCase();
    return commands.filter(c => c.title.toLowerCase().includes(q));
});

function navigate(direction: number) {
    if (filteredCommands.value.length === 0) return;
    selectedIndex.value = (selectedIndex.value + direction + filteredCommands.value.length) % filteredCommands.value.length;
}

function selectCurrent() {
    if (filteredCommands.value[selectedIndex.value]) {
        execute(filteredCommands.value[selectedIndex.value]);
    }
}

function execute(cmd: any) {
    close();
    cmd.action();
}

function open() {
    isOpen.value = true;
    query.value = '';
    selectedIndex.value = 0;
    nextTick(() => inputRef.value?.focus());
}

function close() {
    isOpen.value = false;
}

function handleKeydown(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        if (isOpen.value) close();
        else open();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

defineExpose({ open, close });
</script>
