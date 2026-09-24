import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api/client';

export interface ProjectItem {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    workspace_path: string;
    files_count?: number;
    conversations_count?: number;
    updated_at: string;
}

export interface FileItem {
    id: number;
    parent_id?: number | null;
    name: string;
    path: string;
    is_directory: boolean;
    size: number;
    extension?: string;
    mime_type?: string;
}

export const useProjectStore = defineStore('project', () => {
    const projects = ref<ProjectItem[]>([]);
    const currentProject = ref<ProjectItem | null>(null);
    const files = ref<FileItem[]>([]);
    const activeFile = ref<{ id: number; path: string; name: string; content: string } | null>(null);
    const isLoadingFiles = ref<boolean>(false);
    const isSavingFile = ref<boolean>(false);

    async function fetchProjects() {
        try {
            const res = await api.get('/projects');
            projects.value = res.data;
        } catch (err) {
            console.error('Failed to load projects', err);
        }
    }

    async function createProject(name: string, description?: string) {
        const res = await api.post('/projects', { name, description });
        projects.value.unshift(res.data);
        currentProject.value = res.data;
        files.value = [];
        activeFile.value = null;
        return res.data;
    }

    async function selectProject(projectId: number) {
        try {
            const res = await api.get(`/projects/${projectId}`);
            currentProject.value = res.data.project;
            files.value = res.data.files || [];
            activeFile.value = null;
        } catch (err) {
            console.error('Failed to select project', err);
        }
    }

    async function loadFiles(projectId: number) {
        isLoadingFiles.value = true;
        try {
            const res = await api.get(`/projects/${projectId}/files`);
            files.value = res.data;
        } finally {
            isLoadingFiles.value = false;
        }
    }

    async function openFile(file: FileItem) {
        if (!currentProject.value || file.is_directory) return;
        try {
            const res = await api.get(`/projects/${currentProject.value.id}/files/${file.id}/content`);
            activeFile.value = {
                id: file.id,
                path: file.path,
                name: file.name,
                content: res.data.content,
            };
        } catch (err) {
            console.error('Failed to read file content', err);
        }
    }

    async function saveActiveFileContent(newContent: string) {
        if (!currentProject.value || !activeFile.value) return;
        isSavingFile.value = true;
        try {
            await api.put(`/projects/${currentProject.value.id}/files/${activeFile.value.id}/content`, {
                content: newContent,
            });
            activeFile.value.content = newContent;
        } finally {
            isSavingFile.value = false;
        }
    }

    async function createFile(path: string, content = '') {
        if (!currentProject.value) return;
        const res = await api.post(`/projects/${currentProject.value.id}/files`, { path, content });
        await loadFiles(currentProject.value.id);
        return res.data.file;
    }

    async function createFolder(path: string) {
        if (!currentProject.value) return;
        const res = await api.post(`/projects/${currentProject.value.id}/folders`, { path });
        await loadFiles(currentProject.value.id);
        return res.data.folder;
    }

    async function deleteFile(fileId: number) {
        if (!currentProject.value) return;
        await api.delete(`/projects/${currentProject.value.id}/files/${fileId}`);
        if (activeFile.value?.id === fileId) {
            activeFile.value = null;
        }
        await loadFiles(currentProject.value.id);
    }

    async function uploadFile(file: File, targetFolder = '') {
        if (!currentProject.value) return;
        const formData = new FormData();
        formData.append('file', file);
        if (targetFolder) {
            formData.append('target_folder', targetFolder);
        }
        const res = await api.post(`/projects/${currentProject.value.id}/upload`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await loadFiles(currentProject.value.id);
        return res.data.file;
    }

    return {
        projects,
        currentProject,
        files,
        activeFile,
        isLoadingFiles,
        isSavingFile,
        fetchProjects,
        createProject,
        selectProject,
        loadFiles,
        openFile,
        saveActiveFileContent,
        createFile,
        createFolder,
        deleteFile,
        uploadFile,
    };
});
