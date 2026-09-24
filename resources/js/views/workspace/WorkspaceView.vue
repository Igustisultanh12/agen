<template>
    <div class="h-full w-full flex flex-col overflow-hidden bg-[#0d0f16]">
        <!-- Top Workspace Bar -->
        <header class="h-12 border-b border-slate-800 bg-[#12141d] px-4 flex items-center justify-between text-xs text-slate-300">
            <div class="flex items-center space-x-3">
                <!-- Project Selector or Info -->
                <div class="flex items-center space-x-1.5 px-2.5 py-1 rounded bg-[#181b26] border border-slate-700/60">
                    <span class="text-blue-400">📁</span>
                    <span class="font-medium text-slate-200">{{ projectStore.currentProject?.name || 'Global Scratchpad' }}</span>
                </div>

                <!-- Active Conversation Title -->
                <div class="flex items-center space-x-2 text-slate-400">
                    <span>/</span>
                    <span class="font-medium text-slate-200 truncate max-w-xs">{{ chatStore.currentConversation?.title || 'New Conversation' }}</span>
                </div>
            </div>

            <!-- Top Right: Session Usage Badges & Editor Toggle -->
            <div class="flex items-center space-x-3">
                <!-- Session Tokens & Estimated Cost -->
                <div v-if="sessionTotalTokens > 0" class="flex items-center space-x-2 px-2.5 py-1 rounded-md bg-[#181b26] border border-slate-800 text-[11px] font-mono text-slate-400">
                    <span>⚡ {{ sessionTotalTokens.toLocaleString() }} tokens</span>
                    <span class="text-slate-600">|</span>
                    <span class="text-emerald-400 font-medium">${{ sessionEstimatedCost.toFixed(4) }} est.</span>
                </div>

                <!-- Toggle Code Editor Panel -->
                <button
                    type="button"
                    class="px-2.5 py-1 rounded border text-xs font-medium flex items-center space-x-1.5 transition-colors"
                    :class="showEditorPanel ? 'bg-blue-600/20 text-blue-400 border-blue-500/40' : 'bg-[#181b26] text-slate-300 border-slate-700 hover:text-white'"
                    @click="showEditorPanel = !showEditorPanel"
                >
                    <span>💻</span>
                    <span>Code Editor</span>
                </button>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Center Chat Panel -->
            <div class="flex-1 flex flex-col min-w-0 bg-[#0f1118]">
                <!-- Messages Stream View -->
                <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-6">
                    <!-- Welcome Hero if Empty -->
                    <div v-if="chatStore.messages.length === 0 && !chatStore.isStreaming" class="h-full flex flex-col items-center justify-center text-center p-8 space-y-4 max-w-md mx-auto">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center shadow-xl shadow-blue-500/20">
                            <span class="text-2xl">🤖</span>
                        </div>
                        <h2 class="text-lg font-bold text-white tracking-tight">AI Coding Workspace</h2>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Ask questions, build features, fix bugs, or request code modifications.
                            Equipped with Free Claude Code (FCC) models and Monaco code editor.
                        </p>
                        <div class="grid grid-cols-2 gap-2 w-full pt-2">
                            <button
                                v-for="prompt in starterPrompts"
                                :key="prompt"
                                class="p-2.5 text-left rounded-lg bg-[#161924] hover:bg-[#1e2230] border border-slate-800 text-xs text-slate-300 transition-colors"
                                @click="inputPrompt = prompt"
                            >
                                {{ prompt }}
                            </button>
                        </div>
                    </div>

                    <!-- Messages list -->
                    <div
                        v-for="(msg, index) in chatStore.messages"
                        :key="index"
                        class="flex space-x-3 text-sm max-w-4xl mx-auto w-full"
                        :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                    >
                        <!-- AI Avatar -->
                        <div v-if="msg.role !== 'user'" class="w-7 h-7 rounded-lg bg-indigo-600/30 border border-indigo-500/30 flex items-center justify-center flex-shrink-0 text-indigo-400 text-xs font-bold mt-1">
                            AI
                        </div>

                        <!-- Message Body -->
                        <div
                            class="rounded-xl px-4 py-3 max-w-[85%] border transition-all"
                            :class="msg.role === 'user'
                                ? 'bg-blue-600 text-white border-blue-500/40 rounded-br-none shadow-md shadow-blue-600/10'
                                : 'bg-[#151822] text-slate-200 border-slate-800 rounded-bl-none shadow-md shadow-black/20'"
                        >
                            <div v-if="msg.role === 'user'" class="whitespace-pre-wrap leading-relaxed">{{ msg.content }}</div>
                            <div v-else>
                                <MarkdownMessage :content="msg.content" @apply-code="handleApplyCode" />

                                <!-- Metadata footer for AI turns -->
                                <div v-if="msg.total_tokens" class="mt-2 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[10px] text-slate-400 font-mono">
                                    <span>Model: {{ msg.model || 'FCC' }}</span>
                                    <div class="flex items-center space-x-2">
                                        <span>{{ msg.total_tokens }} tokens</span>
                                        <span v-if="msg.estimated_cost">• ${{ msg.estimated_cost.toFixed(4) }}</span>
                                        <span v-if="msg.duration_ms">• {{ (msg.duration_ms / 1000).toFixed(1) }}s</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Avatar -->
                        <div v-if="msg.role === 'user'" class="w-7 h-7 rounded-lg bg-blue-600 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold mt-1">
                            U
                        </div>
                    </div>

                    <!-- Streaming Assistant Message Bubble -->
                    <div v-if="chatStore.isStreaming" class="flex space-x-3 text-sm max-w-4xl mx-auto w-full justify-start">
                        <div class="w-7 h-7 rounded-lg bg-indigo-600/30 border border-indigo-500/30 flex items-center justify-center flex-shrink-0 text-indigo-400 text-xs font-bold mt-1 animate-pulse">
                            AI
                        </div>
                        <div class="rounded-xl px-4 py-3 max-w-[85%] border bg-[#151822] text-slate-200 border-slate-800 rounded-bl-none">
                            <MarkdownMessage :content="chatStore.streamingContent" @apply-code="handleApplyCode" />
                            <span class="cursor-blink ml-1"></span>
                        </div>
                    </div>
                </div>

                <!-- Attached File Pills in Context -->
                <div v-if="attachedFiles.length > 0" class="px-4 py-2 bg-[#12141e] border-t border-slate-800/80 flex items-center space-x-2 overflow-x-auto text-xs">
                    <span class="text-slate-400 text-[11px] font-mono">Context Files:</span>
                    <div
                        v-for="file in attachedFiles"
                        :key="file.id"
                        class="flex items-center space-x-1.5 px-2.5 py-1 rounded bg-[#1a1e2b] border border-slate-700 text-slate-300"
                    >
                        <span>📄 {{ file.name }}</span>
                        <button type="button" class="text-slate-500 hover:text-red-400 ml-1" @click="detachFile(file.id)">×</button>
                    </div>
                </div>

                <!-- Chat Input Controls Bar -->
                <div class="p-4 bg-[#12141d] border-t border-slate-800">
                    <div class="max-w-4xl mx-auto bg-[#181b26] border border-slate-700/80 rounded-xl shadow-xl overflow-hidden focus-within:border-blue-500 transition-colors">
                        <!-- Textarea -->
                        <textarea
                            ref="textareaRef"
                            v-model="inputPrompt"
                            rows="3"
                            placeholder="Ask AI anything, generate code, refactor... (Ctrl+Enter to send)"
                            class="w-full bg-transparent px-4 py-3 text-sm text-slate-100 placeholder-slate-500 focus:outline-none resize-none font-sans leading-relaxed"
                            @keydown.ctrl.enter.prevent="handleSend"
                            @keydown.meta.enter.prevent="handleSend"
                            @keydown.esc.prevent="handleEsc"
                        ></textarea>

                        <!-- Bottom Controls -->
                        <div class="px-3 py-2 bg-[#141620] border-t border-slate-800/60 flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center space-x-2">
                                <!-- Model Selector -->
                                <ModelSelector
                                    v-model="chatStore.selectedModelId"
                                    :providers="chatStore.providers"
                                />

                                <!-- Agent Selector -->
                                <AgentSelector
                                    v-model="chatStore.selectedAgentId"
                                    :agents="chatStore.agents"
                                />

                                <!-- Attach File Button -->
                                <button
                                    v-if="projectStore.currentProject"
                                    type="button"
                                    class="flex items-center space-x-1 px-2.5 py-1.5 rounded-lg bg-[#1a1d27] border border-slate-700/60 hover:border-slate-500 text-xs text-slate-300 transition-colors"
                                    @click="showAttachFileModal = true"
                                    title="Attach project file as context"
                                >
                                    <span>📎</span>
                                    <span>Attach File</span>
                                </button>
                            </div>

                            <div class="flex items-center space-x-2">
                                <!-- Stop generation button -->
                                <button
                                    v-if="chatStore.isStreaming"
                                    type="button"
                                    class="px-3 py-1.5 rounded-lg bg-red-600/30 hover:bg-red-600/50 text-red-400 border border-red-500/40 text-xs font-medium flex items-center space-x-1.5 transition-colors"
                                    @click="chatStore.stopGeneration"
                                >
                                    <span class="w-2 h-2 rounded-sm bg-red-400"></span>
                                    <span>Stop (Esc)</span>
                                </button>

                                <!-- Send button -->
                                <button
                                    v-else
                                    type="button"
                                    :disabled="!inputPrompt.trim()"
                                    class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-40 text-white text-xs font-semibold flex items-center space-x-1.5 transition-all shadow-md shadow-blue-600/20"
                                    @click="handleSend"
                                >
                                    <span>Send</span>
                                    <span class="text-[10px] opacity-75 font-mono">↵</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Code Editor & Project Workspace Explorer -->
            <div
                v-if="showEditorPanel"
                class="w-[450px] lg:w-[550px] border-l border-slate-800 flex flex-col bg-[#141620] overflow-hidden"
            >
                <!-- Editor Top Header Tabs -->
                <div class="h-10 bg-[#161823] border-b border-slate-800 flex items-center justify-between px-3">
                    <div class="flex items-center space-x-2">
                        <button
                            type="button"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-colors"
                            :class="rightTab === 'editor' ? 'bg-[#1e2230] text-blue-400 border border-slate-700' : 'text-slate-400 hover:text-slate-200'"
                            @click="rightTab = 'editor'"
                        >
                            Monaco Editor
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-colors"
                            :class="rightTab === 'files' ? 'bg-[#1e2230] text-blue-400 border border-slate-700' : 'text-slate-400 hover:text-slate-200'"
                            @click="rightTab = 'files'"
                        >
                            Project Files ({{ projectStore.files.length }})
                        </button>
                    </div>

                    <button
                        type="button"
                        class="p-1 rounded text-slate-500 hover:text-slate-300"
                        @click="showEditorPanel = false"
                        title="Close editor panel"
                    >
                        ✕
                    </button>
                </div>

                <!-- Tab 1: Monaco Code Editor -->
                <div v-show="rightTab === 'editor'" class="flex-1 w-full h-full flex flex-col overflow-hidden">
                    <div v-if="!projectStore.activeFile" class="flex-1 flex flex-col items-center justify-center p-6 text-center text-slate-500 text-xs space-y-2">
                        <span>📝 No file open in editor</span>
                        <p class="max-w-xs text-slate-400">Select a file from the Project Files tab or apply AI-generated code directly here.</p>
                        <button
                            v-if="projectStore.currentProject"
                            class="px-3 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 transition-colors"
                            @click="handleCreateScratchFile"
                        >
                            + Create Scratch File
                        </button>
                    </div>

                    <MonacoEditor
                        v-else
                        v-model="editorContent"
                        :filename="projectStore.activeFile.name"
                        :is-saving="projectStore.isSavingFile"
                        @save="handleSaveFile"
                    />
                </div>

                <!-- Tab 2: Project Files Tree -->
                <div v-show="rightTab === 'files'" class="flex-1 p-3 overflow-y-auto space-y-2">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Explorer</span>
                        <div class="flex items-center space-x-1">
                            <button
                                type="button"
                                class="p-1 rounded hover:bg-slate-800 text-slate-400 text-xs"
                                @click="showNewFileDialog = true"
                                title="New File"
                            >
                                + File
                            </button>
                            <button
                                type="button"
                                class="p-1 rounded hover:bg-slate-800 text-slate-400 text-xs"
                                @click="showNewFolderDialog = true"
                                title="New Folder"
                            >
                                + Folder
                            </button>
                        </div>
                    </div>

                    <div v-if="!projectStore.currentProject" class="text-center py-8 text-xs text-slate-500">
                        No active project selected.
                    </div>

                    <div v-else-if="projectStore.files.length === 0" class="text-center py-8 text-xs text-slate-500">
                        Project workspace is empty. Create a file or folder above.
                    </div>

                    <div v-else class="space-y-0.5">
                        <div
                            v-for="file in projectStore.files"
                            :key="file.id"
                            class="group flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs hover:bg-slate-800/60 cursor-pointer transition-colors"
                            :class="projectStore.activeFile?.id === file.id ? 'bg-slate-800 text-blue-400 font-medium' : 'text-slate-300'"
                            @click="handleSelectFile(file)"
                        >
                            <div class="flex items-center space-x-2 truncate">
                                <span>{{ file.is_directory ? '📁' : '📄' }}</span>
                                <span class="truncate">{{ file.path }}</span>
                            </div>
                            <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button
                                    v-if="!file.is_directory"
                                    type="button"
                                    class="p-1 text-slate-400 hover:text-blue-400"
                                    @click.stop="attachFile(file)"
                                    title="Add to AI context"
                                >
                                    📎
                                </button>
                                <button
                                    type="button"
                                    class="p-1 text-slate-400 hover:text-red-400"
                                    @click.stop="projectStore.deleteFile(file.id)"
                                    title="Delete file"
                                >
                                    🗑️
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attach File Modal -->
        <div v-if="showAttachFileModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showAttachFileModal = false">
            <div class="w-full max-w-md bg-[#161924] border border-slate-800 rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-white">Attach File to AI Context</h3>
                    <button class="text-slate-400 hover:text-white" @click="showAttachFileModal = false">✕</button>
                </div>
                <div class="max-h-60 overflow-y-auto space-y-1">
                    <div
                        v-for="file in projectStore.files.filter(f => !f.is_directory)"
                        :key="file.id"
                        class="flex items-center justify-between px-3 py-2 rounded-lg bg-[#11131a] hover:bg-[#1a1d28] cursor-pointer text-xs"
                        @click="attachFile(file); showAttachFileModal = false;"
                    >
                        <span>📄 {{ file.path }}</span>
                        <span class="text-[11px] text-slate-400 font-mono">{{ file.size }} bytes</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- New File Dialog -->
        <div v-if="showNewFileDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showNewFileDialog = false">
            <div class="w-full max-w-sm bg-[#161924] border border-slate-800 rounded-xl p-5 space-y-4">
                <h3 class="text-sm font-semibold text-white">Create New File</h3>
                <input
                    v-model="newFilePath"
                    type="text"
                    placeholder="e.g. src/index.ts or app.php"
                    class="w-full px-3 py-2 rounded-lg bg-[#11131a] border border-slate-700 text-xs text-white focus:outline-none focus:border-blue-500"
                />
                <div class="flex justify-end space-x-2">
                    <button class="px-3 py-1.5 rounded bg-slate-800 text-xs text-slate-300" @click="showNewFileDialog = false">Cancel</button>
                    <button class="px-3 py-1.5 rounded bg-blue-600 text-xs text-white font-medium" @click="confirmCreateFile">Create</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import { useChatStore } from '../../stores/chat';
