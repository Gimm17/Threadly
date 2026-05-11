<script setup>
defineProps({
    hooks: { type: Array, required: true },
});

const formatCount = (n) => {
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return n.toString();
};
</script>

<template>
    <div class="card">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-headline-md text-on-background">Top Hooks</h3>
            <span class="text-label-caps text-on-surface-variant bg-surface-container px-2 py-1 rounded-md text-[11px]">
                Berdasarkan Save Rate
            </span>
        </div>

        <div class="space-y-3">
            <div
                v-for="(hook, i) in hooks"
                :key="hook.id"
                class="border border-outline-variant/40 rounded-lg p-3 hover:border-secondary transition-colors"
            >
                <div class="flex justify-between items-start mb-2 gap-2">
                    <p class="text-body-sm text-on-background font-semibold leading-snug">
                        "{{ hook.hook_text }}"
                    </p>
                    <span
                        :class="[
                            'shrink-0 font-bold text-xs px-2 py-0.5 rounded',
                            i === 0
                                ? 'text-primary bg-primary-container/20'
                                : 'text-on-surface-variant bg-surface-variant'
                        ]"
                    >
                        #{{ i + 1 }}
                    </span>
                </div>
                <div class="flex items-center gap-4 text-xs text-on-surface-variant">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-4-7 4V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
                        {{ formatCount(hook.save_count) }} Saves
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        {{ formatCount(hook.view_count) }} Views
                    </span>
                </div>
            </div>
        </div>

        <div v-if="hooks.length === 0" class="py-8 text-center text-on-surface-variant text-sm">
            Belum ada hook tersimpan.
        </div>
    </div>
</template>
