<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    IconAlertCircle,
    IconAlignLeft,
    IconCalendarPlus,
    IconCheck,
    IconExternalLink,
    IconEye,
    IconHash,
    IconHistory,
    IconLoader2,
    IconPhoto,
    IconSend,
    IconSparkles,
    IconUpload,
    IconVideo,
    IconX,
} from '@tabler/icons-vue';

const props = defineProps({
    pillars: { type: Array, default: () => [] },
    generatedPosts: { type: Array, default: () => [] },
    hasThreadsAccess: { type: Boolean, default: false },
    prefill: { type: Object, default: () => ({}) },
});

const form = useForm({
    body: props.prefill.body || '',
    hook: props.prefill.hook || '',
    content_pillar_id: null,
    status: 'draft',
    scheduled_at: '',
    publish_mode: 'manual',
    link_url: '',
    media: [],
    generated_media_ids: [],
});

const hookCount = computed(() => form.hook.length);
const bodyCount = computed(() => form.body.length);
const fileInput = ref(null);
const isDragging = ref(false);
const mediaError = ref('');
const mediaPreviews = ref([]);
const totalMediaCount = computed(() => mediaPreviews.value.length);
const readyBrief = ref('');
const readyGenerateImage = ref(true);
const readyStyle = ref('photography');
const readyAspectRatio = ref('1:1');
const imagePromptState = ref(null);
const mediaPolling = ref({});
const mediaPollAttempts = ref({});
const currentGeneratedPostId = ref(null);
const generatedActionLoading = ref('');
const generatedActionMessage = ref('');
const generatedHistory = ref(props.generatedPosts.map((item) => ({
    ...item,
    hashtags: item.hashtags || [],
})));
const currentGeneratedPost = computed(() => generatedHistory.value.find((item) => item.id === currentGeneratedPostId.value) || null);
const styleLabels = {
    photography: 'Photography',
    realistic: 'Realistic',
    minimalist: 'Minimalist',
    illustration: 'Illustration',
    '3d': '3D Render',
    cartoon: 'Cartoon',
};
const aspectRatioLabels = {
    '1:1': '1:1 Square',
    '4:5': '4:5 Portrait',
    '16:9': '16:9 Wide',
    '9:16': '9:16 Story',
};
const imageSizeLabels = {
    '1:1': '1024 x 1024',
    '4:5': '1024 x 1280',
    '16:9': '1536 x 1024',
    '9:16': '1024 x 1536',
};
const selectedStyleLabel = computed(() => styleLabels[readyStyle.value] || readyStyle.value);
const selectedAspectRatioLabel = computed(() => aspectRatioLabels[readyAspectRatio.value] || readyAspectRatio.value);
const selectedImageSize = computed(() => imageSizeLabels[readyAspectRatio.value] || '1024 x 1024');

const submit = (status) => {
    form.status = status;
    form.post(route('posts.store'), {
        forceFormData: true,
    });
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

const upsertGeneratedPost = (item) => {
    if (!item?.id) return;

    const normalized = {
        ...item,
        hashtags: item.hashtags || [],
    };
    const index = generatedHistory.value.findIndex((existing) => existing.id === normalized.id);

    if (index >= 0) {
        generatedHistory.value[index] = normalized;
    } else {
        generatedHistory.value.unshift(normalized);
    }
};

const editorPayload = (item = null) => {
    const useEditor = !item || item.id === currentGeneratedPostId.value;

    return {
        hook: useEditor ? form.hook : item.hook,
        body: useEditor ? form.body : item.body,
        content_pillar_id: useEditor ? form.content_pillar_id : null,
        link_url: useEditor ? form.link_url : '',
    };
};

const generatedStatusLabel = (status) => ({
    generated: 'Generated',
    drafted: 'Drafted',
    scheduled: 'Scheduled',
    publishing: 'Publishing',
    published: 'Published',
    failed: 'Failed',
}[status] || status);

const generatedStatusClass = (status) => ({
    generated: 'bg-primary/10 text-primary border-primary/20',
    drafted: 'bg-surface-container text-on-surface-variant border-outline-variant',
    scheduled: 'bg-warning/10 text-warning border-warning/30',
    publishing: 'bg-secondary/10 text-secondary border-secondary/30',
    published: 'bg-success/15 text-success border-success/30',
    failed: 'bg-error/15 text-error border-error/30',
}[status] || 'bg-surface-container text-on-surface-variant border-outline-variant');

const clearAiMediaPreviews = () => {
    mediaPreviews.value = mediaPreviews.value.filter((preview) => preview.source !== 'ai');
    form.generated_media_ids = [];
};

const openGeneratedPost = (item) => {
    form.hook = item.hook || '';
    form.body = item.body || '';
    currentGeneratedPostId.value = item.id;
    imagePromptState.value = item.image_prompt || null;
    generatedActionMessage.value = 'Hasil generate dibuka di editor.';
    clearAiMediaPreviews();

    if (item.media) {
        applyGeneratedMedia(item.media);

        if (['queued', 'processing'].includes(item.media.generation_status)) {
            pollGeneratedMedia(item.media.id);
        }
    }

    if (!item.media && item.image_error) {
        mediaError.value = item.image_error;
    }
};

const generatedActionRequest = async (action, item = null, extra = {}) => {
    const target = item || currentGeneratedPost.value;

    if (!target?.id) {
        generatedActionMessage.value = 'Generate Post Lengkap dulu, lalu pilih action ini.';
        return;
    }

    generatedActionLoading.value = `${action}-${target.id}`;
    generatedActionMessage.value = '';

    try {
        const res = await fetch(route(`generated-posts.${action}`, target.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                ...editorPayload(target),
                ...extra,
            }),
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            generatedActionMessage.value = data.message || 'Action gagal diproses.';
            return;
        }

        upsertGeneratedPost(data.generated_post);
        currentGeneratedPostId.value = data.generated_post.id;
        generatedActionMessage.value = data.message;
    } catch (e) {
        generatedActionMessage.value = 'Koneksi gagal. Coba lagi.';
    } finally {
        generatedActionLoading.value = '';
    }
};

