<script setup>
import { IconSparkles, IconTrendingUp, IconBulb, IconAlertTriangle } from '@tabler/icons-vue';

defineProps({
    insights: { type: Array, required: true },
});

const getIcon = (type) => {
    switch (type) {
        case 'success': return IconTrendingUp;
        case 'info': return IconBulb;
        case 'warning': return IconAlertTriangle;
        default: return IconBulb;
    }
};

const getIconColor = (type) => {
    switch (type) {
        case 'success': return 'text-secondary';
        case 'info': return 'text-primary';
        case 'warning': return 'text-error';
        default: return 'text-secondary';
    }
};
</script>

<template>
    <div class="card flex flex-col">
        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-outline-variant/30">
            <IconSparkles :size="22" class="text-primary-container" :stroke-width="1.5" />
            <h3 class="text-headline-md text-on-background">AI Insights</h3>
        </div>

        <div class="flex flex-col gap-4 flex-1">
            <div
                v-for="(insight, i) in insights"
                :key="i"
                class="flex items-start gap-3 p-3 bg-surface-container-low rounded-lg border border-surface-variant/50"
            >
                <component
                    :is="getIcon(insight.type)"
                    :size="20"
                    :stroke-width="1.5"
                    :class="['mt-0.5 shrink-0', getIconColor(insight.type)]"
                />
                <div>
                    <h4 class="text-label-caps text-on-background font-bold mb-1">{{ insight.title }}</h4>
                    <p class="text-body-sm text-on-surface-variant">{{ insight.body }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
