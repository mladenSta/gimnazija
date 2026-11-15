<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowUpRight, Minus } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes';

type Period = {
    label: string;
    visitors: number;
    page_views: number;
    change: number | null;
    compare_label: string;
};

type Day = {
    date: string;
    label: string;
    weekday: string;
    visitors: number;
    page_views: number;
};

type Month = { label: string; visitors: number; page_views: number };

const props = defineProps<{
    visits: {
        since: string | null;
        periods: Period[];
        daily: Day[];
        months: Month[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Kontrolna tabla', href: dashboard() }],
    },
});

const nf = new Intl.NumberFormat('sr-Latn-BA');
const fmt = (n: number): string => nf.format(n);

// Y scale: round the top up to a "nice" number so gridlines land on whole values.
const scaleMax = computed(() => {
    const max = Math.max(...props.visits.daily.map((d) => d.visitors), 0);

    if (max <= 4) {
        return 4;
    }

    const step = 10 ** Math.floor(Math.log10(max));
    const nice = [1, 2, 2.5, 5, 10].find((m) => m * step >= max) ?? 10;

    return nice * step;
});

const gridlines = computed(() =>
    [1, 0.75, 0.5, 0.25].map((f) => Math.round(scaleMax.value * f)),
);

const hovered = ref<number | null>(null);
const hoveredDay = computed(() =>
    hovered.value === null ? null : props.visits.daily[hovered.value],
);

const monthsMax = computed(() =>
    Math.max(...props.visits.months.map((m) => m.visitors), 1),
);
</script>

