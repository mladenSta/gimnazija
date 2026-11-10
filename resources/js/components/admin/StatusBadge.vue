<script setup lang="ts">
const props = defineProps<{
    status: 'published' | 'scheduled' | 'draft' | 'trash';
}>();

const styles: Record<typeof props.status, { label: string; class: string }> = {
    published: {
        label: 'Objavljeno',
        class: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    },
    scheduled: {
        label: 'Zakazano',
        class: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    },
    draft: {
        label: 'Nacrt',
        class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    },
    trash: { label: 'U otpadu', class: 'bg-muted text-muted-foreground' },
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="styles[status].class"
    >
        {{ styles[status].label }}
    </span>
</template>
