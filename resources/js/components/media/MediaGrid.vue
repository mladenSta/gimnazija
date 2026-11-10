<script setup lang="ts">
import { Check, TriangleAlert } from '@lucide/vue';
import { sizeWarning } from '@/lib/media';
import type { MediaItem, RecommendedSize } from '@/types/media';

withDefaults(
    defineProps<{
        items: MediaItem[];
        selectedIds?: number[];
        recommended?: RecommendedSize | null;
    }>(),
    { selectedIds: () => [], recommended: null },
);

const emit = defineEmits<{
    open: [media: MediaItem];
}>();
</script>

<template>
    <ul
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8"
    >
        <li v-for="item in items" :key="item.id">
            <button
                type="button"
                class="group relative block aspect-square w-full overflow-hidden rounded-lg border bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    selectedIds.includes(item.id)
                        ? 'ring-2 ring-primary ring-offset-2'
                        : ''
                "
                :title="item.original_name"
                @click="emit('open', item)"
            >
                <img
                    :src="item.thumb_url"
                    :alt="item.alt ?? ''"
                    loading="lazy"
                    class="size-full object-cover transition-transform group-hover:scale-105"
                />
                <span
                    v-if="selectedIds.includes(item.id)"
                    class="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-primary text-primary-foreground"
                >
                    <Check class="size-4" />
                </span>
                <span
                    v-if="recommended && sizeWarning(item, recommended)"
                    class="absolute bottom-1.5 left-1.5 grid size-6 place-items-center rounded-full bg-amber-400 text-amber-950"
                    :title="sizeWarning(item, recommended) ?? ''"
                >
                    <TriangleAlert class="size-3.5" />
                </span>
            </button>
        </li>
    </ul>
</template>
