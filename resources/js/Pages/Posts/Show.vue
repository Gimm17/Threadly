<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    IconArrowLeft,
    IconCalendarTime,
    IconEdit,
    IconExternalLink,
    IconHash,
    IconInfoCircle,
    IconLink,
    IconNotes,
    IconPhoto,
    IconSparkles,
    IconVideo,
} from '@tabler/icons-vue';

const props = defineProps({
    post: { type: Object, required: true },
});

const selectedMediaId = ref(props.post.media?.[0]?.id ?? null);

const selectedMedia = computed(() => {
    return props.post.media?.find((media) => media.id === selectedMediaId.value) || props.post.media?.[0] || null;
});

const hashtags = computed(() => {
    const matches = `${props.post.body || ''}`.match(/#[\p{L}\p{N}_]+/gu) || [];
    return [...new Set(matches)];
});

const bodyWithoutHashtags = computed(() => {
    if (!props.post.body) return '';
    return props.post.body.replace(/\s*(#[\p{L}\p{N}_]+\s*)+$/u, '').trim();
});

const bodyParagraphs = computed(() => {
    return bodyWithoutHashtags.value
        .split(/\n{2,}|\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);
});

const scheduleLabel = computed(() => {
    if (props.post.status === 'scheduled') {
        return props.post.scheduled_display || 'Scheduled';
    }

    if (props.post.status === 'published') {
        return props.post.published_display || 'Published';
    }

    if (props.post.status === 'failed') {
        return 'Publish gagal';
    }

    return 'Belum dijadwalkan';
});

const statusClass = computed(() => {
    const map = {
        scheduled: 'bg-warning/10 text-warning border-warning/30',
        draft: 'bg-surface-container text-on-surface-variant border-outline-variant/60',
        published: 'bg-success/15 text-success border-success/30',
        failed: 'bg-error/15 text-error border-error/30',
        cancelled: 'bg-surface-container text-on-surface-variant border-outline-variant/60',
    };

    return map[props.post.status] || map.draft;
});

const mediaTypeLabel = (media) => {
    if (!media) return 'Media';
    return media.type === 'video' ? 'Video' : 'Image';
};

const fileSizeLabel = (bytes) => {
    if (!bytes) return '0 KB';
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <Head title="Detail Post" />
    <AppLayout>
        <template #header>
            <div>
                <Link :href="route('posts.index')" class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary mb-1">
                    <IconArrowLeft :size="14" :stroke-width="1.7" />
                    Draft & Jadwal
                </Link>
                <h2 class="text-headline-md font-bold text-primary">Detail Post</h2>
            </div>
        </template>

        <template #actions>
            <Link
                :href="route('posts.edit', post.id)"
                class="inline-flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all"
            >
                <IconEdit :size="16" :stroke-width="1.8" />
                Edit Post
            </Link>
        </template>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_420px] gap-6">
            <div class="space-y-6">
                <section class="card">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-3">
                                <span :class="['text-[11px] font-bold px-2.5 py-1 rounded-full border', statusClass]">
                                    {{ post.status_label }}
                                </span>
                                <span v-if="post.pillar" class="inline-flex items-center gap-1 text-xs font-semibold text-secondary">
                                    <span
                                        class="w-2 h-2 rounded-full"
                                        :style="{ backgroundColor: post.pillar.color_hex || '#3B82F6' }"
                                    />
                                    {{ post.pillar.name }}
                                </span>
                            </div>
                            <h3 class="text-xl font-bold text-on-background leading-snug">
                                {{ post.hook || 'Untitled Post' }}
                            </h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="rounded-lg bg-surface-container-low border border-outline-variant/50 p-3">
                            <p class="text-xs font-semibold text-on-surface-variant mb-1">Status</p>
                            <p class="text-sm font-bold text-on-background">{{ post.status_label }}</p>
                        </div>
                        <div class="rounded-lg bg-surface-container-low border border-outline-variant/50 p-3">
                            <p class="text-xs font-semibold text-on-surface-variant mb-1">Jadwal</p>
                            <p class="text-sm font-bold text-on-background">{{ scheduleLabel }}</p>
                        </div>
                        <div class="rounded-lg bg-surface-container-low border border-outline-variant/50 p-3">
                            <p class="text-xs font-semibold text-on-surface-variant mb-1">Metode</p>
                            <p class="text-sm font-bold text-on-background">{{ post.publish_mode || 'Manual' }}</p>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconNotes :size="20" :stroke-width="1.5" class="text-primary" />
                        Struktur Postingan
                    </h3>

                    <div class="space-y-5">
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <h4 class="text-sm font-bold text-on-background">Hook</h4>
                                <span class="text-xs text-on-surface-variant">{{ (post.hook || '').length }} / 150</span>
                            </div>
                            <div class="rounded-lg border border-outline-variant/60 bg-surface-container-low p-4">
                                <p class="text-base font-semibold text-on-background leading-relaxed">
                                    {{ post.hook || '-' }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <h4 class="text-sm font-bold text-on-background">Isi Post</h4>
                                <span class="text-xs text-on-surface-variant">{{ (post.body || '').length }} / 500</span>
                            </div>
                            <div class="rounded-lg border border-outline-variant/60 bg-white p-4 space-y-3">
                                <p
                                    v-for="(paragraph, index) in bodyParagraphs"
                                    :key="index"
                                    class="text-sm text-on-background leading-relaxed"
                                >
                                    {{ paragraph }}
                                </p>
                                <p v-if="!bodyParagraphs.length" class="text-sm text-on-surface-variant">Belum ada isi post.</p>
                            </div>
                        </div>

                        <div v-if="hashtags.length">
                            <h4 class="text-sm font-bold text-on-background mb-2 flex items-center gap-1.5">
                                <IconHash :size="16" :stroke-width="1.6" />
                                Hashtag
                            </h4>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="tag in hashtags"
                                    :key="tag"
                                    class="px-2.5 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold"
                                >
                                    {{ tag }}
                                </span>
                            </div>
                        </div>

                        <div v-if="post.link_url">
                            <h4 class="text-sm font-bold text-on-background mb-2 flex items-center gap-1.5">
                                <IconLink :size="16" :stroke-width="1.6" />
                                Link
                            </h4>
                            <a
                                :href="post.link_url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-secondary hover:underline break-all"
                            >
                                {{ post.link_url }}
                                <IconExternalLink :size="14" :stroke-width="1.6" />
                            </a>
                        </div>

                        <div v-if="post.notes">
                            <h4 class="text-sm font-bold text-on-background mb-2">Catatan Internal</h4>
                            <div class="rounded-lg border border-outline-variant/60 bg-surface-container-low p-4">
                                <p class="text-sm text-on-surface-variant whitespace-pre-line">{{ post.notes }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconPhoto :size="20" :stroke-width="1.5" class="text-primary" />
                        Media
                    </h3>

                    <div v-if="selectedMedia" class="space-y-4">
                        <div class="rounded-xl overflow-hidden border border-outline-variant/60 bg-surface-container-low">
                            <div class="aspect-square w-full bg-surface-container flex items-center justify-center">
                                <img
                                    v-if="selectedMedia.type !== 'video'"
                                    :src="selectedMedia.url"
                                    :alt="selectedMedia.file_name || 'Media post'"
                                    class="w-full h-full object-contain bg-white"
                                />
                                <video
                                    v-else
                                    :src="selectedMedia.url"
                                    class="w-full h-full object-contain bg-black"
                                    controls
                                />
                            </div>
                        </div>

                        <div class="rounded-lg border border-outline-variant/60 bg-surface-container-low p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-on-background truncate">{{ selectedMedia.file_name || 'Media post' }}</p>
                                    <p class="text-xs text-on-surface-variant mt-1">
                                        {{ mediaTypeLabel(selectedMedia) }} - {{ fileSizeLabel(selectedMedia.file_size) }}
                                    </p>
                                </div>
                                <span v-if="selectedMedia.is_ai_generated" class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-full bg-secondary/10 text-secondary text-[11px] font-bold">
                                    <IconSparkles :size="12" :stroke-width="1.7" />
                                    AI
                                </span>
                            </div>
                            <p v-if="selectedMedia.ai_prompt" class="text-xs text-on-surface-variant mt-3 leading-relaxed">
                                {{ selectedMedia.ai_prompt }}
                            </p>
                        </div>

                        <div v-if="post.media.length > 1" class="grid grid-cols-4 gap-2">
                            <button
                                v-for="media in post.media"
                                :key="media.id"
                                type="button"
                                :class="[
                                    'aspect-square rounded-lg overflow-hidden border transition-colors bg-surface-container flex items-center justify-center',
                                    selectedMediaId === media.id ? 'border-primary ring-2 ring-primary/20' : 'border-outline-variant/60 hover:border-primary/60'
                                ]"
                                @click="selectedMediaId = media.id"
                            >
                                <img v-if="media.type !== 'video'" :src="media.url" alt="" class="w-full h-full object-cover" />
                                <IconVideo v-else :size="22" :stroke-width="1.5" class="text-on-surface-variant" />
                            </button>
                        </div>
                    </div>

                    <div v-else class="rounded-xl border border-dashed border-outline-variant p-8 text-center">
                        <IconPhoto :size="32" :stroke-width="1.2" class="mx-auto text-on-surface-variant/50 mb-2" />
                        <p class="text-sm font-semibold text-on-background">Tidak ada media</p>
                        <p class="text-xs text-on-surface-variant mt-1">Tambahkan gambar atau video dari halaman edit.</p>
                    </div>
                </section>

                <section class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconInfoCircle :size="20" :stroke-width="1.5" class="text-primary" />
                        Metadata
                    </h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-on-surface-variant">Dibuat</dt>
                            <dd class="font-semibold text-on-background text-right">{{ post.created_at || '-' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-on-surface-variant">Update terakhir</dt>
                            <dd class="font-semibold text-on-background text-right">{{ post.updated_at || '-' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-on-surface-variant">Creator</dt>
                            <dd class="font-semibold text-on-background text-right">{{ post.creator || '-' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-on-surface-variant flex items-center gap-1">
                                <IconCalendarTime :size="14" :stroke-width="1.5" />
                                Published
                            </dt>
                            <dd class="font-semibold text-on-background text-right">{{ post.published_display || '-' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-on-surface-variant">Threads ID</dt>
                            <dd class="font-semibold text-on-background text-right break-all">{{ post.threads_post_id || '-' }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
