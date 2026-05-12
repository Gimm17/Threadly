<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    IconPencil,
    IconWand,
    IconCopy,
    IconCheck,
    IconRefresh,
    IconBrandThreads,
    IconSparkles,
    IconMoodSmile,
    IconBriefcase,
    IconBook,
    IconTrendingUp,
    IconLoader2,
} from '@tabler/icons-vue';

const props = defineProps({
    contentPillars: { type: Array, default: () => [] },
});

// State
const activeTab = ref('post'); // post | thread | variations
const loading = ref(false);
const result = ref('');
const threadResult = ref([]);
const variationsResult = ref([]);
const copied = ref(false);

// Post generation form
const postForm = ref({
    topic: '',
    tone: 'professional',
    pillar_id: '',
    include_hashtags: true,
    include_cta: false,
    max_length: 500,
});

// Thread generation form
const threadForm = ref({
    topic: '',
    tone: 'professional',
    num_posts: 5,
    pillar_id: '',
});

// Variations form
const variationsForm = ref({
    original_text: '',
    num_variations: 3,
    tone: '',
});

const tones = [
    { value: 'professional', label: 'Professional', icon: IconBriefcase },
    { value: 'casual', label: 'Casual', icon: IconMoodSmile },
    { value: 'educational', label: 'Educational', icon: IconBook },
    { value: 'inspirational', label: 'Inspirational', icon: IconTrendingUp },
    { value: 'humorous', label: 'Humorous', icon: IconMoodSmile },
];

const tabs = [
    { id: 'post', label: 'Single Post', icon: IconPencil },
    { id: 'thread', label: 'Thread', icon: IconBrandThreads },
    { id: 'variations', label: 'Variations', icon: IconRefresh },
];

// Methods
async function generatePost() {
    loading.value = true;
    result.value = '';
    try {
        const response = await axios.post(route('copywriting.generate-post'), postForm.value);
        result.value = response.data.content;
    } catch (e) {
        result.value = 'Error: ' + (e.response?.data?.message || e.message);
    } finally {
        loading.value = false;
    }
}

async function generateThread() {
    loading.value = true;
    threadResult.value = [];
    try {
        const response = await axios.post(route('copywriting.generate-thread'), threadForm.value);
        threadResult.value = response.data.posts || [];
    } catch (e) {
        threadResult.value = [];
        result.value = 'Error: ' + (e.response?.data?.message || e.message);
    } finally {
        loading.value = false;
    }
}

