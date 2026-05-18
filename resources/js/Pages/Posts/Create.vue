<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { IconSparkles, IconHash, IconAlignLeft, IconUpload, IconCheck, IconEye, IconLoader2, IconPhoto, IconVideo, IconX } from '@tabler/icons-vue';

const props = defineProps({
    pillars: { type: Array, default: () => [] },
});

const form = useForm({
    body: '',
    hook: '',
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

const submit = (status) => {
    form.status = status;
    form.post(route('posts.store'), {
        forceFormData: true,
    });
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
    if (!media?.id || form.generated_media_ids.includes(media.id) || totalMediaCount.value >= 4) {
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
    try {
        const pillar = props.pillars.find(p => p.id === form.content_pillar_id);
        const res = await fetch(route('ai.ready-post'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                topic,
                pillar: pillar?.name || null,
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

            if (data.media?.generation_status === 'completed') {
                appendGeneratedMediaPreview(data.media);
            }

            if (data.image_error) {
                mediaError.value = data.image_error;
                setTimeout(() => mediaError.value = '', 8000);
            }
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
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
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
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
    <Head title="Buat Post Baru" />
    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Buat Post Baru</h2>
        </template>
        <template #actions>
            <button @click="submit('draft')" :disabled="form.processing" class="px-4 py-2 rounded-lg border border-outline-variant text-sm font-semibold text-on-surface-variant hover:bg-surface-container transition-colors">Save Draft</button>
            <button @click="submit('scheduled')" :disabled="form.processing" class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all">
                <IconSparkles :size="16" :stroke-width="1.5" /> Schedule
            </button>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Content Card -->
                <div class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconAlignLeft :size="20" class="text-primary" :stroke-width="1.5" /> Konten
                    </h3>
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
                        <IconSparkles :size="20" class="text-primary-container" :stroke-width="1.5" /> AI Assistant
                    </h3>

                    <!-- AI Error -->
                    <div v-if="aiError" class="mb-3 px-3 py-2 bg-error/10 text-error text-sm rounded-lg">
                        {{ aiError }}
                    </div>

                    <div class="space-y-3">
                        <div class="space-y-2 rounded-lg border border-outline-variant/60 p-3 bg-surface-container-low">
                            <label class="text-body-sm font-semibold text-on-background block">Brief Post + Gambar</label>
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
                                {{ aiLoading === 'ready' ? 'Menyiapkan post...' : 'Generate Post + Gambar' }}
                            </button>
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