const saveGeneratedDraft = (item = null) => generatedActionRequest('save-draft', item);

const scheduleGeneratedPost = (item = null) => {
    if (!form.scheduled_at) {
        generatedActionMessage.value = 'Isi tanggal dan waktu publish sebelum Schedule.';
        return;
    }

    generatedActionRequest('schedule', item, {
        scheduled_at: form.scheduled_at,
        publish_mode: form.publish_mode || 'manual',
    });
};

const publishGeneratedPost = (item = null) => {
    if (!props.hasThreadsAccess) {
        generatedActionMessage.value = 'Publish Now butuh Threads API token. Atur dulu di Settings.';
        return;
    }

    generatedActionRequest('publish-now', item);
};

const saveDraft = () => {
    if (currentGeneratedPost.value) {
        saveGeneratedDraft();
        return;
    }

    submit('draft');
};

const schedulePost = () => {
    if (currentGeneratedPost.value) {
        scheduleGeneratedPost();
        return;
    }

    submit('scheduled');
};

const publishNow = () => {
    if (!currentGeneratedPost.value) {
        generatedActionMessage.value = 'Gunakan Generate Post Lengkap dulu agar hasil AI bisa langsung dipublish.';
        return;
    }

    publishGeneratedPost();
};

const addMediaFiles = (fileList) => {
    mediaError.value = '';
    const files = Array.from(fileList || []);
    const availableSlots = 4 - totalMediaCount.value;

    if (availableSlots <= 0) {
        mediaError.value = 'Maksimal 4 media per post.';
        return;
    }

    if (files.length > availableSlots) {
        mediaError.value = `Hanya ${availableSlots} file lagi yang bisa ditambahkan.`;
    }

    files.slice(0, availableSlots).forEach((file) => {
        const previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
        form.media.push(file);
        mediaPreviews.value.push({
            id: `${file.name}-${file.lastModified}-${file.size}`,
            name: file.name,
            type: file.type,
            size: file.size,
            url: previewUrl,
            source: 'upload',
            isObjectUrl: Boolean(previewUrl),
        });
    });

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const handleFileChange = (event) => {
    addMediaFiles(event.target.files);
};

const removeMedia = (index) => {
    const [preview] = mediaPreviews.value.splice(index, 1);
    if (preview?.isObjectUrl && preview?.url) {
        URL.revokeObjectURL(preview.url);
    }

    if (preview?.source === 'ai') {
        form.generated_media_ids = form.generated_media_ids.filter((id) => id !== preview.generated_media_id);
        return;
    }

    form.media = form.media.filter((file) => `${file.name}-${file.lastModified}-${file.size}` !== preview?.id);
};

const chooseFiles = () => {
    fileInput.value?.click();
};

const dropFiles = (event) => {
    isDragging.value = false;
    addMediaFiles(event.dataTransfer?.files);
};

const fileSizeLabel = (bytes) => {
    if (!bytes) return '0 KB';
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const qualityChecks = computed(() => [
    { label: 'Hook kurang dari 150 karakter', passed: form.hook.length > 0 && form.hook.length <= 150 },
    { label: 'Mengandung Call to Action (CTA)', passed: /\b(coba|simpan|share|klik|follow|tag|komen)\b/i.test(form.body) },
    { label: 'Isi post terisi', passed: form.body.length > 10 },
]);

// ── AI Integration ──
const aiLoading = ref('');
const aiError = ref('');
const hookSuggestions = ref([]);

const appendGeneratedMediaPreview = (media) => {
    if (!media?.id || media.generation_status !== 'completed' || form.generated_media_ids.includes(media.id) || totalMediaCount.value >= 4) {
        return;
    }

    form.generated_media_ids.push(media.id);
    mediaPreviews.value.push({
        id: `ai-${media.id}`,
        generated_media_id: media.id,
        name: media.file_name || 'AI poster',
        type: media.mime_type || 'image/png',
        size: media.file_size || 0,
        url: media.url || null,
        source: 'ai',
        isObjectUrl: false,
    });
};

const applyGeneratedMedia = (media) => {
    if (!media?.id) return;

    if (media.generation_status === 'completed') {
        clearAiMediaPreviews();
        appendGeneratedMediaPreview(media);
        imagePromptState.value = {
            ...(imagePromptState.value || {}),
            status: 'generated',
            error: null,
        };
        generatedActionMessage.value = 'Gambar AI sudah selesai dan masuk ke preview.';
        return;
    }

    if (['queued', 'processing'].includes(media.generation_status)) {
        imagePromptState.value = {
            ...(imagePromptState.value || {}),
            status: media.generation_status,
            error: null,
        };
        generatedActionMessage.value = 'Gambar AI sedang diproses di background.';
        return;
    }

    if (media.generation_status === 'failed') {
        const message = media.error_message || 'Image AI gagal dibuat. Cek model image atau provider di Settings.';
        mediaError.value = message;
        imagePromptState.value = {
            ...(imagePromptState.value || {}),
            status: 'failed',
            error: message,
        };
    }
};

const pollGeneratedMedia = (mediaId, attempt = 0) => {
    if (!mediaId || mediaPolling.value[mediaId]) return;

    mediaPollAttempts.value[mediaId] = attempt;
    mediaPolling.value[mediaId] = window.setInterval(async () => {
        try {
            mediaPollAttempts.value[mediaId] = (mediaPollAttempts.value[mediaId] || 0) + 1;
            const res = await fetch(route('image-studio.jobs.show', mediaId), {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            const media = data.media;

            if (!media) return;

            if (currentGeneratedPost.value) {
                upsertGeneratedPost({
                    ...currentGeneratedPost.value,
                    media,
                });
            }
            applyGeneratedMedia(media);

            if (['completed', 'failed'].includes(media.generation_status) || mediaPollAttempts.value[mediaId] >= 60) {
                window.clearInterval(mediaPolling.value[mediaId]);
                delete mediaPolling.value[mediaId];
                delete mediaPollAttempts.value[mediaId];
            }
        } catch (e) {
            window.clearInterval(mediaPolling.value[mediaId]);
            delete mediaPolling.value[mediaId];
            delete mediaPollAttempts.value[mediaId];
        }
    }, 3000);
};

const generateReadyPost = async () => {
    const topic = readyBrief.value.trim() || [form.hook, form.body].filter(Boolean).join('\n\n').trim();
    if (!topic) {
        aiError.value = 'Tulis brief atau isi post terlebih dahulu.';
        setTimeout(() => aiError.value = '', 3000);
        return;
    }

    const shouldGenerateImage = readyGenerateImage.value && totalMediaCount.value < 4;
    if (readyGenerateImage.value && !shouldGenerateImage) {
        mediaError.value = 'Hapus salah satu media sebelum generate gambar AI.';
        setTimeout(() => mediaError.value = '', 4000);
        return;
    }

    aiLoading.value = 'ready';
    aiError.value = '';
    mediaError.value = '';
    imagePromptState.value = shouldGenerateImage
        ? {
            status: 'running',
            style: readyStyle.value,
            aspect_ratio: readyAspectRatio.value,
            visual_prompt: 'AI sedang menyusun prompt image dari brief, style, dan ukuran yang dipilih.',
        }
        : null;
    try {
        const pillar = props.pillars.find(p => p.id === form.content_pillar_id);
        const res = await fetch(route('ai.ready-post'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                topic,
                pillar: pillar?.name || null,
                content_pillar_id: form.content_pillar_id,
                style: readyStyle.value,
                aspect_ratio: readyAspectRatio.value,
                generate_image: shouldGenerateImage,
            }),
        });
        const data = await res.json();
        if (data.success) {
            form.hook = data.post?.hook || form.hook;
            form.body = data.post?.body || form.body;
            hookSuggestions.value = data.post?.hook_variants || [];

            if (data.generated_post) {
                upsertGeneratedPost(data.generated_post);
                currentGeneratedPostId.value = data.generated_post.id;
            }

            imagePromptState.value = data.image_prompt || data.generated_post?.image_prompt || imagePromptState.value;

            if (data.media) {
                applyGeneratedMedia(data.generated_post?.media || data.media);

                if (['queued', 'processing'].includes(data.media.generation_status)) {
                    generatedActionMessage.value = 'Copy dan prompt image berhasil dibuat. Gambar AI sedang diproses di background.';
                    pollGeneratedMedia(data.media.id);
                }
            }

            if (data.image_error) {
                mediaError.value = data.image_error;
                imagePromptState.value = {
                    ...(imagePromptState.value || {}),
                    status: 'failed',
                    error: data.image_error,
                };
            }

            generatedActionMessage.value = data.media?.generation_status === 'completed'
                ? 'Post lengkap dan gambar AI berhasil dibuat. Review di editor, lalu Save Draft, Schedule, atau Publish Now.'
                : (generatedActionMessage.value || 'Post lengkap berhasil dibuat, gambar AI sedang diproses. Cek status Prompt Image AI di panel kanan.');
        } else {
            aiError.value = data.message || 'Gagal generate post siap posting.';
            setTimeout(() => aiError.value = '', 5000);
        }
    } catch (e) {
        aiError.value = 'Koneksi AI gagal. Coba lagi.';
        setTimeout(() => aiError.value = '', 5000);
    } finally {
        aiLoading.value = '';
    }
};

const generateHook = async () => {
    if (!form.body && !form.hook) {
        aiError.value = 'Tulis isi post atau topik terlebih dahulu.';
        setTimeout(() => aiError.value = '', 3000);
        return;
    }
    aiLoading.value = 'hook';
    aiError.value = '';
    try {
        const pillar = props.pillars.find(p => p.id === form.content_pillar_id);
        const res = await fetch(route('ai.content-assist'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                topic: form.body || form.hook,
                pillar: pillar?.name || null,
            }),
        });
        const data = await res.json();
        if (data.success) {
            hookSuggestions.value = data.assist?.hooks || [];
            form.hook = hookSuggestions.value[0]?.hook || '';
        } else {
            aiError.value = data.message || 'Gagal generate hook.';
            setTimeout(() => aiError.value = '', 4000);
        }
    } catch (e) {
        aiError.value = 'Koneksi AI gagal. Coba lagi.';
        setTimeout(() => aiError.value = '', 4000);
    } finally {
        aiLoading.value = '';
    }
};

