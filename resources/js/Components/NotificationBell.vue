<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { IconBell } from '@tabler/icons-vue';
import axios from 'axios';

const notifications = ref([]);
const unreadCount = ref(0);
const isOpen = ref(false);
let pollInterval = null;

const fetchNotifications = async () => {
    try {
        const { data } = await axios.get(route('notifications.index'));
        notifications.value = data.notifications;
        unreadCount.value = data.unread_count;
    } catch (e) {
        // silently fail
    }
};

const pollUnread = async () => {
    try {
        const { data } = await axios.get(route('notifications.unread-count'));
        unreadCount.value = data.unread_count;
    } catch (e) {}
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotifications();
    }
};

const markAsRead = async (notification) => {
    if (!notification.read_at) {
        await axios.post(route('notifications.mark-read', notification.id));
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    if (notification.action_url) {
        window.location.href = notification.action_url;
    }
};

const markAllAsRead = async () => {
    await axios.post(route('notifications.mark-all-read'));
    notifications.value.forEach(n => n.read_at = new Date().toISOString());
    unreadCount.value = 0;
};

const typeIcon = (type) => {
    switch (type) {
        case 'post_published': return '✅';
        case 'post_failed': return '❌';
        case 'post_scheduled': return '📅';
        case 'reminder': return '🔔';
        default: return '📢';
    }
};

const timeAgo = (dateStr) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return `${Math.floor(diff / 60)}m lalu`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}j lalu`;
    return `${Math.floor(diff / 86400)}h lalu`;
};

// Close dropdown when clicking outside
const closeDropdown = (e) => {
    if (!e.target.closest('.notification-bell')) {
        isOpen.value = false;
    }
};

onMounted(() => {
    pollUnread();
    pollInterval = setInterval(pollUnread, 30000); // Poll every 30s
    document.addEventListener('click', closeDropdown);
});

onUnmounted(() => {
    clearInterval(pollInterval);
    document.removeEventListener('click', closeDropdown);
});
</script>

<template>
    <div class="relative notification-bell">
        <!-- Bell Button -->
        <button
            @click="toggleDropdown"
            class="relative p-2 rounded-lg hover:bg-surface-container transition-colors"
        >
            <IconBell :size="20" :stroke-width="1.5" class="text-on-surface-variant" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-error text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-elevated border border-outline-variant/30 overflow-hidden z-50"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-outline-variant/30">
                    <h3 class="text-sm font-bold text-on-background">Notifikasi</h3>
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        class="text-xs text-secondary hover:text-secondary/80 font-medium"
                    >
                        Tandai semua dibaca
                    </button>
                </div>

                <!-- Notification List -->
                <div class="max-h-80 overflow-y-auto">
                    <div
                        v-for="notif in notifications"
                        :key="notif.id"
                        @click="markAsRead(notif)"
                        :class="[
                            'flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-surface-container-low transition-colors border-b border-outline-variant/20 last:border-0',
                            !notif.read_at ? 'bg-primary/5' : ''
                        ]"
                    >
                        <span class="text-lg mt-0.5">{{ typeIcon(notif.type) }}</span>
                        <div class="flex-1 min-w-0">
                            <p :class="['text-sm leading-snug', !notif.read_at ? 'font-semibold text-on-background' : 'text-on-surface-variant']">
                                {{ notif.title }}
                            </p>
                            <p v-if="notif.message" class="text-xs text-on-surface-variant mt-0.5 truncate">
                                {{ notif.message }}
                            </p>
                            <span class="text-[10px] text-on-surface-variant/70 mt-1 block">
                                {{ timeAgo(notif.created_at) }}
                            </span>
                        </div>
                        <span
                            v-if="!notif.read_at"
                            class="w-2 h-2 rounded-full bg-primary mt-2 flex-shrink-0"
                        />
                    </div>

                    <!-- Empty State -->
                    <div v-if="notifications.length === 0" class="py-8 text-center">
                        <IconBell :size="32" :stroke-width="1" class="mx-auto text-on-surface-variant/30 mb-2" />
                        <p class="text-sm text-on-surface-variant">Belum ada notifikasi</p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
