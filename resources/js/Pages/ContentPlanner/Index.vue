<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    IconPlus,
    IconCalendarEvent,
    IconChevronLeft,
    IconChevronRight,
    IconDotsVertical,
} from '@tabler/icons-vue';

const props = defineProps({
    pillars: { type: Array, default: () => [] },
    ideas: { type: Array, default: () => [] },
});

// Calendar state
const currentDate = ref(new Date());
const currentMonth = computed(() => currentDate.value.getMonth());
const currentYear = computed(() => currentDate.value.getFullYear());

const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const dayNames = ['SEN', 'SEL', 'RAB', 'KAM', 'JUM', 'SAB', 'MIN'];

const calendarDays = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = (firstDay.getDay() + 6) % 7; // Monday-based
    const days = [];

    // Previous month padding
    const prevMonth = new Date(year, month, 0);
    for (let i = startDay - 1; i >= 0; i--) {
        days.push({ day: prevMonth.getDate() - i, currentMonth: false });
    }

    // Current month
    for (let d = 1; d <= lastDay.getDate(); d++) {
        days.push({ day: d, currentMonth: true });
    }

    // Next month padding
    const remaining = 7 - (days.length % 7);
    if (remaining < 7) {
        for (let d = 1; d <= remaining; d++) {
            days.push({ day: d, currentMonth: false });
        }
    }

    return days;
});

const prevMonth = () => {
    currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1);
};
const nextMonth = () => {
    currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1);
};

const statusColor = (status) => {
    switch (status) {
        case 'draft': return 'bg-success/20 text-success border-success/30';
        case 'in_progress': return 'bg-warning/20 text-warning border-warning/30';
        default: return 'bg-surface-container text-on-surface-variant';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'draft': return 'Draf';
        case 'in_progress': return 'In Progress';
        case 'converted': return 'Converted';
        default: return status;
    }
};

// Create Idea form
const showIdeaForm = ref(false);
const ideaForm = useForm({
    title: '',
    content_pillar_id: null,
    status: 'draft',
    target_date: '',
    notes: '',
});

const submitIdea = () => {
    ideaForm.post(route('content-ideas.store'), {
        onSuccess: () => {
            showIdeaForm.value = false;
            ideaForm.reset();
        },
    });
};

// Create Pillar form
const showPillarForm = ref(false);
const pillarForm = useForm({
    name: '',
    description: '',
    color_hex: '#3b82f6',
    icon: 'bookmark',
    frequency_unit: 'week',
    frequency_value: 2,
});

