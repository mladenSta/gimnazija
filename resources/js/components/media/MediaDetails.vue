<script setup lang="ts">
import { Copy, ExternalLink, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { HttpError, requestJson } from '@/lib/http';
import { formatBytes, formatDimensions } from '@/lib/media';
import { destroy, show, update } from '@/routes/admin/media';
import type { MediaItem } from '@/types/media';

const props = defineProps<{
    media: MediaItem | null;
}>();

const emit = defineEmits<{
    close: [];
    updated: [media: MediaItem];
    deleted: [id: number];
}>();

const open = computed({
    get: () => props.media !== null,
    set: (value) => !value && emit('close'),
});

const details = ref<MediaItem | null>(null);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const confirmingDelete = ref(false);
const form = ref({ alt: '', caption: '' });
const errors = ref<Record<string, string>>({});

const usages = computed(() => details.value?.usages ?? []);
const inUse = computed(() => usages.value.length > 0);

watch(
    () => props.media,
    async (media) => {
        confirmingDelete.value = false;
        errors.value = {};

        if (!media) {
            details.value = null;

            return;
        }

        details.value = media;
        form.value = { alt: media.alt ?? '', caption: media.caption ?? '' };
        loading.value = true;

        try {
            const { data } = await requestJson<{ data: MediaItem }>(
                'get',
                show.url(media.id),
            );
            details.value = data.data;
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

async function save(): Promise<void> {
    if (!details.value) {
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const { data } = await requestJson<{ data: MediaItem }>(
            'patch',
            update.url(details.value.id),
            form.value,
        );
        details.value = { ...data.data, usages: details.value.usages };
        emit('updated', data.data);
        toast.success('Sačuvano.');
    } catch (e) {
        if (e instanceof HttpError && e.data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(e.data.errors).map(([k, v]) => [k, v[0]]),
            );
        } else {
            toast.error('Čuvanje nije uspjelo.');
        }
    } finally {
        saving.value = false;
    }
}

async function remove(): Promise<void> {
    if (!details.value) {
        return;
    }

    deleting.value = true;

    try {
        await requestJson('delete', destroy.url(details.value.id));
        toast.success('Fotografija je obrisana.');
        emit('deleted', details.value.id);
    } catch (e) {
        if (e instanceof HttpError && e.status === 409) {
            details.value = {
                ...details.value,
                usages: (e.data.usages as MediaItem['usages']) ?? [],
            };
        }

        toast.error(
            e instanceof HttpError
                ? e.firstMessage()
                : 'Brisanje nije uspjelo.',
        );
    } finally {
        deleting.value = false;
        confirmingDelete.value = false;
    }
}

async function copyUrl(): Promise<void> {
    if (details.value) {
        await navigator.clipboard.writeText(details.value.url);
        toast.success('Link je kopiran.');
    }
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <template v-if="details">
                <SheetHeader>
                    <SheetTitle class="truncate pr-6">{{
                        details.original_name
                    }}</SheetTitle>
                    <SheetDescription>Detalji fotografije</SheetDescription>
                </SheetHeader>

                <div class="space-y-5 px-4">
                    <a
                        :href="details.url"
                        target="_blank"
                        rel="noopener"
                        class="block overflow-hidden rounded-lg border bg-muted"
                    >
                        <img
                            :src="details.medium_url"
                            :alt="details.alt ?? ''"
                            class="max-h-72 w-full object-contain"
                        />
                    </a>

                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm"
                    >
                        <dt class="text-muted-foreground">Dimenzije</dt>
                        <dd>{{ formatDimensions(details) }} px</dd>
                        <dt class="text-muted-foreground">Veličina</dt>
                        <dd>{{ formatBytes(details.size) }}</dd>
                        <dt class="text-muted-foreground">Otpremljeno</dt>
                        <dd>
                            {{ details.uploaded_at
                            }}<span v-if="details.uploader">
                                · {{ details.uploader }}</span
                            >
                        </dd>
                    </dl>

                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="copyUrl"
                            ><Copy /> Kopiraj link</Button
                        >
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="details.url"
                                target="_blank"
                                rel="noopener"
                                ><ExternalLink /> Otvori</a
                            >
                        </Button>
                    </div>

                    <form class="space-y-4" @submit.prevent="save">
                        <div class="grid gap-2">
                            <Label for="media-alt">Alternativni tekst</Label>
                            <Input
                                id="media-alt"
                                v-model="form.alt"
                                placeholder="Šta se vidi na fotografiji?"
                            />
                            <p class="text-xs text-muted-foreground">
                                Za slabovide posjetioce i pretraživače.
                            </p>
                            <InputError :message="errors.alt" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="media-caption">Opis</Label>
                            <Input id="media-caption" v-model="form.caption" />
                            <InputError :message="errors.caption" />
                        </div>
                        <Button type="submit" :disabled="saving">
                            <Spinner v-if="saving" /> Sačuvaj
                        </Button>
                    </form>

                    <section class="space-y-2">
                        <h3 class="text-sm font-medium">Koristi se u</h3>
                        <Spinner v-if="loading" />
                        <p
                            v-else-if="!inUse"
                            class="text-sm text-muted-foreground"
                        >
                            Fotografija se trenutno ne koristi.
                        </p>
                        <ul v-else class="space-y-1 text-sm">
                            <li v-for="(usage, i) in usages" :key="i">
                                <span class="text-muted-foreground"
                                    >{{ usage.type }}:</span
                                >
                                {{ usage.title }}
                                <span
                                    v-if="usage.trashed"
                                    class="text-xs text-muted-foreground"
                                    >(u otpadu)</span
                                >
                            </li>
                        </ul>
                    </section>
                </div>

                <SheetFooter>
                    <p v-if="inUse" class="text-xs text-muted-foreground">
                        Fotografija koja se koristi ne može se obrisati. Prvo je
                        uklonite sa mjesta navedenih iznad.
                    </p>
                    <template v-else-if="confirmingDelete">
                        <p class="text-sm">
                            Obrisati fotografiju? Ovo se ne može poništiti.
                        </p>
                        <div class="flex gap-2">
                            <Button
                                variant="destructive"
                                type="button"
                                :disabled="deleting"
                                @click="remove"
                            >
                                <Spinner v-if="deleting" /> Da, obriši
                            </Button>
                            <Button
                                variant="outline"
                                type="button"
                                @click="confirmingDelete = false"
                                >Odustani</Button
                            >
                        </div>
                    </template>
                    <Button
                        v-else
                        variant="destructive"
                        type="button"
                        :disabled="loading"
                        @click="confirmingDelete = true"
                    >
                        <Trash2 /> Obriši fotografiju
                    </Button>
                </SheetFooter>
            </template>
        </SheetContent>
    </Sheet>
</template>
