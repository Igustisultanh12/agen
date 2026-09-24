<template>
    <div class="h-screen w-screen flex overflow-hidden bg-[#F8FAFC] text-[#334155] font-sans antialiased">
        <Sidebar v-if="authStore.isAuthenticated" />
        
        <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">
            <router-view />
        </main>

        <CommandPalette ref="cmdPaletteRef" />
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from './stores/auth';
import { useChatStore } from './stores/chat';
import Sidebar from './components/Sidebar.vue';
import CommandPalette from './components/CommandPalette.vue';

const authStore = useAuthStore();
const chatStore = useChatStore();
const cmdPaletteRef = ref<any>(null);

onMounted(async () => {
    if (authStore.isAuthenticated) {
        await authStore.fetchMe();
        await chatStore.fetchCatalog();
        await chatStore.fetchConversations();
    }
});
</script>
