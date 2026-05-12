<script setup>
import { computed } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { IconSettings, IconPlus, IconDeviceFloppy, IconBrandThreads, IconClock, IconWorld } from '@tabler/icons-vue';

const page = usePage();
const isAdmin = computed(() => ['owner', 'admin'].includes(page.props.auth?.user?.role));

const props = defineProps({
    workspace: { type: Object, required: true },
    timezones: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.workspace.name,
    threads_handle: props.workspace.threads_handle ?? '',
    timezone: props.workspace.timezone ?? 'Asia/Makassar',
});

const submit = () => {
    form.put(route('settings.general.update'));
};
</script>

<template>
    <Head title="Pengaturan Umum" />
    <AppLayout>
        <template #header>
            <h2 class="text-headline-md font-bold text-primary">Pengaturan</h2>
        </template>
        <template #actions>
            <a :href="route('posts.create')" class="flex items-center gap-1.5 bg-primary-container text-on-primary-container font-bold text-label-caps px-4 py-2 rounded-lg hover:brightness-105 transition-all">
                <IconPlus :size="16" :stroke-width="2" /> Buat Konten
            </a>
        </template>

        <!-- Settings Tabs -->
        <div class="flex gap-1 mb-8 border-b border-outline-variant">
            <Link
                :href="route('settings.general')"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 border-primary text-primary"
            >
                Umum
            </Link>
            <Link
                v-if="isAdmin"
                :href="route('settings.ai-models')"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-on-surface-variant hover:text-on-surface hover:border-outline transition-colors"
            >
                AI Models
            </Link>
        </div>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-8">
                <!-- Workspace Info Section -->
                <div class="card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-lg bg-primary-container/20 flex items-center justify-center text-primary">
                            <IconSettings :size="20" :stroke-width="1.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-on-background">Informasi Workspace</h3>
                            <p class="text-body-sm text-on-surface-variant">Pengaturan dasar workspace kamu</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <!-- Workspace Name -->
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1.5">Nama Workspace</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none"
                                placeholder="Contoh: Brand Saya"
                            />
                            <p v-if="form.errors.name" class="text-xs text-error mt-1">{{ form.errors.name }}</p>
                        </div>

                        <!-- Threads Handle -->
                        <div>
                            <label class="text-body-sm font-semibold text-on-background block mb-1.5">
                                <IconBrandThreads :size="14" :stroke-width="1.5" class="inline -mt-0.5" />
                                Threads Handle
                            </label>
                            <div class="flex">
                                <span class="inline-flex items-center px-3 rounded-l-lg bg-surface-container border border-r-0 border-outline-variant text-sm text-on-surface-variant">@</span>
                                <input
                                    v-model="form.threads_handle"
                                    type="text"
                                    class="flex-1 border border-outline-variant rounded-r-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none"
                                    placeholder="username"
                                />
                            </div>
                            <p v-if="form.errors.threads_handle" class="text-xs text-error mt-1">{{ form.errors.threads_handle }}</p>
                        </div>
                    </div>
                </div>

                <!-- Timezone Section -->
                <div class="card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-lg bg-primary-container/20 flex items-center justify-center text-primary">
                            <IconWorld :size="20" :stroke-width="1.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-on-background">Zona Waktu</h3>
                            <p class="text-body-sm text-on-surface-variant">Digunakan untuk penjadwalan posting & reminder</p>
                        </div>
                    </div>

                    <div>
                        <label class="text-body-sm font-semibold text-on-background block mb-1.5">Timezone</label>
                        <select
                            v-model="form.timezone"
                            class="w-full border border-outline-variant rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-secondary outline-none bg-white"
                        >
                            <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                {{ tz.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.timezone" class="text-xs text-error mt-1">{{ form.errors.timezone }}</p>
                    </div>
                </div>

                <!-- Plan & Reminder Info -->
                <div class="card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-lg bg-primary-container/20 flex items-center justify-center text-primary">
                            <IconClock :size="20" :stroke-width="1.5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-on-background">Pengingat & Paket</h3>
                            <p class="text-body-sm text-on-surface-variant">Informasi paket dan pengingat posting</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-surface-container rounded-lg p-4">
                            <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-1">Paket Saat Ini</p>
                            <p class="text-base font-bold text-on-background capitalize">{{ workspace.plan || 'Free' }}</p>
                        </div>
                        <div class="bg-surface-container rounded-lg p-4">
                            <p class="text-xs text-on-surface-variant uppercase tracking-wide mb-1">Pengingat Posting</p>
                            <p class="text-base font-bold text-on-background">{{ workspace.reminder_offset }} menit sebelum</p>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 px-6 py-2.5 bg-primary-container text-on-primary-container font-bold text-sm rounded-lg hover:brightness-105 transition-all disabled:opacity-50"
                    >
                        <IconDeviceFloppy :size="16" :stroke-width="1.5" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
