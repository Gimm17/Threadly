<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconLayoutDashboard,
    IconCalendarEvent,
    IconClock,
    IconSparkles,
    IconFileText,
    IconPhoto,
    IconChartLine,
    IconSettings,
    IconUser,
    IconMenu2,
    IconX,
} from '@tabler/icons-vue';

const page = usePage();
const currentRoute = computed(() => route().current());

const isMobileOpen = ref(false);
const toggleMobile = () => { isMobileOpen.value = !isMobileOpen.value; };

const navItems = [
    { name: 'Dashboard', route: 'dashboard', icon: IconLayoutDashboard },
    { name: 'Content Planner', route: 'content-planner', icon: IconCalendarEvent },
    { name: 'Post Scheduler', route: 'posts.index', icon: IconClock },
    { name: 'Hook Generator', route: null, icon: IconSparkles, v2: true },
    { name: 'Copywriting AI', route: null, icon: IconFileText, v2: true },
    { name: 'Image Studio', route: null, icon: IconPhoto, v2: true },
    { name: 'Analytics', route: null, icon: IconChartLine, v2: true },
    { name: 'Settings', route: 'settings.ai-models', icon: IconSettings },
];

const isActive = (item) => {
    if (!item.route) return false;
    return currentRoute.value?.startsWith(item.route.split('.')[0]);
};

const flash = computed(() => page.props.flash || {});
</script>

<template>
    <div class="min-h-screen bg-surface">
        <!-- Mobile Overlay -->
        <div
            v-if="isMobileOpen"
            class="fixed inset-0 bg-black/50 z-30 lg:hidden"
            @click="toggleMobile"
        />

        <!-- Sidebar -->
        <nav
            :class="[
                'fixed left-0 top-0 h-screen flex flex-col py-6 z-40 transition-transform duration-300',
                'w-[260px] bg-[#1a3263]',
                isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        >
            <!-- Brand -->
            <div class="px-6 mb-8">
                <h1 class="text-xl font-bold text-[#ffc570]">ThreadsAI</h1>
                <p class="text-xs text-[#94a3b8] mt-0.5">by Gimora Digital</p>
            </div>

            <!-- Navigation -->
            <div class="flex-1 overflow-y-auto scrollbar-thin">
                <ul class="space-y-0.5">
                    <li v-for="item in navItems" :key="item.name">
                        <!-- Active item -->
                        <Link
                            v-if="item.route && !item.v2"
                            :href="route(item.route)"
                            :class="[
                                'flex items-center gap-3 px-5 py-3 text-sm font-medium transition-all duration-200',
                                isActive(item)
                                    ? 'bg-white/10 text-[#ffc570] border-l-4 border-[#ffc570]'
                                    : 'text-[#94a3b8] hover:text-white hover:bg-white/5 border-l-4 border-transparent'
                            ]"
                        >
                            <component :is="item.icon" :size="20" :stroke-width="1.5" />
                            <span>{{ item.name }}</span>
                        </Link>

                        <!-- V2 / disabled item -->
                        <div
                            v-else
                            class="flex items-center gap-3 px-5 py-3 text-sm font-medium text-[#475569] cursor-not-allowed border-l-4 border-transparent"
                        >
                            <component :is="item.icon" :size="20" :stroke-width="1.5" />
                            <span>{{ item.name }}</span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Account -->
            <div class="px-4 border-t border-white/10 pt-4 mt-auto">
                <div class="flex items-center gap-3 px-2 py-2 text-[#94a3b8]">
                    <IconUser :size="20" :stroke-width="1.5" />
                    <span class="text-sm">@{{ page.props.auth?.user?.name || 'user' }}</span>
                </div>
            </div>
        </nav>

        <!-- Main Area -->
        <div class="lg:ml-[260px]">
            <!-- Top Bar -->
            <header class="sticky top-0 z-20 bg-surface border-b border-outline-variant h-16 flex items-center justify-between px-6">
                <div class="flex items-center gap-4">
                    <!-- Mobile hamburger -->
                    <button
                        class="lg:hidden p-2 rounded-lg hover:bg-surface-container transition-colors"
                        @click="toggleMobile"
                    >
                        <IconMenu2 v-if="!isMobileOpen" :size="22" />
                        <IconX v-else :size="22" />
                    </button>

                    <slot name="header" />
                </div>

                <div class="flex items-center gap-3">
                    <slot name="actions" />
                </div>
            </header>

            <!-- Flash Messages -->
            <div v-if="flash.success" class="mx-6 mt-4">
                <div class="bg-success/10 border border-success/30 text-success rounded-lg px-4 py-3 text-sm flex items-center gap-2">
                    <IconSparkles :size="16" />
                    {{ flash.success }}
                </div>
            </div>

            <!-- Page Content -->
            <main class="p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