import { useProjectStore, type FileItem } from '../../stores/project';
import MarkdownMessage from '../../components/MarkdownMessage.vue';
import MonacoEditor from '../../components/MonacoEditor.vue';
import ModelSelector from '../../components/ModelSelector.vue';
import AgentSelector from '../../components/AgentSelector.vue';

const chatStore = useChatStore();
const projectStore = useProjectStore();

const inputPrompt = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const showEditorPanel = ref(true);
const rightTab = ref<'editor' | 'files'>('editor');
const editorContent = ref('');
const showAttachFileModal = ref(false);
const showNewFileDialog = ref(false);
const showNewFolderDialog = ref(false);
const newFilePath = ref('');
const attachedFiles = ref<FileItem[]>([]);

const starterPrompts = [
    'Create a Laravel Sanctum auth controller',
    'Write a TypeScript function to debounce API calls',
    'Explain how Free Claude Code proxies models',
    'Generate a Vue 3 reactive Pinia store',
];

const sessionTotalTokens = computed(() => {
    return chatStore.messages.reduce((sum, m) => sum + (m.total_tokens || 0), 0);
});

const sessionEstimatedCost = computed(() => {
    return chatStore.messages.reduce((sum, m) => sum + (m.estimated_cost || 0), 0);
});

onMounted(async () => {
    if (chatStore.conversations.length === 0) {
        await chatStore.fetchConversations();
    }
    if (!chatStore.currentConversation && chatStore.conversations.length > 0) {
        await chatStore.selectConversation(chatStore.conversations[0].id);
    }
    scrollToBottom();
});

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

