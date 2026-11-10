<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    GripVertical,
    ImagePlus,
    Lock,
    Tag,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import FocalPointPicker from '@/components/galleries/FocalPointPicker.vue';
import TagInput from '@/components/galleries/TagInput.vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { recommendedHint, sizeWarning } from '@/lib/media';
import { destroy, index, update } from '@/routes/admin/galleries';
import type { MediaItem, UploadSettings } from '@/types/media';

type Item = {
    key: string;
    id: number | null;
    media: MediaItem;
    caption: string | null;
    focal_x: number;
    focal_y: number;
    tags: string[];
    badge: string | null;
    title: string | null;
    subtitle: string | null;
    link_label: string | null;
    link_url: string | null;
};

const props = defineProps<{
    gallery: {
        id: number;
        name: string;
        slug: string;
        type: 'slider' | 'standard';
        is_system: boolean;
        can_delete: boolean;
        items: Omit<Item, 'key'>[];
    };
    allTags: string[];
    upload: UploadSettings;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Galerije', href: index() }] },
});

let nextKey = 1;
const keyed = (items: Omit<Item, 'key'>[]): Item[] =>
    items.map((i) => ({ ...i, key: `i${nextKey++}` }));

const name = ref(props.gallery.name);
const items = ref<Item[]>(keyed(props.gallery.items));
const selectedKey = ref<string | null>(items.value[0]?.key ?? null);
const pickerOpen = ref(false);
const saving = ref(false);
const dirty = ref(false);
const errors = ref<Record<string, string>>({});

const isSlider = computed(() => props.gallery.type === 'slider');
const recommendedKey = computed(() => (isSlider.value ? 'slider' : 'gallery'));
const recommended = computed(
    () => props.upload.recommended[recommendedKey.value],
);
const selectedIndex = computed(() =>
    items.value.findIndex((i) => i.key === selectedKey.value),
);
const selected = computed(() =>
    selectedIndex.value >= 0 ? items.value[selectedIndex.value] : null,
);
const suggestions = computed(() =>
    [
        ...new Set([...props.allTags, ...items.value.flatMap((i) => i.tags)]),
    ].sort(),
);

watch([name, items], () => (dirty.value = true), { deep: true });
setTimeout(() => (dirty.value = false));

function addMedia(media: MediaItem[]): void {
    for (const m of media) {
        items.value.push({
            key: `i${nextKey++}`,
            id: null,
            media: m,
            caption: m.caption,
            focal_x: 50,
            focal_y: 50,
            tags: [],
            badge: null,
            title: null,
            subtitle: null,
            link_label: null,
            link_url: null,
        });
    }

    if (media.length) {
        selectedKey.value = items.value[items.value.length - 1].key;
    }
}

function removeItem(key: string): void {
    const i = items.value.findIndex((it) => it.key === key);
    items.value.splice(i, 1);

    if (selectedKey.value === key) {
        selectedKey.value =
            items.value[Math.min(i, items.value.length - 1)]?.key ?? null;
    }
}

const itemError = (i: number, field: string) =>
    errors.value[`items.${i}.${field}`];
const itemHasError = (i: number) =>
    Object.keys(errors.value).some((k) => k.startsWith(`items.${i}.`));

function save(): void {
    saving.value = true;
    errors.value = {};

    const payload = {
        name: name.value,
        items: items.value.map((i) => ({
            id: i.id,
            media_id: i.media.id,
            caption: i.caption,
            focal_x: i.focal_x,
            focal_y: i.focal_y,
            tags: i.tags,
            badge: i.badge,
            title: i.title,
            subtitle: i.subtitle,
            link_label: i.link_label,
            link_url: i.link_url,
        })),
    };

    router.put(update.url(props.gallery.id), payload as never, {
        preserveScroll: true,
        onSuccess: () => {
            const keepIndex = selectedIndex.value;
            items.value = keyed(props.gallery.items);
            selectedKey.value =
                items.value[keepIndex]?.key ?? items.value[0]?.key ?? null;
            setTimeout(() => (dirty.value = false));
        },
        onError: (e) => {
            errors.value = e;
            const first = Object.keys(e).find((k) => k.startsWith('items.'));

            if (first) {
                selectedKey.value =
                    items.value[Number(first.split('.')[1])]?.key ??
                    selectedKey.value;
            }
        },
        onFinish: () => (saving.value = false),
    });
}

