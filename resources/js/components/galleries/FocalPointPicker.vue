<script setup lang="ts">
/**
 * Click on the photo to choose the part that must stay visible when the
 * site crops it (CSS object-position). Shows crop previews for common shapes.
 */
const props = defineProps<{
    src: string;
    alt?: string;
}>();

const x = defineModel<number>('x', { required: true });
const y = defineModel<number>('y', { required: true });

function pick(event: MouseEvent): void {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    x.value = Math.round(
        Math.min(
            100,
            Math.max(0, ((event.clientX - rect.left) / rect.width) * 100),
        ),
    );
    y.value = Math.round(
        Math.min(
            100,
            Math.max(0, ((event.clientY - rect.top) / rect.height) * 100),
        ),
    );
}

const previews = [
    { label: 'Široko (slajder)', ratio: '16 / 7' },
    { label: '4:3', ratio: '4 / 3' },
    { label: 'Kvadrat', ratio: '1 / 1' },
    { label: 'Portret', ratio: '3 / 4' },
];
</script>

<template>
    <div class="space-y-3">
        <div
            class="relative cursor-crosshair overflow-hidden rounded-lg border bg-muted"
            @click="pick"
        >
            <img
                :src="props.src"
                :alt="props.alt ?? ''"
                class="block w-full select-none"
                draggable="false"
            />
            <span
                class="pointer-events-none absolute size-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_2px_rgba(0,0,0,0.5)]"
                :style="{ left: `${x}%`, top: `${y}%` }"
            />
        </div>
        <p class="text-xs text-muted-foreground">
            Kliknite na dio fotografije koji mora ostati vidljiv kada sajt
            isijeca sliku.
            <button
                type="button"
                class="underline"
                @click="((x = 50), (y = 50))"
            >
                Centriraj
            </button>
        </p>
        <div class="grid grid-cols-4 gap-2">
            <figure v-for="p in previews" :key="p.label" class="space-y-1">
                <div
                    class="overflow-hidden rounded border bg-muted"
                    :style="{ aspectRatio: p.ratio }"
                >
                    <img
                        :src="props.src"
                        alt=""
                        class="size-full object-cover"
                        :style="{ objectPosition: `${x}% ${y}%` }"
                    />
                </div>
                <figcaption
                    class="text-center text-[10px] text-muted-foreground"
                >
                    {{ p.label }}
                </figcaption>
            </figure>
        </div>
    </div>
</template>
