<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarClock, Star } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RichEditor from '@/components/editor/RichEditor.vue';
import InputError from '@/components/InputError.vue';
import GalleryField from '@/components/media/GalleryField.vue';
import ImageField from '@/components/media/ImageField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { slugify } from '@/lib/slug';
import { destroy, index, store, update } from '@/routes/admin/posts';
import type { MediaItem, UploadSettings } from '@/types/media';

type PostData = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string | null;
    video_url_1: string | null;
    video_url_2: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
    is_scheduled: boolean;
    featured_image: MediaItem | null;
    gallery: MediaItem[];
    category_ids: number[];
    meta_title: string | null;
    meta_description: string | null;
    author: string | null;
    trashed: boolean;
    updated_at: string | null;
};

const props = defineProps<{
    post: PostData | null;
    categories: { id: number; name: string; parent_id: number | null }[];
    statuses: { value: string; label: string }[];
    upload: UploadSettings;
    siteUrl: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Objave', href: index() }] },
});

const isNew = computed(() => props.post === null);

const featured = ref<MediaItem | null>(props.post?.featured_image ?? null);
const gallery = ref<MediaItem[]>(props.post?.gallery ?? []);

const form = useForm({
    title: props.post?.title ?? '',
    slug: props.post?.slug ?? '',
    excerpt: props.post?.excerpt ?? '',
    content: props.post?.content ?? '',
    video_url_1: props.post?.video_url_1 ?? '',
    video_url_2: props.post?.video_url_2 ?? '',
    status: props.post?.status ?? 'draft',
    published_at: props.post?.published_at ?? '',
    category_ids: props.post?.category_ids ?? ([] as number[]),
    meta_title: props.post?.meta_title ?? '',
    meta_description: props.post?.meta_description ?? '',
    featured_media_id: (props.post?.featured_image?.id ?? null) as
        | number
        | null,
    gallery_ids: (props.post?.gallery ?? []).map((m) => m.id),
});

// Auto-generate the slug from the title until the user edits it (new posts only).
const slugTouched = ref(!isNew.value);
watch(
    () => form.title,
    (title) => {
        if (!slugTouched.value) {
            form.slug = slugify(title);
        }
    },
);

const permalink = computed(() => `${props.siteUrl}/${form.slug || '…'}/`);
const isFuture = computed(
    () => !!form.published_at && new Date(form.published_at) > new Date(),
);

const submitLabel = computed(() => {
    if (form.status === 'draft') {
        return 'Sačuvaj nacrt';
    }

    if (isFuture.value) {
        return 'Zakaži';
    }

    return isNew.value || props.post?.status === 'draft'
        ? 'Objavi'
        : 'Ažuriraj';
});

function toggleCategory(id: number): void {
    form.category_ids = form.category_ids.includes(id)
        ? form.category_ids.filter((c) => c !== id)
        : [...form.category_ids, id];
}

function makePrimary(id: number): void {
    form.category_ids = [id, ...form.category_ids.filter((c) => c !== id)];
}

function submit(): void {
    form.featured_media_id = featured.value?.id ?? null;
    form.gallery_ids = gallery.value.map((m) => m.id);

    if (props.post) {
        form.put(update.url(props.post.id), { preserveScroll: true });
    } else {
        form.post(store.url());
    }
}

function moveToTrash(): void {
    if (props.post && window.confirm('Premjestiti objavu u otpad?')) {
        router.delete(destroy.url(props.post.id), {
            onSuccess: () => router.visit(index.url()),
        });
    }
}
</script>

