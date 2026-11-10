<script setup lang="ts">
import { ImagePlus, TriangleAlert, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { Button } from '@/components/ui/button';
import { recommendedHint, sizeWarning } from '@/lib/media';
import type {
    MediaItem,
    RecommendedSizeKey,
    UploadSettings,
} from '@/types/media';

/**
 * One image (e.g. featured image). v-model = the chosen MediaItem or null.
 */
const props = defineProps<{
    upload: UploadSettings;
    recommendedKey: RecommendedSizeKey;
    title?: string;
}>();

const model = defineModel<MediaItem | null>({ default: null });
const open = ref(false);

const recommended = computed(
    () => props.upload.recommended[props.recommendedKey],
);
const warning = computed(() =>
    model.value ? sizeWarning(model.value, recommended.value) : null,
);
</script>

<template>
    <div class="space-y-2">
        <div
            v-if="model"
            class="group relative overflow-hidden rounded-lg border bg-muted"
        >
            <img
                :src="model.medium_url"
                :alt="model.alt ?? ''"
                class="aspect-video w-full object-cover"
            />
            <button
                type="button"
                class="absolute top-2 right-2 grid size-7 place-items-center rounded-full bg-background/90 shadow hover:bg-background"
                aria-label="Ukloni sliku"
                @click="model = null"
            >
                <X class="size-4" />
            </button>
        </div>

        <p
            v-if="warning"
            class="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400"
        >
            <TriangleAlert class="mt-0.5 size-3.5 shrink-0" /> {{ warning }}
        </p>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-full"
            @click="open = true"
        >
            <ImagePlus /> {{ model ? 'Promijeni sliku' : 'Izaberi sliku' }}
        </Button>
        <p class="text-xs text-muted-foreground">
            {{ recommendedHint(recommended) }}
        </p>

        <MediaPicker
            v-model:open="open"
            :settings="upload"
            :recommended-key="recommendedKey"
            :title="title ?? 'Izaberite sliku'"
            :initial-ids="model ? [model.id] : []"
            @select="model = $event[0] ?? null"
        />
    </div>
</template>
