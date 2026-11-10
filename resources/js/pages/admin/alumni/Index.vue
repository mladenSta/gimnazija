<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    EyeOff,
    GripVertical,
    Pencil,
    Plus,
    Trash2,
    UserRound,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ImageField from '@/components/media/ImageField.vue';
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
import { destroy, index, reorder, store, update } from '@/routes/admin/alumni';
import type { MediaItem, UploadSettings } from '@/types/media';

type Testimonial = {
    id: number;
    name: string;
    generation: number | null;
    profession: string | null;
    quote: string;
    photo: MediaItem | null;
    is_published: boolean;
};

const props = defineProps<{
    testimonials: Testimonial[];
    upload: UploadSettings;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Bivši učenici', href: index() }] },
});

const list = ref<Testimonial[]>([...props.testimonials]);
watch(
    () => props.testimonials,
    (value) => (list.value = [...value]),
);

function saveOrder(): void {
    router.put(
        reorder.url(),
        { ids: list.value.map((t) => t.id) },
        { preserveScroll: true, preserveState: true },
    );
}

const open = ref(false);
const editing = ref<Testimonial | null>(null);
const photo = ref<MediaItem | null>(null);
const form = useForm({
    name: '',
    generation: '' as number | '',
    profession: '',
    quote: '',
    photo_media_id: null as number | null,
    is_published: true,
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    photo.value = null;
    open.value = true;
}

function openEdit(t: Testimonial): void {
    editing.value = t;
    form.clearErrors();
    form.name = t.name;
    form.generation = t.generation ?? '';
    form.profession = t.profession ?? '';
    form.quote = t.quote;
    form.is_published = t.is_published;
    photo.value = t.photo;
    open.value = true;
}

function submit(): void {
    form.photo_media_id = photo.value?.id ?? null;
    form.transform((d) => ({
        ...d,
        generation: d.generation === '' ? null : d.generation,
        profession: d.profession || null,
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

function remove(t: Testimonial): void {
    if (window.confirm(`Obrisati poruku – ${t.name}?`)) {
        router.delete(destroy.url(t.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Bivši učenici" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Poruke bivših učenika"
                description="Prikazuju se na početnoj strani redoslijedom ispod. Prevucite za promjenu redoslijeda."
            />
            <Button @click="openCreate"><Plus /> Nova poruka</Button>
        </div>

        <VueDraggable
            v-model="list"
            :animation="150"
            handle=".drag-handle"
            :force-fallback="true"
            class="space-y-3"
            @end="saveOrder"
        >
            <div
                v-for="t in list"
                :key="t.id"
                class="flex items-start gap-3 rounded-lg border p-4"
                :class="t.is_published ? '' : 'opacity-60'"
            >
                <span
                    class="drag-handle mt-1 grid size-7 cursor-grab place-items-center rounded text-muted-foreground hover:bg-accent"
                    ><GripVertical class="size-4"
                /></span>
                <img
                    v-if="t.photo"
                    :src="t.photo.thumb_url"
                    alt=""
                    class="size-12 shrink-0 rounded-full object-cover"
                />
                <span
                    v-else
                    class="grid size-12 shrink-0 place-items-center rounded-full bg-muted text-muted-foreground"
                    ><UserRound class="size-5"
                /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ t.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="t.generation"
                            >Generacija {{ t.generation }}.</template
                        >
                        <template v-if="t.generation && t.profession">
                            · </template
                        >{{ t.profession }}
                        <span
                            v-if="!t.is_published"
                            class="ml-2 inline-flex items-center gap-1"
                            ><EyeOff class="size-3" /> Skriveno</span
                        >
                    </p>
                    <p
                        class="mt-2 line-clamp-2 text-sm text-muted-foreground italic"
                    >
                        „{{ t.quote }}"
                    </p>
                </div>
                <div class="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Uredi"
                        @click="openEdit(t)"
                        ><Pencil
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Obriši"
                        class="text-destructive"
                        @click="remove(t)"
                        ><Trash2
                    /></Button>
                </div>
            </div>
        </VueDraggable>

        <p
            v-if="!list.length"
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            Još nema poruka.
        </p>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Uredi poruku' : 'Nova poruka'
                }}</DialogTitle>
                <DialogDescription
                    >Poruka bivšeg učenika za početnu stranu.</DialogDescription
                >
            </DialogHeader>
            <form id="alumni-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="a-name">Ime i prezime</Label>
                    <Input id="a-name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="a-gen">Generacija</Label>
                        <Input
                            id="a-gen"
                            v-model="form.generation"
                            type="number"
                            min="1900"
                            placeholder="2015"
                        />
                        <InputError :message="form.errors.generation" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="a-prof">Zanimanje</Label>
                        <Input
                            id="a-prof"
                            v-model="form.profession"
                            placeholder="ljekarka"
                        />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="a-quote">Poruka</Label>
                    <textarea
                        id="a-quote"
                        v-model="form.quote"
                        rows="4"
                        class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.quote" />
                </div>
                <div class="grid gap-2">
                    <Label>Fotografija</Label>
                    <ImageField
                        v-model="photo"
                        :upload="upload"
                        recommended-key="alumni"
                        title="Fotografija bivšeg učenika"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.is_published"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />
                    Prikaži na sajtu
                </label>
            </form>
            <DialogFooter>
                <Button variant="outline" type="button" @click="open = false"
                    >Odustani</Button
                >
                <Button
                    type="submit"
                    form="alumni-form"
                    :disabled="form.processing"
                    >Sačuvaj</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
