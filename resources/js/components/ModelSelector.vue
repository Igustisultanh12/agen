<template>
    <div class="relative" ref="dropdownRef">
        <button
            type="button"
            class="flex items-center space-x-2 px-3.5 py-1.5 rounded-xl bg-white border border-[#E2E8F0] hover:border-slate-300 text-xs font-semibold text-slate-700 shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-[#2563EB]/20"
            @click="isOpen = !isOpen"
        >
            <span class="w-2 h-2 rounded-full" :class="selectedModelHealth === 'healthy' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
            <span class="truncate max-w-[150px]">{{ selectedModelName || 'Pilih Model AI' }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400 ml-1 transition-transform" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Dropdown Menu (Floating Upwards with high z-index and clean SISFOPERS KC style) -->
        <div
            v-if="isOpen"
            class="absolute bottom-full mb-2.5 left-0 z-[100] w-80 max-h-96 overflow-y-auto bg-white border border-[#E2E8F0] rounded-2xl shadow-2xl shadow-slate-400/30 p-2.5 space-y-2.5 text-slate-800"
        >
            <div class="px-2 pt-1 pb-0.5 border-b border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                <span>Model AI Tersedia</span>
                <span class="text-[10px] text-slate-400 font-normal">{{ providers.length }} Provider</span>
            </div>

            <div v-for="group in providers" :key="group.provider_slug" class="space-y-1">
                <div class="flex items-center justify-between px-2 pt-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                    <span>{{ group.provider_name }}</span>
                    <span
                        class="text-[9px] px-1.5 py-0.5 rounded-md font-semibold"
                        :class="group.provider_health === 'healthy' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-amber-50 text-amber-600 border border-amber-200'"
                    >
                        {{ group.provider_health }}
                    </span>
                </div>

                <div class="space-y-0.5">
                    <button
                        v-for="model in group.models"
                        :key="model.id"
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs transition-all text-left"
                        :class="modelValue === model.id ? 'bg-[#2563EB] text-white font-bold shadow-sm' : 'text-slate-700 hover:bg-slate-50'"
                        @click="select(model)"
                    >
                        <div class="truncate mr-2">
                            <div class="font-medium" :class="modelValue === model.id ? 'text-white' : 'text-slate-800'">{{ model.name }}</div>
                            <div class="text-[10px] font-mono mt-0.5" :class="modelValue === model.id ? 'text-blue-100' : 'text-slate-400'">
                                {{ model.category.toUpperCase() }} • {{ (model.context_window / 1000).toFixed(0) }}k ctx
                            </div>
                        </div>
                        <span v-if="model.pricing.is_free" class="text-[10px] px-2 py-0.5 rounded-md font-bold" :class="modelValue === model.id ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700'">GRATIS</span>
                        <span v-else class="text-[10px] font-mono" :class="modelValue === model.id ? 'text-blue-100' : 'text-slate-500'">${{ model.pricing.input_price_per_1m }}/1M</span>
                    </button>
                </div>
            </div>

            <div v-if="providers.length === 0" class="text-center py-4 text-xs text-slate-400 italic">
                Belum ada model aktif.
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
