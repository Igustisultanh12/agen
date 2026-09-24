<template>
    <div class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-800" v-html="renderedHtml" @click="handleBlockClick"></div>
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

        return `<div class="relative group my-3 rounded-2xl overflow-hidden border border-slate-800 bg-[#0f172a] shadow-lg">
            <div class="flex items-center justify-between px-4 py-2 bg-[#1e293b] border-b border-slate-700/60 text-xs text-slate-300 font-mono">
                <span class="font-bold text-slate-200">${language}</span>
                <div class="flex items-center space-x-2">
                    <button class="apply-code-btn px-2.5 py-1 rounded-lg bg-blue-600/40 text-blue-300 hover:bg-blue-600 hover:text-white transition-colors text-[11px] font-semibold" data-code="${encodedCode}">Apply to Editor</button>
                    <button class="copy-code-btn px-2.5 py-1 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 transition-colors text-[11px] font-semibold" data-code="${encodedCode}">Salin Kode</button>
                </div>
            </div>
            <pre class="p-4 text-xs overflow-x-auto text-slate-100 font-mono leading-relaxed"><code>${escaped}</code></pre>
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
            target.innerText = 'Tersalin!';
            target.classList.add('bg-emerald-600');
            setTimeout(() => {
                target.innerText = originalText;
                target.classList.remove('bg-emerald-600');
            }, 2000);
        });
    }

    if (target.classList.contains('apply-code-btn')) {
        const rawCode = decodeURIComponent(target.getAttribute('data-code') || '');
        emit('apply-code', rawCode);
        const originalText = target.innerText;
        target.innerText = 'Applied!';
        target.classList.add('bg-blue-700');
        setTimeout(() => {
            target.innerText = originalText;
            target.classList.remove('bg-blue-700');
        }, 1500);
    }
}
</script>
