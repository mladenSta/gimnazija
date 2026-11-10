<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Tags as chips. Enter or comma adds a tag; suggestions come from existing tags.
 */
const props = defineProps<{
    suggestions: string[];
    id?: string;
}>();

const tags = defineModel<string[]>({ default: () => [] });
const text = ref('');
const listId = computed(() => `${props.id ?? 'tags'}-list`);

function add(): void {
    const value = text.value.replace(/,$/, '').trim();

    if (
        value &&
        !tags.value.some((t) => t.toLowerCase() === value.toLowerCase())
    ) {
        tags.value = [...tags.value, value];
    }

    text.value = '';
}

function remove(tag: string): void {
    tags.value = tags.value.filter((t) => t !== tag);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        add();
    } else if (
        event.key === 'Backspace' &&
        text.value === '' &&
        tags.value.length
    ) {
        tags.value = tags.value.slice(0, -1);
    }
}
</script>

<template>
    <div
        class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border px-2 py-1.5"
    >
        <span
            v-for="tag in tags"
            :key="tag"
            class="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs"
        >
            {{ tag }}
            <button
                type="button"
                :aria-label="`Ukloni ${tag}`"
                @click="remove(tag)"
            >
                <X class="size-3" />
            </button>
        </span>
        <input
            :id="id"
            v-model="text"
            :list="listId"
            class="min-w-24 flex-1 bg-transparent text-sm focus:outline-none"
            placeholder="Dodaj tag…"
            @keydown="onKeydown"
            @blur="add"
            @change="add"
        />
        <datalist :id="listId">
            <option v-for="s in suggestions" :key="s" :value="s" />
        </datalist>
    </div>
</template>