const submitPillar = () => {
    pillarForm.post(route('content-pillars.store'), {
        onSuccess: () => {
            showPillarForm.value = false;
            pillarForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Content Planner" />

    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Content Planner</h2>
        </template>

        <template #actions>
            <a
                :href="route('posts.create')"
                class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all"
            >
                <IconPlus :size="16" :stroke-width="2" />
                Buat Konten
            </a>
            <button
                @click="showPillarForm = true"
                class="flex items-center gap-1.5 bg-[#1a3263] text-white font-bold text-label-caps px-4 py-2 rounded-lg hover:bg-[#1a3263]/90 transition-all"
            >
                <IconPlus :size="16" :stroke-width="2" />
                Tambah Pilar
            </button>
        </template>

        <!-- Active Pillars -->
        <div class="mb-8">
            <h3 class="text-headline-md text-on-background mb-4">Pilar Konten Aktif</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div
                    v-for="pillar in pillars"
                    :key="pillar.id"
                    class="card"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div
                                class="w-3 h-3 rounded-full"
                                :style="{ backgroundColor: pillar.color_hex }"
                            />
                        </div>
                    </div>
                    <h4 class="font-semibold text-on-background text-sm mb-0.5">{{ pillar.name }}</h4>
                    <p class="text-body-sm text-on-surface-variant mb-4">{{ pillar.description }}</p>
                    <div class="border-t border-outline-variant/30 pt-3 flex gap-6">
                        <div>
                            <span class="text-display-metric text-on-background text-lg font-bold">{{ pillar.posts_count }}</span>
                            <span class="text-label-caps text-on-surface-variant block text-[10px]">TOTAL</span>
                        </div>
                        <div>
                            <span class="text-label-caps text-on-surface-variant text-[10px]">FREKUENSI</span>
                            <span class="text-body-sm text-on-background block">{{ pillar.frequency_value }}x/{{ pillar.frequency_unit === 'week' ? 'Minggu' : 'Bulan' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar + Ideas Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Calendar -->
            <div class="lg:col-span-2 card">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-headline-md text-on-background">
                        {{ monthNames[currentMonth] }} {{ currentYear }}
                    </h3>
                    <div class="flex items-center gap-2">
                        <button @click="prevMonth" class="p-1.5 rounded-lg hover:bg-surface-container transition-colors">
                            <IconChevronLeft :size="20" :stroke-width="1.5" />
                        </button>
                        <button @click="nextMonth" class="p-1.5 rounded-lg hover:bg-surface-container transition-colors">
                            <IconChevronRight :size="20" :stroke-width="1.5" />
                        </button>
                    </div>
                </div>

                <!-- Day headers -->
                <div class="grid grid-cols-7 mb-2">
                    <div
                        v-for="day in dayNames"
                        :key="day"
                        class="text-center text-label-caps text-on-surface-variant py-2"
                    >
                        {{ day }}
                    </div>
                </div>

                <!-- Day grid -->
                <div class="grid grid-cols-7 border-t border-l border-outline-variant/30">
                    <div
                        v-for="(cell, idx) in calendarDays"
                        :key="idx"
                        :class="[
                            'min-h-[80px] p-2 border-r border-b border-outline-variant/30',
                            cell.currentMonth ? 'bg-white' : 'bg-surface-container-low/50'
                        ]"
                    >
                        <span
                            :class="[
                                'text-sm font-medium',
                                cell.currentMonth ? 'text-on-background' : 'text-on-surface-variant/50'
                            ]"
                        >
                            {{ cell.day }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Ideas Sidebar -->
            <div class="card">
                <h3 class="text-headline-md text-on-background mb-4">Ide Konten</h3>

                <div class="space-y-3">
                    <div
                        v-for="idea in ideas"
                        :key="idea.id"
                        class="p-3 border border-outline-variant/40 rounded-lg hover:border-secondary transition-colors"
                    >
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <span
                                :class="['text-[10px] font-bold px-2 py-0.5 rounded-full border', statusColor(idea.status)]"
                            >
                                {{ statusLabel(idea.status) }}
                            </span>
                            <button class="text-on-surface-variant hover:text-on-background">
                                <IconDotsVertical :size="16" :stroke-width="1.5" />
                            </button>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-0.5">{{ idea.title }}</h4>
                        <p class="text-body-sm text-on-surface-variant">{{ idea.content_pillar?.name }}</p>
                    </div>
                </div>

                <button
                    @click="showIdeaForm = true"
                    class="w-full mt-4 py-2.5 border-2 border-dashed border-outline-variant/50 rounded-lg text-sm text-on-surface-variant hover:border-secondary hover:text-secondary transition-colors flex items-center justify-center gap-1.5"
                >
                    <IconPlus :size="16" :stroke-width="1.5" />
                    Tambah Ide
                </button>
            </div>
        </div>

        <!-- Create Pillar Modal -->
        <Teleport to="body">
            <div v-if="showPillarForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showPillarForm = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-elevated">
                    <h3 class="text-headline-md text-on-background mb-4">Tambah Pilar Baru</h3>
                    <form @submit.prevent="submitPillar" class="space-y-4">
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Nama Pilar</label>
                            <input v-model="pillarForm.name" type="text" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none" placeholder="Tech Tips" />
                        </div>
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Deskripsi</label>
                            <input v-model="pillarForm.description" type="text" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none" placeholder="Tips & Trik Teknologi" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-body-sm font-semibold text-on-background block mb-1">Warna</label>
                                <input v-model="pillarForm.color_hex" type="color" class="w-full h-10 border border-outline-variant rounded-lg cursor-pointer" />
                            </div>
                            <div>
                                <label class="text-body-sm font-semibold text-on-background block mb-1">Frekuensi</label>
                                <div class="flex gap-2">
                                    <input v-model="pillarForm.frequency_value" type="number" min="1" max="30" class="w-16 border border-outline-variant rounded-lg px-2 py-2 text-sm text-center focus:ring-2 focus:ring-secondary outline-none" />
                                    <select v-model="pillarForm.frequency_unit" class="flex-1 border border-outline-variant rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none">
                                        <option value="day">/ Hari</option>
                                        <option value="week">/ Minggu</option>
                                        <option value="month">/ Bulan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showPillarForm = false" class="flex-1 py-2 rounded-lg border border-outline-variant text-sm font-semibold text-on-surface-variant hover:bg-surface-container transition-colors">Batal</button>
                            <button type="submit" :disabled="pillarForm.processing" class="flex-1 py-2 rounded-lg bg-primary-container text-on-primary-container text-sm font-bold hover:brightness-105 transition-all">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Create Idea Modal -->
        <Teleport to="body">
            <div v-if="showIdeaForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showIdeaForm = false">
                <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-elevated">
                    <h3 class="text-headline-md text-on-background mb-4">Tambah Ide Konten</h3>
                    <form @submit.prevent="submitIdea" class="space-y-4">
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Judul Ide</label>
                            <input v-model="ideaForm.title" type="text" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary outline-none" placeholder="5 Cara Optimasi Profil LinkedIn" />
                        </div>
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Pilar Konten</label>
                            <select v-model="ideaForm.content_pillar_id" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none">
                                <option :value="null">-- Pilih Pilar --</option>
                                <option v-for="p in pillars" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1">Target Tanggal</label>
                            <input v-model="ideaForm.target_date" type="date" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-secondary outline-none" />
                        </div>
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="showIdeaForm = false" class="flex-1 py-2 rounded-lg border border-outline-variant text-sm font-semibold text-on-surface-variant hover:bg-surface-container transition-colors">Batal</button>
                            <button type="submit" :disabled="ideaForm.processing" class="flex-1 py-2 rounded-lg bg-primary-container text-on-primary-container text-sm font-bold hover:brightness-105 transition-all">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