async function handleSend() {
    if (!inputPrompt.value.trim() || chatStore.isStreaming) return;

    const promptText = inputPrompt.value.trim();
    inputPrompt.value = '';

    const fileIds = attachedFiles.value.map(f => f.id);
    const activePath = projectStore.activeFile?.path;
    const activeCode = editorContent.value;

    scrollToBottom();

    await chatStore.sendMessageStream(promptText, fileIds, activePath, activeCode);

    scrollToBottom();
}

function handleEsc() {
    if (chatStore.isStreaming) {
        chatStore.stopGeneration();
    }
}

function handleApplyCode(code: string) {
    editorContent.value = code;
    showEditorPanel.value = true;
    rightTab.value = 'editor';
}

async function handleSelectFile(file: FileItem) {
    if (file.is_directory) return;
    await projectStore.openFile(file);
    if (projectStore.activeFile) {
        editorContent.value = projectStore.activeFile.content;
        rightTab.value = 'editor';
    }
}

async function handleSaveFile(content: string) {
    await projectStore.saveActiveFileContent(content);
}

async function handleCreateScratchFile() {
    if (!projectStore.currentProject) return;
    const file = await projectStore.createFile('scratch.ts', '// Start coding here\n');
    if (file) {
        await handleSelectFile(file);
    }
}

async function confirmCreateFile() {
    if (!newFilePath.value.trim()) return;
    await projectStore.createFile(newFilePath.value.trim());
    newFilePath.value = '';
    showNewFileDialog.value = false;
}

function attachFile(file: FileItem) {
    if (!attachedFiles.value.some(f => f.id === file.id)) {
        attachedFiles.value.push(file);
    }
}

function detachFile(fileId: number) {
    attachedFiles.value = attachedFiles.value.filter(f => f.id !== fileId);
}
</script>
