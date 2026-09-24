<template>
    <div class="min-h-screen w-full flex items-center justify-center p-4 bg-[#0d0f16] bg-gradient-to-br from-[#0d0f16] via-[#121520] to-[#181a28]">
        <div class="w-full max-w-md bg-[#161924] border border-slate-800 rounded-2xl shadow-2xl p-8 space-y-6">
            <!-- App Brand & Title -->
            <div class="text-center space-y-2">
                <div class="inline-flex w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-500 to-purple-500 items-center justify-center shadow-lg shadow-indigo-500/25 mb-2">
                    <span class="text-white font-bold text-lg">AG</span>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">AI Coding Workspace</h1>
                <p class="text-xs text-slate-400">Powered by Free Claude Code (FCC) Engine</p>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMessage" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-400">
                {{ errorMessage }}
            </div>

            <!-- Login Form -->
            <form class="space-y-4" @submit.prevent="handleSubmit">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Email Address</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg bg-[#11131a] border border-slate-700/80 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors"
                        placeholder="you@domain.com"
                    />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-medium text-slate-300">Password</label>
                    </div>
                    <input
                        v-model="password"
                        type="password"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg bg-[#11131a] border border-slate-700/80 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors"
                        placeholder="••••••••"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="isLoading"
                    class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium transition-all shadow-md shadow-blue-600/25 flex items-center justify-center space-x-2"
                >
                    <span v-if="isLoading">Signing in...</span>
                    <span v-else>Sign In</span>
                </button>
            </form>

            <!-- Quick Demo Credentials for Fast Evaluation -->
            <div class="pt-2 border-t border-slate-800 text-center space-y-2">
                <span class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Demo Quick Fill</span>
                <div class="flex items-center justify-center space-x-2">
                    <button
                        type="button"
                        class="px-3 py-1 rounded-md bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs font-medium transition-colors"
                        @click="fillAdmin"
                    >
                        Fill Admin Demo
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1 rounded-md bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/30 text-xs font-medium transition-colors"
                        @click="fillUser"
                    >
                        Fill User Demo
                    </button>
                </div>
            </div>

            <!-- Register link -->
            <div class="text-center text-xs text-slate-400">
                Don't have an account?
                <router-link to="/register" class="text-blue-400 hover:underline font-medium ml-1">Create an account</router-link>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useChatStore } from '../../stores/chat';

const router = useRouter();
const authStore = useAuthStore();
const chatStore = useChatStore();

const email = ref('');
const password = ref('');
const errorMessage = ref('');
const isLoading = ref(false);

function fillAdmin() {
    email.value = 'admin@example.com';
    password.value = 'admin123456';
}

function fillUser() {
    email.value = 'user@example.com';
    password.value = 'user123456';
}

async function handleSubmit() {
    errorMessage.value = '';
    isLoading.value = true;
    try {
        await authStore.login({ email: email.value, password: password.value });
        await chatStore.fetchCatalog();
        await chatStore.fetchConversations();
        router.push('/workspace');
    } catch (err: any) {
        errorMessage.value = err.response?.data?.message || 'Login failed. Please check your credentials.';
    } finally {
        isLoading.value = false;
    }
}
</script>