const improveText = async () => {
    if (!form.body) {
        aiError.value = 'Tulis isi post terlebih dahulu.';
        setTimeout(() => aiError.value = '', 3000);
        return;
    }
    aiLoading.value = 'improve';
    aiError.value = '';
    try {
        const res = await fetch(route('ai.improve-text'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                text: form.body,
                instruction: 'Perbaiki tata bahasa dan buat lebih engaging untuk Threads',
            }),
        });
        const data = await res.json();
        if (data.success) {
            form.body = data.text;
        } else {
            aiError.value = data.message || 'Gagal memperbaiki teks.';
            setTimeout(() => aiError.value = '', 4000);
        }
    } catch (e) {
        aiError.value = 'Koneksi AI gagal. Coba lagi.';
        setTimeout(() => aiError.value = '', 4000);
    } finally {
        aiLoading.value = '';
    }
};

const suggestHashtags = async () => {
    if (!form.body) {
        aiError.value = 'Tulis isi post terlebih dahulu.';
        setTimeout(() => aiError.value = '', 3000);
        return;
    }
    aiLoading.value = 'hashtag';
    aiError.value = '';
    try {
        const res = await fetch(route('ai.generate-hashtags'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                text: form.body,
            }),
        });
        const data = await res.json();
        if (data.success) {
            const hashtags = data.hashtags || [];
            const withoutExisting = form.body.replace(/\n?\s*(#[\p{L}\p{N}_]+\s*)+$/u, '').trim();
            form.body = [withoutExisting, hashtags.join(' ')].filter(Boolean).join('\n\n').slice(0, 500);
        } else {
            aiError.value = data.message || 'Gagal generate hashtag.';
            setTimeout(() => aiError.value = '', 4000);
        }
    } catch (e) {
        aiError.value = 'Koneksi AI gagal. Coba lagi.';
        setTimeout(() => aiError.value = '', 4000);
    } finally {
        aiLoading.value = '';
    }
};
</script>

