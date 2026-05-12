<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    IconSparkles,
    IconPlus,
    IconBookmark,
    IconCopy,
    IconStar,
    IconTrash,
    IconLoader2,
} from '@tabler/icons-vue';

const props = defineProps({
    hooks: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

// Sorting & filtering
const changeSort = (sort) => {
    router.get(route('hooks.index'), { sort, category: props.filters.category }, { preserveState: true });
};
const changeCategory = (cat) => {
    router.get(route('hooks.index'), { category: cat, sort: props.filters.sort }, { preserveState: true });
};

// Add hook form
const showAddForm = ref(false);
const addForm = useForm({
    hook_text: '',
    category: '',
});
const submitAdd = () => {
    addForm.post(route('hooks.store'), {
        onSuccess: () => { showAddForm.value = false; addForm.reset(); },
    });
};

// AI Generate
const showGenerate = ref(false);
const genForm = useForm({
    topic: '',
    category: '',
    count: 5,
});
const generating = ref(false);
const submitGenerate = () => {
    generating.value = true;
    axios.post(route('hooks.generate'), genForm.data())
        .then(() => {
            showGenerate.value = false;
            genForm.reset();
            router.reload();
        })
        .catch(() => {})
        .finally(() => { generating.value = false; });
};

// Actions
const scoring = ref({});
const scoreHook = async (id) => {
    scoring.value[id] = true;
    try {
        await axios.post(route('hooks.score', id));
        router.reload({ only: ['hooks'] });
    } finally {
        scoring.value[id] = false;
    }
};

const saveHook = (id) => {
    axios.post(route('hooks.save', id));
};

const copyHook = (text) => {
    navigator.clipboard.writeText(text);
};

const deleteHook = (id) => {
    if (confirm('Hapus hook ini?')) {
        router.delete(route('hooks.destroy', id));
    }
};

const scoreColor = (score) => {
    if (score >= 80) return 'text-success';
    if (score >= 50) return 'text-warning';
    return 'text-error';
};

const scoreBg = (score) => {
    if (score >= 80) return 'bg-success/10 border-success/30';
    if (score >= 50) return 'bg-warning/10 border-warning/30';
    return 'bg-error/10 border-error/30';
};
</script>

<template>
    <Head title="Hook Generator" />

    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Hook Generator</h2>
        </template>

        <template #actions>
            <button
                @click="showGenerate = true"
                class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all"
            >
                <IconSparkles :size="16" :stroke-width="2" />
                AI Generate
            </button>
            <button
                @click="showAddForm = true"
                class="flex items-center gap-1.5 bg-[#1a3263] text-white font-bold text-label-caps px-4 py-2 rounded-lg hover:bg-[#1a3263]/90 transition-all"
            >
                <IconPlus :size="16" :stroke-width="2" />
                Tambah Manual
            </button>
        </template>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <div class="flex gap-2">
                <button
                    @click="changeSort('score')"
                    :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors', filters.sort === 'score' || !filters.sort ? 'bg-secondary text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high']"
                >
                    Top Score
                </button>
                <button
                    @click="changeSort('saves')"
                    :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors', filters.sort === 'saves' ? 'bg-secondary text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high']"
                >
                    Most Saved
                </button>
                <button
                    @click="changeSort('newest')"
                    :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors', filters.sort === 'newest' ? 'bg-secondary text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high']"
                >
                    Terbaru
                </button>
            </div>

            <div class="border-l border-outline-variant h-6 mx-1" />

            <button
                @click="changeCategory(null)"
                :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors', !filters.category ? 'bg-primary text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high']"
            >
                Semua
            </button>
            <button
                v-for="cat in categories"
                :key="cat"
                @click="changeCategory(cat)"
                :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors', filters.category === cat ? 'bg-primary text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high']"
            >
                {{ cat }}
            </button>
        </div>

        <!-- Hooks Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="hook in hooks.data"
                :key="hook.id"
                class="card group"
            >
                <div class="flex items-start justify-between gap-2 mb-3">
                    <span
                        v-if="hook.category"
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant"
                    >
                        {{ hook.category }}
                    </span>
                    <span
                        v-if="hook.is_ai_generated"
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary"
                    >
                        AI
                    </span>
                </div>

                <p class="text-sm text-on-background leading-relaxed mb-4 min-h-[60px]">
                    "{{ hook.hook_text }}"
                </p>

                <!-- Score -->
                <div class="flex items-center gap-3 mb-3">
                    <div
                        v-if="hook.score"
                        :class="['flex items-center gap-1 px-2 py-1 rounded-lg border text-xs font-bold', scoreBg(hook.score)]"
                    >
                        <IconStar :size="12" :stroke-width="2" :class="scoreColor(hook.score)" />
                        <span :class="scoreColor(hook.score)">{{ hook.score }}/100</span>
                    </div>
                    <span class="text-[10px] text-on-surface-variant">
                        {{ hook.save_count || 0 }} saves · {{ hook.usage_count || 0 }} uses
                    </span>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 pt-3 border-t border-outline-variant/30 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                        @click="copyHook(hook.hook_text)"
                        class="flex items-center gap-1 px-2 py-1 rounded text-xs text-on-surface-variant hover:text-primary hover:bg-primary/5 transition-colors"
                        title="Copy"
                    >
                        <IconCopy :size="14" :stroke-width="1.5" />
                        Copy
                    </button>
                    <button
                        @click="saveHook(hook.id)"
                        class="flex items-center gap-1 px-2 py-1 rounded text-xs text-on-surface-variant hover:text-warning hover:bg-warning/5 transition-colors"
                        title="Save"
                    >
                        <IconBookmark :size="14" :stroke-width="1.5" />
                        Save
                    </button>
                    <button
                        @click="scoreHook(hook.id)"
                        :disabled="scoring[hook.id]"
                        class="flex items-center gap-1 px-2 py-1 rounded text-xs text-on-surface-variant hover:text-secondary hover:bg-secondary/5 transition-colors"
                        title="AI Score"
                    >
                        <IconLoader2 v-if="scoring[hook.id]" :size="14" :stroke-width="1.5" class="animate-spin" />
                        <IconSparkles v-else :size="14" :stroke-width="1.5" />
                        Score
                    </button>
                    <button
                        @click="deleteHook(hook.id)"
                        class="flex items-center gap-1 px-2 py-1 rounded text-xs text-on-surface-variant hover:text-error hover:bg-error/5 transition-colors ml-auto"
                        title="Delete"
                    >
                        <IconTrash :size="14" :stroke-width="1.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!hooks.data?.length" class="text-center py-16">
            <IconSparkles :size="48" :stroke-width="1" class="mx-auto text-on-surface-variant/30 mb-4" />
            <h3 class="text-lg font-semibold text-on-background mb-2">Belum ada hook</h3>
            <p class="text-sm text-on-surface-variant mb-6">Mulai dengan generate hook AI atau tambah hook manual.</p>
            <button
                @click="showGenerate = true"
                class="inline-flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold px-6 py-2.5 rounded-lg hover:brightness-105 transition-all"
            >
                <IconSparkles :size="16" :stroke-width="2" />
                Generate Hook AI
            </button>
        </div>

        <!-- Add Hook Modal -->
        <Teleport to="body">
            <div v-if="showAddForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showAddForm = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-elevated">
                    <h3 class="text-headline-md text-on-background mb-4">Tambah Hook Manual</h3>
                    <form @submit.prevent="submitAdd" class="space-y-4">
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Hook Text</label>
                            <textarea v-model="addForm.hook_text" rows="3" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none resize-none" placeholder="Tulis hook yang menarik..." />
                        </div>
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Kategori</label>
                            <input v-model="addForm.category" type="text" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="Tips, Story, Motivation..." />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showAddForm = false" class="flex-1 py-2 rounded-lg border border-outline-variant text-sm font-semibold text-on-surface-variant hover:bg-surface-container transition-colors">Batal</button>
                            <button type="submit" :disabled="addForm.processing" class="flex-1 py-2 rounded-lg bg-primary-container text-on-primary-container text-sm font-bold hover:brightness-105 transition-all">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- AI Generate Modal -->
        <Teleport to="body">
            <div v-if="showGenerate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showGenerate = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-elevated">
                    <h3 class="text-headline-md text-on-background mb-4">
                        <IconSparkles :size="20" :stroke-width="1.5" class="inline -mt-0.5 text-primary" />
                        Generate Hook dengan AI
                    </h3>
                    <form @submit.prevent="submitGenerate" class="space-y-4">
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Topik / Tema</label>
                            <input v-model="genForm.topic" type="text" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="Produktivitas kerja, Self-improvement..." />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-body-sm font-semibold text-on-background block mb-1">Kategori</label>
                                <input v-model="genForm.category" type="text" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="Tips" />
                            </div>
                            <div>
                                <label class="text-body-sm font-semibold text-on-background block mb-1">Jumlah</label>
                                <input v-model="genForm.count" type="number" min="1" max="10" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm text-center focus:ring-2 focus:ring-secondary outline-none" />
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showGenerate = false" class="flex-1 py-2 rounded-lg border border-outline-variant text-sm font-semibold text-on-surface-variant hover:bg-surface-container transition-colors">Batal</button>
                            <button type="submit" :disabled="generating" class="flex-1 py-2 rounded-lg bg-primary-container text-on-primary-container text-sm font-bold hover:brightness-105 transition-all flex items-center justify-center gap-1.5">
                                <IconLoader2 v-if="generating" :size="16" :stroke-width="2" class="animate-spin" />
                                <IconSparkles v-else :size="16" :stroke-width="2" />
                                {{ generating ? 'Generating...' : 'Generate' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
