<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import { slugify } from '@/lib/slug';
import { destroy, index, store, update } from '@/routes/admin/categories';

type CategoryRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    parent_name: string | null;
    posts_count: number;
};

const props = defineProps<{
    categories: CategoryRow[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Kategorije', href: index() }] },
});

const open = ref(false);
const editing = ref<CategoryRow | null>(null);
const slugTouched = ref(false);

const form = useForm({
    name: '',
    slug: '',
    description: '',
    parent_id: '' as number | '',
});

const parentOptions = computed(() =>
    props.categories.filter((c) => c.id !== editing.value?.id),
);

watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = slugify(name);
        }
    },
);

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    slugTouched.value = false;
    open.value = true;
}

function openEdit(category: CategoryRow): void {
    editing.value = category;
    form.clearErrors();
    form.name = category.name;
    form.slug = category.slug;
    form.description = category.description ?? '';
    form.parent_id = category.parent_id ?? '';
    slugTouched.value = true;
    open.value = true;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        parent_id: data.parent_id === '' ? null : data.parent_id,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.put(update.url(editing.value.id), options);
    } else {
        form.post(store.url(), options);
    }
}

function remove(category: CategoryRow): void {
    const note = category.posts_count
        ? ` Objave (${category.posts_count}) ostaju, samo gube ovu kategoriju.`
        : '';

    if (window.confirm(`Obrisati kategoriju „${category.name}"?${note}`)) {
        router.delete(destroy.url(category.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Kategorije" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Kategorije"
                description="Kategorije vijesti. Link kategorije: /category/link/"
            />
            <Button @click="openCreate"><Plus /> Nova kategorija</Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="p-3">Naziv</th>
                        <th class="p-3">Link</th>
                        <th class="hidden p-3 md:table-cell">Nadređena</th>
                        <th class="p-3 text-right">Objava</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in categories"
                        :key="c.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="p-3 font-medium">{{ c.name }}</td>
                        <td class="p-3 font-mono text-xs text-muted-foreground">
                            /category/{{ c.slug }}/
                        </td>
                        <td
                            class="hidden p-3 text-muted-foreground md:table-cell"
                        >
                            {{ c.parent_name ?? '—' }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ c.posts_count }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Uredi"
                                    @click="openEdit(c)"
                                    ><Pencil
                                /></Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Obriši"
                                    class="text-destructive"
                                    @click="remove(c)"
                                    ><Trash2
                                /></Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!categories.length">
                        <td
                            colspan="5"
                            class="p-10 text-center text-muted-foreground"
                        >
                            Još nema kategorija.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Uredi kategoriju' : 'Nova kategorija'
                }}</DialogTitle>
                <DialogDescription
                    >Link se pravi automatski iz naziva.</DialogDescription
                >
            </DialogHeader>

            <form id="category-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="cat-name">Naziv</Label>
                    <Input id="cat-name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-slug">Link</Label>
                    <Input
                        id="cat-slug"
                        v-model="form.slug"
                        class="font-mono"
                        @input="slugTouched = true"
                    />
                    <InputError :message="form.errors.slug" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-parent">Nadređena kategorija</Label>
                    <select
                        id="cat-parent"
                        v-model="form.parent_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">— Nema —</option>
                        <option
                            v-for="c in parentOptions"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-desc">Opis</Label>
                    <textarea
                        id="cat-desc"
                        v-model="form.description"
                        rows="3"
                        class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" type="button" @click="open = false"
                    >Odustani</Button
                >
                <Button
                    type="submit"
                    form="category-form"
                    :disabled="form.processing"
                    >Sačuvaj</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
