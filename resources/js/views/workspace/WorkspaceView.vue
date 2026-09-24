<template>
    <div class="h-full w-full flex flex-col overflow-hidden bg-[#F8FAFC]">
        <!-- Top Workspace Bar (SISFOPERS KC style) -->
        <header class="h-16 border-b border-[#E2E8F0] bg-white px-6 flex items-center justify-between text-xs text-slate-600 shadow-sm shrink-0">
            <div class="flex items-center space-x-3">
                <!-- Project Selector or Info -->
                <div class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-[#E2E8F0] font-semibold text-slate-700 shadow-xs">
                    <span class="text-blue-600">📁</span>
                    <span>{{ projectStore.currentProject?.name || 'Global Scratchpad' }}</span>
                </div>

                <!-- Active Conversation Title -->
                <div class="flex items-center space-x-2 text-slate-400">
                    <span class="text-slate-300 font-bold">/</span>
                    <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px] truncate max-w-xs">
                        {{ chatStore.currentConversation?.title || 'Chat Baru' }}
                    </span>
                </div>
            </div>

            <!-- Top Right: Session Usage Badges & Editor Toggle -->
            <div class="flex items-center space-x-3">
                <!-- Session Tokens & Estimated Cost -->
                <div v-if="sessionTotalTokens > 0" class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-[#E2E8F0] text-[11px] font-mono text-slate-600 shadow-xs">
                    <span>⚡ {{ sessionTotalTokens.toLocaleString() }} tokens</span>
                    <span class="text-slate-300">|</span>
                    <span class="text-emerald-600 font-semibold">${{ formatCost(sessionEstimatedCost) }} est.</span>
                </div>

                <!-- Toggle Code Editor Panel -->
                <button
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl border text-xs font-semibold flex items-center space-x-1.5 transition-all shadow-xs cursor-pointer"
                    :class="showEditorPanel ? 'bg-[#2563EB]/10 text-[#2563EB] border-[#2563EB]/30' : 'bg-white text-slate-700 border-[#E2E8F0] hover:bg-slate-50'"
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
            <div class="flex-1 flex flex-col min-w-0 bg-[#F8FAFC]">
                <!-- Messages Stream View -->
                <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Welcome Hero if Empty (SISFOPERS KC style) -->
                    <div v-if="chatStore.messages.length === 0 && !chatStore.isStreaming" class="my-auto h-full flex flex-col items-center justify-center text-center p-8 space-y-5 max-w-xl mx-auto">
                        <div class="w-14 h-14 rounded-2xl bg-[#2563EB]/10 border border-[#2563EB]/20 flex items-center justify-center text-2xl shadow-sm text-[#2563EB]">
                            🤖
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">AI Coding Workspace</h2>
                            <p class="text-xs text-slate-500 leading-relaxed max-w-md mx-auto">
                                Tanyakan solusi teknis, bangun fitur aplikasi, refactor kode, atau otomatisasi workflow Anda dengan model AI terpilih.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 w-full pt-2">
                            <button
                                v-for="prompt in starterPrompts"
                                :key="prompt"
                                class="p-3 text-left rounded-xl bg-white hover:bg-blue-50/40 border border-slate-200 hover:border-[#2563EB]/40 text-xs font-medium text-slate-700 transition-all shadow-xs"
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
                        <div v-if="msg.role !== 'user'" class="w-8 h-8 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center flex-shrink-0 text-[#2563EB] text-xs font-bold mt-1 shadow-xs">
                            AI
                        </div>

                        <!-- Message Body -->
                        <div
                            class="rounded-2xl px-5 py-4 max-w-[85%] border transition-all text-sm leading-relaxed"
                            :class="msg.role === 'user'
                                ? 'bg-[#2563EB] text-white border-blue-600 rounded-tr-none shadow-sm'
                                : 'bg-white text-slate-800 border-[#E2E8F0] rounded-tl-none shadow-sm'"
                        >
                            <!-- User attachments gallery & document badges -->
                            <div v-if="msg.role === 'user' && msg.metadata?.attachments?.length" class="mb-3 flex flex-wrap gap-2">
                                <div
                                    v-for="att in msg.metadata.attachments"
                                    :key="att.id"
                                    class="flex items-center gap-2 p-1.5 rounded-xl bg-blue-700/60 border border-blue-500/50 text-xs text-white"
                                >
                                    <img
                                        v-if="att.is_image"
                                        :src="att.url || att.base64"
                                        :alt="att.name"
                                        class="w-16 h-16 object-cover rounded-lg border border-white/20 cursor-pointer hover:opacity-90 transition-opacity"
                                        @click="openImageModal(att.url || att.base64)"
                                    />
                                    <div v-else class="flex items-center space-x-2 px-2 py-1">
                                        <span class="text-base">{{ att.extension === 'pdf' ? '📕' : '📄' }}</span>
                                        <div class="truncate max-w-[180px]">
                                            <div class="font-medium truncate text-[11px]">{{ att.name }}</div>
                                            <div class="text-[10px] text-blue-200 font-mono">{{ formatFileSize(att.size) }}</div>
                                        </div>
                                        <a
                                            v-if="att.url"
                                            :href="att.url"
                                            target="_blank"
                                            download
                                            class="p-1 hover:bg-white/20 rounded text-blue-100 transition-colors ml-1"
                                            title="Buka / Unduh file"
                                        >
                                            ↗
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div v-if="msg.role === 'user'" class="whitespace-pre-wrap leading-relaxed">{{ msg.content }}</div>
                            <div v-else>
                                <MarkdownMessage :content="msg.content" @apply-code="handleApplyCode" />

                                <!-- Metadata footer for AI turns -->
                                <div v-if="msg.total_tokens" class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                                    <span>Model: <strong class="text-slate-600">{{ msg.model || 'NVIDIA / FCC' }}</strong></span>
                                    <div class="flex items-center space-x-2">
                                        <span>{{ msg.total_tokens }} tokens</span>
                                        <span v-if="msg.estimated_cost">• ${{ formatCost(msg.estimated_cost) }}</span>
                                        <span v-if="msg.duration_ms">• {{ (Number(msg.duration_ms || 0) / 1000).toFixed(1) }}s</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Avatar -->
                        <div v-if="msg.role === 'user'" class="w-8 h-8 rounded-xl bg-[#2563EB] flex items-center justify-center flex-shrink-0 text-white text-xs font-bold mt-1 shadow-sm">
                            U
                        </div>
                    </div>

                    <!-- Streaming Assistant Message Bubble -->
                    <div v-if="chatStore.isStreaming" class="flex space-x-3 text-sm max-w-4xl mx-auto w-full justify-start">
                        <div class="w-8 h-8 rounded-xl bg-[#2563EB]/10 border border-[#2563EB]/30 flex items-center justify-center flex-shrink-0 text-[#2563EB] text-xs font-bold mt-1 animate-pulse">
                            AI
                        </div>
                        <div class="rounded-2xl px-5 py-4 max-w-[85%] border bg-white text-slate-800 border-[#E2E8F0] rounded-tl-none shadow-sm">
                            <MarkdownMessage :content="chatStore.streamingContent" @apply-code="handleApplyCode" />
                            <span class="inline-block w-2 h-4 bg-[#2563EB] animate-pulse ml-1 align-middle"></span>
                        </div>
                    </div>
                </div>

                <!-- Attached File Pills in Context -->
                <div v-if="attachedFiles.length > 0" class="px-6 py-2 bg-slate-100/60 border-t border-slate-200/80 flex items-center space-x-2 overflow-x-auto text-xs">
                    <span class="text-slate-500 text-[11px] font-bold uppercase tracking-wider">File Konteks:</span>
                    <div
                        v-for="file in attachedFiles"
                        :key="file.id"
                        class="flex items-center space-x-1.5 px-3 py-1 rounded-xl bg-white border border-[#E2E8F0] text-slate-700 shadow-xs"
                    >
                        <span>📄 {{ file.name }}</span>
                        <button type="button" class="text-slate-400 hover:text-red-500 ml-1 font-bold" @click="detachFile(file.id)">×</button>
                    </div>
                </div>

                <!-- Chat Input Controls Bar (Fixed Dropdown Visibility) -->
                <div class="p-6 bg-gradient-to-t from-[#F8FAFC] via-[#F8FAFC] to-transparent border-t border-slate-200/60">
                    <div
                        class="max-w-4xl mx-auto bg-white border rounded-2xl shadow-xl shadow-slate-200/50 transition-all relative"
                        :class="isDragging ? 'border-[#2563EB] ring-4 ring-[#2563EB]/20 bg-blue-50/20' : 'border-[#E2E8F0] focus-within:border-[#2563EB] focus-within:ring-2 focus-within:ring-[#2563EB]/15'"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                    >
                        <!-- Drag & Drop Overlay Indicator -->
                        <div v-if="isDragging" class="absolute inset-0 rounded-2xl bg-blue-50/95 border-2 border-dashed border-[#2563EB] z-30 flex flex-col items-center justify-center text-[#2563EB] font-bold text-sm pointer-events-none backdrop-blur-2xs space-y-1">
                            <span class="text-3xl">📥</span>
                            <span class="text-xs font-bold uppercase tracking-wider">Lepaskan File di Sini</span>
                            <span class="text-[11px] text-slate-500 font-normal">Mendukung Foto/Gambar, PDF, Dokumen Word, dan Teks</span>
                        </div>

                        <!-- Pending Uploaded Attachment Preview Chips -->
                        <div v-if="pendingAttachments.length > 0 || isUploadingAttachment" class="px-4 pt-3 pb-2 flex flex-wrap gap-2 border-b border-slate-100">
                            <div
                                v-for="att in pendingAttachments"
                                :key="att.id"
                                class="group relative flex items-center space-x-2 px-2.5 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 shadow-2xs hover:border-[#2563EB]/40 transition-all"
                            >
                                <img
                                    v-if="att.is_image"
                                    :src="att.url || att.base64"
                                    :alt="att.name"
                                    class="w-8 h-8 object-cover rounded-lg border border-slate-200 cursor-pointer"
                                    @click="openImageModal(att.url || att.base64)"
                                />
                                <span v-else class="text-base">{{ att.extension === 'pdf' ? '📕' : '📄' }}</span>
                                <div class="truncate max-w-[150px]">
                                    <div class="font-medium text-slate-800 truncate text-[11px]">{{ att.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ formatFileSize(att.size) }}</div>
                                </div>
                                <button
                                    type="button"
                                    class="w-5 h-5 rounded-full bg-slate-200 hover:bg-red-500 hover:text-white flex items-center justify-center text-[10px] text-slate-500 transition-colors ml-1 cursor-pointer"
                                    @click="removePendingAttachment(att.id)"
                                    title="Hapus lampiran"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Uploading Indicator Spinner -->
                            <div v-if="isUploadingAttachment" class="flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-blue-50 border border-blue-200 text-xs text-[#2563EB] animate-pulse">
                                <svg class="animate-spin h-3.5 w-3.5 text-[#2563EB]" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="font-medium text-[11px]">Memproses file...</span>
                            </div>
                        </div>

                        <!-- Textarea with paste listener -->
                        <textarea
                            ref="textareaRef"
                            v-model="inputPrompt"
                            rows="3"
                            placeholder="Ketik instruksi koding, tanyakan fitur, atau refactor... (Tekan Ctrl+Enter untuk kirim)"
                            class="w-full bg-transparent px-5 py-4 text-sm text-slate-800 placeholder-slate-400 focus:outline-none resize-none font-sans leading-relaxed rounded-t-2xl"
                            @keydown.ctrl.enter.prevent="handleSend"
                            @keydown.meta.enter.prevent="handleSend"
                            @keydown.esc.prevent="handleEsc"
                            @paste="handlePaste"
                        ></textarea>

                        <!-- Bottom Controls Bar -->
                        <div class="px-4 py-2.5 bg-slate-50/80 border-t border-[#E2E8F0] flex items-center justify-between flex-wrap gap-2 rounded-b-2xl relative">
                            <div class="flex items-center space-x-2">
                                <!-- Model Selector (Floats upward without glitch) -->
                                <ModelSelector
                                    v-model="chatStore.selectedModelId"
                                    :providers="chatStore.providers"
                                />

                                <!-- Agent Selector -->
                                <AgentSelector
                                    v-model="chatStore.selectedAgentId"
                                    :agents="chatStore.agents"
                                />

                                <!-- Attach File / Media Button (Direct Computer Upload: Images, PDFs, Docs) -->
                                <button
                                    type="button"
                                    class="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-white border border-[#E2E8F0] hover:border-slate-300 text-xs font-semibold text-slate-700 shadow-xs transition-colors cursor-pointer"
                                    @click="triggerFileInput"
                                    title="Lampirkan foto, PDF, atau dokumen dari perangkat Anda"
                                >
                                    <span>📎</span>
                                    <span>Lampirkan</span>
                                </button>

                                <!-- Hidden Native File Input -->
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    multiple
                                    accept="image/*,.pdf,.doc,.docx,.txt,.csv,.json,.md,.js,.ts,.py,.php,.html,.css,.sql"
                                    class="hidden"
                                    @change="handleFileInputChange"
                                />

                                <!-- Attach Project File Button if in a Project -->
                                <button
                                    v-if="projectStore.currentProject"
                                    type="button"
                                    class="flex items-center space-x-1 px-2.5 py-1.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-[#E2E8F0] text-xs font-semibold text-slate-600 shadow-xs transition-colors cursor-pointer"
                                    @click="showAttachFileModal = true"
                                    title="Pilih file dari project tree"
                                >
                                    <span>📁</span>
                                    <span>Project File</span>
                                </button>
                            </div>

                            <div class="flex items-center space-x-2">
                                <!-- Stop generation button -->
                                <button
                                    v-if="chatStore.isStreaming"
                                    type="button"
                                    class="px-4 py-1.5 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold flex items-center space-x-1.5 transition-colors cursor-pointer"
                                    @click="chatStore.stopGeneration"
                                >
                                    <span class="w-2 h-2 rounded-sm bg-red-600"></span>
                                    <span>Stop (Esc)</span>
                                </button>

                                <!-- Send button -->
                                <button
                                    v-else
                                    type="button"
                                    :disabled="!inputPrompt.trim() && pendingAttachments.length === 0"
                                    class="px-5 py-2 rounded-xl bg-[#2563EB] hover:bg-blue-700 disabled:opacity-40 text-white text-xs font-bold flex items-center space-x-1.5 transition-all shadow-md shadow-blue-500/20 cursor-pointer"
                                    @click="handleSend"
                                >
                                    <span>Kirim</span>
                                    <span class="text-[10px] opacity-80 font-mono">↵</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Code Editor & Project Workspace Explorer -->
            <div
                v-if="showEditorPanel"
                class="w-[480px] lg:w-[580px] border-l border-[#E2E8F0] flex flex-col bg-white overflow-hidden shadow-sm"
            >
                <!-- Editor Top Header Tabs (SISFOPERS KC style) -->
                <div class="h-12 bg-slate-50/80 border-b border-[#E2E8F0] flex items-center justify-between px-4">
                    <div class="flex items-center space-x-1">
                        <button
                            type="button"
                            class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer"
                            :class="rightTab === 'editor' ? 'bg-white text-[#2563EB] shadow-xs border border-[#E2E8F0]' : 'text-slate-500 hover:text-slate-800'"
                            @click="rightTab = 'editor'"
                        >
                            Monaco Editor
                        </button>
                        <button
                            type="button"
                            class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer"
                            :class="rightTab === 'files' ? 'bg-white text-[#2563EB] shadow-xs border border-[#E2E8F0]' : 'text-slate-500 hover:text-slate-800'"
                            @click="rightTab = 'files'"
                        >
                            Project Files ({{ projectStore.files.length }})
                        </button>
                    </div>

                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                        @click="showEditorPanel = false"
                        title="Tutup panel editor"
                    >
                        ✕
                    </button>
                </div>

                <!-- Tab 1: Monaco Code Editor -->
                <div v-show="rightTab === 'editor'" class="flex-1 w-full h-full flex flex-col overflow-hidden bg-white">
                    <div v-if="!projectStore.activeFile" class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400 text-xs space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-xl text-slate-400">
                            📝
                        </div>
                        <div class="space-y-1">
                            <span class="font-bold text-slate-700">Belum ada file yang dibuka</span>
                            <p class="max-w-xs text-slate-400">Pilih file dari tab Project Files atau terapkan kode hasil AI langsung ke sini.</p>
                        </div>
                        <button
                            v-if="projectStore.currentProject"
                            class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors shadow-xs"
                            @click="handleCreateScratchFile"
                        >
                            + Buat File Scratch
                        </button>
                    </div>

                    <MonacoEditor
                        v-else
                        v-model="editorContent"
                        :filename="projectStore.activeFile.name"
                        @save="handleSaveFile"
                    />
                </div>

                <!-- Tab 2: Project Files Explorer -->
                <div v-show="rightTab === 'files'" class="flex-1 overflow-y-auto p-4 space-y-2 bg-white">
                    <div class="flex items-center justify-between pb-2 border-b border-[#E2E8F0]">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">File Explorer</span>
                        <div class="flex items-center space-x-1">
                            <button
                                type="button"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                                @click="showNewFileDialog = true"
                            >
                                + File
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1 pt-1">
                        <div
                            v-for="file in projectStore.files"
                            :key="file.id"
                            class="group flex items-center justify-between px-3 py-2 rounded-xl text-xs hover:bg-slate-50 cursor-pointer transition-colors"
                            :class="projectStore.activeFile?.id === file.id ? 'bg-[#2563EB]/10 text-[#2563EB] font-bold' : 'text-slate-700'"
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
                                    class="p-1 text-slate-400 hover:text-blue-600"
                                    @click.stop="attachFile(file)"
                                    title="Tambahkan ke konteks AI"
                                >
                                    📎
                                </button>
                                <button
                                    type="button"
                                    class="p-1 text-slate-400 hover:text-red-600"
                                    @click.stop="projectStore.deleteFile(file.id)"
                                    title="Hapus file"
                                >
                                    🗑️
                                </button>
                            </div>
                        </div>

                        <div v-if="projectStore.files.length === 0" class="text-center py-8 text-xs text-slate-400 italic">
                            Belum ada file di project ini.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attach File Modal (SISFOPERS KC style) -->
        <div v-if="showAttachFileModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showAttachFileModal = false">
            <div class="w-full max-w-md bg-white border border-[#E2E8F0] rounded-2xl p-6 space-y-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900">Lampirkan File ke Konteks AI</h3>
                    <button class="text-slate-400 hover:text-slate-600 p-1" @click="showAttachFileModal = false">✕</button>
                </div>
                <div class="max-h-60 overflow-y-auto space-y-1">
                    <div
                        v-for="file in projectStore.files.filter(f => !f.is_directory)"
                        :key="file.id"
                        class="flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 hover:bg-blue-50/50 cursor-pointer text-xs transition-colors"
                        @click="attachFile(file); showAttachFileModal = false;"
                    >
                        <span class="font-medium text-slate-700">📄 {{ file.path }}</span>
                        <span class="text-[11px] text-slate-400 font-mono">{{ file.size }} bytes</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- New File Dialog (SISFOPERS KC style) -->
        <div v-if="showNewFileDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="showNewFileDialog = false">
            <div class="w-full max-w-sm bg-white border border-[#E2E8F0] rounded-2xl p-6 space-y-4 shadow-2xl">
                <h3 class="text-sm font-bold text-slate-900">Buat File Baru</h3>
                <input
                    v-model="newFilePath"
                    type="text"
                    placeholder="Contoh: src/index.ts atau app.php"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-[#2563EB] focus:bg-white transition-colors"
                />
                <div class="flex justify-end space-x-2 pt-2">
                    <button class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-semibold text-slate-600 hover:bg-slate-200" @click="showNewFileDialog = false">Batal</button>
                    <button class="px-4 py-2 rounded-xl bg-[#2563EB] hover:bg-blue-700 text-xs text-white font-bold shadow-sm" @click="confirmCreateFile">Buat File</button>
                </div>
            </div>
        </div>

        <!-- Image Preview Modal (Lightbox) -->
        <div v-if="showImageModal" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4" @click.self="showImageModal = false">
            <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center">
                <button
                    class="absolute -top-10 right-0 text-white hover:text-slate-300 text-xs font-bold bg-slate-800/80 px-3 py-1.5 rounded-lg transition-colors cursor-pointer"
                    @click="showImageModal = false"
                >
                    ✕ Tutup Preview
                </button>
                <img :src="previewImageUrl" class="max-w-full max-h-[85vh] object-contain rounded-2xl shadow-2xl border border-slate-700" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import { useChatStore, type AttachmentItem } from '../../stores/chat';
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
const fileInputRef = ref<HTMLInputElement | null>(null);
const pendingAttachments = ref<AttachmentItem[]>([]);
const isUploadingAttachment = ref(false);
const isDragging = ref(false);
const showImageModal = ref(false);
const previewImageUrl = ref('');
const showEditorPanel = ref(true);
const rightTab = ref<'editor' | 'files'>('editor');
const editorContent = ref('');
const showAttachFileModal = ref(false);
const showNewFileDialog = ref(false);
const showNewFolderDialog = ref(false);
const newFilePath = ref('');
const attachedFiles = ref<FileItem[]>([]);

const starterPrompts = [
    'Buatkan controller autentikasi Laravel Sanctum',
    'Tuliskan fungsi TypeScript debounce API calls',
    'Jelaskan cara kerja proxy model Free Claude Code',
    'Buatkan state management Vue 3 dengan Pinia',
];

function formatCost(val: any, decimals = 4): string {
    const num = parseFloat(String(val ?? 0));
    return isNaN(num) ? '0.0000' : num.toFixed(decimals);
}

const sessionTotalTokens = computed(() => {
    return chatStore.messages.reduce((sum, m) => sum + (Number(m.total_tokens) || 0), 0);
});

const sessionEstimatedCost = computed<number>(() => {
    return chatStore.messages.reduce((sum, m) => {
        const val = parseFloat(String(m.estimated_cost ?? 0));
        return sum + (isNaN(val) ? 0 : val);
    }, 0);
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
    if ((!inputPrompt.value.trim() && pendingAttachments.value.length === 0) || chatStore.isStreaming) return;

    const promptText = inputPrompt.value.trim() || 'Tolong analisa file yang saya lampirkan:';
    inputPrompt.value = '';

    const currentAttachments = [...pendingAttachments.value];
    pendingAttachments.value = [];

    const fileIds = attachedFiles.value.map(f => f.id);
    const activePath = projectStore.activeFile?.path;
    const activeCode = editorContent.value;

    scrollToBottom();

    await chatStore.sendMessageStream(promptText, fileIds, activePath, activeCode, currentAttachments);

    scrollToBottom();
}

function triggerFileInput() {
    fileInputRef.value?.click();
}

async function uploadSingleFile(file: File) {
    isUploadingAttachment.value = true;
    try {
        const att = await chatStore.uploadAttachment(file);
        pendingAttachments.value.push(att);
    } catch (err: any) {
        alert(err.response?.data?.message || 'Gagal mengunggah file. Pastikan ukuran di bawah 20MB.');
    } finally {
        isUploadingAttachment.value = false;
    }
}

async function handleFileInputChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;
    const files = Array.from(target.files);
    for (const f of files) {
        await uploadSingleFile(f);
    }
    target.value = '';
}

async function handleDrop(e: DragEvent) {
    isDragging.value = false;
    if (!e.dataTransfer || !e.dataTransfer.files || e.dataTransfer.files.length === 0) return;
    const files = Array.from(e.dataTransfer.files);
    for (const f of files) {
        await uploadSingleFile(f);
    }
}

function handlePaste(e: ClipboardEvent) {
    if (!e.clipboardData) return;
    const items = e.clipboardData.items;
    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            const file = items[i].getAsFile();
            if (file) {
                e.preventDefault();
                uploadSingleFile(file);
            }
        }
    }
}

function removePendingAttachment(id: string) {
    pendingAttachments.value = pendingAttachments.value.filter(a => a.id !== id);
}

function openImageModal(url?: string | null) {
    if (!url) return;
    previewImageUrl.value = url;
    showImageModal.value = true;
}

function formatFileSize(bytes?: number): string {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
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
