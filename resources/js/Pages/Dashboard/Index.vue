<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MetricCard from '@/Components/Dashboard/MetricCard.vue';
import EngagementChart from '@/Components/Dashboard/EngagementChart.vue';
import InsightPanel from '@/Components/Dashboard/InsightPanel.vue';
import UpcomingPosts from '@/Components/Dashboard/UpcomingPosts.vue';
import TopHooks from '@/Components/Dashboard/TopHooks.vue';
import {
    IconUsers,
    IconHeart,
    IconEye,
    IconCalendarEvent,
    IconRefresh,
    IconPlus,
    IconCalendar,
    IconChevronDown,
} from '@tabler/icons-vue';

defineProps({
    metrics: { type: Object, required: true },
    chartData: { type: Object, required: true },
    upcomingPosts: { type: Array, default: () => [] },
    topHooks: { type: Array, default: () => [] },
    insights: { type: Array, default: () => [] },
});

const formatNumber = (n) => {
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return n?.toLocaleString('id-ID') ?? '0';
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Dashboard</h2>
            <div class="hidden md:flex items-center bg-surface-container rounded-full px-3 py-1.5 text-on-surface-variant text-sm gap-2 ml-4 cursor-pointer hover:bg-surface-container-high transition-colors">
                <IconCalendar :size="16" :stroke-width="1.5" />
                <span>30 hari terakhir</span>
                <IconChevronDown :size="16" :stroke-width="1.5" />
            </div>
        </template>

        <template #actions>
            <button class="flex items-center gap-2 px-4 py-2 rounded-lg text-label-caps font-semibold text-secondary border border-secondary hover:bg-secondary/10 transition-colors">
                <IconRefresh :size="16" :stroke-width="1.5" />
                Sync Data
            </button>
            <a
                :href="route('posts.create')"
                class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all"
            >
                <IconPlus :size="16" :stroke-width="2" />
                Buat Konten
            </a>
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

        <!-- Chart + Insights Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2">
                <EngagementChart :chart-data="chartData" />
            </div>
            <InsightPanel :insights="insights" />
        </div>

        <!-- Bottom Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <UpcomingPosts :posts="upcomingPosts" />
            <TopHooks :hooks="topHooks" />
        </div>
    </AppLayout>
</template>