<template>
    <Head title="Kontrolna tabla" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Posjete sajtu"
            :description="
                visits.since
                    ? `Brojimo od ${visits.since} Posjetilac = jedna osoba (uređaj) u jednom danu; prijavljeni korisnici i botovi se ne broje.`
                    : 'Brojanje počinje s prvom posjetom javnom sajtu. Prijavljeni korisnici i botovi se ne broje.'
            "
        />

        <!-- Stat tiles -->
        <div class="grid gap-4 md:grid-cols-3">
            <section
                v-for="p in visits.periods"
                :key="p.label"
                class="rounded-xl border p-5"
            >
                <h2 class="text-sm text-muted-foreground">{{ p.label }}</h2>
                <p class="mt-2 text-3xl font-semibold tabular-nums">
                    {{ fmt(p.visitors) }}
                    <span class="text-sm font-normal text-muted-foreground"
                        >posjetilaca</span
                    >
                </p>
                <p class="mt-1 text-sm text-muted-foreground tabular-nums">
                    {{ fmt(p.page_views) }} pregleda stranica
                </p>
                <p
                    v-if="p.change !== null"
                    class="mt-3 flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <ArrowUpRight
                        v-if="p.change > 0"
                        class="size-3.5 text-emerald-600 dark:text-emerald-400"
                    />
                    <ArrowDownRight
                        v-else-if="p.change < 0"
                        class="size-3.5 text-rose-600 dark:text-rose-400"
                    />
                    <Minus v-else class="size-3.5" />
                    <span class="font-medium text-foreground tabular-nums"
                        >{{ p.change > 0 ? '+' : '' }}{{ p.change }}%</span
                    >
                    {{ p.compare_label }}
                </p>
            </section>
        </div>

        <!-- Daily visitors, last 30 days -->
        <section class="rounded-xl border p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold">
                    Posjetioci po danima — posljednjih 30 dana
                </h2>
                <p
                    class="min-h-5 text-sm text-muted-foreground tabular-nums"
                    aria-live="polite"
                >
                    <template v-if="hoveredDay">
                        {{ hoveredDay.weekday }} {{ hoveredDay.date }}:
                        <span class="font-medium text-foreground">{{
                            fmt(hoveredDay.visitors)
                        }}</span>
                        posjetilaca ·
                        {{ fmt(hoveredDay.page_views) }} pregleda
                    </template>
                </p>
            </div>

            <div class="mt-4 flex gap-2">
                <!-- Y axis labels -->
                <div
                    class="relative h-48 w-8 shrink-0 text-right text-[11px] text-muted-foreground tabular-nums"
                    aria-hidden="true"
                >
                    <span
                        v-for="(g, i) in gridlines"
                        :key="g"
                        class="absolute right-0 -translate-y-1/2"
                        :style="{ top: `${i * 25}%` }"
                        >{{ fmt(g) }}</span
                    >
                    <span class="absolute right-0 bottom-0 translate-y-1/2"
                        >0</span
                    >
                </div>

                <div class="min-w-0 flex-1">
                    <div class="relative h-48">
                        <!-- Gridlines -->
                        <div
                            v-for="(g, i) in gridlines"
                            :key="g"
                            class="absolute inset-x-0 border-t border-border/60"
                            :style="{ top: `${i * 25}%` }"
                            aria-hidden="true"
                        />
                        <div
                            class="absolute inset-x-0 bottom-0 border-t border-border"
                            aria-hidden="true"
                        />

                        <!-- Bars (each column is a full-height hover target) -->
                        <div
                            class="absolute inset-0 flex items-end gap-[2px]"
                            role="img"
                            :aria-label="`Posjetioci po danima, od ${visits.daily[0]?.date} do ${visits.daily.at(-1)?.date}`"
                            @mouseleave="hovered = null"
                        >
                            <div
                                v-for="(d, i) in visits.daily"
                                :key="d.date"
                                class="flex h-full min-w-0 flex-1 items-end"
                                @mouseenter="hovered = i"
                            >
                                <div
                                    class="w-full rounded-t-[4px] transition-colors"
                                    :class="
                                        hovered === i
                                            ? 'bg-[#00797d] dark:bg-[#3cc7cb]'
                                            : 'bg-[#00a3a8]'
                                    "
                                    :style="{
                                        height: `${(d.visitors / scaleMax) * 100}%`,
                                        minHeight: d.visitors ? '2px' : '0',
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- X labels: every 5th day + today -->
                    <div
                        class="mt-2 flex gap-[2px] text-[11px] text-muted-foreground"
                        aria-hidden="true"
                    >
                        <span
                            v-for="(d, i) in visits.daily"
                            :key="d.date"
                            class="min-w-0 flex-1 overflow-visible text-center whitespace-nowrap"
                            >{{
                                (visits.daily.length - 1 - i) % 5 === 0
                                    ? d.label
                                    : ''
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer text-muted-foreground">
                    Prikaži kao tabelu
                </summary>
                <div class="mt-2 max-h-72 overflow-y-auto">
                    <table class="w-full text-left tabular-nums">
                        <thead class="text-xs text-muted-foreground">
                            <tr>
                                <th class="py-1 font-medium">Dan</th>
                                <th class="py-1 text-right font-medium">
                                    Posjetioci
                                </th>
                                <th class="py-1 text-right font-medium">
                                    Pregledi stranica
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="d in [...visits.daily].reverse()"
                                :key="d.date"
                                class="border-t"
                            >
                                <td class="py-1">
                                    {{ d.weekday }} {{ d.date }}
                                </td>
                                <td class="py-1 text-right">
                                    {{ fmt(d.visitors) }}
                                </td>
                                <td class="py-1 text-right">
                                    {{ fmt(d.page_views) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <!-- Months of this year -->
        <section class="rounded-xl border p-5">
            <h2 class="text-sm font-semibold">Po mjesecima ove godine</h2>
            <table class="mt-3 w-full text-sm tabular-nums">
                <thead class="text-xs text-muted-foreground">
                    <tr>
                        <th class="py-1 text-left font-medium">Mjesec</th>
                        <th class="w-1/2 py-1" />
                        <th class="py-1 text-right font-medium">Posjetioci</th>
                        <th class="py-1 text-right font-medium">Pregledi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="m in [...visits.months].reverse()"
                        :key="m.label"
                        class="border-t"
                    >
                        <td class="py-1.5 whitespace-nowrap">{{ m.label }}</td>
                        <td class="px-3 py-1.5" aria-hidden="true">
                            <div
                                class="h-2 rounded-r-[4px] bg-[#00a3a8]"
                                :style="{
                                    width: `${(m.visitors / monthsMax) * 100}%`,
                                }"
                            />
                        </td>
                        <td class="py-1.5 text-right">
                            {{ fmt(m.visitors) }}
                        </td>
                        <td class="py-1.5 pl-3 text-right">
                            {{ fmt(m.page_views) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
