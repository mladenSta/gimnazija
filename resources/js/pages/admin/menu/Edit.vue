<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import Heading from '@/components/Heading.vue';
import MenuItemCard from '@/components/menu/MenuItemCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/admin/menu';
import type { MenuNode } from '@/types/menu';

type PageOption = {
    id: number;
    title: string;
    slug: string;
    published: boolean;
};
type CategoryOption = { id: number; name: string; slug: string };

const props = defineProps<{
    items: Omit<MenuNode, 'key'>[];
    pages: PageOption[];
    categories: CategoryOption[];
    maxDepth: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Meni', href: edit() }] },
});

let nextKey = 1;
const withKeys = (nodes: Omit<MenuNode, 'key'>[]): MenuNode[] =>
    nodes.map((n) => ({
        ...n,
        key: `k${nextKey++}`,
        open: false,
        children: withKeys(n.children ?? []),
    }));

const tree = ref<MenuNode[]>(withKeys(props.items));
const saving = ref(false);
const dragging = ref(false);
const dirty = ref(false);
const errors = ref<Record<string, string>>({});

watch(tree, () => (dirty.value = true), { deep: true });

// ---- Adding items ---------------------------------------------------------
const pageSearch = ref('');
const selectedPages = ref<number[]>([]);
const selectedCategories = ref<number[]>([]);
const custom = ref({ label: '', url: '' });

const filteredPages = computed(() => {
    const q = pageSearch.value.toLowerCase();

    return q
        ? props.pages.filter((p) => p.title.toLowerCase().includes(q))
        : props.pages;
});

function node(partial: Partial<MenuNode> & Pick<MenuNode, 'type'>): MenuNode {
    return {
        key: `k${nextKey++}`,
        id: null,
        page_id: null,
        category_id: null,
        url: null,
        label: null,
        original_label: null,
        href: null,
        warning: null,
        children: [],
        open: false,
        ...partial,
    };
}

function addPages(): void {
    for (const id of selectedPages.value) {
        const page = props.pages.find((p) => p.id === id);

        if (page) {
            tree.value.push(
                node({
                    type: 'page',
                    page_id: page.id,
                    original_label: page.title,
                    href: `/${page.slug}/`,
                    warning: page.published
                        ? null
                        : 'Stranica nije objavljena – stavka se ne prikazuje.',
                }),
            );
        }
    }

    selectedPages.value = [];
}

function addCategories(): void {
    for (const id of selectedCategories.value) {
        const category = props.categories.find((c) => c.id === id);

        if (category) {
            tree.value.push(
                node({
                    type: 'category',
                    category_id: category.id,
                    original_label: category.name,
                    href: `/category/${category.slug}/`,
                }),
            );
        }
    }

    selectedCategories.value = [];
}

function addCustom(): void {
    if (!custom.value.label.trim()) {
        return;
    }

    tree.value.push(
        node({
            type: 'custom',
            label: custom.value.label.trim(),
            url: custom.value.url.trim() || null,
            href: custom.value.url.trim() || null,
        }),
    );
    custom.value = { label: '', url: '' };
}

function addPostsIndex(): void {
    tree.value.push(node({ type: 'posts-index', href: '/vijesti/' }));
}

// ---- Tree editing ---------------------------------------------------------

/**
 * Keep the menu two levels deep: an item that has its own submenu
 * can't be dropped into another item's submenu.
 */
function canMove(event: { dragged: HTMLElement; to: HTMLElement }): boolean {
    return !(
        event.to.dataset.depth === '2' &&
        event.dragged.dataset.hasChildren === 'true'
    );
}

function removeRoot(index: number): void {
    // Like WordPress: the submenu items move up to the top level.
    const [removed] = tree.value.splice(index, 1);
    tree.value.splice(index, 0, ...removed.children);
}

function removeChild(parent: MenuNode, index: number): void {
    parent.children.splice(index, 1);
}

