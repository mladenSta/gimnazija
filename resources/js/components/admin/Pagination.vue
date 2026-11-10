<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import type { Paginated } from '@/types/media';

const props = defineProps<{
    meta: Paginated<unknown>['meta'];
}>();

// Laravel's first/last links are "previous"/"next"; the middle ones are page numbers.
const pages = computed(() => props.meta.links.slice(1, -1));
const prev = computed(() => props.meta.links[0]?.url ?? null);
const next = computed(
    () => props.meta.links[props.meta.links.length - 1]?.url ?? null,
);
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="flex flex-wrap items-center justify-between gap-3 text-sm"
        aria-label="Stranice"
    >
        <p class="text-muted-foreground">
            {{ meta.from }}–{{ meta.to }} od {{ meta.total }}
        </p>

        <div class="flex items-center gap-1">
            <component
                :is="prev ? Link : 'span'"
                :href="prev ?? undefined"
                preserve-scroll
                class="grid size-8 place-items-center rounded-md border"
                :class="prev ? 'hover:bg-accent' : 'opacity-40'"
                aria-label="Prethodna"
            >
                <ChevronLeft class="size-4" />
            </component>

            <template v-for="link in pages" :key="link.label">
                <span v-if="!link.url" class="px-2 text-muted-foreground"
                    >…</span
                >
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="grid h-8 min-w-8 place-items-center rounded-md border px-2 tabular-nums"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                >
                    {{ link.label }}
                </Link>
            </template>

            <component
                :is="next ? Link : 'span'"
                :href="next ?? undefined"
                preserve-scroll
                class="grid size-8 place-items-center rounded-md border"
                :class="next ? 'hover:bg-accent' : 'opacity-40'"
                aria-label="Sljedeća"
            >
                <ChevronRight class="size-4" />
            </component>
        </div>
    </nav>
</template>