<template>
    <Head :title="isNew ? 'Nova objava' : form.title || 'Objava'" />

    <form class="flex flex-1 flex-col gap-6 p-4" @submit.prevent="submit">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link :href="index()" aria-label="Nazad na objave"
                        ><ArrowLeft
                    /></Link>
                </Button>
                <h1 class="text-xl font-semibold">
                    {{ isNew ? 'Nova objava' : 'Uređivanje objave' }}
                </h1>
            </div>
            <p v-if="post?.updated_at" class="text-xs text-muted-foreground">
                Posljednja izmjena: {{ post.updated_at
                }}<span v-if="post.author"> · Autor: {{ post.author }}</span>
            </p>
        </div>

        <div
            v-if="post?.trashed"
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Ova objava je u otpadu i ne prikazuje se na sajtu.
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <!-- Main column -->
            <div class="min-w-0 space-y-6">
                <div class="grid gap-2">
                    <Label for="title">Naslov</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        class="h-11 text-lg"
                        placeholder="Naslov objave"
                        autofocus
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="slug">Link</Label>
                    <div
                        class="flex items-center rounded-md border bg-muted/40 text-sm"
                    >
                        <span class="truncate pl-3 text-muted-foreground"
                            >{{ siteUrl }}/</span
                        >
                        <input
                            id="slug"
                            v-model="form.slug"
                            class="min-w-0 flex-1 bg-transparent py-2 font-mono text-sm focus:outline-none"
                            @input="slugTouched = true"
                        />
                        <span class="pr-3 text-muted-foreground">/</span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <a
                            v-if="post"
                            :href="`${siteUrl}/${post.slug}/`"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-primary underline underline-offset-2"
                        >
                            {{
                                post.status === 'published' &&
                                !post.is_scheduled
                                    ? 'Pogledaj na sajtu'
                                    : 'Pregled'
                            }}
                        </a>
                        <span v-if="post"> · </span>
                        {{ permalink }}
                        <span
                            v-if="
                                post &&
                                post.status === 'published' &&
                                form.slug !== post.slug
                            "
                        >
                            — stari link će automatski preusmjeravati na novi.
                        </span>
                    </p>
                    <InputError :message="form.errors.slug" />
                </div>

                <div class="grid gap-2">
                    <Label>Tekst</Label>
                    <RichEditor v-model="form.content" :upload="upload" />
                    <InputError :message="form.errors.content" />
                </div>

                <div class="grid gap-3">
                    <div>
                        <Label>Video (YouTube)</Label>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Zalijepite link sa YouTube-a (npr.
                            https://www.youtube.com/watch?v=… ili
                            https://youtu.be/…). Video se prikazuje ispod
                            teksta, iznad fotografija.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="video_url_1" class="sr-only"
                            >YouTube video 1</Label
                        >
                        <Input
                            id="video_url_1"
                            v-model="form.video_url_1"
                            type="url"
                            inputmode="url"
                            placeholder="YouTube link 1"
                        />
                        <InputError :message="form.errors.video_url_1" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="video_url_2" class="sr-only"
                            >YouTube video 2</Label
                        >
                        <Input
                            id="video_url_2"
                            v-model="form.video_url_2"
                            type="url"
                            inputmode="url"
                            placeholder="YouTube link 2"
                        />
                        <InputError :message="form.errors.video_url_2" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Galerija ispod teksta</Label>
                    <GalleryField v-model="gallery" :upload="upload" />
                    <InputError :message="form.errors.gallery_ids" />
                </div>

                <div class="grid gap-2">
                    <Label for="excerpt">Kratak opis</Label>
                    <textarea
                        id="excerpt"
                        v-model="form.excerpt"
                        rows="3"
                        class="rounded-md border bg-transparent px-3 py-2 text-sm"
                        placeholder="Prikazuje se u listi vijesti. Ako ostane prazno, koristi se početak teksta."
                    />
                    <InputError :message="form.errors.excerpt" />
                </div>

                <details class="rounded-lg border p-4">
                    <summary class="cursor-pointer text-sm font-medium">
                        SEO (pretraživači i društvene mreže)
                    </summary>
                    <div class="mt-4 space-y-4">
                        <div class="grid gap-2">
                            <Label for="meta_title">SEO naslov</Label>
                            <Input
                                id="meta_title"
                                v-model="form.meta_title"
                                :placeholder="form.title"
                            />
                            <InputError :message="form.errors.meta_title" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="meta_description">SEO opis</Label>
                            <textarea
                                id="meta_description"
                                v-model="form.meta_description"
                                rows="2"
                                class="rounded-md border bg-transparent px-3 py-2 text-sm"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ form.meta_description.length }}/160
                                preporučeno
                            </p>
                            <InputError
                                :message="form.errors.meta_description"
                            />
                        </div>
                    </div>
                </details>
            </div>

            <!-- Sidebar -->
            <aside class="space-y-5">
                <section class="space-y-4 rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Objavljivanje</h2>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option
                                v-for="s in statuses"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="published_at">Datum i vrijeme objave</Label>
                        <Input
                            id="published_at"
                            v-model="form.published_at"
                            type="datetime-local"
                        />
                        <p class="text-xs text-muted-foreground">
                            Prazno = odmah. Budući datum = objava se zakazuje.
                        </p>
                        <p
                            v-if="form.status === 'published' && isFuture"
                            class="flex items-center gap-1.5 text-xs text-sky-700 dark:text-sky-400"
                        >
                            <CalendarClock class="size-3.5" /> Objava će se
                            pojaviti na sajtu u zakazano vrijeme.
                        </p>
                        <InputError :message="form.errors.published_at" />
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2">
                        <Button
                            v-if="post && !post.trashed"
                            type="button"
                            variant="link"
                            class="px-0 text-destructive"
                            @click="moveToTrash"
                        >
                            Premjesti u otpad
                        </Button>
                        <span v-else />
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" /> {{ submitLabel }}
                        </Button>
                    </div>
                    <p v-if="form.hasErrors" class="text-xs text-destructive">
                        Provjerite označena polja.
                    </p>
                </section>

                <section class="space-y-3 rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Kategorije</h2>
                    <p
                        v-if="!categories.length"
                        class="text-sm text-muted-foreground"
                    >
                        Još nema kategorija.
                    </p>
                    <ul class="max-h-64 space-y-1 overflow-y-auto">
                        <li
                            v-for="c in categories"
                            :key="c.id"
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <label
                                class="flex items-center gap-2"
                                :class="c.parent_id ? 'pl-4' : ''"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 accent-primary"
                                    :checked="form.category_ids.includes(c.id)"
                                    @change="toggleCategory(c.id)"
                                />
                                {{ c.name }}
                            </label>
                            <span
                                v-if="form.category_ids[0] === c.id"
                                class="flex items-center gap-1 text-xs text-primary"
                            >
                                <Star class="size-3 fill-current" /> Glavna
                            </span>
                            <button
                                v-else-if="form.category_ids.includes(c.id)"
                                type="button"
                                class="text-xs text-muted-foreground hover:underline"
                                @click="makePrimary(c.id)"
                            >
                                Postavi kao glavnu
                            </button>
                        </li>
                    </ul>
                    <p class="text-xs text-muted-foreground">
                        Glavna kategorija se prikazuje u putanji (breadcrumb).
                    </p>
                    <InputError :message="form.errors.category_ids" />
                </section>

                <section class="space-y-3 rounded-lg border p-4">
                    <h2 class="text-sm font-semibold">Naslovna slika</h2>
                    <ImageField
                        v-model="featured"
                        :upload="upload"
                        recommended-key="featured"
                        title="Naslovna slika objave"
                    />
                    <InputError :message="form.errors.featured_media_id" />
                </section>
            </aside>
        </div>
    </form>
</template>
