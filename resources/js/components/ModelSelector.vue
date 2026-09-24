<template>
    <div class="relative" ref="dropdownRef">
        <button
            type="button"
            class="flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-[#1a1d27] border border-slate-700/60 hover:border-slate-500 text-xs text-slate-200 transition-colors focus:outline-none"
            @click="isOpen = !isOpen"
        >
            <span class="w-2 h-2 rounded-full" :class="selectedModelHealth === 'healthy' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
            <span class="font-medium truncate max-w-[140px]">{{ selectedModelName || 'Select Model' }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400 ml-1 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute bottom-full mb-1 left-0 z-50 w-72 max-h-80 overflow-y-auto bg-[#161922] border border-slate-700 rounded-xl shadow-2xl p-2 space-y-3"
        >
            <div v-for="group in providers" :key="group.provider_slug" class="space-y-1">
                <div class="flex items-center justify-between px-2 pt-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                    <span>{{ group.provider_name }}</span>
                    <span
                        class="text-[9px] px-1.5 py-0.5 rounded font-mono"
                        :class="group.provider_health === 'healthy' ? 'bg-emerald-950 text-emerald-400' : 'bg-amber-950 text-amber-400'"
                    >
                        {{ group.provider_health }}
                    </span>
                </div>

                <div class="space-y-0.5">
                    <button
                        v-for="model in group.models"
                        :key="model.id"
                        type="button"
                        class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs transition-colors text-left"
                        :class="modelValue === model.id ? 'bg-blue-600 text-white font-medium' : 'text-slate-300 hover:bg-slate-800/80'"
                        @click="select(model)"
                    >
                        <div class="truncate mr-2">
                            <div>{{ model.name }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ model.category.toUpperCase() }} • {{ (model.context_window / 1000).toFixed(0) }}k ctx</div>
                        </div>
                        <span v-if="model.pricing.is_free" class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300">FREE</span>
                        <span v-else class="text-[10px] text-slate-400 font-mono">${{ model.pricing.input_price_per_1m }}/1M</span>
                    </button>
                </div>
            </div>

            <div v-if="providers.length === 0" class="text-center py-4 text-xs text-slate-500">
                No active models available.
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { ProviderGroup, ModelOption } from '../stores/chat';

const props = defineProps<{
    modelValue: number | null;
    providers: ProviderGroup[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
    (e: 'change', model: ModelOption): void;
}>();

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const currentSelected = computed(() => {
    for (const group of props.providers) {
        const found = group.models.find(m => m.id === props.modelValue);
        if (found) return { model: found, group };
    }
    return null;
});

const selectedModelName = computed(() => currentSelected.value?.model.name);
const selectedModelHealth = computed(() => currentSelected.value?.group.provider_health || 'healthy');

function select(model: ModelOption) {
    emit('update:modelValue', model.id);
    emit('change', model);
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
