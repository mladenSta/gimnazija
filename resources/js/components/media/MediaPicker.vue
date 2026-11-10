<script setup lang="ts">
import { Search, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import MediaDropzone from '@/components/media/MediaDropzone.vue';
import MediaGrid from '@/components/media/MediaGrid.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/http';
import { recommendedHint, sizeWarning } from '@/lib/media';
import { browse } from '@/routes/admin/media';
import type {
    MediaItem,
    Paginated,
    RecommendedSize,
    UploadSettings,
} from '@/types/media';

/**
 * Choose one or more images from the library (or upload new ones).
 *
 * <MediaPicker v-model:open="open" :settings="upload" recommended-key="featured" @select="onSelect" />
 */
const props = withDefaults(
    defineProps<{
        settings: UploadSettings;
        multiple?: boolean;
        recommendedKey?: keyof UploadSettings['recommended'] | null;
        title?: string;
        /** Already-chosen ids (pre-selected when the dialog opens). */
        initialIds?: number[];
    }>(),
    {
        multiple: false,
        recommendedKey: null,
        title: 'Izaberite fotografiju',
        initialIds: () => [],
    },
);

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    select: [media: MediaItem[]];
}>();

const items = ref<MediaItem[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const search = ref('');
const selected = ref<MediaItem[]>([]);

const recommended = computed<RecommendedSize | null>(() =>
    props.recommendedKey
        ? props.settings.recommended[props.recommendedKey]
        : null,
);
const selectedIds = computed(() => selected.value.map((m) => m.id));
const warning = computed(() =>
    recommended.value && selected.value.length === 1
        ? sizeWarning(selected.value[0], recommended.value)
        : null,
);

async function load(reset = false): Promise<void> {
    if (reset) {
        page.value = 1;
    }

    loading.value = true;

    try {
        const query: Record<string, string | number> = { page: page.value };

        if (search.value) {
            query.search = search.value;
        }

        const { data } = await requestJson<Paginated<MediaItem>>(
            'get',
            browse.url({ query }),
        );
        items.value = reset ? data.data : [...items.value, ...data.data];
        lastPage.value = data.meta.last_page;
    } finally {
        loading.value = false;
    }
}

function loadMore(): void {
    page.value++;
    void load();
}

watch(open, (isOpen) => {
    if (isOpen) {
        search.value = '';
        selected.value = [];
        void load(true).then(() => {
            selected.value = items.value.filter((m) =>
                props.initialIds.includes(m.id),
            );
        });
    }
});

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    if (!open.value) {
        return;
    }

    clearTimeout(timer);
    timer = setTimeout(() => void load(true), 300);
});

function toggle(media: MediaItem): void {
    if (!props.multiple) {
        selected.value = selectedIds.value.includes(media.id) ? [] : [media];

        return;
    }

    selected.value = selectedIds.value.includes(media.id)
        ? selected.value.filter((m) => m.id !== media.id)
        : [...selected.value, media];
}

function onUploaded(media: MediaItem): void {
    items.value = [media, ...items.value.filter((m) => m.id !== media.id)];
    toggle(media);
}

function confirm(): void {
    emit('select', selected.value);
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[90vh] flex-col sm:max-w-5xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>
                    <template v-if="recommended"
                        >{{ recommended.label }} –
                        {{ recommendedHint(recommended) }}.
                    </template>
                    Fotografije drugačijih dimenzija sajt automatski isijeca, pa
                    raspored ostaje isti.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                <MediaDropzone
                    :settings="settings"
                    compact
                    @uploaded="onUploaded"
                />

                <div class="relative max-w-sm">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        placeholder="Pretraži…"
                        class="pl-9"
                    />
                </div>

                <MediaGrid
                    :items="items"
                    :selected-ids="selectedIds"
                    :recommended="recommended"
                    @open="toggle"
                />

                <p
                    v-if="!loading && !items.length"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    Nema fotografija.
                </p>

                <div class="flex justify-center">
                    <Spinner v-if="loading" />
                    <Button
                        v-else-if="page < lastPage"
                        variant="outline"
                        type="button"
                        @click="loadMore"
                        >Učitaj još</Button
                    >
                </div>
            </div>

            <DialogFooter class="items-center gap-3 sm:justify-between">
                <p
                    v-if="warning"
                    class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400"
                >
                    <TriangleAlert class="size-4 shrink-0" /> {{ warning }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    {{
                        multiple
                            ? `Izabrano: ${selected.length}`
                            : selected.length
                              ? '1 fotografija izabrana'
                              : ''
                    }}
                </p>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        type="button"
                        @click="open = false"
                        >Odustani</Button
                    >
                    <Button
                        type="button"
                        :disabled="!selected.length"
                        @click="confirm"
                        >Izaberi</Button
                    >
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
