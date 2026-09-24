<template>
    <div class="relative" ref="dropdownRef">
        <button
            type="button"
            class="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-[#1a1d27] border border-slate-700/60 hover:border-slate-500 text-xs text-slate-200 transition-colors focus:outline-none"
            @click="isOpen = !isOpen"
        >
            <span class="text-indigo-400">🤖</span>
            <span class="font-medium truncate max-w-[120px]">{{ selectedAgentName || 'Select Agent' }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400 ml-1 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute bottom-full mb-1 left-0 z-50 w-64 max-h-72 overflow-y-auto bg-[#161922] border border-slate-700 rounded-xl shadow-2xl p-1.5 space-y-0.5"
        >
            <div class="px-2 py-1 text-[10px] font-semibold uppercase text-slate-400">
                Active Coding Agent
            </div>

            <button
                v-for="agent in agents"
                :key="agent.id"
                type="button"
                class="w-full flex flex-col px-2.5 py-1.5 rounded-lg text-xs transition-colors text-left"
                :class="modelValue === agent.id ? 'bg-indigo-600 text-white font-medium' : 'text-slate-300 hover:bg-slate-800/80'"
                @click="select(agent)"
            >
                <div class="flex items-center justify-between">
                    <span>{{ agent.name }}</span>
                    <span class="text-[10px] font-mono opacity-60">{{ agent.harness_type }}</span>
                </div>
                <div class="text-[11px] opacity-70 truncate mt-0.5">{{ agent.description }}</div>
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { AgentOption } from '../stores/chat';

const props = defineProps<{
    modelValue: number | null;
    agents: AgentOption[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
    (e: 'change', agent: AgentOption): void;
}>();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const selectedAgent = computed(() => {
    return props.agents.find(a => a.id === props.modelValue) || null;
});

const selectedAgentName = computed(() => selectedAgent.value?.name);

function select(agent: AgentOption) {
    emit('update:modelValue', agent.id);
    emit('change', agent);
    isOpen.value = false;
}

function handleClickOutside(e: MouseEvent) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>
