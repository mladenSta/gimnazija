<script setup lang="ts">
import { GripVertical, ImagePlus, X } from '@lucide/vue';
import { ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { Button } from '@/components/ui/button';
import type {
    MediaItem,
    RecommendedSizeKey,
    UploadSettings,
} from '@/types/media';

/**
 * Ordered list of images (drag to reorder). v-model = MediaItem[].
 */
withDefaults(
    defineProps<{
        upload: UploadSettings;
        recommendedKey?: RecommendedSizeKey;
    }>(),
    { recommendedKey: 'gallery' },
);

const model = defineModel<MediaItem[]>({ default: () => [] });
const open = ref(false);

function add(media: MediaItem[]): void {
    const existing = new Set(model.value.map((m) => m.id));
    model.value = [...model.value, ...media.filter((m) => !existing.has(m.id))];
}

function remove(id: number): void {
    model.value = model.value.filter((m) => m.id !== id);
}
</script>

<template>
    <div class="space-y-3">
        <VueDraggable
            v-if="model.length"
            v-model="model"
            :animation="150"
            handle=".drag-handle"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
        >
            <div
                v-for="item in model"
                :key="item.id"
                class="group relative aspect-square overflow-hidden rounded-lg border bg-muted"
            >
                <img
                    :src="item.thumb_url"
                    :alt="item.alt ?? ''"
                    class="size-full object-cover"
                />
                <span
                    class="drag-handle absolute top-1 left-1 grid size-6 cursor-grab place-items-center rounded bg-background/90 text-muted-foreground shadow"
                    title="Prevucite za promjenu redoslijeda"
                >
                    <GripVertical class="size-3.5" />
                </span>
                <button
                    type="button"
                    class="absolute top-1 right-1 grid size-6 place-items-center rounded-full bg-background/90 shadow"
                    aria-label="Ukloni iz galerije"
                    @click="remove(item.id)"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </VueDraggable>

        <p v-else class="text-sm text-muted-foreground">
            Nema fotografija. Ako ih dodate, prikazaće se kao galerija ispod
            teksta.
        </p>

        <Button type="button" variant="outline" size="sm" @click="open = true"
            ><ImagePlus /> Dodaj fotografije</Button
        >

        <MediaPicker
            v-model:open="open"
            :settings="upload"
            multiple
            :recommended-key="recommendedKey"
            title="Dodajte fotografije u galeriju"
            @select="add"
        />
    </div>
</template>
