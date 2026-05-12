<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    IconSparkles, IconFileText, IconPhoto, IconBulb, IconPlus,
    IconBolt, IconCoins, IconClock, IconCircleCheck, IconChevronDown,
    IconSearch, IconAlertTriangle, IconCheck,
} from '@tabler/icons-vue';

const props = defineProps({
    configs: { type: Array, default: () => [] },
    modelCatalog: { type: Array, default: () => [] },
    usageStats: { type: Object, default: () => ({}) },
    usagePerFeature: { type: Array, default: () => [] },
    recentLogs: { type: Array, default: () => [] },
});

const expandedPrompt = ref(null);
const openDropdown = ref(null);
const searchQuery = ref('');

const togglePrompt = (id) => {
    expandedPrompt.value = expandedPrompt.value === id ? null : id;
};

const toggleDropdown = (id) => {
    openDropdown.value = openDropdown.value === id ? null : id;
    searchQuery.value = '';
};

const featureIcons = {
    hook_generator: IconSparkles,
    copywriting: IconFileText,
    image_generation: IconPhoto,
    insight: IconBulb,
};

const featureLabels = {
    hook_generator: 'Hook Generator',
    copywriting: 'Copywriting AI',
    image_generation: 'Image Generation',
    insight: 'Daily Insights',
};

// Get models filtered by feature capability
const getModelsForFeature = (feature) => {
    const catalog = props.modelCatalog || [];
    if (feature === 'image_generation') {
        return catalog.filter(m => (m.capabilities || []).includes('image'));
    }
    // For text features, show models with 'text' capability (or all if no capabilities field)
    return catalog.filter(m => !m.capabilities || m.capabilities.includes('text'));
};

// Group models by vendor for dropdown (context-aware per feature)
const getGroupedModels = (feature) => {
    const groups = {};
    getModelsForFeature(feature).forEach(m => {
        const vendor = m.vendor || 'Other';
        if (!groups[vendor]) groups[vendor] = [];
        groups[vendor].push(m);
    });
    return groups;
};

// Filtered models based on search + feature
const getFilteredGroups = (feature) => {
    const grouped = getGroupedModels(feature);
    const q = searchQuery.value.toLowerCase().trim();
    if (!q) return grouped;
    const result = {};
    Object.entries(grouped).forEach(([vendor, models]) => {
        const filtered = models.filter(m =>
            m.name.toLowerCase().includes(q) ||
            m.id.toLowerCase().includes(q) ||
            m.desc.toLowerCase().includes(q) ||
            vendor.toLowerCase().includes(q)
        );
        if (filtered.length) result[vendor] = filtered;
    });
    return result;
};

// Check if a model has image capability
const hasImageCapability = (modelId) => {
    const found = (props.modelCatalog || []).find(m => m.id === modelId);
    return found && (found.capabilities || []).includes('image');
};

const getModelName = (modelId) => {
    const found = (props.modelCatalog || []).find(m => m.id === modelId);
    return found ? found.name : modelId;
};

const getModelDesc = (modelId) => {
    const found = (props.modelCatalog || []).find(m => m.id === modelId);
    return found ? found.desc : '';
};

