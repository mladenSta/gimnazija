<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff, Pencil, Plus, RotateCcw, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create,
    destroy,
    edit,
    forceDelete,
    index,
    restore,
} from '@/routes/admin/posts';
import type { Paginated } from '@/types/media';

type Status = 'published' | 'scheduled' | 'draft' | 'trash';
type Tab = 'all' | Status;

type PostRow = {
    id: number;
    title: string;
    slug: string;
    status: Status;
    date: string | null;
    author: string | null;
    categories: string[];
    thumb_url: string | null;
    can: { update: boolean; delete: boolean };
};

const props = defineProps<{
    posts: Paginated<PostRow>;
    filters: { status: Tab; search: string; category: number | null };
    counts: Record<Tab, number>;
    categories: { id: number; name: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Objave', href: index() }] },
});

const tabs: { key: Tab; label: string }[] = [
    { key: 'all', label: 'Sve' },
    { key: 'published', label: 'Objavljene' },
    { key: 'scheduled', label: 'Zakazane' },
    { key: 'draft', label: 'Nacrti' },
    { key: 'trash', label: 'Otpad' },
];

const search = ref(props.filters.search);
const category = ref<string>(
    props.filters.category ? String(props.filters.category) : '',
);

function visit(overrides: Record<string, string | number | null> = {}): void {
    const query: Record<string, string | number> = {};
    const values = {
        status: props.filters.status,
        search: search.value,
        category: category.value,
        ...overrides,
    };

    for (const [k, v] of Object.entries(values)) {
        if (v !== null && v !== '' && !(k === 'status' && v === 'all')) {
            query[k] = v;
        }
    }

    router.get(index.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => visit(), 300);
});
watch(category, () => visit());

function trash(post: PostRow): void {
    router.delete(destroy.url(post.id), { preserveScroll: true });
}

function restorePost(post: PostRow): void {
    router.post(restore.url(post.id), {}, { preserveScroll: true });
}

function deleteForever(post: PostRow): void {
    if (
        window.confirm(
            `Trajno obrisati „${post.title}"? Ovo se ne može poništiti.`,
        )
    ) {
        router.delete(forceDelete.url(post.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Objave" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading title="Objave" description="Vijesti i članci sa sajta." />
            <Button as-child
                ><Link :href="create()"><Plus /> Nova objava</Link></Button
            >
        </div>

        <nav class="flex flex-wrap gap-1 border-b">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="
                    filters.status === tab.key
                        ? 'border-primary font-medium'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="visit({ status: tab.key, page: null })"
            >
                {{ tab.label }}
                <span class="text-xs text-muted-foreground tabular-nums"
                    >({{ counts[tab.key] }})</span
                >
            </button>
        </nav>

        <div class="flex flex-wrap gap-3">
            <div class="relative w-full max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Pretraži naslove…"
                    class="pl-9"
                />
            </div>
            <select
                v-model="category"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">Sve kategorije</option>
                <option
                    v-for="c in categories"
                    :key="c.id"
                    :value="String(c.id)"
                >
                    {{ c.name }}
                </option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="w-16 p-3"></th>
                        <th class="p-3">Naslov</th>
                        <th class="hidden p-3 md:table-cell">Kategorije</th>
                        <th class="hidden p-3 lg:table-cell">Autor</th>
                        <th class="p-3">Datum</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="post in posts.data"
                        :key="post.id"
                        class="border-t align-middle hover:bg-muted/30"
                    >
                        <td class="p-3">
                            <img
                                v-if="post.thumb_url"
                                :src="post.thumb_url"
                                alt=""
                                class="size-12 rounded object-cover"
                            />
                            <span
                                v-else
                                class="grid size-12 place-items-center rounded bg-muted text-muted-foreground"
                            >
                                <ImageOff class="size-4" />
                            </span>
                        </td>
                        <td class="p-3">
                            <Link
                                v-if="
                                    post.can.update && post.status !== 'trash'
                                "
                                :href="edit(post.id)"
                                class="font-medium hover:underline"
                            >
                                {{ post.title }}
                            </Link>
                            <span v-else class="font-medium">{{
                                post.title
                            }}</span>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                            >
                                <StatusBadge :status="post.status" />
                                <span>/{{ post.slug }}/</span>
                            </div>
                        </td>
                        <td
                            class="hidden p-3 text-muted-foreground md:table-cell"
                        >
                            {{ post.categories.join(', ') || '—' }}
                        </td>
                        <td
                            class="hidden p-3 text-muted-foreground lg:table-cell"
                        >
                            {{ post.author ?? '—' }}
                        </td>
                        <td
                            class="p-3 whitespace-nowrap text-muted-foreground tabular-nums"
                        >
                            {{ post.date ?? '—' }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1">
                                <template v-if="post.status === 'trash'">
                                    <Button
                                        v-if="post.can.delete"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Vrati"
                                        @click="restorePost(post)"
                                    >
                                        <RotateCcw />
                                    </Button>
                                    <Button
                                        v-if="post.can.delete"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Trajno obriši"
                                        class="text-destructive"
                                        @click="deleteForever(post)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </template>
                                <template v-else>
                                    <Button
                                        v-if="post.can.update"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Uredi"
                                        as-child
                                    >
                                        <Link :href="edit(post.id)"
                                            ><Pencil
                                        /></Link>
                                    </Button>
                                    <Button
                                        v-if="post.can.delete"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Premjesti u otpad"
                                        @click="trash(post)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </template>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!posts.data.length">
                        <td
                            colspan="6"
                            class="p-10 text-center text-muted-foreground"
                        >
                            Nema objava.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="posts.meta" />
    </div>
</template>
