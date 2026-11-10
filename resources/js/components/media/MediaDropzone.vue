<script setup lang="ts">
import { CheckCircle2, ImagePlus, TriangleAlert, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useMediaUpload } from '@/composables/useMediaUpload';
import { recommendedHint } from '@/lib/media';
import type { MediaItem, RecommendedSize, UploadSettings } from '@/types/media';

const props = defineProps<{
    settings: UploadSettings;
    /** Size hint for the current use (picker). Omit in the library. */
    recommended?: RecommendedSize | null;
    compact?: boolean;
}>();

const emit = defineEmits<{
    uploaded: [media: MediaItem];
}>();

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const { tasks, add, clearFinished } = useMediaUpload(props.settings, (media) =>
    emit('uploaded', media),
);

const maxMb = computed(() => Math.round(props.settings.maxKb / 1024));
const hasFinished = computed(() =>
    tasks.value.some((t) => t.status === 'done' || t.status === 'error'),
);

function onDrop(event: DragEvent): void {
    dragging.value = false;

    if (event.dataTransfer?.files.length) {
        add(event.dataTransfer.files);
    }
}

function onPick(event: Event): void {
    const target = event.target as HTMLInputElement;

    if (target.files?.length) {
        add(target.files);
    }

    target.value = '';
}
</script>

<template>
    <div class="space-y-3">
        <div
            class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed text-center transition-colors"
            :class="[
                dragging ? 'border-primary bg-primary/5' : 'border-border',
                compact ? 'px-4 py-5' : 'px-6 py-10',
            ]"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <ImagePlus class="size-8 text-muted-foreground" />
            <p class="text-sm">
                Prevucite fotografije ovdje ili
                <button
                    type="button"
                    class="font-medium text-primary underline underline-offset-4"
                    @click="input?.click()"
                >
                    izaberite sa računara
                </button>
            </p>
            <p class="text-xs text-muted-foreground">
                JPG, PNG ili WebP, do {{ maxMb }} MB. Fotografije se automatski
                smanjuju i pretvaraju u WebP.
            </p>
            <p v-if="recommended" class="text-xs font-medium text-foreground">
                {{ recommended.label }} – {{ recommendedHint(recommended) }}
            </p>
            <input
                ref="input"
                type="file"
                class="hidden"
                multiple
                :accept="settings.accept"
                @change="onPick"
            />
        </div>

        <ul v-if="tasks.length" class="space-y-1.5">
            <li
                v-for="task in tasks"
                :key="task.key"
                class="flex items-center gap-3 rounded-lg border px-3 py-2 text-sm"
            >
                <Spinner
                    v-if="
                        task.status === 'uploading' || task.status === 'queued'
                    "
                    class="size-4"
                />
                <CheckCircle2
                    v-else-if="task.status === 'done'"
                    class="size-4 text-green-600"
                />
                <TriangleAlert v-else class="size-4 text-destructive" />

                <div class="min-w-0 flex-1">
                    <p class="truncate">{{ task.name }}</p>
                    <p v-if="task.error" class="text-xs text-destructive">
                        {{ task.error }}
                    </p>
                    <div
                        v-else-if="task.status === 'uploading'"
                        class="mt-1 h-1 overflow-hidden rounded bg-muted"
                    >
                        <div
                            class="h-full bg-primary transition-all"
                            :style="{ width: `${task.progress}%` }"
                        />
                    </div>
                </div>

                <span
                    v-if="task.status === 'uploading'"
                    class="text-xs text-muted-foreground tabular-nums"
                >
                    {{ task.progress < 100 ? `${task.progress}%` : 'Obrada…' }}
                </span>
            </li>
        </ul>

        <Button
            v-if="hasFinished"
            variant="ghost"
            size="sm"
            type="button"
            @click="clearFinished"
        >
            <X /> Očisti listu
        </Button>
    </div>
</template>
