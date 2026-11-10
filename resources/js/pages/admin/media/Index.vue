<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import Pagination from '@/components/admin/Pagination.vue';
import Heading from '@/components/Heading.vue';
import MediaDetails from '@/components/media/MediaDetails.vue';
import MediaDropzone from '@/components/media/MediaDropzone.vue';
import MediaGrid from '@/components/media/MediaGrid.vue';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/media';
import type { MediaItem, Paginated, UploadSettings } from '@/types/media';

const props = defineProps<{
    media: Paginated<MediaItem>;
    filters: { search: string };
    upload: UploadSettings;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mediji', href: index() }],
    },
});

const search = ref(props.filters.search);
const selected = ref<MediaItem | null>(null);

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(index.url(), value ? { search: value } : {}, {
            preserveState: true,
            replace: true,
            only: ['media', 'filters'],
        });
    }, 300);
});

let reloadTimer: ReturnType<typeof setTimeout> | undefined;
function onUploaded(): void {
    // Several files can finish close together: reload once.
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => router.reload({ only: ['media'] }), 400);
}

function onUpdated(media: MediaItem): void {
    const item = props.media.data.find((m) => m.id === media.id);

    if (item) {
        Object.assign(item, media);
    }
}

function onDeleted(): void {
    selected.value = null;
    router.reload({ only: ['media'] });
}
</script>

<template>
    <Head title="Mediji" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Mediji"
            description="Sve fotografije otpremljene na sajt. Kliknite na fotografiju za detalje."
        />

        <MediaDropzone :settings="upload" @uploaded="onUploaded" />

        <div class="relative max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                placeholder="Pretraži po nazivu ili opisu…"
                class="pl-9"
            />
        </div>

        <MediaGrid
            v-if="media.data.length"
            :items="media.data"
            @open="selected = $event"
        />
        <p
            v-else
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            {{
                filters.search
                    ? 'Nema fotografija za ovu pretragu.'
                    : 'Još nema otpremljenih fotografija.'
            }}
        </p>

        <Pagination :meta="media.meta" />
    </div>

    <MediaDetails
        :media="selected"
        @close="selected = null"
        @updated="onUpdated"
        @deleted="onDeleted"
    />
</template>
