<script setup>
import { ref, onMounted, watch } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip);

const props = defineProps({
    chartData: { type: Object, required: true },
});

const data = ref({
    labels: props.chartData.labels || [],
    datasets: [
        {
            label: 'Engagement',
            data: props.chartData.datasets?.[0]?.data || [],
            borderColor: '#547792',
            backgroundColor: 'rgba(255, 197, 112, 0.12)',
            pointBackgroundColor: '#FFC570',
            pointBorderColor: '#547792',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7,
            fill: true,
            tension: 0.3,
            borderWidth: 2.5,
        },
    ],
});

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1a3263',
            titleColor: '#ffc570',
            bodyColor: '#ffffff',
            borderColor: '#ffc570',
            borderWidth: 1,
            cornerRadius: 8,
            padding: 12,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                color: '#504538',
                font: { size: 11, family: 'Inter' },
                maxTicksLimit: 6,
            },
            border: { color: 'rgba(212, 196, 178, 0.3)' },
        },
        y: {
            grid: {
                color: 'rgba(212, 196, 178, 0.15)',
                drawBorder: false,
            },
            ticks: {
                color: '#504538',
                font: { size: 11, family: 'Inter' },
                callback: (value) => {
                    if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
                    return value;
                },
            },
            border: { display: false },
        },
    },
    interaction: {
        intersect: false,
        mode: 'index',
    },
};

watch(() => props.chartData, (newData) => {
    data.value = {
        labels: newData.labels || [],
        datasets: [{ ...data.value.datasets[0], data: newData.datasets?.[0]?.data || [] }],
    };
}, { deep: true });
</script>

<template>
    <div class="card">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-headline-md text-on-background">Engagement Trend</h3>
            <button class="text-secondary hover:bg-surface-container-low p-2 rounded-full transition-colors">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
            </button>
        </div>
        <div class="h-64">
            <Line :data="data" :options="options" />
        </div>
    </div>
</template>
