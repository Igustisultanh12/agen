import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../api/client';
import { useAuthStore } from './auth';

export interface ModelOption {
    id: number;
    name: string;
    slug: string;
    provider_model_id: string;
    description: string;
    category: string;
    context_window: number;
    is_default: boolean;
    pricing: {
        input_price_per_1m: number;
        output_price_per_1m: number;
        currency: string;
        is_free: boolean;
    };
}

export interface ProviderGroup {
    provider_name: string;
    provider_slug: string;
    provider_health: string;
    models: ModelOption[];
}

export interface AgentOption {
    id: number;
    name: string;
    slug: string;
    harness_type: string;
    description: string;
    is_default: boolean;
}

export interface MessageItem {
    id?: number;
    role: 'user' | 'assistant' | 'system' | 'tool';
    content: string;
    model?: string;
    input_tokens?: number;
    output_tokens?: number;
    total_tokens?: number;
    estimated_cost?: number;
    duration_ms?: number;
    is_streaming?: boolean;
    created_at?: string;
}

export interface ConversationItem {
    id: number;
    uuid: string;
    title: string;
    project_id?: number | null;
    model_id?: number | null;
    agent_id?: number | null;
    status: string;
    updated_at: string;
    model?: any;
    agent?: any;
    project?: any;
}

