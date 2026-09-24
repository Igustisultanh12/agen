<template>
    <div class="relative w-full h-full flex flex-col bg-[#14161f]">
        <!-- Editor Header Bar -->
        <div class="flex items-center justify-between px-3 py-1.5 bg-[#1a1d27] border-b border-slate-800 text-xs text-slate-300">
            <div class="flex items-center space-x-2">
                <span class="text-blue-400 font-mono">{{ filename || 'Untitled' }}</span>
                <span v-if="isDirty" class="w-2 h-2 rounded-full bg-amber-400" title="Unsaved changes"></span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-slate-500 font-mono">{{ detectedLanguage }}</span>
                <button
                    class="px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-500 text-white font-medium flex items-center space-x-1 transition-colors"
                    :disabled="isSaving"
                    @click="triggerSave"
                >
                    <span v-if="isSaving">Saving...</span>
                    <span v-else>Save (Ctrl+S)</span>
                </button>
            </div>
        </div>

        <!-- Monaco Container -->
        <div ref="editorContainer" class="flex-1 w-full h-full"></div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import * as monaco from 'monaco-editor';

const props = defineProps<{
    modelValue: string;
    filename?: string;
    isSaving?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'save', value: string): void;
}>();

const editorContainer = ref<HTMLElement | null>(null);
let editorInstance: monaco.editor.IStandaloneCodeEditor | null = null;
const isDirty = ref(false);

const detectedLanguage = computed(() => {
    if (!props.filename) return 'javascript';
    const ext = props.filename.split('.').pop()?.toLowerCase();
    switch (ext) {
        case 'ts':
        case 'tsx':
            return 'typescript';
        case 'js':
        case 'jsx':
        case 'mjs':
            return 'javascript';
        case 'vue':
        case 'html':
            return 'html';
        case 'css':
            return 'css';
        case 'json':
            return 'json';
        case 'php':
            return 'php';
        case 'py':
            return 'python';
        case 'sql':
            return 'sql';
        case 'md':
            return 'markdown';
        case 'yaml':
        case 'yml':
            return 'yaml';
        default:
            return 'plaintext';
    }
});

onMounted(() => {
    if (!editorContainer.value) return;

    editorInstance = monaco.editor.create(editorContainer.value, {
        value: props.modelValue || '',
        language: detectedLanguage.value,
        theme: 'vs-dark',
        fontSize: 13,
        lineNumbers: 'on',
        minimap: { enabled: true },
        automaticLayout: true,
        scrollBeyondLastLine: false,
        padding: { top: 12 },
        wordWrap: 'on',
    });

    editorInstance.onDidChangeModelContent(() => {
        const val = editorInstance?.getValue() || '';
        isDirty.value = true;
        emit('update:modelValue', val);
    });

    // Save keybinding Ctrl+S
    editorInstance.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
        triggerSave();
    });
});

watch(() => props.modelValue, (newVal) => {
    if (editorInstance && newVal !== editorInstance.getValue()) {
        editorInstance.setValue(newVal);
        isDirty.value = false;
    }
});

watch(() => props.filename, () => {
    if (editorInstance) {
        const model = editorInstance.getModel();
        if (model) {
            monaco.editor.setModelLanguage(model, detectedLanguage.value);
        }
    }
});

function triggerSave() {
    if (!editorInstance) return;
    const val = editorInstance.getValue();
    emit('save', val);
    isDirty.value = false;
}

onUnmounted(() => {
    if (editorInstance) {
        editorInstance.dispose();
        editorInstance = null;
    }
});
</script>