async function generateVariations() {
    loading.value = true;
    variationsResult.value = [];
    try {
        const response = await axios.post(route('copywriting.generate-variations'), variationsForm.value);
        variationsResult.value = response.data.variations || [];
    } catch (e) {
        variationsResult.value = [];
        result.value = 'Error: ' + (e.response?.data?.message || e.message);
    } finally {
        loading.value = false;
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function useAsPost(text) {
    router.visit(route('posts.create'), { data: { content: text } });
}
</script>

<template>
    <Head title="AI Copywriting" />

    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary flex items-center gap-2">
                <IconSparkles class="w-6 h-6" />
                AI Copywriting Studio
            </h2>
        </template>

        <div class="mb-6">
            <p class="text-body-sm text-on-surface-variant">
                Generate engaging content for Threads with AI assistance
            </p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                    'flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all border',
                    activeTab === tab.id
                        ? 'bg-primary-container text-on-primary-container border-primary-container'
                        : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant/30 hover:bg-surface-container hover:text-on-background',
                ]"
            >
                <component :is="tab.icon" class="w-4 h-4" />
                {{ tab.label }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Input Form -->
            <div class="card space-y-6">
                <!-- Single Post Form -->
                <div v-if="activeTab === 'post'" class="space-y-5">
                    <div>
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Topic / Idea</label>
                        <textarea
                            v-model="postForm.topic"
                            rows="3"
                            class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-on-background"
                            placeholder="What do you want to write about?"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Tone</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <button
                                v-for="tone in tones"
                                :key="tone.value"
                                @click="postForm.tone = tone.value"
                                :class="[
                                    'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all border',
                                    postForm.tone === tone.value
                                        ? 'bg-secondary/10 text-secondary border-secondary/30'
                                        : 'text-on-surface-variant border-outline-variant/30 hover:border-outline-variant',
                                ]"
                            >
                                <component :is="tone.icon" class="w-4 h-4" />
                                {{ tone.label }}
                            </button>
                        </div>
                    </div>

                    <div v-if="contentPillars.length" >
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Content Pillar</label>
                        <select v-model="postForm.pillar_id" class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-on-background">
                            <option value="">None</option>
                            <option v-for="p in contentPillars" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-on-background font-medium cursor-pointer">
                            <input type="checkbox" v-model="postForm.include_hashtags" class="rounded border-outline-variant text-secondary focus:ring-secondary" />
                            Include hashtags
                        </label>
                        <label class="flex items-center gap-2 text-sm text-on-background font-medium cursor-pointer">
                            <input type="checkbox" v-model="postForm.include_cta" class="rounded border-outline-variant text-secondary focus:ring-secondary" />
                            Include CTA
                        </label>
                    </div>

                    <button
                        @click="generatePost"
                        :disabled="loading || !postForm.topic"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary-container text-on-primary-container rounded-xl font-bold hover:brightness-105 disabled:opacity-50 transition-all text-sm"
                    >
                        <IconWand class="w-5 h-5" :class="{ 'animate-spin': loading }" />
                        {{ loading ? 'Generating...' : 'Generate Post' }}
                    </button>
                </div>

                <!-- Thread Form -->
                <div v-if="activeTab === 'thread'" class="space-y-5">
                    <div>
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Thread Topic</label>
                        <textarea
                            v-model="threadForm.topic"
                            rows="3"
                            class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-on-background"
                            placeholder="What should this thread be about?"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Tone</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <button
                                v-for="tone in tones"
                                :key="tone.value"
                                @click="threadForm.tone = tone.value"
                                :class="[
                                    'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all border',
                                    threadForm.tone === tone.value
                                        ? 'bg-secondary/10 text-secondary border-secondary/30'
                                        : 'text-on-surface-variant border-outline-variant/30 hover:border-outline-variant',
                                ]"
                            >
                                <component :is="tone.icon" class="w-4 h-4" />
                                {{ tone.label }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Number of Posts: {{ threadForm.num_posts }}</label>
                        <input type="range" v-model.number="threadForm.num_posts" min="3" max="10" class="w-full accent-secondary" />
                    </div>

                    <button
                        @click="generateThread"
                        :disabled="loading || !threadForm.topic"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary-container text-on-primary-container rounded-xl font-bold hover:brightness-105 disabled:opacity-50 transition-all text-sm"
                    >
                        <IconWand class="w-5 h-5" :class="{ 'animate-spin': loading }" />
                        {{ loading ? 'Generating...' : 'Generate Thread' }}
                    </button>
                </div>

                <!-- Variations Form -->
                <div v-if="activeTab === 'variations'" class="space-y-5">
                    <div>
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Original Text</label>
                        <textarea
                            v-model="variationsForm.original_text"
                            rows="5"
                            class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none text-on-background"
                            placeholder="Paste the text you want variations of..."
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-body-sm font-semibold text-on-background mb-1.5">Number of Variations: {{ variationsForm.num_variations }}</label>
                        <input type="range" v-model.number="variationsForm.num_variations" min="2" max="5" class="w-full accent-secondary" />
                    </div>

                    <button
                        @click="generateVariations"
                        :disabled="loading || !variationsForm.original_text"
                        class="w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary-container text-on-primary-container rounded-xl font-bold hover:brightness-105 disabled:opacity-50 transition-all text-sm"
                    >
                        <IconWand class="w-5 h-5" :class="{ 'animate-spin': loading }" />
                        {{ loading ? 'Generating...' : 'Generate Variations' }}
                    </button>
                </div>
            </div>

            <!-- Right: Output -->
            <div class="card space-y-4">
                <h3 class="text-headline-md text-on-background mb-2">Generated Content</h3>

                <!-- Empty state -->
                <div v-if="!result && !threadResult.length && !variationsResult.length && !loading" class="flex flex-col items-center justify-center py-16 text-on-surface-variant">
                    <IconSparkles class="w-12 h-12 mb-4 opacity-30" />
                    <p class="text-sm font-medium">Your AI-generated content will appear here</p>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="flex flex-col items-center justify-center py-16">
                    <IconLoader2 class="w-8 h-8 text-secondary animate-spin mb-4" />
                    <p class="text-on-surface-variant font-medium text-sm">Generating content...</p>
                </div>

                <!-- Single Post Result -->
                <div v-if="!loading && result && activeTab === 'post'" class="space-y-4">
                    <div class="bg-surface-container-low rounded-xl p-4 border border-outline-variant/30">
                        <p class="text-on-background whitespace-pre-wrap text-sm leading-relaxed">{{ result }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="copyToClipboard(result)" class="flex items-center gap-2 px-4 py-2 bg-surface-container-lowest border border-outline-variant/30 rounded-lg text-sm font-medium text-on-surface-variant hover:bg-surface-container transition-all">
                            <component :is="copied ? IconCheck : IconCopy" class="w-4 h-4" />
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                        <button @click="useAsPost(result)" class="flex items-center gap-2 px-4 py-2 bg-secondary/10 border border-secondary/20 rounded-lg text-sm font-medium text-secondary hover:bg-secondary/20 transition-all">
                            <IconPencil class="w-4 h-4" />
                            Use as Post
                        </button>
                    </div>
                </div>

                <!-- Thread Result -->
                <div v-if="!loading && threadResult.length && activeTab === 'thread'" class="space-y-3">
                    <div v-for="(post, index) in threadResult" :key="index" class="bg-surface-container-low rounded-xl p-4 border border-outline-variant/30">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] font-bold text-secondary bg-secondary/10 px-2 py-0.5 rounded-full border border-secondary/20">{{ index + 1 }}/{{ threadResult.length }}</span>
                        </div>
                        <p class="text-on-background whitespace-pre-wrap text-sm leading-relaxed">{{ post }}</p>
                        <button @click="copyToClipboard(post)" class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-on-surface-variant hover:text-secondary transition-colors">
                            <IconCopy class="w-3.5 h-3.5" />
                            Copy
                        </button>
                    </div>
                </div>

                <!-- Variations Result -->
                <div v-if="!loading && variationsResult.length && activeTab === 'variations'" class="space-y-3">
                    <div v-for="(variation, index) in variationsResult" :key="index" class="bg-surface-container-low rounded-xl p-4 border border-outline-variant/30">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[10px] font-bold text-info bg-info/10 px-2 py-0.5 rounded-full border border-info/20">
                                {{ variation.tone ? `${variation.tone}` : `Variation ${index + 1}` }}
                            </span>
                            <div class="flex gap-1.5">
                                <button @click="copyToClipboard(variation.text || variation)" class="p-1.5 text-on-surface-variant hover:text-secondary transition-colors rounded hover:bg-surface-container">
                                    <IconCopy class="w-4 h-4" />
                                </button>
                                <button @click="useAsPost(variation.text || variation)" class="p-1.5 text-on-surface-variant hover:text-secondary transition-colors rounded hover:bg-surface-container">
                                    <IconPencil class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <p class="text-on-background whitespace-pre-wrap text-sm leading-relaxed">{{ variation.text || variation }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