export const useChatStore = defineStore('chat', () => {
    const providers = ref<ProviderGroup[]>([]);
    const agents = ref<AgentOption[]>([]);
    const conversations = ref<ConversationItem[]>([]);
    const currentConversation = ref<ConversationItem | null>(null);
    const messages = ref<MessageItem[]>([]);
    const isStreaming = ref<boolean>(false);
    const streamingContent = ref<string>('');
    const selectedModelId = ref<number | null>(null);
    const selectedAgentId = ref<number | null>(null);

    let abortController: AbortController | null = null;

    async function fetchCatalog() {
        try {
            const res = await api.get('/chat/catalog');
            providers.value = res.data.providers || [];
            agents.value = res.data.agents || [];

            // Set default model and agent if not chosen
            if (!selectedModelId.value && providers.value.length > 0) {
                for (const group of providers.value) {
                    const def = group.models.find(m => m.is_default);
                    if (def) {
                        selectedModelId.value = def.id;
                        break;
                    }
                }
                if (!selectedModelId.value && providers.value[0]?.models[0]) {
                    selectedModelId.value = providers.value[0].models[0].id;
                }
            }

            if (!selectedAgentId.value && agents.value.length > 0) {
                const defAgent = agents.value.find(a => a.is_default) || agents.value[0];
                if (defAgent) {
                    selectedAgentId.value = defAgent.id;
                }
            }
        } catch (err) {
            console.error('Failed to load catalog', err);
        }
    }

    async function fetchConversations(projectId?: number) {
        try {
            const params = projectId ? { project_id: projectId } : {};
            const res = await api.get('/chat/conversations', { params });
            conversations.value = res.data.data || res.data;
        } catch (err) {
            console.error('Failed to load conversations', err);
        }
    }

    async function createConversation(projectId?: number, title = 'New Chat') {
        try {
            const payload: any = {
                title,
                model_id: selectedModelId.value,
                agent_id: selectedAgentId.value,
            };
            if (projectId) payload.project_id = projectId;

            const res = await api.post('/chat/conversations', payload);
            const conv = res.data;
            conversations.value.unshift(conv);
            currentConversation.value = conv;
            messages.value = [];
            return conv;
        } catch (err) {
            console.error('Failed to create conversation', err);
            throw err;
        }
    }

    async function selectConversation(conversationId: number) {
        try {
            const res = await api.get(`/chat/conversations/${conversationId}`);
            currentConversation.value = res.data.conversation;
            messages.value = res.data.messages || [];
            if (res.data.conversation.model_id) {
                selectedModelId.value = res.data.conversation.model_id;
            }
            if (res.data.conversation.agent_id) {
                selectedAgentId.value = res.data.conversation.agent_id;
            }
        } catch (err) {
            console.error('Failed to load conversation details', err);
        }
    }

    async function deleteConversation(conversationId: number) {
        try {
            await api.delete(`/chat/conversations/${conversationId}`);
            conversations.value = conversations.value.filter(c => c.id !== conversationId);
            if (currentConversation.value?.id === conversationId) {
                currentConversation.value = null;
                messages.value = [];
            }
        } catch (err) {
            console.error('Failed to delete conversation', err);
        }
    }

    async function sendMessageFallback(
        convId: number,
        prompt: string,
        selectedFileIds: number[] = [],
        currentFilePath?: string,
        currentFileContent?: string
    ) {
        const payload: any = {
            prompt,
            selected_file_ids: selectedFileIds,
            current_file_path: currentFilePath,
            current_file_content: currentFileContent,
        };
        if (selectedModelId.value) {
            payload.model_id = selectedModelId.value;
        }

        const res = await api.post(`/chat/conversations/${convId}/send`, payload);
        const assistantMsg = res.data.message;

        messages.value.push({
            id: assistantMsg.id,
            role: 'assistant',
            content: assistantMsg.content,
            model: assistantMsg.model,
            input_tokens: assistantMsg.input_tokens,
            output_tokens: assistantMsg.output_tokens,
            total_tokens: assistantMsg.total_tokens,
            estimated_cost: res.data.usage?.estimated_cost,
            duration_ms: assistantMsg.duration_ms,
            created_at: assistantMsg.created_at || new Date().toISOString(),
        });
        streamingContent.value = '';

        const authStore = useAuthStore();
        await authStore.fetchMe();
        await selectConversation(convId);
    }

    async function sendMessageStream(
        prompt: string,
        selectedFileIds: number[] = [],
        currentFilePath?: string,
        currentFileContent?: string
    ) {
        if (!currentConversation.value) {
            await createConversation();
        }

        const convId = currentConversation.value!.id;

        // Push local user message immediately
        messages.value.push({
            role: 'user',
            content: prompt,
            created_at: new Date().toISOString(),
        });

        isStreaming.value = true;
        streamingContent.value = '';
        abortController = new AbortController();

        const token = localStorage.getItem('auth_token');

        try {
            const streamUrl = `/api/chat/conversations/${convId}/stream`;

            const response = await fetch(streamUrl.toString(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Authorization': `Bearer ${token}`,
                },
                body: JSON.stringify({
                    prompt,
                    model_id: selectedModelId.value,
                    selected_file_ids: selectedFileIds,
                    current_file_path: currentFilePath,
                    current_file_content: currentFileContent,
                }),
                signal: abortController.signal,
            });

            if (!response.ok) {
                console.warn('Streaming endpoint returned error, switching to direct send fallback...');
                await sendMessageFallback(convId, prompt, selectedFileIds, currentFilePath, currentFileContent);
                return;
            }

            const reader = response.body?.getReader();
            if (!reader) {
                await sendMessageFallback(convId, prompt, selectedFileIds, currentFilePath, currentFileContent);
                return;
            }

            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (let i = 0; i < lines.length; i++) {
                    const line = lines[i].trim();
                    if (line.startsWith('data: ')) {
                        const jsonStr = line.substring(6);
                        try {
                            const data = JSON.parse(jsonStr);
                            if (data.text) {
                                streamingContent.value += data.text;
                            } else if (data.message_id) {
                                // Stream completed
                                messages.value.push({
                                    id: data.message_id,
                                    role: 'assistant',
                                    content: streamingContent.value,
                                    input_tokens: data.input_tokens,
                                    output_tokens: data.output_tokens,
                                    total_tokens: data.total_tokens,
                                    estimated_cost: data.estimated_cost,
                                    duration_ms: data.duration_ms,
                                    created_at: new Date().toISOString(),
                                });
                                streamingContent.value = '';
                            } else if (data.error) {
                                messages.value.push({
                                    role: 'assistant',
                                    content: `⚠️ ${data.error}`,
                                    created_at: new Date().toISOString(),
                                });
                            }
                        } catch {
                            // ignore malformed chunks
                        }
                    }
                }
            }

            // Refresh user quota in auth store
            const authStore = useAuthStore();
            await authStore.fetchMe();
            // Refresh conversation title if updated
            await selectConversation(convId);
        } catch (err: any) {
            if (err.name === 'AbortError') {
                if (streamingContent.value) {
                    messages.value.push({
                        role: 'assistant',
                        content: streamingContent.value + ' [Stopped]',
                        created_at: new Date().toISOString(),
                    });
                }
            } else {
                try {
                    console.warn('Stream connection failed, falling back to direct send...', err);
                    await sendMessageFallback(convId, prompt, selectedFileIds, currentFilePath, currentFileContent);
                } catch (fallbackErr: any) {
                    messages.value.push({
                        role: 'assistant',
                        content: `⚠️ Error: ${fallbackErr.response?.data?.message || fallbackErr.message || err.message || 'Connection failed'}`,
                        created_at: new Date().toISOString(),
                    });
                }
            }
        } finally {
            isStreaming.value = false;
            streamingContent.value = '';
            abortController = null;
        }
    }

    async function stopGeneration() {
        if (abortController) {
            abortController.abort();
            abortController = null;
        }
        try {
            await api.post('/chat/stop');
        } catch {
            // ignore
        }
        isStreaming.value = false;
    }

    return {
        providers,
        agents,
        conversations,
        currentConversation,
        messages,
        isStreaming,
        streamingContent,
        selectedModelId,
        selectedAgentId,
        fetchCatalog,
        fetchConversations,
        createConversation,
        selectConversation,
        deleteConversation,
        sendMessageStream,
        stopGeneration,
    };
});
