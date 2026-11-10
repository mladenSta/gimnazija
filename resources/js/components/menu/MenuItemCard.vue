<script setup lang="ts">
import { ChevronDown, GripVertical, TriangleAlert, X } from '@lucide/vue';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MenuNode } from '@/types/menu';

const props = defineProps<{
    errors?: Record<string, string>;
    errorPrefix: string;
}>();

const item = defineModel<MenuNode>({ required: true });

const emit = defineEmits<{
    remove: [];
}>();

const typeLabels: Record<MenuNode['type'], string> = {
    page: 'Stranica',
    category: 'Kategorija',
    custom: 'Prilagođeni link',
    'posts-index': 'Sve vijesti',
};

const title = computed(
    () =>
        item.value.label ||
        item.value.original_label ||
        (item.value.type === 'posts-index' ? 'Vijesti' : '(bez naziva)'),
);
const hasError = computed(() =>
    Object.keys(props.errors ?? {}).some(
        (k) =>
            k.startsWith(`${props.errorPrefix}.`) &&
            !k.startsWith(`${props.errorPrefix}.children`),
    ),
);
const error = (field: string) =>
    props.errors?.[`${props.errorPrefix}.${field}`];
</script>

<template>
    <div
        class="rounded-lg border bg-background shadow-xs"
        :class="hasError ? 'border-destructive' : ''"
    >
        <div class="flex items-center gap-2 px-2 py-2">
            <span
                class="drag-handle grid size-7 cursor-grab place-items-center rounded text-muted-foreground hover:bg-accent"
                title="Prevucite"
            >
                <GripVertical class="size-4" />
            </span>
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-2 text-left"
                @click="item.open = !item.open"
            >
                <span class="truncate text-sm font-medium">{{ title }}</span>
                <TriangleAlert
                    v-if="item.warning"
                    class="size-4 shrink-0 text-amber-600"
                    :title="item.warning"
                />
                <span class="ml-auto shrink-0 text-xs text-muted-foreground">{{
                    typeLabels[item.type]
                }}</span>
                <ChevronDown
                    class="size-4 shrink-0 text-muted-foreground transition-transform"
                    :class="item.open ? 'rotate-180' : ''"
                />
            </button>
        </div>

        <div v-if="item.open || hasError" class="space-y-3 border-t px-3 py-3">
            <p
                v-if="item.warning"
                class="text-xs text-amber-700 dark:text-amber-400"
            >
                {{ item.warning }}
            </p>

            <div class="grid gap-1.5">
                <Label :for="`label-${item.key}`">Naziv u meniju</Label>
                <Input
                    :id="`label-${item.key}`"
                    :model-value="item.label ?? ''"
                    @update:model-value="item.label = String($event) || null"
                    :placeholder="
                        item.original_label ??
                        (item.type === 'posts-index' ? 'Vijesti' : '')
                    "
                />
                <p v-if="error('label')" class="text-xs text-destructive">
                    {{ error('label') }}
                </p>
            </div>

            <div v-if="item.type === 'custom'" class="grid gap-1.5">
                <Label :for="`url-${item.key}`">Link</Label>
                <Input
                    :id="`url-${item.key}`"
                    :model-value="item.url ?? ''"
                    @update:model-value="item.url = String($event) || null"
                    placeholder="/kontakt/ ili https://… (prazno = samo naslov podmenija)"
                />
                <p v-if="error('url')" class="text-xs text-destructive">
                    {{ error('url') }}
                </p>
            </div>
            <p v-else-if="item.href" class="text-xs text-muted-foreground">
                Link: {{ item.href }}
            </p>

            <p v-if="error('page_id')" class="text-xs text-destructive">
                {{ error('page_id') }}
            </p>
            <p v-if="error('category_id')" class="text-xs text-destructive">
                {{ error('category_id') }}
            </p>

            <button
                type="button"
                class="inline-flex items-center gap-1 text-xs text-destructive hover:underline"
                @click="emit('remove')"
            >
                <X class="size-3.5" /> Ukloni iz menija
            </button>
        </div>
    </div>
</template>
