<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { IconPlus, IconDotsVertical, IconEye, IconClock } from '@tabler/icons-vue';

const props = defineProps({
    posts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const statusTabs = [
    { label: 'All', value: null },
    { label: 'Draft', value: 'draft' },
    { label: 'Scheduled', value: 'scheduled' },
    { label: 'Published', value: 'published' },
];

const filterByStatus = (status) => {
    router.get(route('posts.index'), { status }, { preserveState: true, replace: true });
};

const badgeClass = (status) => {
    const map = {
        scheduled: 'bg-primary-container/30 text-primary border border-primary-container',
        draft: 'bg-surface-container text-on-surface-variant border border-outline-variant/50',
        published: 'bg-success/15 text-success border border-success/30',
        failed: 'bg-error/15 text-error border border-error/30',
    };
    return map[status] || map.draft;
};
</script>

<template>
    <Head title="Post Scheduler" />
    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Post Scheduler</h2>
        </template>
        <template #actions>
            <div class="hidden md:flex items-center border border-outline-variant/50 rounded-lg overflow-hidden">
                <button
                    v-for="tab in statusTabs" :key="tab.label"
                    @click="filterByStatus(tab.value)"
                    :class="['px-3 py-1.5 text-sm font-medium transition-colors',
                        (filters.status || null) === tab.value ? 'bg-[#1a3263] text-white' : 'text-on-surface-variant hover:bg-surface-container']"
                >{{ tab.label }}</button>
            </div>
            <a :href="route('posts.create')" class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all">
                <IconPlus :size="16" :stroke-width="2" /> Buat Post
            </a>
        </template>

        <div class="space-y-4">
            <div v-for="post in posts.data" :key="post.id" class="card hover:shadow-elevated transition-shadow">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <span :class="['text-[11px] font-bold px-2 py-0.5 rounded-full', badgeClass(post.status)]">{{ post.status_label }}</span>
                            <span v-if="post.pillar" class="text-body-sm text-secondary font-medium">{{ post.pillar }}</span>
                            <span v-if="post.scheduled_display" class="text-body-sm text-on-surface-variant flex items-center gap-1">
                                <IconClock :size="14" :stroke-width="1.5" /> {{ post.scheduled_display }}
                            </span>
                        </div>
                        <h3 class="text-base font-semibold text-on-background mb-1">{{ post.hook || 'Untitled Post' }}</h3>
                        <p class="text-body-sm text-on-surface-variant line-clamp-2">{{ post.body }}</p>
                    </div>
                    <div class="flex items-start gap-3 shrink-0">
                        <div v-if="post.media?.length" class="flex gap-1">
                            <div v-for="m in post.media.slice(0, 2)" :key="m.id" class="w-16 h-16 rounded-lg overflow-hidden bg-surface-container">
                                <img v-if="m.type === 'image'" :src="m.url" class="w-full h-full object-cover" />
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <button class="text-on-surface-variant hover:text-on-background p-1 rounded-lg hover:bg-surface-container transition-colors">
                                <IconDotsVertical :size="18" :stroke-width="1.5" />
                            </button>
                            <div v-if="post.status === 'draft'" class="text-right mt-1">
                                <span class="text-body-sm text-on-surface-variant">Belum dijadwalkan</span>
                                <Link :href="route('posts.edit', post.id)" class="text-body-sm text-secondary hover:underline block">Edit</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="posts.data?.length === 0" class="card text-center py-12">
                <p class="text-on-surface-variant mb-4">Belum ada post.</p>
                <a :href="route('posts.create')" class="inline-flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-sm px-4 py-2 rounded-lg">
                    <IconPlus :size="16" :stroke-width="2" /> Buat Post Pertama
                </a>
            </div>
        </div>
    </AppLayout>
</template>