function removeGallery(): void {
    if (
        window.confirm(
            `Obrisati galeriju „${props.gallery.name}"? Fotografije ostaju u medijima.`,
        )
    ) {
        router.delete(destroy.url(props.gallery.id));
    }
}

const nullable = (v: string | number) =>
    String(v).trim() === '' ? null : String(v);
</script>

<template>
    <Head :title="gallery.name" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link :href="index()" aria-label="Nazad na galerije"
                        ><ArrowLeft
                    /></Link>
                </Button>
                <h1 class="flex items-center gap-2 text-xl font-semibold">
                    <Lock
                        v-if="gallery.is_system"
                        class="size-4 text-muted-foreground"
                    />
                    {{ gallery.name }}
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <span
                    v-if="dirty"
                    class="text-xs text-amber-700 dark:text-amber-400"
                    >Nesačuvane izmjene</span
                >
                <Button
                    v-if="gallery.can_delete"
                    variant="ghost"
                    class="text-destructive"
                    @click="removeGallery"
                    ><Trash2 /> Obriši galeriju</Button
                >
                <Button :disabled="saving" @click="save"
                    ><Spinner v-if="saving" /> Sačuvaj galeriju</Button
                >
            </div>
        </div>

        <div class="grid max-w-md gap-2">
            <Label for="gallery-name">Naziv</Label>
            <Input id="gallery-name" v-model="name" />
            <p v-if="errors.name" class="text-xs text-destructive">
                {{ errors.name }}
            </p>
        </div>

        <p v-if="Object.keys(errors).length" class="text-sm text-destructive">
            Neke fotografije imaju greške – označene su crvenim okvirom.
        </p>

        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <!-- Photos -->
            <section class="min-w-0 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">
                        {{ items.length }} fotografija · prevucite za promjenu
                        redoslijeda · {{ recommended.label }}:
                        {{ recommendedHint(recommended) }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="pickerOpen = true"
                        ><ImagePlus /> Dodaj fotografije</Button
                    >
                </div>

                <VueDraggable
                    v-model="items"
                    :animation="150"
                    handle=".drag-handle"
                    :force-fallback="true"
                    class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5"
                >
                    <div
                        v-for="(item, i) in items"
                        :key="item.key"
                        class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg border-2 bg-muted"
                        :class="[
                            item.key === selectedKey
                                ? 'border-primary'
                                : 'border-transparent',
                            itemHasError(i) ? 'border-destructive!' : '',
                        ]"
                        @click="selectedKey = item.key"
                    >
                        <img
                            :src="item.media.thumb_url"
                            :alt="item.media.alt ?? ''"
                            class="size-full object-cover"
                            :style="{
                                objectPosition: `${item.focal_x}% ${item.focal_y}%`,
                            }"
                        />
                        <span
                            class="drag-handle absolute top-1 left-1 grid size-6 cursor-grab place-items-center rounded bg-background/90 shadow"
                        >
                            <GripVertical class="size-3.5" />
                        </span>
                        <button
                            type="button"
                            class="absolute top-1 right-1 grid size-6 place-items-center rounded-full bg-background/90 shadow"
                            aria-label="Ukloni iz galerije"
                            @click.stop="removeItem(item.key)"
                        >
                            <X class="size-3.5" />
                        </button>
                        <span
                            v-if="item.tags.length"
                            class="absolute bottom-1 left-1 inline-flex items-center gap-1 rounded bg-background/90 px-1.5 py-0.5 text-[10px] shadow"
                        >
                            <Tag class="size-3" /> {{ item.tags.length }}
                        </span>
                    </div>
                </VueDraggable>

                <p
                    v-if="!items.length"
                    class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
                >
                    Galerija je prazna. Dodajte fotografije iz medija ili
                    otpremite nove.
                </p>
            </section>

            <!-- Selected photo -->
            <aside v-if="selected" class="space-y-4 rounded-lg border p-4">
                <h2 class="text-sm font-semibold">
                    Fotografija {{ selectedIndex + 1 }} od {{ items.length }}
                </h2>

                <p
                    v-if="sizeWarning(selected.media, recommended)"
                    class="text-xs text-amber-700 dark:text-amber-400"
                >
                    {{ sizeWarning(selected.media, recommended) }}
                </p>

                <FocalPointPicker
                    v-model:x="selected.focal_x"
                    v-model:y="selected.focal_y"
                    :src="selected.media.medium_url"
                    :alt="selected.media.alt ?? ''"
                />

                <template v-if="isSlider">
                    <div class="grid gap-1.5">
                        <Label for="s-badge">Oznaka (npr. 🎓 Upis 2026.)</Label>
                        <Input
                            id="s-badge"
                            :model-value="selected.badge ?? ''"
                            @update:model-value="
                                selected.badge = nullable($event)
                            "
                        />
                        <p
                            v-if="itemError(selectedIndex, 'badge')"
                            class="text-xs text-destructive"
                        >
                            {{ itemError(selectedIndex, 'badge') }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-title">Naslov</Label>
                        <Input
                            id="s-title"
                            :model-value="selected.title ?? ''"
                            @update:model-value="
                                selected.title = nullable($event)
                            "
                        />
                        <p
                            v-if="itemError(selectedIndex, 'title')"
                            class="text-xs text-destructive"
                        >
                            {{ itemError(selectedIndex, 'title') }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="s-subtitle">Podnaslov</Label>
                        <textarea
                            id="s-subtitle"
                            :value="selected.subtitle ?? ''"
                            rows="2"
                            class="rounded-md border bg-transparent px-3 py-2 text-sm"
                            @input="
                                selected.subtitle = nullable(
                                    ($event.target as HTMLTextAreaElement)
                                        .value,
                                )
                            "
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="grid gap-1.5">
                            <Label for="s-link-label">Tekst dugmeta</Label>
                            <Input
                                id="s-link-label"
                                :model-value="selected.link_label ?? ''"
                                placeholder="Saznaj više"
                                @update:model-value="
                                    selected.link_label = nullable($event)
                                "
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="s-link-url">Link dugmeta</Label>
                            <Input
                                id="s-link-url"
                                :model-value="selected.link_url ?? ''"
                                placeholder="/upis-2020/"
                                @update:model-value="
                                    selected.link_url = nullable($event)
                                "
                            />
                        </div>
                    </div>
                    <p
                        v-if="itemError(selectedIndex, 'link_url')"
                        class="text-xs text-destructive"
                    >
                        {{ itemError(selectedIndex, 'link_url') }}
                    </p>
                </template>

                <div v-else class="grid gap-1.5">
                    <Label for="s-caption">Opis (ispod fotografije)</Label>
                    <Input
                        id="s-caption"
                        :model-value="selected.caption ?? ''"
                        @update:model-value="
                            selected.caption = nullable($event)
                        "
                    />
                    <p
                        v-if="itemError(selectedIndex, 'caption')"
                        class="text-xs text-destructive"
                    >
                        {{ itemError(selectedIndex, 'caption') }}
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="s-tags">Tagovi (filteri na sajtu)</Label>
                    <TagInput
                        id="s-tags"
                        v-model="selected.tags"
                        :suggestions="suggestions"
                    />
                    <p class="text-xs text-muted-foreground">
                        Enter ili zarez dodaje tag. Npr. Ekskurzije, Matura,
                        Sekcije.
                    </p>
                </div>
            </aside>
        </div>

        <MediaPicker
            v-model:open="pickerOpen"
            :settings="upload"
            multiple
            :recommended-key="recommendedKey"
            title="Dodajte fotografije u galeriju"
            @select="addMedia"
        />
    </div>
</template>
