<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white tracking-tight">Your Projects</h1>
                <p class="text-xs text-slate-400">Isolated workspace directories with individual files, Monaco editor, and chat sessions.</p>
            </div>
            <button
                type="button"
                class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 transition-all flex items-center space-x-1.5"
                @click="showCreateModal = true"
            >
                <span>+</span>
                <span>Create Project</span>
            </button>
        </div>

        <!-- Projects Grid -->
        <div v-if="projectStore.projects.length === 0" class="p-12 rounded-xl bg-[#141622] border border-slate-800 text-center space-y-3">
            <span class="text-4xl">📁</span>
            <h3 class="text-sm font-semibold text-white">No projects created yet</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto">Create a project to store source code files, upload assets, and prompt AI agents within dedicated workspace context.</p>
            <button
                class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-medium"
                @click="showCreateModal = true"
            >
                Create First Project
            </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div
                v-for="project in projectStore.projects"
                :key="project.id"
                class="p-5 rounded-xl bg-[#141622] border border-slate-800/80 hover:border-slate-700 transition-all flex flex-col justify-between space-y-4"
            >
                <div class="space-y-2">
                    <div class="flex items-start justify-between">
                        <span class="text-sm font-bold text-white truncate">{{ project.name }}</span>
                        <button
                            type="button"
                            class="text-xs text-slate-500 hover:text-red-400"
                            @click="handleArchive(project.id)"
                            title="Archive Project"
                        >
                            ✕
                        </button>
                    </div>
                    <p class="text-xs text-slate-400 line-clamp-3 leading-relaxed">
                        {{ project.description || 'No description provided.' }}
                    </p>
                </div>

                <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
                    <div class="flex items-center space-x-3 text-slate-500 font-mono text-[11px]">
                        <span>📄 {{ project.files_count || 0 }} files</span>
                        <span>💬 {{ project.conversations_count || 0 }} chats</span>
                    </div>

                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white transition-all font-medium text-xs flex items-center space-x-1"
                        @click="openWorkspace(project.id)"
                    >
                        <span>Open</span>
                        <span>→</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Create Project Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showCreateModal = false">
            <div class="w-full max-w-md bg-[#161924] border border-slate-800 rounded-xl p-6 space-y-5 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-white">Create New Project</h3>
                    <button class="text-slate-400 hover:text-white" @click="showCreateModal = false">✕</button>
                </div>

                <form class="space-y-4" @submit.prevent="handleCreateProject">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Project Name</label>
                        <input
                            v-model="projectName"
                            type="text"
                            required
                            placeholder="e.g. My Next.js SaaS or Inventory API"
                            class="w-full px-3.5 py-2.5 rounded-lg bg-[#11131a] border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Description (Optional)</label>
                        <textarea
                            v-model="projectDescription"
                            rows="3"
                            placeholder="Brief summary of your project..."
                            class="w-full px-3.5 py-2.5 rounded-lg bg-[#11131a] border border-slate-700 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 resize-none"
                        ></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg bg-slate-800 text-xs text-slate-300 hover:bg-slate-700"
                            @click="showCreateModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-lg bg-blue-600 text-xs text-white font-medium hover:bg-blue-500"
                        >
                            Create Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useProjectStore } from '../../stores/project';

const router = useRouter();
const projectStore = useProjectStore();

const showCreateModal = ref(false);
const projectName = ref('');
const projectDescription = ref('');

onMounted(() => {
    projectStore.fetchProjects();
});

async function handleCreateProject() {
    if (!projectName.value.trim()) return;
    await projectStore.createProject(projectName.value.trim(), projectDescription.value.trim());
    projectName.value = '';
    projectDescription.value = '';
    showCreateModal.value = false;
    router.push('/workspace');
}

async function openWorkspace(projectId: number) {
    await projectStore.selectProject(projectId);
    router.push('/workspace');
}

async function handleArchive(projectId: number) {
    if (confirm('Archive this project?')) {
        await projectStore.deleteFile(projectId); // or archive endpoint
        await projectStore.fetchProjects();
    }
}
</script>
