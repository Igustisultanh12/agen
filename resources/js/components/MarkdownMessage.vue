<template>
    <div class="prose prose-invert max-w-none text-sm leading-relaxed" v-html="renderedHtml" @click="handleBlockClick"></div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import MarkdownIt from 'markdown-it';

const props = defineProps<{
    content: string;
}>();

const emit = defineEmits<{
    (e: 'apply-code', code: string): void;
}>();

const md: any = new MarkdownIt({
    html: false,
    linkify: true,
    breaks: true,
    highlight: function (str: string, lang: string) {
        const language = lang || 'plaintext';
        const escaped = md.utils.escapeHtml(str);
        const encodedCode = encodeURIComponent(str);

        return `<div class="relative group my-3 rounded-lg overflow-hidden border border-slate-700/60 bg-[#12141c]">
            <div class="flex items-center justify-between px-3 py-1.5 bg-[#181b24] border-b border-slate-700/50 text-xs text-slate-400 font-mono">
                <span>${language}</span>
                <div class="flex items-center space-x-2">
                    <button class="apply-code-btn px-2 py-0.5 rounded bg-blue-600/30 text-blue-400 hover:bg-blue-600/50 transition-colors" data-code="${encodedCode}">Apply to Editor</button>
                    <button class="copy-code-btn px-2 py-0.5 rounded bg-slate-700 hover:bg-slate-600 text-slate-200 transition-colors" data-code="${encodedCode}">Copy</button>
                </div>
            </div>
            <pre class="p-3 text-xs overflow-x-auto text-slate-200 font-mono"><code>${escaped}</code></pre>
        </div>`;
    }
});

const renderedHtml = computed(() => {
    return md.render(props.content || '');
});

function handleBlockClick(e: MouseEvent) {
    const target = e.target as HTMLElement;

    if (target.classList.contains('copy-code-btn')) {
        const rawCode = decodeURIComponent(target.getAttribute('data-code') || '');
        navigator.clipboard.writeText(rawCode).then(() => {
            const originalText = target.innerText;
            target.innerText = 'Copied!';
            target.classList.add('bg-green-600');
            setTimeout(() => {
                target.innerText = originalText;
                target.classList.remove('bg-green-600');
            }, 2000);
        });
    }

    if (target.classList.contains('apply-code-btn')) {
        const rawCode = decodeURIComponent(target.getAttribute('data-code') || '');
        emit('apply-code', rawCode);
    }
}
</script>