<template>
    <Head title="Buat Konten" />
    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Buat Konten</h2>
        </template>
        <template #actions>
            <button
                @click="saveDraft"
                :disabled="form.processing || generatedActionLoading !== ''"
                class="px-4 py-2 rounded-lg border border-outline-variant text-sm font-semibold text-on-surface-variant hover:bg-surface-container transition-colors disabled:opacity-50"
            >
                Save Draft
            </button>
            <button
                @click="schedulePost"
                :disabled="form.processing || generatedActionLoading !== ''"
                class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all disabled:opacity-50"
            >
                <IconCalendarPlus :size="16" :stroke-width="1.5" /> Schedule
            </button>
            <button
                @click="publishNow"
                :disabled="form.processing || generatedActionLoading !== '' || !hasThreadsAccess"
                :title="hasThreadsAccess ? 'Publish hasil AI aktif ke Threads' : 'Threads API token belum tersedia. Atur di Settings.'"
                class="hidden sm:flex items-center gap-1.5 bg-[#1a3263] text-white font-bold text-label-caps px-4 py-2 rounded-lg hover:bg-[#1a3263]/90 transition-all disabled:opacity-50"
            >
                <IconSend :size="16" :stroke-width="1.5" /> Publish Now
            </button>
        </template>

        <div class="mb-6 space-y-3">
            <p class="text-body-sm text-on-surface-variant max-w-3xl">
                Mulai dari brief, generate post lengkap, lalu review hasilnya di editor. Hasil AI tersimpan di Riwayat Generate dan baru masuk Draft & Jadwal setelah kamu pilih Save Draft, Schedule, atau Publish Now.
            </p>
            <div v-if="generatedActionMessage" class="rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-primary flex items-start gap-2">
                <IconAlertCircle :size="16" :stroke-width="1.7" class="mt-0.5 shrink-0" />
                <span>{{ generatedActionMessage }}</span>
            </div>
            <div v-if="!hasThreadsAccess" class="rounded-lg border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-on-surface-variant">
                Publish Now nonaktif karena Threads API token belum tersedia. Buka Settings untuk mengaktifkan publish otomatis.
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Content Card -->
                <div class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconAlignLeft :size="20" class="text-primary" :stroke-width="1.5" /> Editor Post Lengkap
                    </h3>
                    <p class="text-body-sm text-on-surface-variant mb-4">
                        Edit hook, isi post, link, media, dan jadwal sebelum menyimpan ke Draft & Jadwal.
                    </p>
                    <div class="space-y-4">
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Pilar Konten</label>
                            <select v-model="form.content_pillar_id" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none bg-white">
                                <option :value="null">-- Pilih Pilar --</option>
                                <option v-for="p in pillars" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="text-body-sm font-semibold text-on-background">Hook (Kalimat Pembuka)</label>
                                <span :class="['text-xs', hookCount > 150 ? 'text-error' : 'text-on-surface-variant']">{{ hookCount }} / 150</span>
                            </div>
                            <textarea v-model="form.hook" rows="2" maxlength="150" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none resize-none" placeholder="Tulis hook yang menarik perhatian..." />
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="text-body-sm font-semibold text-on-background">Isi Post</label>
                                <span :class="['text-xs', bodyCount > 500 ? 'text-error' : 'text-on-surface-variant']">{{ bodyCount }} / 500</span>
                            </div>
                            <textarea v-model="form.body" rows="5" maxlength="500" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none resize-none" placeholder="Tulis isi konten Anda di sini..." />
                        </div>
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Tambahkan Link</label>
                            <input v-model="form.link_url" type="url" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="https://..." />
                        </div>
                    </div>
                </div>

                <!-- Media & Schedule Card -->
                <div class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconUpload :size="20" class="text-primary" :stroke-width="1.5" /> Media & Jadwal
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-2">Upload Media</label>
                            <input
                                ref="fileInput"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/gif,video/mp4,video/quicktime"
                                class="hidden"
                                @change="handleFileChange"
                            />
                            <div
                                :class="[
                                    'border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer',
                                    isDragging ? 'border-secondary bg-secondary/5' : 'border-outline-variant/50 hover:border-secondary',
                                ]"
                                @click="chooseFiles"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="dropFiles"
                            >
                                <IconUpload :size="32" class="mx-auto text-on-surface-variant/50 mb-2" :stroke-width="1" />
                                <p class="text-sm text-on-surface-variant">Klik untuk upload atau drag & drop</p>
                                <p class="text-xs text-on-surface-variant/70 mt-1">JPG, PNG, GIF, MP4, MOV. Maksimal 4 file, 50MB/file.</p>
                            </div>
                            <p v-if="mediaError" class="text-xs text-error mt-2">{{ mediaError }}</p>
                            <p v-if="form.errors.media" class="text-xs text-error mt-2">{{ form.errors.media }}</p>
                            <div v-if="mediaPreviews.length" class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div
                                    v-for="(media, index) in mediaPreviews"
                                    :key="media.id"
                                    class="flex items-center gap-3 rounded-lg border border-outline-variant p-3 bg-surface-container-low"
                                >
                                    <div class="w-12 h-12 rounded-md bg-surface-container flex items-center justify-center overflow-hidden shrink-0">
                                        <img v-if="media.url" :src="media.url" alt="" class="w-full h-full object-cover" />
                                        <IconVideo v-else-if="media.type.startsWith('video/')" :size="22" class="text-on-surface-variant" :stroke-width="1.5" />
                                        <IconPhoto v-else :size="22" class="text-on-surface-variant" :stroke-width="1.5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <p class="text-sm font-medium text-on-background truncate">{{ media.name }}</p>
                                            <span v-if="media.source === 'ai'" class="shrink-0 text-[10px] font-bold text-primary bg-primary-container/30 rounded px-1.5 py-0.5">AI</span>
                                        </div>
                                        <p class="text-xs text-on-surface-variant">{{ fileSizeLabel(media.size) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="w-8 h-8 rounded-md flex items-center justify-center text-on-surface-variant hover:bg-surface-container"
                                        @click.stop="removeMedia(index)"
                                    >
                                        <IconX :size="16" :stroke-width="1.8" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-body-sm font-semibold text-on-background block mb-1">Tanggal & Waktu Publish</label>
                                <input v-model="form.scheduled_at" type="datetime-local" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none" />
                            </div>
                            <div>
                                <label class="text-body-sm font-semibold text-on-background block mb-1">Metode Publish</label>
                                <div class="flex border border-outline-variant rounded-lg overflow-hidden">
                                    <button @click="form.publish_mode = 'auto'" :class="['flex-1 py-2.5 text-sm font-medium transition-colors', form.publish_mode === 'auto' ? 'bg-surface-container text-on-background' : 'text-on-surface-variant hover:bg-surface-container-low']">Auto</button>
                                    <button @click="form.publish_mode = 'manual'" :class="['flex-1 py-2.5 text-sm font-medium transition-colors', form.publish_mode === 'manual' ? 'bg-surface-container text-on-background' : 'text-on-surface-variant hover:bg-surface-container-low']">Manual</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- AI Assistant -->
                <div class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconSparkles :size="20" class="text-primary-container" :stroke-width="1.5" /> Generate Post Lengkap
                    </h3>

                    <!-- AI Error -->
                    <div v-if="aiError" class="mb-3 px-3 py-2 bg-error/10 text-error text-sm rounded-lg">
                        {{ aiError }}
                    </div>

                    <div class="space-y-3">
                        <div class="space-y-2 rounded-lg border border-outline-variant/60 p-3 bg-surface-container-low">
                            <label class="text-body-sm font-semibold text-on-background block">Brief Konten</label>
                            <textarea
                                v-model="readyBrief"
                                rows="3"
                                maxlength="1000"
                                class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none resize-none bg-white"
                                placeholder="Contoh: edukasi AI untuk UMKM agar admin chat pelanggan lebih rapi"
                            />
                            <div class="grid grid-cols-2 gap-2">
                                <select v-model="readyStyle" class="border border-outline-variant rounded-lg px-2 py-2 text-xs bg-white focus:ring-2 focus:ring-secondary outline-none">
                                    <option value="photography">Photography</option>
                                    <option value="realistic">Realistic</option>
                                    <option value="minimalist">Minimalist</option>
                                    <option value="illustration">Illustration</option>
                                    <option value="3d">3D Render</option>
                                    <option value="cartoon">Cartoon</option>
                                </select>
                                <select v-model="readyAspectRatio" class="border border-outline-variant rounded-lg px-2 py-2 text-xs bg-white focus:ring-2 focus:ring-secondary outline-none">
                                    <option value="1:1">1:1 Square</option>
                                    <option value="4:5">4:5 Portrait</option>
                                    <option value="16:9">16:9 Wide</option>
                                    <option value="9:16">9:16 Story</option>
                                </select>
                            </div>
                            <div class="rounded-lg bg-white/70 border border-outline-variant/60 px-3 py-2">
                                <div class="flex items-center justify-between gap-2 text-[11px] text-on-surface-variant">
                                    <span>Style: <strong class="text-on-background">{{ selectedStyleLabel }}</strong></span>
                                    <span>Size: <strong class="text-on-background">{{ selectedImageSize }}</strong></span>
                                </div>
                                <p class="mt-1 text-[11px] text-on-surface-variant">
                                    Prompt image dibuat otomatis dari brief dan mengikuti ratio {{ selectedAspectRatioLabel }}.
                                </p>
                            </div>
                            <label class="flex items-center gap-2 text-xs text-on-surface-variant">
                                <input v-model="readyGenerateImage" type="checkbox" class="rounded border-outline-variant text-primary focus:ring-primary" />
                                Generate gambar AI
                            </label>
                            <button
                                type="button"
                                @click="generateReadyPost"
                                :disabled="aiLoading !== ''"
                                class="w-full px-3 py-2 rounded-lg bg-primary text-on-primary text-sm font-bold hover:brightness-105 transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
                            >
                                <IconLoader2 v-if="aiLoading === 'ready'" :size="16" :stroke-width="1.5" class="animate-spin" />
                                <IconSparkles v-else :size="16" :stroke-width="1.5" />
                                {{ aiLoading === 'ready' ? 'Menyiapkan post...' : 'Generate Post Lengkap' }}
                            </button>
                        </div>

                        <div class="rounded-lg border border-outline-variant/60 bg-white p-3">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <h4 class="text-sm font-bold text-on-background flex items-center gap-1.5">
                                    <IconPhoto :size="16" :stroke-width="1.5" class="text-primary" />
                                    Prompt Image AI
                                </h4>
                                <span
                                    :class="[
                                        'text-[10px] font-bold px-2 py-0.5 rounded-full border',
                                        imagePromptState?.status === 'generated'
                                            ? 'bg-success/15 text-success border-success/30'
                                            : imagePromptState?.status === 'failed'
                                                ? 'bg-error/15 text-error border-error/30'
                                                : ['queued', 'processing'].includes(imagePromptState?.status)
                                                    ? 'bg-warning/10 text-warning border-warning/30'
                                                    : 'bg-primary/10 text-primary border-primary/20'
                                    ]"
                                >
                                    {{ imagePromptState?.status === 'generated' ? 'Image ready' : imagePromptState?.status === 'failed' ? 'Image failed' : ['queued', 'processing'].includes(imagePromptState?.status) ? 'Processing' : 'Auto' }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface-variant mb-2">
                                Berjalan otomatis saat Generate Post Lengkap, memakai brief konten, style, dan ukuran pilihan.
                            </p>
                            <div class="grid grid-cols-2 gap-2 mb-2 text-[11px]">
                                <div class="rounded-md bg-surface-container-low px-2 py-1.5">
                                    <span class="text-on-surface-variant block">Style</span>
                                    <strong class="text-on-background">{{ styleLabels[imagePromptState?.style] || selectedStyleLabel }}</strong>
                                </div>
                                <div class="rounded-md bg-surface-container-low px-2 py-1.5">
                                    <span class="text-on-surface-variant block">Size</span>
                                    <strong class="text-on-background">{{ imageSizeLabels[imagePromptState?.aspect_ratio] || selectedImageSize }}</strong>
                                </div>
                            </div>
                            <p
                                :class="[
                                    'text-xs leading-relaxed rounded-md border p-2 max-h-32 overflow-y-auto whitespace-pre-line',
                                    imagePromptState?.error ? 'bg-error/5 border-error/20 text-error' : 'bg-surface-container-low border-outline-variant/40 text-on-surface-variant'
                                ]"
                            >
                                {{ imagePromptState?.error || imagePromptState?.visual_prompt || 'Prompt image akan muncul di sini setelah Generate Post Lengkap.' }}
                            </p>
                        </div>

                        <button
                            @click="generateHook"
                            :disabled="aiLoading !== ''"
                            class="w-full text-left px-3 py-2 rounded-lg bg-primary-container/20 text-primary text-sm font-medium hover:bg-primary-container/30 transition-colors flex items-center gap-2 disabled:opacity-50"
                        >
                            <IconLoader2 v-if="aiLoading === 'hook'" :size="16" :stroke-width="1.5" class="animate-spin" />
                            <IconSparkles v-else :size="16" :stroke-width="1.5" />
                            {{ aiLoading === 'hook' ? 'Generating...' : 'Generate Hook' }}
                        </button>
                        <div v-if="hookSuggestions.length" class="space-y-1 pt-2">
                            <button
                                v-for="suggestion in hookSuggestions"
                                :key="suggestion.hook"
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg border border-outline-variant/50 text-xs text-on-surface-variant hover:text-on-background hover:bg-surface-container-low"
                                @click="form.hook = suggestion.hook"
                            >
                                {{ suggestion.hook }}
                            </button>
                        </div>
                        <button
                            @click="improveText"
                            :disabled="aiLoading !== ''"
                            class="w-full text-left px-3 py-2 rounded-lg bg-secondary/10 text-secondary text-sm font-medium hover:bg-secondary/20 transition-colors flex items-center gap-2 disabled:opacity-50"
                        >
                            <IconLoader2 v-if="aiLoading === 'improve'" :size="16" :stroke-width="1.5" class="animate-spin" />
                            <IconAlignLeft v-else :size="16" :stroke-width="1.5" />
                            {{ aiLoading === 'improve' ? 'Improving...' : 'Perbaiki Tata Bahasa' }}
                        </button>
                        <button
                            @click="suggestHashtags"
                            :disabled="aiLoading !== ''"
                            class="w-full text-left px-3 py-2 rounded-lg bg-tertiary/10 text-tertiary text-sm font-medium hover:bg-tertiary/20 transition-colors flex items-center gap-2 disabled:opacity-50"
                        >
                            <IconLoader2 v-if="aiLoading === 'hashtag'" :size="16" :stroke-width="1.5" class="animate-spin" />
                            <IconHash v-else :size="16" :stroke-width="1.5" />
                            {{ aiLoading === 'hashtag' ? 'Generating...' : 'Saran Hashtag' }}
                        </button>
                    </div>
                </div>

                <!-- Generated History -->
                <div class="card">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h3 class="text-headline-md text-on-background flex items-center gap-2">
                            <IconHistory :size="20" :stroke-width="1.5" /> Riwayat Generate
                        </h3>
                        <span class="text-xs text-on-surface-variant">{{ generatedHistory.length }} hasil</span>
                    </div>
                    <p class="text-body-sm text-on-surface-variant mb-4">
                        Ini bukan daftar Draft. Draft dan jadwal hanya muncul setelah kamu menyimpan hasil generate.
                    </p>

                    <div v-if="generatedHistory.length" class="space-y-3 max-h-[560px] overflow-y-auto pr-1">
                        <div
                            v-for="item in generatedHistory"
                            :key="item.id"
                            :class="[
                                'rounded-xl border p-3 transition-colors',
                                currentGeneratedPostId === item.id ? 'border-primary/40 bg-primary/5' : 'border-outline-variant/50 bg-surface-container-low'
                            ]"
                        >
                            <div class="flex items-start gap-3">
                                <div v-if="item.media?.url" class="w-12 h-12 rounded-lg overflow-hidden bg-surface-container shrink-0">
                                    <img :src="item.media.url" alt="" class="w-full h-full object-cover" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', generatedStatusClass(item.status)]">
                                            {{ generatedStatusLabel(item.status) }}
                                        </span>
                                        <span class="text-[11px] text-on-surface-variant">{{ item.created_at }}</span>
                                    </div>
                                    <p class="text-sm font-semibold text-on-background line-clamp-2">{{ item.hook || item.prompt }}</p>
                                    <p class="text-xs text-on-surface-variant line-clamp-2 mt-1">{{ item.body }}</p>
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="openGeneratedPost(item)"
                                    class="px-2 py-1.5 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface-variant hover:bg-surface-container transition-colors"
                                >
                                    Open in editor
                                </button>
                                <button
                                    type="button"
                                    @click="saveGeneratedDraft(item)"
                                    :disabled="generatedActionLoading !== ''"
                                    class="px-2 py-1.5 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface-variant hover:bg-surface-container transition-colors disabled:opacity-50"
                                >
                                    Save Draft
                                </button>
                                <button
                                    type="button"
                                    @click="scheduleGeneratedPost(item)"
                                    :disabled="generatedActionLoading !== ''"
                                    class="px-2 py-1.5 rounded-lg bg-primary-container/20 text-primary text-xs font-semibold hover:bg-primary-container/30 transition-colors disabled:opacity-50"
                                >
                                    Schedule
                                </button>
                                <button
                                    type="button"
                                    @click="publishGeneratedPost(item)"
                                    :disabled="generatedActionLoading !== '' || !hasThreadsAccess"
                                    class="px-2 py-1.5 rounded-lg bg-[#1a3263] text-white text-xs font-semibold hover:bg-[#1a3263]/90 transition-colors disabled:opacity-50"
                                >
                                    Publish Now
                                </button>
                            </div>

                            <Link
                                v-if="item.post_url"
                                :href="item.post_url"
                                class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:underline"
                            >
                                Lihat post tersimpan
                                <IconExternalLink :size="12" :stroke-width="1.8" />
                            </Link>
                        </div>
                    </div>

                    <div v-else class="rounded-xl border border-dashed border-outline-variant p-5 text-center">
                        <IconSparkles :size="28" :stroke-width="1.2" class="mx-auto text-on-surface-variant/40 mb-2" />
                        <p class="text-sm font-semibold text-on-background">Belum ada hasil generate</p>
                        <p class="text-xs text-on-surface-variant mt-1">Gunakan Generate Post Lengkap untuk membuat riwayat pertama.</p>
                    </div>
                </div>

                <!-- Preview -->
                <div class="card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-headline-md text-on-background flex items-center gap-2">
                            <IconEye :size="20" :stroke-width="1.5" /> Preview
                        </h3>
                        <span class="text-xs border border-outline-variant rounded px-2 py-0.5 text-on-surface-variant">Threads</span>
                    </div>
                    <div class="bg-surface-container-low rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-full bg-[#1a3263] text-white flex items-center justify-center text-xs font-bold">G</div>
                            <span class="text-sm font-semibold text-on-background">gimoradigital.id</span>
                            <span class="text-xs text-on-surface-variant">Sekarang</span>
                        </div>
                        <p v-if="form.hook" class="text-sm font-semibold text-on-background mb-1">{{ form.hook }}</p>
                        <p class="text-sm text-on-surface-variant whitespace-pre-line">{{ form.body || 'Tulis isi konten Anda di sini...' }}</p>
                        <div v-if="mediaPreviews.length" class="mt-3 grid grid-cols-2 gap-2">
                            <div
                                v-for="media in mediaPreviews"
                                :key="`preview-${media.id}`"
                                class="aspect-square rounded-lg bg-surface-container overflow-hidden flex items-center justify-center"
                            >
                                <img v-if="media.url" :src="media.url" alt="" class="w-full h-full object-cover" />
                                <IconVideo v-else-if="media.type.startsWith('video/')" :size="24" class="text-on-surface-variant" :stroke-width="1.5" />
                                <IconPhoto v-else :size="24" class="text-on-surface-variant" :stroke-width="1.5" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quality Checklist -->
                <div class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconCheck :size="20" :stroke-width="1.5" /> Checklist Kualitas
                    </h3>
                    <div class="space-y-2">
                        <div v-for="check in qualityChecks" :key="check.label" class="flex items-center gap-2">
                            <div :class="['w-5 h-5 rounded-full flex items-center justify-center shrink-0', check.passed ? 'bg-success/20 text-success' : 'bg-surface-container text-on-surface-variant/40']">
                                <IconCheck :size="12" :stroke-width="2.5" />
                            </div>
                            <span class="text-body-sm text-on-surface-variant">{{ check.label }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
