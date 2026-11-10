<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { EyeOff, MapPin, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Pagination from '@/components/admin/Pagination.vue';
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
import { destroy, index, store, update } from '@/routes/admin/events';
import type { Paginated } from '@/types/media';

type EventRow = {
    id: number;
    title: string;
    short_description: string | null;
    date: string;
    date_display: string;
    time: string | null;
    location: string | null;
    label: string | null;
    is_published: boolean;
};

defineProps<{
    events: Paginated<EventRow>;
    tab: 'upcoming' | 'past';
    counts: { upcoming: number; past: number };
    labels: string[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Najave', href: index() }] },
});

const months = [
    'jan',
    'feb',
    'mar',
    'apr',
    'maj',
    'jun',
    'jul',
    'avg',
    'sep',
    'okt',
    'nov',
    'dec',
];
const day = (iso: string) => iso.slice(8, 10);
const month = (iso: string) => months[Number(iso.slice(5, 7)) - 1];

const open = ref(false);
const editing = ref<EventRow | null>(null);
const form = useForm({
    title: '',
    short_description: '',
    date: '',
    time: '',
    location: '',
    label: '',
    is_published: true,
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(event: EventRow): void {
    editing.value = event;
    form.clearErrors();
    form.title = event.title;
    form.short_description = event.short_description ?? '';
    form.date = event.date;
    form.time = event.time ?? '';
    form.location = event.location ?? '';
    form.label = event.label ?? '';
    form.is_published = event.is_published;
    open.value = true;
}

function submit(): void {
    form.transform((d) => ({
        ...d,
        time: d.time || null,
        short_description: d.short_description || null,
        location: d.location || null,
        label: d.label || null,
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

function remove(event: EventRow): void {
    if (window.confirm(`Obrisati najavu „${event.title}"?`)) {
        router.delete(destroy.url(event.id), { preserveScroll: true });
    }
}

function switchTab(tab: 'upcoming' | 'past'): void {
    router.get(index.url(), tab === 'past' ? { tab } : {}, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Najave" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Najave"
                description="Događaji na početnoj strani. Prošli događaji se automatski skrivaju."
            />
            <Button @click="openCreate"><Plus /> Nova najava</Button>
        </div>

        <nav class="flex gap-1 border-b">
            <button
                v-for="t in [
                    { key: 'upcoming', label: 'Predstojeće' },
                    { key: 'past', label: 'Prošle' },
                ] as const"
                :key="t.key"
                type="button"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="
                    tab === t.key
                        ? 'border-primary font-medium'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="switchTab(t.key)"
            >
                {{ t.label }}
                <span class="text-xs text-muted-foreground"
                    >({{ counts[t.key] }})</span
                >
            </button>
        </nav>

        <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="e in events.data"
                :key="e.id"
                class="flex gap-4 rounded-lg border p-4"
                :class="e.is_published ? '' : 'opacity-60'"
            >
                <div
                    class="w-14 shrink-0 overflow-hidden rounded-lg border text-center"
                >
                    <div
                        class="bg-primary py-0.5 text-[11px] font-semibold text-primary-foreground uppercase"
                    >
                        {{ month(e.date) }}
                    </div>
                    <div class="py-1 text-2xl font-bold">{{ day(e.date) }}</div>
                </div>
                <div class="min-w-0 flex-1">
                    <p
                        v-if="e.label"
                        class="text-xs font-semibold text-primary uppercase"
                    >
                        {{ e.label }}
                    </p>
                    <p class="font-medium">{{ e.title }}</p>
                    <p
                        v-if="e.short_description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ e.short_description }}
                    </p>
                    <p
                        class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground"
                    >
                        <span
                            >{{ e.date_display
                            }}<template v-if="e.time">
                                u {{ e.time }}</template
                            ></span
                        >
                        <span
                            v-if="e.location"
                            class="inline-flex items-center gap-1"
                            ><MapPin class="size-3" /> {{ e.location }}</span
                        >
                        <span
                            v-if="!e.is_published"
                            class="inline-flex items-center gap-1"
                            ><EyeOff class="size-3" /> Skriveno</span
                        >
                    </p>
                </div>
                <div class="flex flex-col gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Uredi"
                        @click="openEdit(e)"
                        ><Pencil
                    /></Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        title="Obriši"
                        class="text-destructive"
                        @click="remove(e)"
                        ><Trash2
                    /></Button>
                </div>
            </li>
        </ul>

        <p
            v-if="!events.data.length"
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            {{
                tab === 'upcoming'
                    ? 'Nema predstojećih najava.'
                    : 'Nema prošlih najava.'
            }}
        </p>

        <Pagination :meta="events.meta" />
    </div>

    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Uredi najavu' : 'Nova najava'
                }}</DialogTitle>
                <DialogDescription
                    >Prikazuje se na početnoj strani do datuma
                    događaja.</DialogDescription
                >
            </DialogHeader>
            <form id="event-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="e-title">Naslov</Label>
                    <Input id="e-title" v-model="form.title" />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="e-date">Datum</Label>
                        <Input id="e-date" v-model="form.date" type="date" />
                        <InputError :message="form.errors.date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="e-time">Vrijeme (opciono)</Label>
                        <Input id="e-time" v-model="form.time" type="time" />
                        <InputError :message="form.errors.time" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="e-desc">Kratak opis</Label>
                    <textarea
                        id="e-desc"
                        v-model="form.short_description"
                        rows="2"
                        class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.short_description" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="e-location">Mjesto (opciono)</Label>
                        <Input
                            id="e-location"
                            v-model="form.location"
                            placeholder="Svečana sala"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="e-label">Oznaka (opciono)</Label>
                        <Input
                            id="e-label"
                            v-model="form.label"
                            list="event-labels"
                            placeholder="Takmičenje"
                        />
                        <datalist id="event-labels">
                            <option v-for="l in labels" :key="l" :value="l" />
                        </datalist>
                    </div>
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
                    form="event-form"
                    :disabled="form.processing"
                    >Sačuvaj</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