// ---- Save -----------------------------------------------------------------
type Payload = Omit<
    MenuNode,
    'key' | 'original_label' | 'href' | 'warning' | 'open' | 'children'
> & { children?: Payload[] };

function serialize(nodes: MenuNode[]): Payload[] {
    return nodes.map((n) => ({
        id: n.id,
        type: n.type,
        page_id: n.page_id,
        category_id: n.category_id,
        url: n.url,
        label: n.label,
        children: serialize(n.children),
    }));
}

function save(): void {
    saving.value = true;
    errors.value = {};

    router.put(update.url(), { items: serialize(tree.value) } as never, {
        preserveScroll: true,
        onSuccess: () => {
            tree.value = withKeys(props.items);
            setTimeout(() => (dirty.value = false));
        },
        onError: (e) => (errors.value = e),
        onFinish: () => (saving.value = false),
    });
}

const hasErrors = computed(() => Object.keys(errors.value).length > 0);

// Warn before leaving with unsaved changes.
let removeBeforeVisit: (() => void) | undefined;
const beforeUnload = (e: BeforeUnloadEvent) => {
    if (dirty.value) {
        e.preventDefault();
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    removeBeforeVisit = router.on('before', (event) => {
        const leaving =
            event.detail.visit.url.pathname !== window.location.pathname;

        return !(
            dirty.value &&
            leaving &&
            !window.confirm(
                'Imate nesačuvane izmjene menija. Napustiti stranicu?',
            )
        );
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    removeBeforeVisit?.();
});

setTimeout(() => (dirty.value = false));
</script>

<template>
    <Head title="Meni" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Glavni meni"
                description="Dodajte stavke lijevo. Prevucite stavku ispod druge da napravite podmeni."
            />
            <div class="flex items-center gap-3">
                <span
                    v-if="dirty"
                    class="text-xs text-amber-700 dark:text-amber-400"
                    >Nesačuvane izmjene</span
                >
                <Button :disabled="saving" @click="save"
                    ><Spinner v-if="saving" /> Sačuvaj meni</Button
                >
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
            <!-- Add items -->
            <aside class="space-y-4">
                <details open class="rounded-lg border p-4">
                    <summary class="cursor-pointer text-sm font-semibold">
                        Stranice
                    </summary>
                    <div class="mt-3 space-y-3">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="pageSearch"
                                type="search"
                                placeholder="Pretraži…"
                                class="pl-9"
                            />
                        </div>
                        <ul class="max-h-56 space-y-1 overflow-y-auto">
                            <li v-for="page in filteredPages" :key="page.id">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="selectedPages"
                                        type="checkbox"
                                        :value="page.id"
                                        class="size-4 accent-primary"
                                    />
                                    <span class="truncate">{{
                                        page.title
                                    }}</span>
                                    <span
                                        v-if="!page.published"
                                        class="text-xs text-muted-foreground"
                                        >(nije objavljena)</span
                                    >
                                </label>
                            </li>
                            <li
                                v-if="!filteredPages.length"
                                class="text-sm text-muted-foreground"
                            >
                                Nema stranica.
                            </li>
                        </ul>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!selectedPages.length"
                            @click="addPages"
                            ><Plus /> Dodaj u meni</Button
                        >
                    </div>
                </details>

                <details class="rounded-lg border p-4">
                    <summary class="cursor-pointer text-sm font-semibold">
                        Kategorije
                    </summary>
                    <div class="mt-3 space-y-3">
                        <p class="text-xs text-muted-foreground">
                            Link vodi na listu vijesti iz te kategorije.
                        </p>
                        <ul class="max-h-56 space-y-1 overflow-y-auto">
                            <li
                                v-for="category in categories"
                                :key="category.id"
                            >
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="selectedCategories"
                                        type="checkbox"
                                        :value="category.id"
                                        class="size-4 accent-primary"
                                    />
                                    {{ category.name }}
                                </label>
                            </li>
                            <li
                                v-if="!categories.length"
                                class="text-sm text-muted-foreground"
                            >
                                Nema kategorija.
                            </li>
                        </ul>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!selectedCategories.length"
                            @click="addCategories"
                            ><Plus /> Dodaj u meni</Button
                        >
                    </div>
                </details>

                <details class="rounded-lg border p-4">
                    <summary class="cursor-pointer text-sm font-semibold">
                        Prilagođeni link
                    </summary>
                    <form class="mt-3 space-y-3" @submit.prevent="addCustom">
                        <Input
                            v-model="custom.label"
                            placeholder="Naziv (npr. O nama)"
                        />
                        <Input
                            v-model="custom.url"
                            placeholder="Link (npr. /kontakt/ ili https://…)"
                        />
                        <p class="text-xs text-muted-foreground">
                            Bez linka: stavka služi samo kao naslov podmenija.
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            type="submit"
                            :disabled="!custom.label.trim()"
                            ><Plus /> Dodaj u meni</Button
                        >
                    </form>
                </details>

                <div class="rounded-lg border p-4">
                    <p class="mb-3 text-sm font-semibold">Sve vijesti</p>
                    <Button variant="outline" size="sm" @click="addPostsIndex"
                        ><Plus /> Dodaj „Vijesti" (/vijesti/)</Button
                    >
                </div>
            </aside>

            <!-- Tree -->
            <section class="min-w-0">
                <p v-if="hasErrors" class="mb-3 text-sm text-destructive">
                    Neke stavke imaju greške – otvorene su ispod.
                </p>
                <p v-if="errors.items" class="mb-3 text-sm text-destructive">
                    {{ errors.items }}
                </p>

                <VueDraggable
                    v-model="tree"
                    group="menu"
                    handle=".drag-handle"
                    :force-fallback="true"
                    :fallback-on-body="true"
                    :swap-threshold="0.65"
                    :animation="150"
                    :on-move="canMove"
                    @start="dragging = true"
                    @end="dragging = false"
                    data-depth="1"
                    class="min-h-24 space-y-2 rounded-lg border border-dashed p-3"
                >
                    <div
                        v-for="(item, i) in tree"
                        :key="item.key"
                        :data-has-children="item.children.length > 0"
                    >
                        <MenuItemCard
                            v-model="tree[i]"
                            :errors="errors"
                            :error-prefix="`items.${i}`"
                            @remove="removeRoot(i)"
                        />

                        <VueDraggable
                            v-model="item.children"
                            group="menu"
                            handle=".drag-handle"
                            :force-fallback="true"
                            :fallback-on-body="true"
                            :swap-threshold="0.65"
                            :animation="150"
                            :on-move="canMove"
                            @start="dragging = true"
                            @end="dragging = false"
                            data-depth="2"
                            :class="[
                                'mt-2 ml-8 space-y-2 rounded-md transition-all',
                                dragging
                                    ? 'min-h-10 border border-dashed border-primary/40 p-1'
                                    : 'min-h-2',
                            ]"
                        >
                            <div
                                v-for="(child, j) in item.children"
                                :key="child.key"
                                data-has-children="false"
                            >
                                <MenuItemCard
                                    v-model="item.children[j]"
                                    :errors="errors"
                                    :error-prefix="`items.${i}.children.${j}`"
                                    @remove="removeChild(item, j)"
                                />
                            </div>
                        </VueDraggable>
                    </div>

                    <p
                        v-if="!tree.length"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Meni je prazan. Dodajte stavke sa lijeve strane.
                    </p>
                </VueDraggable>

                <p class="mt-3 text-xs text-muted-foreground">
                    Meni ima najviše {{ maxDepth }} nivoa: glavne stavke i
                    podmeni. Stavke sa upozorenjem (neobjavljene stranice) ne
                    prikazuju se na sajtu.
                </p>
            </section>
        </div>
    </div>
</template>
