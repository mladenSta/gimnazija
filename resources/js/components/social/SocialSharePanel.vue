<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, ExternalLink, Share2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { share } from '@/routes/admin/posts';

export type SocialNetworkPanel = {
    key: 'facebook' | 'instagram' | 'linkedin';
    label: string;
    button: string;
    disabled_reason: string | null;
    last: {
        success: boolean;
        message: string;
        url: string | null;
        at: string | null;
        by: string | null;
    } | null;
    shared_at: string | null;
    shared_url: string | null;
};

const props = defineProps<{
    postId: number;
    networks: SocialNetworkPanel[];
    /** Unsaved changes in the editor: the networks get the saved version. */
    dirty: boolean;
}>();

const busy = ref<string | null>(null);

function publish(n: SocialNetworkPanel): void {
    if (
        n.shared_at &&
        !window.confirm(
            `Ova objava je već objavljena na mreži ${n.label} (${n.shared_at}). Objaviti ponovo?`,
        )
    ) {
        return;
    }

    busy.value = n.key;
    router.post(
        share.url({ post: props.postId, network: n.key }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['social'],
            onFinish: () => (busy.value = null),
        },
    );
}
</script>

<template>
    <section class="space-y-3 rounded-lg border p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
            <Share2 class="size-4" /> Društvene mreže
        </h2>
        <p
            v-if="dirty"
            class="rounded-md bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            Imate nesačuvane izmjene. Na mreže ide posljednja sačuvana verzija —
            prvo kliknite „Ažuriraj”.
        </p>

        <div v-for="n in networks" :key="n.key" class="space-y-1.5">
            <Button
                type="button"
                variant="outline"
                class="w-full justify-center"
                :disabled="!!n.disabled_reason || busy !== null"
                @click="publish(n)"
            >
                <Spinner v-if="busy === n.key" />
                {{ n.shared_at ? `${n.button} ponovo` : n.button }}
            </Button>
            <p v-if="n.disabled_reason" class="text-xs text-muted-foreground">
                {{ n.disabled_reason }}
            </p>
            <p
                v-if="n.last"
                class="flex gap-1.5 text-xs"
                :class="
                    n.last.success
                        ? 'text-emerald-700 dark:text-emerald-400'
                        : 'text-destructive'
                "
                role="status"
            >
                <CircleCheck
                    v-if="n.last.success"
                    class="mt-px size-3.5 shrink-0"
                />
                <CircleAlert v-else class="mt-px size-3.5 shrink-0" />
                <span class="min-w-0 break-words">
                    {{ n.last.message }}
                    <span class="text-muted-foreground"
                        >({{ n.last.at
                        }}<template v-if="n.last.by">
                            · {{ n.last.by }}</template
                        >)</span
                    >
                    <a
                        v-if="n.last.success && n.last.url"
                        :href="n.last.url"
                        target="_blank"
                        rel="noopener"
                        class="ml-1 inline-flex items-center gap-0.5 font-medium underline underline-offset-2"
                        >Otvori <ExternalLink class="size-3"
                    /></a>
                </span>
            </p>
            <p
                v-if="n.last && !n.last.success && n.shared_at"
                class="text-xs text-muted-foreground"
            >
                Ranije uspješno objavljeno: {{ n.shared_at }}
                <a
                    v-if="n.shared_url"
                    :href="n.shared_url"
                    target="_blank"
                    rel="noopener"
                    class="underline underline-offset-2"
                    >otvori</a
                >
            </p>
        </div>
    </section>
</template>
