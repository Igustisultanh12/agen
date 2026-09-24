<template>
    <div class="min-h-screen w-full flex items-center justify-center p-4 bg-[#0d0f16] bg-gradient-to-br from-[#0d0f16] via-[#121520] to-[#181a28]">
        <div class="w-full max-w-md bg-[#161924] border border-slate-800 rounded-2xl shadow-2xl p-8 space-y-6">
            <!-- App Brand & Title -->
            <div class="text-center space-y-2">
                <div class="inline-flex w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-500 to-purple-500 items-center justify-center shadow-lg shadow-indigo-500/25 mb-2">
                    <span class="text-white font-bold text-lg">AG</span>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Create Workspace Account</h1>
                <p class="text-xs text-slate-400">Get started with free AI token quota instantly</p>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMessage" class="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-xs text-red-400">
                {{ errorMessage }}
            </div>

            <!-- Register Form -->
            <form class="space-y-4" @submit.prevent="handleSubmit">
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Full Name</label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg bg-[#11131a] border border-slate-700/80 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors"
                        placeholder="John Doe"
                    />
                </div>

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
                    <label class="block text-xs font-medium text-slate-300 mb-1">Password</label>
                    <input
                        v-model="password"
                        type="password"
                        required
                        minlength="8"
                        class="w-full px-3.5 py-2.5 rounded-lg bg-[#11131a] border border-slate-700/80 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors"
                        placeholder="Minimum 8 characters"
                    />
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1">Confirm Password</label>
                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        required
                        class="w-full px-3.5 py-2.5 rounded-lg bg-[#11131a] border border-slate-700/80 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition-colors"
                        placeholder="Repeat password"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="isLoading"
                    class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium transition-all shadow-md shadow-blue-600/25 flex items-center justify-center space-x-2"
                >
                    <span v-if="isLoading">Creating account...</span>
                    <span v-else>Register & Get Started</span>
                </button>
            </form>

            <!-- Login link -->
            <div class="text-center text-xs text-slate-400">
                Already have an account?
                <router-link to="/login" class="text-blue-400 hover:underline font-medium ml-1">Sign in here</router-link>
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

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const errorMessage = ref('');
const isLoading = ref(false);

async function handleSubmit() {
    if (password.value !== passwordConfirmation.value) {
        errorMessage.value = 'Passwords do not match.';
        return;
    }

    errorMessage.value = '';
    isLoading.value = true;
    try {
        await authStore.register({
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });
        await chatStore.fetchCatalog();
        await chatStore.fetchConversations();
        router.push('/workspace');
    } catch (err: any) {
        errorMessage.value = err.response?.data?.message || 'Registration failed. Please check your details.';
    } finally {
        isLoading.value = false;
    }
}
</script>
