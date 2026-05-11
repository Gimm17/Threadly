<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { IconSparkles, IconAlignLeft, IconUpload, IconCheck, IconEye } from '@tabler/icons-vue';

const props = defineProps({
    post: { type: Object, required: true },
    pillars: { type: Array, default: () => [] },
});

const form = useForm({
    body: props.post.body,
    hook: props.post.hook || '',
    content_pillar_id: props.post.content_pillar_id,
    status: props.post.status,
    scheduled_at: props.post.scheduled_at || '',
    publish_mode: props.post.publish_mode,
    link_url: props.post.link_url || '',
    media: [],
});

const hookCount = computed(() => form.hook.length);
const bodyCount = computed(() => form.body.length);

const submit = (status) => {
    form.status = status;
    form.put(route('posts.update', props.post.id));
};
</script>

<template>
    <Head title="Edit Post" />
    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Edit Post</h2>
        </template>
        <template #actions>
            <button @click="submit('draft')" :disabled="form.processing" class="px-4 py-2 rounded-lg border border-outline-variant text-sm font-semibold text-on-surface-variant hover:bg-surface-container transition-colors">Save Draft</button>
            <button @click="submit('scheduled')" :disabled="form.processing" class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all">
                <IconSparkles :size="16" :stroke-width="1.5" /> Schedule
            </button>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
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
                                <label class="text-body-sm font-semibold text-on-background">Hook</label>
                                <span class="text-xs text-on-surface-variant">{{ hookCount }} / 150</span>
                            </div>
                            <textarea v-model="form.hook" rows="2" maxlength="150" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none resize-none" />
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="text-body-sm font-semibold text-on-background">Isi Post</label>
                                <span class="text-xs text-on-surface-variant">{{ bodyCount }} / 500</span>
                            </div>
                            <textarea v-model="form.body" rows="5" maxlength="500" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none resize-none" />
                        </div>
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Link</label>
                            <input v-model="form.link_url" type="url" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none" />
                        </div>
                    </div>
                </div>
                <div class="card">
                    <h3 class="text-headline-md text-on-background mb-4">Jadwal</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Tanggal & Waktu</label>
                            <input v-model="form.scheduled_at" type="datetime-local" class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none" />
                        </div>
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Metode</label>
                            <div class="flex border border-outline-variant rounded-lg overflow-hidden">
                                <button @click="form.publish_mode = 'auto'" :class="['flex-1 py-2.5 text-sm font-medium transition-colors', form.publish_mode === 'auto' ? 'bg-surface-container text-on-background' : 'text-on-surface-variant']">Auto</button>
                                <button @click="form.publish_mode = 'manual'" :class="['flex-1 py-2.5 text-sm font-medium transition-colors', form.publish_mode === 'manual' ? 'bg-surface-container text-on-background' : 'text-on-surface-variant']">Manual</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-6">
                <div class="card">
                    <h3 class="text-headline-md text-on-background mb-4 flex items-center gap-2">
                        <IconEye :size="20" :stroke-width="1.5" /> Preview
                    </h3>
                    <div class="bg-surface-container-low rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-full bg-[#1a3263] text-white flex items-center justify-center text-xs font-bold">G</div>
                            <span class="text-sm font-semibold">gimoradigital.id</span>
                        </div>
                        <p v-if="form.hook" class="text-sm font-semibold mb-1">{{ form.hook }}</p>
                        <p class="text-sm text-on-surface-variant whitespace-pre-line">{{ form.body }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
