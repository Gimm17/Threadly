<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EngagementChart from '@/Components/Dashboard/EngagementChart.vue';
import MetricCard from '@/Components/Dashboard/MetricCard.vue';
import {
    IconUsers,
    IconHeart,
    IconEye,
    IconPlus,
    IconUpload,
    IconX,
    IconCalendarEvent,
} from '@tabler/icons-vue';

const props = defineProps({
    snapshots: { type: Object, default: () => [] },
    metrics: { type: Object, required: true },
    chartData: { type: Object, required: true },
});

const showForm = ref(false);

const form = useForm({
    snapshot_date: new Date().toISOString().split('T')[0],
    followers_count: null,
    impressions: null,
    likes: null,
    replies: null,
    reposts: null,
    quotes: null,
    engagement_rate: null,
});

const submitSnapshot = () => {
    form.post(route('analytics.snapshot'), {
        onSuccess: () => {
            showForm.value = false;
            form.reset();
            form.snapshot_date = new Date().toISOString().split('T')[0];
        },
    });
};

const formatNumber = (n) => {
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return n?.toLocaleString('id-ID') ?? '0';
};
</script>

<template>
    <Head title="Analytics" />
    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Analytics</h2>
        </template>
        <template #actions>
            <button
                @click="showForm = !showForm"
                class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all"
            >
                <IconUpload :size="16" :stroke-width="2" />
                Input Data
            </button>
        </template>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <MetricCard
                label="Total Followers"
                :value="formatNumber(metrics.followers?.value)"
                :delta="metrics.followers?.delta"
                :icon="IconUsers"
            />
            <MetricCard
                label="Avg Engagement Rate"
                :value="metrics.engagement_rate?.value + '%'"
                :delta="'+' + metrics.engagement_rate?.delta + '%'"
                :icon="IconHeart"
            />
            <MetricCard
                label="Total Impressi"
                :value="formatNumber(metrics.impressions?.value)"
                :delta="'+' + formatNumber(metrics.impressions?.delta)"
                :icon="IconEye"
            />
            <MetricCard
                label="Post Terjadwal"
                :value="metrics.scheduled_posts?.value"
                delta-label="Minggu ini"
                :icon="IconCalendarEvent"
            />
        </div>

        <!-- Chart -->
        <div class="mb-8">
            <EngagementChart :chart-data="chartData" />
        </div>

        <!-- Input Snapshot Form -->
        <div v-if="showForm" class="card mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-bold text-on-background">Input Data Analytics</h3>
                <button @click="showForm = false" class="text-on-surface-variant hover:text-on-surface">
                    <IconX :size="20" />
                </button>
            </div>
            <form @submit.prevent="submitSnapshot" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Tanggal</label>
                        <input v-model="form.snapshot_date" type="date" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" />
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Followers</label>
                        <input v-model.number="form.followers_count" type="number" min="0" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="0" />
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Impressions</label>
                        <input v-model.number="form.impressions" type="number" min="0" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="0" />
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Engagement Rate (%)</label>
                        <input v-model.number="form.engagement_rate" type="number" min="0" max="100" step="0.1" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="0.0" />
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Likes</label>
                        <input v-model.number="form.likes" type="number" min="0" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="0" />
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Replies</label>
                        <input v-model.number="form.replies" type="number" min="0" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="0" />
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Reposts</label>
                        <input v-model.number="form.reposts" type="number" min="0" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="0" />
                    </div>
                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1">Quotes</label>
                        <input v-model.number="form.quotes" type="number" min="0" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" placeholder="0" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 px-5 py-2 bg-primary-container text-on-primary-container font-bold text-sm rounded-lg hover:brightness-105 transition-all disabled:opacity-50"
                    >
                        <IconPlus :size="16" :stroke-width="1.5" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Data' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Snapshots Table -->
        <div class="card">
            <h3 class="text-base font-bold text-on-background mb-4">Riwayat Data Analytics</h3>

            <div v-if="snapshots.length === 0" class="text-center py-12 text-on-surface-variant">
                <IconEye :size="48" :stroke-width="1" class="mx-auto mb-3 opacity-40" />
                <p class="text-sm">Belum ada data analytics. Klik "Input Data" untuk mulai.</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline-variant text-left text-on-surface-variant">
                            <th class="pb-3 font-semibold">Tanggal</th>
                            <th class="pb-3 font-semibold text-right">Followers</th>
                            <th class="pb-3 font-semibold text-right">Impressions</th>
                            <th class="pb-3 font-semibold text-right">Likes</th>
                            <th class="pb-3 font-semibold text-right">Replies</th>
                            <th class="pb-3 font-semibold text-right">Reposts</th>
                            <th class="pb-3 font-semibold text-right">ER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="snap in snapshots"
                            :key="snap.id"
                            class="border-b border-outline-variant/50 hover:bg-surface-container/50 transition-colors"
                        >
                            <td class="py-3 text-on-background font-medium">{{ snap.date_display }}</td>
                            <td class="py-3 text-right text-on-background">{{ formatNumber(snap.followers_count) }}</td>
                            <td class="py-3 text-right text-on-background">{{ formatNumber(snap.impressions) }}</td>
                            <td class="py-3 text-right text-on-background">{{ formatNumber(snap.likes) }}</td>
                            <td class="py-3 text-right text-on-background">{{ formatNumber(snap.replies) }}</td>
                            <td class="py-3 text-right text-on-background">{{ formatNumber(snap.reposts) }}</td>
                            <td class="py-3 text-right">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-container/20 text-primary">
                                    {{ snap.engagement_rate }}%
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
