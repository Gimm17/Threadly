<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, required: true },
});

const page = usePage();
const isAdmin = computed(() => ['owner', 'admin'].includes(page.props.auth?.user?.role));

const tabs = computed(() => [
    { key: 'general', label: 'Umum', route: 'settings.general', visible: true },
    { key: 'ai-models', label: 'AI Models', route: 'settings.ai-models', visible: isAdmin.value },
]);
</script>

<template>
    <div class="flex gap-1 mb-8 border-b border-outline-variant">
        <Link
            v-for="tab in tabs.filter(item => item.visible)"
            :key="tab.key"
            :href="route(tab.route)"
            :class="[
                'px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors',
                active === tab.key
                    ? 'border-primary text-primary'
                    : 'border-transparent text-on-surface-variant hover:text-on-surface hover:border-outline'
            ]"
        >
            {{ tab.label }}
        </Link>
    </div>
</template>