const selectModel = (config, modelId) => {
    config.model_id = modelId;
    openDropdown.value = null;
    searchQuery.value = '';
    saveConfig(config);
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

const formatNumber = (n) => {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return String(n);
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

        <!-- ═══════════════════════════════════════════ -->
        <!-- AI USAGE DASHBOARD                         -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="mb-8">
            <h3 class="text-lg font-bold text-on-background mb-4 flex items-center gap-2">
                <IconBolt :size="20" class="text-primary" />
                AI Usage Dashboard
            </h3>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <!-- Total Requests -->
                <div class="card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                            <IconBolt :size="20" class="text-blue-500" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-on-background">{{ usageStats.total_requests_today || 0 }}</p>
                            <p class="text-xs text-on-surface-variant">Request Hari Ini</p>
                        </div>
                    </div>
                </div>

                <!-- Token Used -->
                <div class="card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                            <IconCoins :size="20" class="text-amber-500" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-on-background">{{ formatNumber(usageStats.total_tokens_today || 0) }}</p>
                            <p class="text-xs text-on-surface-variant">Token Digunakan</p>
                        </div>
                    </div>
                </div>

                <!-- Cost -->
                <div class="card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                            <IconCoins :size="20" class="text-emerald-500" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-on-background">${{ (usageStats.total_cost_today || 0).toFixed(4) }}</p>
                            <p class="text-xs text-on-surface-variant">Estimasi Biaya</p>
                        </div>
                    </div>
                </div>

                <!-- Success Rate -->
                <div class="card !p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center">
                            <IconCircleCheck :size="20" class="text-green-500" />
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-on-background">{{ usageStats.success_rate_today || 100 }}%</p>
                            <p class="text-xs text-on-surface-variant">Success Rate</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usage per Feature (7 days) -->
            <div v-if="usagePerFeature.length" class="card !p-4 mb-6">
                <h4 class="text-sm font-bold text-on-background mb-3">Penggunaan per Fitur (7 Hari Terakhir)</h4>
                <div class="space-y-3">
                    <div v-for="item in usagePerFeature" :key="item.feature" class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <component :is="featureIcons[item.feature] || IconSparkles" :size="16" class="text-primary" />
                            <span class="text-sm text-on-background">{{ featureLabels[item.feature] || item.feature }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-on-surface-variant">
                            <span>{{ item.requests }} req</span>
                            <span>{{ formatNumber(item.tokens) }} tok</span>
                            <span class="text-primary font-semibold">${{ item.cost.toFixed(4) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Logs -->
            <div v-if="recentLogs.length" class="card !p-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-outline-variant/30">
                    <h4 class="text-sm font-bold text-on-background flex items-center gap-2">
                        <IconClock :size="16" class="text-on-surface-variant" />
                        Log Terbaru
                    </h4>
                </div>
                <div class="max-h-56 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-surface">
                            <tr class="text-left text-on-surface-variant border-b border-outline-variant/20">
                                <th class="px-4 py-2 font-medium">Fitur</th>
                                <th class="px-4 py-2 font-medium">Model</th>
                                <th class="px-4 py-2 font-medium text-right">Tokens</th>
                                <th class="px-4 py-2 font-medium text-right">Waktu</th>
                                <th class="px-4 py-2 font-medium text-center">Status</th>
                                <th class="px-4 py-2 font-medium text-right">Kapan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in recentLogs" :key="log.id" class="border-b border-outline-variant/10 hover:bg-primary-container/5 transition-colors">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-1.5">
                                        <component :is="featureIcons[log.feature] || IconSparkles" :size="12" class="text-primary" />
                                        <span class="text-on-background">{{ featureLabels[log.feature] || log.feature }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-on-surface-variant font-mono">{{ log.model_id.split('/').pop() }}</td>
                                <td class="px-4 py-2 text-right text-on-background tabular-nums">{{ formatNumber(log.total_tokens || 0) }}</td>
                                <td class="px-4 py-2 text-right text-on-surface-variant tabular-nums">{{ log.response_time_ms ? (log.response_time_ms / 1000).toFixed(1) + 's' : '-' }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span v-if="log.is_success" class="inline-flex items-center gap-0.5 text-green-500">
                                        <IconCheck :size="12" /> OK
                                    </span>
                                    <span v-else class="inline-flex items-center gap-0.5 text-red-400" :title="log.error_message">
                                        <IconAlertTriangle :size="12" /> Fail
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-on-surface-variant">{{ log.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="!usagePerFeature.length" class="card !p-8 text-center">
                <IconBolt :size="32" class="mx-auto text-on-surface-variant/40 mb-2" />
                <p class="text-sm text-on-surface-variant">Belum ada data penggunaan AI. Mulai gunakan fitur AI untuk melihat statistik.</p>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- MODEL CONFIGURATIONS                       -->
        <!-- ═══════════════════════════════════════════ -->
        <h3 class="text-lg font-bold text-on-background mb-4">Konfigurasi Model per Fitur</h3>

        <div class="space-y-6">
            <div v-for="config in configs" :key="config.id" class="card">
                <!-- Header -->
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
                    <!-- Model Selector (Dropdown) -->
                    <div class="relative">
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Model Selector</label>
                        <button
                            @click="toggleDropdown(config.id)"
                            type="button"
                            class="w-full flex items-center justify-between border border-outline-variant rounded-lg px-3 py-2 text-sm text-left hover:border-primary/40 focus:ring-2 focus:ring-secondary outline-none transition-all bg-surface"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <p class="text-on-background font-medium truncate">{{ getModelName(config.model_id) }}</p>
                                    <span v-if="hasImageCapability(config.model_id)" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-full bg-violet-500/15 text-violet-400 shrink-0">
                                        <IconPhoto :size="10" /> IMG
                                    </span>
                                </div>
                                <p class="text-xs text-on-surface-variant truncate">{{ config.model_id }}</p>
                            </div>
                            <IconChevronDown :size="16" :class="['text-on-surface-variant transition-transform shrink-0 ml-2', openDropdown === config.id ? 'rotate-180' : '']" />
                        </button>

                        <!-- Dropdown Panel -->
                        <Transition
                            enter-active-class="transition ease-out duration-150"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition ease-in duration-100"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <div v-if="openDropdown === config.id" class="absolute z-50 mt-1 w-full md:w-96 bg-surface border border-outline-variant rounded-xl shadow-2xl max-h-80 flex flex-col">
                                <!-- Search -->
                                <div class="p-2 border-b border-outline-variant/30">
                                    <div class="relative">
                                        <IconSearch :size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant" />
                                        <input
                                            v-model="searchQuery"
                                            type="text"
                                            placeholder="Cari model..."
                                            class="w-full pl-8 pr-3 py-1.5 text-xs border border-outline-variant/50 rounded-lg focus:ring-1 focus:ring-secondary outline-none bg-surface"
                                            @click.stop
                                        />
                                    </div>
                                </div>
                                <!-- Model List -->
                                <div class="overflow-y-auto flex-1 py-1">
                                    <template v-for="(models, vendor) in getFilteredGroups(config.feature)" :key="vendor">
                                        <div class="px-3 py-1.5 text-[10px] font-bold text-on-surface-variant uppercase tracking-wider sticky top-0 bg-surface/95 backdrop-blur">{{ vendor }}</div>
                                        <button
                                            v-for="model in models"
                                            :key="model.id"
                                            @click="selectModel(config, model.id)"
                                            :class="['w-full text-left px-3 py-2 hover:bg-primary-container/10 transition-colors flex items-center justify-between gap-2',
                                                config.model_id === model.id ? 'bg-primary-container/15' : '']"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5">
                                                    <p class="text-sm text-on-background font-medium truncate">{{ model.name }}</p>
                                                    <span v-if="(model.capabilities || []).includes('image')" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider rounded-full bg-violet-500/15 text-violet-400 shrink-0">
                                                        <IconPhoto :size="10" /> IMG
                                                    </span>
                                                </div>
                                                <p class="text-[11px] text-on-surface-variant truncate">{{ model.desc }}</p>
                                            </div>
                                            <IconCheck v-if="config.model_id === model.id" :size="14" class="text-primary shrink-0" />
                                        </button>
                                    </template>
                                    <p v-if="!Object.keys(getFilteredGroups(config.feature)).length" class="px-3 py-4 text-xs text-on-surface-variant text-center">Tidak ditemukan</p>
                                </div>
                                <!-- Custom Input -->
                                <div class="p-2 border-t border-outline-variant/30">
                                    <p class="text-[10px] text-on-surface-variant mb-1">Atau ketik model ID custom:</p>
                                    <input
                                        v-model="config.model_id"
                                        type="text"
                                        class="w-full border border-outline-variant/50 rounded-lg px-2.5 py-1.5 text-xs focus:ring-1 focus:ring-secondary outline-none font-mono"
                                        placeholder="vendor/model-name"
                                        @keydown.enter="openDropdown = null; saveConfig(config)"
                                        @click.stop
                                    />
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Temperature -->
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Temperature ({{ config.temperature }})</label>
                        <input v-model.number="config.temperature" type="range" min="0" max="2" step="0.1" class="w-full accent-primary-container mt-2" @change="saveConfig(config)" />
                        <div class="flex justify-between text-xs text-on-surface-variant mt-1">
                            <span>Precise</span>
                            <span>Creative</span>
                        </div>
                    </div>

                    <!-- Max Tokens -->
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Max Tokens</label>
                        <input v-model.number="config.max_tokens" type="number" min="100" max="16000" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" @change="saveConfig(config)" />
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

        <!-- Click-outside overlay to close dropdown -->
        <div v-if="openDropdown !== null" class="fixed inset-0 z-40" @click="openDropdown = null" />
    </AppLayout>
</template>
