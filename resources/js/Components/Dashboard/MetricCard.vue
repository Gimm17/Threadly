<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    delta: { type: [String, Number], default: null },
    deltaLabel: { type: String, default: null },
    icon: { type: Object, required: true },
});
</script>

<template>
    <div class="card relative overflow-hidden">
        <!-- Top color strip -->
        <div class="absolute top-0 left-0 w-full h-1 bg-tertiary-container" />

        <div class="flex justify-between items-start mb-4">
            <p class="text-label-caps text-on-surface-variant">{{ label }}</p>
            <component :is="icon" :size="20" class="text-secondary" :stroke-width="1.5" />
        </div>

        <div class="flex items-baseline gap-2">
            <h3 class="text-display-metric text-on-background">{{ value }}</h3>
            <span
                v-if="delta !== null && delta !== undefined"
                class="text-sm font-semibold flex items-center gap-0.5"
                :class="typeof delta === 'number' && delta >= 0 ? 'text-primary' : 'text-error'"
            >
                <svg v-if="typeof delta === 'number' && delta >= 0" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5m-7 7l7-7 7 7"/></svg>
                <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14m7-7l-7 7-7-7"/></svg>
                {{ typeof delta === 'number' ? (delta >= 0 ? '+' : '') + delta : delta }}
            </span>
            <span v-if="deltaLabel" class="text-body-sm text-on-surface-variant">{{ deltaLabel }}</span>
        </div>
    </div>
</template>
