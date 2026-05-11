<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { IconSparkles, IconHash, IconAlignLeft, IconUpload, IconCheck, IconEye } from '@tabler/icons-vue';

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
});

const hookCount = computed(() => form.hook.length);
const bodyCount = computed(() => form.body.length);

const submit = (status) => {
    form.status = status;
    form.post(route('posts.store'));
};

const qualityChecks = computed(() => [
    { label: 'Hook kurang dari 150 karakter', passed: form.hook.length > 0 && form.hook.length <= 150 },
    { label: 'Mengandung Call to Action (CTA)', passed: /\b(coba|simpan|share|klik|follow|tag|komen)\b/i.test(form.body) },
    { label: 'Isi post terisi', passed: form.body.length > 10 },
]);
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
                            <div class="border-2 border-dashed border-outline-variant/50 rounded-xl p-8 text-center hover:border-secondary transition-colors cursor-pointer">
                                <IconUpload :size="32" class="mx-auto text-on-surface-variant/50 mb-2" :stroke-width="1" />
                                <p class="text-sm text-on-surface-variant">Klik untuk upload atau drag & drop</p>
                                <p class="text-xs text-on-surface-variant/70 mt-1">JPG, PNG, GIF up to 5MB</p>
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
                    <div class="space-y-2">
                        <button class="w-full text-left px-3 py-2 rounded-lg bg-primary-container/20 text-primary text-sm font-medium hover:bg-primary-container/30 transition-colors flex items-center gap-2">
                            <IconSparkles :size="16" :stroke-width="1.5" /> Generate Hook
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded-lg bg-secondary/10 text-secondary text-sm font-medium hover:bg-secondary/20 transition-colors flex items-center gap-2">
                            <IconAlignLeft :size="16" :stroke-width="1.5" /> Perbaiki Tata Bahasa
                        </button>
                        <button class="w-full text-left px-3 py-2 rounded-lg bg-tertiary/10 text-tertiary text-sm font-medium hover:bg-tertiary/20 transition-colors flex items-center gap-2">
                            <IconHash :size="16" :stroke-width="1.5" /> Saran Hashtag
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
