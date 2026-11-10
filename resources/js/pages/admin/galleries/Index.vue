<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImageOff, Lock, Plus } from '@lucide/vue';
import { ref } from 'vue';
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
import { edit, index, store } from '@/routes/admin/galleries';

type GalleryRow = {
    id: number;
    name: string;
    slug: string;
    type: 'slider' | 'standard';
    type_label: string;
    is_system: boolean;
    items_count: number;
    cover_url: string | null;
};

defineProps<{
    galleries: GalleryRow[];
    types: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Galerije', href: index() }] },
});

const open = ref(false);
const form = useForm({ name: '', type: 'standard' });

function submit(): void {
    form.post(store.url(), { onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Head title="Galerije" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Galerije"
                description="Fotografije za početnu stranu i druge galerije. Galerije označene katancem koristi početna strana."
            />
            <Button @click="(form.reset(), (open = true))"
                ><Plus /> Nova galerija</Button
            >
        </div>

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <li v-for="g in galleries" :key="g.id">
                <Link
                    :href="edit(g.id)"
                    class="group block overflow-hidden rounded-xl border hover:border-primary"
                >
                    <div class="aspect-video bg-muted">
                        <img
                            v-if="g.cover_url"
                            :src="g.cover_url"
                            alt=""
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="grid size-full place-items-center text-muted-foreground"
                        >
                            <ImageOff class="size-6" />
                        </div>
                    </div>
                    <div class="space-y-1 p-3">
                        <p class="flex items-center gap-1.5 font-medium">
                            <Lock
                                v-if="g.is_system"
                                class="size-3.5 text-muted-foreground"
                            />
                            {{ g.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ g.type_label }} · {{ g.items_count }} fotografija
                        </p>
                    </div>
                </Link>
            </li>
        </ul>
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Nova galerija</DialogTitle>
                <DialogDescription
                    >Fotografije dodajete u sljedećem koraku.</DialogDescription
                >
            </DialogHeader>
            <form id="gallery-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="g-name">Naziv</Label>
                    <Input id="g-name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="g-type">Vrsta</Label>
                    <select
                        id="g-type"
                        v-model="form.type"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="t in types"
                            :key="t.value"
                            :value="t.value"
                        >
                            {{ t.label }}
                        </option>
                    </select>
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" type="button" @click="open = false"
                    >Odustani</Button
                >
                <Button
                    type="submit"
                    form="gallery-form"
                    :disabled="form.processing"
                    >Napravi</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
