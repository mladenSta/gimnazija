<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ListTree, Pencil, Plus, RotateCcw, Search, Trash2 } from '@lucide/vue';
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
} from '@/routes/admin/pages';
import type { Paginated } from '@/types/media';

type Status = 'published' | 'scheduled' | 'draft' | 'trash';
type Tab = 'all' | Status;

type PageRow = {
    id: number;
    title: string;
    slug: string;
    status: Status;
    updated_at: string | null;
    author: string | null;
    in_menu: boolean;
    can: { update: boolean; delete: boolean };
};

const props = defineProps<{
    pages: Paginated<PageRow>;
    filters: { status: Tab; search: string };
    counts: Record<Tab, number>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Stranice', href: index() }] },
});

const tabs: { key: Tab; label: string }[] = [
    { key: 'all', label: 'Sve' },
    { key: 'published', label: 'Objavljene' },
    { key: 'scheduled', label: 'Zakazane' },
    { key: 'draft', label: 'Nacrti' },
    { key: 'trash', label: 'Otpad' },
];

const search = ref(props.filters.search);

function visit(overrides: Record<string, string | number | null> = {}): void {
    const query: Record<string, string | number> = {};
    const values = {
        status: props.filters.status,
        search: search.value,
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

function trash(page: PageRow): void {
    const note = page.in_menu
        ? ' Stranica je u meniju; link će prestati da radi dok je u otpadu.'
        : '';

    if (window.confirm(`Premjestiti „${page.title}" u otpad?${note}`)) {
        router.delete(destroy.url(page.id), { preserveScroll: true });
    }
}

function restorePage(page: PageRow): void {
    router.post(restore.url(page.id), {}, { preserveScroll: true });
}

function deleteForever(page: PageRow): void {
    const note = page.in_menu ? ' Biće uklonjena i iz menija.' : '';

    if (
        window.confirm(
            `Trajno obrisati „${page.title}"? Ovo se ne može poništiti.${note}`,
        )
    ) {
        router.delete(forceDelete.url(page.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Stranice" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Stranice"
                description="Stalne stranice sajta (Istorijat škole, Kontakt, Biblioteka…)."
            />
            <Button as-child
                ><Link :href="create()"><Plus /> Nova stranica</Link></Button
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

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="p-3">Naslov</th>
                        <th class="hidden p-3 lg:table-cell">Autor</th>
                        <th class="p-3">Izmijenjeno</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="page in pages.data"
                        :key="page.id"
                        class="border-t align-middle hover:bg-muted/30"
                    >
                        <td class="p-3">
                            <Link
                                v-if="
                                    page.can.update && page.status !== 'trash'
                                "
                                :href="edit(page.id)"
                                class="font-medium hover:underline"
                            >
                                {{ page.title }}
                            </Link>
                            <span v-else class="font-medium">{{
                                page.title
                            }}</span>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                            >
                                <StatusBadge :status="page.status" />
                                <span
                                    v-if="page.in_menu"
                                    class="inline-flex items-center gap-1"
                                    title="Stranica je u glavnom meniju"
                                >
                                    <ListTree class="size-3" /> u meniju
                                </span>
                                <span>/{{ page.slug }}/</span>
                            </div>
                        </td>
                        <td
                            class="hidden p-3 text-muted-foreground lg:table-cell"
                        >
                            {{ page.author ?? '—' }}
                        </td>
                        <td
                            class="p-3 whitespace-nowrap text-muted-foreground tabular-nums"
                        >
                            {{ page.updated_at ?? '—' }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1">
                                <template v-if="page.status === 'trash'">
                                    <Button
                                        v-if="page.can.delete"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Vrati"
                                        @click="restorePage(page)"
                                    >
                                        <RotateCcw />
                                    </Button>
                                    <Button
                                        v-if="page.can.delete"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Trajno obriši"
                                        class="text-destructive"
                                        @click="deleteForever(page)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </template>
                                <template v-else>
                                    <Button
                                        v-if="page.can.update"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Uredi"
                                        as-child
                                    >
                                        <Link :href="edit(page.id)"
                                            ><Pencil
                                        /></Link>
                                    </Button>
                                    <Button
                                        v-if="page.can.delete"
                                        variant="ghost"
                                        size="icon-sm"
                                        title="Premjesti u otpad"
                                        @click="trash(page)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </template>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!pages.data.length">
                        <td
                            colspan="4"
                            class="p-10 text-center text-muted-foreground"
                        >
                            Nema stranica.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="pages.meta" />
    </div>
</template>
