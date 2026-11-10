<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

/**
 * School mark (same as the public site's header logo).
 * Replace with the official logo when available.
 */
defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 64 64"
        :class="className"
        v-bind="$attrs"
        aria-hidden="true"
    >
        <circle cx="32" cy="32" r="30" fill="#2894ac" />
        <path d="M32 14l18 9-18 9-18-9z" fill="#fff" />
        <path d="M20 29v9c6 5 18 5 24 0v-9l-12 6z" fill="#43036e" />
        <circle cx="48" cy="40" r="2.6" fill="#fff" />
        <path d="M48 24v16" stroke="#fff" stroke-width="2" />
    </svg>
</template>
