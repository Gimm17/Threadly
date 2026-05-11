<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { IconSparkles, IconFileText, IconPhoto, IconPlus } from '@tabler/icons-vue';

const props = defineProps({
    configs: { type: Array, default: () => [] },
});

const expandedPrompt = ref(null);

const togglePrompt = (id) => {
    expandedPrompt.value = expandedPrompt.value === id ? null : id;
};

const featureIcons = {
    hook_generator: IconSparkles,
    copywriting: IconFileText,
    image_generation: IconPhoto,
};

const saveConfig = (config) => {
    const form = useForm({
        model_id: config.model_id,
        temperature: config.temperature,
        max_tokens: config.max_tokens,
        system_prompt: config.system_prompt,
        is_active: config.is_active,
    });
    form.put(route('settings.ai-models.update', config.id));
};
</script>

<template>
    <Head title="Konfigurasi AI Model" />
    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Konfigurasi AI Model</h2>
        </template>
        <template #actions>
            <a :href="route('posts.create')" class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all">
                <IconPlus :size="16" :stroke-width="2" /> Buat Konten
            </a>
        </template>

        <p class="text-body-sm text-on-surface-variant mb-6">Konfigurasi provider dan parameter model AI untuk berbagai fitur platform.</p>

        <div class="space-y-6">
            <div v-for="config in configs" :key="config.id" class="card">
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-primary-container/20 flex items-center justify-center text-primary">
                            <component :is="featureIcons[config.feature] || IconSparkles" :size="20" :stroke-width="1.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-on-background">{{ config.feature_label }}</h3>
                            <p class="text-body-sm text-on-surface-variant">Provider: {{ config.provider }}</p>
                        </div>
                    </div>
                    <!-- Toggle -->
                    <button
                        @click="config.is_active = !config.is_active; saveConfig(config)"
                        :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                            config.is_active ? 'bg-primary-container' : 'bg-outline-variant']"
                    >
                        <span :class="['inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow',
                            config.is_active ? 'translate-x-6' : 'translate-x-1']" />
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Model Selector</label>
                        <input v-model="config.model_id" type="text" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" />
                        <p class="text-xs text-primary mt-1">Cost: ~$0.01 / 1k tokens</p>
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Temperature ({{ config.temperature }})</label>
                        <input v-model.number="config.temperature" type="range" min="0" max="2" step="0.1" class="w-full accent-primary-container mt-2" />
                        <div class="flex justify-between text-xs text-on-surface-variant mt-1">
                            <span>Precise</span>
                            <span>Creative</span>
                        </div>
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Max Tokens</label>
                        <input v-model.number="config.max_tokens" type="number" min="100" max="8000" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" />
                    </div>
                </div>

                <!-- System Prompt Expandable -->
                <button
                    @click="togglePrompt(config.id)"
                    class="text-sm text-secondary font-medium hover:underline flex items-center gap-1"
                >
                    <svg :class="['w-4 h-4 transition-transform', expandedPrompt === config.id ? 'rotate-90' : '']" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                    System Prompt Configuration
                </button>
                <div v-if="expandedPrompt === config.id" class="mt-3">
                    <textarea
                        v-model="config.system_prompt"
                        rows="4"
                        class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none resize-none"
                        placeholder="Enter system prompt..."
                    />
                    <div class="flex justify-end mt-2">
                        <button @click="saveConfig(config)" class="px-4 py-1.5 bg-primary-container text-on-primary-container text-sm font-bold rounded-lg hover:brightness-105 transition-all">Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
