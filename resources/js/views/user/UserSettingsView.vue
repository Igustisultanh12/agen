<template>
    <div class="h-full w-full overflow-y-auto p-8 bg-[#0d0f16] space-y-8">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-5">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Account & AI Preferences</h1>
                <p class="text-xs text-slate-400 mt-1">Manage your profile, credentials, and custom AI coding instructions.</p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-1 text-xs rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-medium">
                    Plan: {{ authStore.user?.user_group?.name || 'Free' }}
                </span>
                <span class="px-2.5 py-1 text-xs rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium capitalize">
                    {{ authStore.user?.role }}
                </span>
            </div>
        </div>

        <!-- Success / Error alerts -->
        <div v-if="alertMessage" :class="alertSuccess ? 'bg-emerald-950/40 border-emerald-500/40 text-emerald-300' : 'bg-red-950/40 border-red-500/40 text-red-300'" class="p-4 rounded-xl border text-xs flex items-center justify-between">
            <span>{{ alertMessage }}</span>
            <button @click="alertMessage = ''" class="text-xs opacity-75 hover:opacity-100">&times;</button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Profile & AI Custom Instructions -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Profile Settings Card -->
                <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-6">
                    <h2 class="text-base font-semibold text-white flex items-center space-x-2">
                        <span>👤</span>
                        <span>Profile Information</span>
                    </h2>

                    <form @submit.prevent="handleUpdateProfile" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Full Name</label>
                                <input
                                    v-model="profileForm.name"
                                    type="text"
                                    required
                                    class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Email Address</label>
                                <input
                                    :value="authStore.user?.email"
                                    type="email"
                                    disabled
                                    class="w-full px-3 py-2 rounded-lg bg-[#0d0f16]/60 border border-slate-800 text-sm text-slate-500 cursor-not-allowed"
                                />
                                <span class="text-[10px] text-slate-500 mt-0.5 block">Email address cannot be changed directly</span>
                            </div>
                        </div>

                        <!-- Custom Instructions (Requirement 19 / Prompt injection) -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-medium text-slate-300">Custom AI Instructions</label>
                                <span class="text-[11px] text-slate-400 font-mono">{{ profileForm.custom_instructions.length }}/5000</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mb-2">
                                What would you like the AI models to know about you to provide better responses? These instructions will be injected into every conversation.
                            </p>
                            <textarea
                                v-model="profileForm.custom_instructions"
                                rows="6"
                                maxlength="5000"
                                placeholder="Example:
- I am building a Laravel 12 + Vue 3 TypeScript project
- Prefer clean functional architecture with Pinia stores
- Always include type definitions and error handling
- Format code cleanly without redundant comments"
                                class="w-full px-3.5 py-2.5 rounded-lg bg-[#0d0f16] border border-slate-700 text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500 placeholder-slate-600 leading-relaxed"
                            ></textarea>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                :disabled="savingProfile"
                                class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all"
                            >
                                {{ savingProfile ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password Update Card -->
                <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-6">
                    <h2 class="text-base font-semibold text-white flex items-center space-x-2">
                        <span>🔒</span>
                        <span>Security & Password</span>
                    </h2>

                    <form @submit.prevent="handleChangePassword" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Current Password</label>
                            <input
                                v-model="passwordForm.current_password"
                                type="password"
                                required
                                class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-blue-500"
                            />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">New Password</label>
                                <input
                                    v-model="passwordForm.password"
                                    type="password"
                                    required
                                    minlength="8"
                                    class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-blue-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Confirm New Password</label>
                                <input
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    required
                                    minlength="8"
                                    class="w-full px-3 py-2 rounded-lg bg-[#0d0f16] border border-slate-700 text-sm text-slate-100 focus:outline-none focus:border-blue-500"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                :disabled="savingPassword"
                                class="px-5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold disabled:opacity-50 transition-all"
                            >
                                {{ savingPassword ? 'Updating...' : 'Update Password' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right 1 Col: Plan & Quota Summary -->
            <div class="space-y-6">
                <div class="p-6 rounded-2xl bg-[#141622] border border-slate-800 space-y-5">
                    <h2 class="text-base font-semibold text-white flex items-center space-x-2">
                        <span>⚡</span>
                        <span>Plan & Quota</span>
                    </h2>

                    <div class="space-y-4">
                        <div class="p-4 rounded-xl bg-[#0d0f16] border border-slate-800/80 space-y-2">
                            <div class="text-xs text-slate-400">Current Plan</div>
                            <div class="text-lg font-bold text-white">{{ authStore.user?.user_group?.name || 'Free' }}</div>
                            <p class="text-[11px] text-slate-400">Access to leading open source and proprietary AI models through Free Claude Code.</p>
                        </div>

                        <div v-if="authStore.quota" class="space-y-3 pt-2">
                            <div>
                                <div class="flex justify-between text-xs text-slate-300 mb-1">
                                    <span>Monthly Token Usage</span>
                                    <span class="font-mono text-blue-400">{{ authStore.quota.monthly_usage_percentage }}%</span>
                                </div>
                                <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-blue-500 transition-all duration-300"
                                        :style="{ width: `${Math.min(100, authStore.quota.monthly_usage_percentage)}%` }"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-400 font-mono mt-1">
                                    <span>{{ (authStore.quota.monthly_used_tokens / 1000000).toFixed(2) }}M used</span>
                                    <span>{{ (authStore.quota.monthly_token_limit / 1000000).toFixed(0) }}M limit</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs text-slate-300 mb-1">
                                    <span>Daily Token Usage</span>
                                    <span class="font-mono text-purple-400">{{ authStore.quota.daily_usage_percentage }}%</span>
                                </div>
                                <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-purple-500 transition-all duration-300"
                                        :style="{ width: `${Math.min(100, authStore.quota.daily_usage_percentage)}%` }"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-400 font-mono mt-1">
                                    <span>{{ (authStore.quota.daily_used_tokens / 1000).toFixed(0) }}k used</span>
                                    <span>{{ (authStore.quota.daily_token_limit / 1000).toFixed(0) }}k limit</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
                                <span class="text-slate-400">Total Month Cost:</span>
                                <span class="font-mono font-bold text-emerald-400">${{ (authStore.quota.total_cost_usd || 0).toFixed(3) }}</span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <router-link
                                to="/usage"
                                class="w-full block text-center py-2 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 transition-colors"
                            >
                                Detailed Usage Analytics →
                            </router-link>
                        </div>
                    </div>
                </div>

                <!-- Attribution Card -->
                <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-[#141622] border border-slate-800/80 space-y-2">
                    <div class="text-xs font-semibold text-slate-300">Free Claude Code (FCC)</div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Powered by the Free Claude Code open-source proxy backend engine. Provides seamless multi-provider routing for deep code generation.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import api from '../../api/client';

const authStore = useAuthStore();

const profileForm = reactive({
    name: '',
    custom_instructions: '',
});

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const savingProfile = ref(false);
const savingPassword = ref(false);
const alertMessage = ref('');
const alertSuccess = ref(true);

onMounted(() => {
    if (authStore.user) {
        profileForm.name = authStore.user.name || '';
        profileForm.custom_instructions = authStore.user.custom_instructions || '';
    }
});

async function handleUpdateProfile() {
    savingProfile.value = true;
    alertMessage.value = '';
    try {
        await authStore.updateProfile({
            name: profileForm.name,
            custom_instructions: profileForm.custom_instructions,
        });
        alertSuccess.value = true;
        alertMessage.value = 'Profile and AI custom instructions updated successfully.';
    } catch (err: any) {
        alertSuccess.value = false;
        alertMessage.value = err.response?.data?.message || 'Failed to update profile.';
    } finally {
        savingProfile.value = false;
    }
}

async function handleChangePassword() {
    if (passwordForm.password !== passwordForm.password_confirmation) {
        alertSuccess.value = false;
        alertMessage.value = 'Password confirmation does not match.';
        return;
    }

    savingPassword.value = true;
    alertMessage.value = '';
    try {
        await api.put('/auth/password', passwordForm);
        alertSuccess.value = true;
        alertMessage.value = 'Password updated successfully.';
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
    } catch (err: any) {
        alertSuccess.value = false;
        alertMessage.value = err.response?.data?.message || 'Failed to change password.';
    } finally {
        savingPassword.value = false;
    }
}
</script>
