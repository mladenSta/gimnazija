<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { CircleCheck, CircleDashed } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/admin/settings';

type NetworkKey = 'facebook' | 'instagram' | 'linkedin';

type Network = {
    key: NetworkKey;
    label: string;
    id_label: string;
    account_id: string | null;
    has_token: boolean;
    token_hint: string | null;
    configured: boolean;
};

const props = defineProps<{
    networks: Network[];
    siteUrl: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Podešavanja', href: edit() }] },
});

const help: Record<NetworkKey, string[]> = {
    facebook: [
        'ID stranice: Facebook stranica → O stranici → Transparentnost stranice (ili Meta Business Suite → Podešavanja).',
        'Token: dugotrajni „Page access token” iz Meta for Developers aplikacije, s dozvolama pages_manage_posts i pages_read_engagement.',
        'Objavljuje se tekst i link na vijest; Facebook sam prikazuje naslovnu sliku i naslov.',
    ],
    instagram: [
        'Instagram nalog mora biti poslovni (Business) i povezan s Facebook stranicom škole.',
        'ID: „instagram_business_account” te Facebook stranice (Graph API Explorer → me/accounts?fields=instagram_business_account).',
        'Token: može biti isti Page token kao za Facebook, s dozvolama instagram_basic i instagram_content_publish.',
        'Objavljuje se naslovna slika objave s tekstom; objava bez naslovne slike ne može na Instagram.',
    ],
    linkedin: [
        'ID: broj iz adrese admin stranice škole, npr. linkedin.com/company/123456/admin.',
        'Token: iz LinkedIn Developer aplikacije s dozvolom w_organization_social; vlasnik tokena mora biti admin stranice.',
        'LinkedIn tokeni traju 60 dana — nakon toga unesite novi.',
    ],
};

function makeForm(n: Network) {
    return useForm({
        account_id: n.account_id ?? '',
        token: '',
    });
}

const forms = Object.fromEntries(
    props.networks.map((n) => [n.key, makeForm(n)]),
) as Record<NetworkKey, ReturnType<typeof makeForm>>;

function save(n: Network): void {
    forms[n.key]
        .transform((data) => ({ networks: { [n.key]: data } }))
        .put(update.url(), {
            preserveScroll: true,
            onSuccess: () => forms[n.key].reset('token'),
        });
}

function clear(n: Network): void {
    if (
        !window.confirm(
            `Ukloniti podatke za ${n.label}? Dugme „Objavi” za ovu mrežu više se neće prikazivati.`,
        )
    ) {
        return;
    }

    forms[n.key]
        .transform(() => ({ networks: { [n.key]: { clear: true } } }))
        .put(update.url(), {
            preserveScroll: true,
            onSuccess: () => {
                forms[n.key].defaults({ account_id: '', token: '' });
                forms[n.key].reset();
            },
        });
}

function error(n: Network, field: 'account_id' | 'token'): string | undefined {
    const errors = forms[n.key].errors as Record<string, string | undefined>;

    return errors[`networks.${n.key}.${field}`];
}
</script>

<template>
    <Head title="Podešavanja" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <Heading
            title="Podešavanja"
            description="Povezivanje s društvenim mrežama škole. Kada su podaci uneseni, u objavljenim vijestima pojavljuje se dugme „Objavi na …”."
        />

        <div class="grid max-w-3xl gap-5">
            <section
                v-for="n in networks"
                :key="n.key"
                class="space-y-4 rounded-lg border p-5"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-semibold">{{ n.label }}</h2>
                    <span
                        v-if="n.configured"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <CircleCheck class="size-3.5" /> Podešeno
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        <CircleDashed class="size-3.5" />
                        {{
                            n.account_id || n.has_token
                                ? 'Nedostaju podaci'
                                : 'Nije podešeno'
                        }}
                    </span>
                </div>

                <ul
                    class="list-disc space-y-1 pl-5 text-xs text-muted-foreground"
                >
                    <li v-for="(line, i) in help[n.key]" :key="i">
                        {{ line }}
                    </li>
                </ul>

                <form class="grid gap-4" @submit.prevent="save(n)">
                    <div class="grid gap-2">
                        <Label :for="`${n.key}-id`">{{ n.id_label }}</Label>
                        <Input
                            :id="`${n.key}-id`"
                            v-model="forms[n.key].account_id"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="npr. 123456789012345"
                        />
                        <InputError :message="error(n, 'account_id')" />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`${n.key}-token`">Access token</Label>
                        <Input
                            :id="`${n.key}-token`"
                            v-model="forms[n.key].token"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                n.has_token
                                    ? `Sačuvan (${n.token_hint}) — ostavite prazno da ostane isti`
                                    : 'Zalijepite token'
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Token se čuva šifrovan i ne prikazuje se ponovo.
                        </p>
                        <InputError :message="error(n, 'token')" />
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <Button
                            v-if="n.account_id || n.has_token"
                            type="button"
                            variant="link"
                            class="px-0 text-destructive"
                            :disabled="forms[n.key].processing"
                            @click="clear(n)"
                        >
                            Ukloni podatke
                        </Button>
                        <span v-else />
                        <Button
                            type="submit"
                            :disabled="forms[n.key].processing"
                        >
                            <Spinner v-if="forms[n.key].processing" />
                            Sačuvaj
                        </Button>
                    </div>
                </form>
            </section>

            <p class="text-xs text-muted-foreground">
                Mreže preuzimaju sliku i link sa adrese {{ siteUrl }} — sajt
                mora biti javno dostupan na internetu da bi objava uspjela.
            </p>
        </div>
    </div>
</template>
