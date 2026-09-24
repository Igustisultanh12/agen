<template>
    <div class="relative" ref="dropdownRef">
        <button
            type="button"
            class="flex items-center space-x-2 px-3.5 py-1.5 rounded-xl bg-white border border-[#E2E8F0] hover:border-slate-300 text-xs font-semibold text-slate-700 shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20"
            @click="isOpen = !isOpen"
        >
            <span class="text-base leading-none">🤖</span>
            <span class="truncate max-w-[130px]">{{ selectedAgentName || 'Pilih Coding Agent' }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400 ml-1 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Menu (Floating Upwards with high z-index and clean SISFOPERS KC style) -->
        <div
            v-if="isOpen"
            class="absolute bottom-full mb-2.5 left-0 z-[100] w-72 max-h-80 overflow-y-auto bg-white border border-[#E2E8F0] rounded-2xl shadow-2xl shadow-slate-400/30 p-2 space-y-1 text-slate-800"
        >
            <div class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                Active Coding Agent Harness
            </div>

            <button
                v-for="agent in agents"
                :key="agent.id"
                type="button"
                class="w-full flex flex-col px-3 py-2 rounded-xl text-xs transition-all text-left"
                :class="modelValue === agent.id ? 'bg-[#2563EB] text-white font-bold shadow-sm' : 'text-slate-700 hover:bg-slate-50'"
                @click="select(agent)"
            >
                <div class="flex items-center justify-between">
                    <span class="font-medium" :class="modelValue === agent.id ? 'text-white' : 'text-slate-900'">{{ agent.name }}</span>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded" :class="modelValue === agent.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'">{{ agent.harness_type }}</span>
                </div>
                <div class="text-[11px] truncate mt-0.5" :class="modelValue === agent.id ? 'text-blue-100' : 'text-slate-500'">{{ agent.description }}</div>
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
