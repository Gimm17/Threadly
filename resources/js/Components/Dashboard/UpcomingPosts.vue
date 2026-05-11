<script setup>
import { IconPhoto, IconPlayerPlay } from '@tabler/icons-vue';

defineProps({
    posts: { type: Array, required: true },
});

const getPostIcon = (body) => {
    if (body && body.toLowerCase().includes('behind')) return IconPlayerPlay;
    return IconPhoto;
};
</script>

<template>
    <div class="card">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-headline-md text-on-background">Upcoming Posts</h3>
            <a href="#" class="text-body-sm text-secondary hover:underline">Lihat Semua</a>
        </div>

        <ul class="divide-y divide-outline-variant/30">
            <li
                v-for="post in posts"
                :key="post.id"
                class="py-3 flex justify-between items-center group cursor-pointer hover:bg-surface-container-low/50 transition-colors -mx-4 px-4 rounded-lg"
            >
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-tertiary-container/30 flex items-center justify-center text-primary">
                        <component :is="getPostIcon(post.hook)" :size="20" :stroke-width="1.5" />
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-on-background group-hover:text-secondary transition-colors">
                            {{ post.hook || post.body?.substring(0, 40) + '...' }}
                        </h4>
                        <p class="text-body-sm text-on-surface-variant">
                            {{ post.pillar || 'Umum' }}
                        </p>
                    </div>
                </div>
                <div class="text-right shrink-0 ml-4">
                    <span class="block text-label-caps text-on-background">{{ post.scheduled_day }}</span>
                    <span class="text-body-sm text-on-surface-variant">{{ post.scheduled_time }}</span>
                </div>
            </li>
        </ul>

        <div v-if="posts.length === 0" class="py-8 text-center text-on-surface-variant text-sm">
            Belum ada post terjadwal.
        </div>
    </div>
</template>
