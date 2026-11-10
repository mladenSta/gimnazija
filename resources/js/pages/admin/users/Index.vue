<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
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
import { destroy, index, store, update } from '@/routes/admin/users';
import type { Role } from '@/types/auth';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    posts_count: number;
    pages_count: number;
    created_at: string | null;
    is_me: boolean;
    two_factor: boolean;
};

const props = defineProps<{
    users: UserRow[];
    roles: { value: Role; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Korisnici', href: index() }] },
});

const roleHelp: Record<Role, string> = {
    admin: 'Sve, uključujući korisnike, kategorije i bivše učenike.',
    editor: 'Sve objave (i tuđe), stranice, meni, galerije, najave, mediji.',
    author: 'Samo svoje objave; stranice, meni, galerije, najave, mediji.',
};

const roleClass: Record<Role, string> = {
    admin: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
    editor: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    author: 'bg-muted text-muted-foreground',
};

// ---- Create / edit ---------------------------------------------------------
const open = ref(false);
const editing = ref<UserRow | null>(null);
const form = useForm({
    name: '',
    email: '',
    role: 'author' as Role,
    password: '',
    password_confirmation: '',
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(user: UserRow): void {
    editing.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.password = '';
    form.password_confirmation = '';
    open.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
        onFinish: () => form.reset('password', 'password_confirmation'),
    };

    if (editing.value) {
        form.put(update.url(editing.value.id), options);
    } else {
        form.post(store.url(), options);
    }
}

// ---- Delete ----------------------------------------------------------------
const deleting = ref<UserRow | null>(null);
const reassignTo = ref<string>('');
const others = computed(() =>
    props.users.filter((u) => u.id !== deleting.value?.id),
);
const deleteBusy = ref(false);

function askDelete(user: UserRow): void {
    deleting.value = user;
    reassignTo.value = String(props.users.find((u) => u.is_me)?.id ?? '');
}

function confirmDelete(): void {
    if (!deleting.value) {
        return;
    }

    deleteBusy.value = true;
    router.delete(destroy.url(deleting.value.id), {
        data: {
            reassign_to: reassignTo.value ? Number(reassignTo.value) : null,
        },
        preserveScroll: true,
        onSuccess: () => (deleting.value = null),
        onFinish: () => (deleteBusy.value = false),
    });
}
</script>

<template>
    <Head title="Korisnici" />

    <div class="flex flex-1 flex-col gap-5 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Korisnici"
                description="Nalozi za administraciju sajta. Javna registracija je isključena – nove naloge pravite ovdje."
            />
            <Button @click="openCreate"><Plus /> Novi korisnik</Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="p-3">Ime</th>
                        <th class="p-3">Uloga</th>
                        <th class="hidden p-3 text-right md:table-cell">
                            Objave
                        </th>
                        <th class="hidden p-3 lg:table-cell">Dodat</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="u in users"
                        :key="u.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="p-3">
                            <p class="font-medium">
                                {{ u.name }}
                                <span
                                    v-if="u.is_me"
                                    class="text-xs font-normal text-muted-foreground"
                                    >(vi)</span
                                >
                                <ShieldCheck
                                    v-if="u.two_factor"
                                    class="ml-1 inline size-3.5 text-green-600"
                                    title="Dvostepena prijava uključena"
                                />
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ u.email }}
                            </p>
                        </td>
                        <td class="p-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="roleClass[u.role]"
                                >{{ u.role_label }}</span
                            >
                        </td>
                        <td
                            class="hidden p-3 text-right tabular-nums md:table-cell"
                        >
                            {{ u.posts_count }}
                        </td>
                        <td
                            class="hidden p-3 text-muted-foreground lg:table-cell"
                        >
                            {{ u.created_at }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Uredi"
                                    @click="openEdit(u)"
                                    ><Pencil
                                /></Button>
                                <Button
                                    v-if="!u.is_me"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Obriši"
                                    class="text-destructive"
                                    @click="askDelete(u)"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <dl class="grid gap-2 rounded-lg border p-4 text-sm md:grid-cols-3">
            <div v-for="r in roles" :key="r.value">
                <dt class="font-medium">{{ r.label }}</dt>
                <dd class="text-muted-foreground">{{ roleHelp[r.value] }}</dd>
            </div>
        </dl>
    </div>

    <!-- Create / edit -->
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    editing ? 'Uredi korisnika' : 'Novi korisnik'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        editing
                            ? 'Ostavite lozinku praznu da ostane ista.'
                            : 'Pošaljite korisniku email i lozinku; lozinku može promijeniti u podešavanjima.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <form id="user-form" class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="u-name">Ime i prezime</Label>
                    <Input id="u-name" v-model="form.name" autocomplete="off" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-email">Email adresa</Label>
                    <Input
                        id="u-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-role">Uloga</Label>
                    <select
                        id="u-role"
                        v-model="form.role"
                        :disabled="editing?.is_me"
                        class="h-9 rounded-md border bg-background px-3 text-sm disabled:opacity-60"
                    >
                        <option
                            v-for="r in roles"
                            :key="r.value"
                            :value="r.value"
                        >
                            {{ r.label }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        {{
                            editing?.is_me
                                ? 'Svoju ulogu ne možete mijenjati.'
                                : roleHelp[form.role]
                        }}
                    </p>
                    <InputError :message="form.errors.role" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-password">{{
                        editing ? 'Nova lozinka (opciono)' : 'Lozinka'
                    }}</Label>
                    <PasswordInput
                        id="u-password"
                        v-model="form.password"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <div class="grid gap-2">
                    <Label for="u-password2">Ponovite lozinku</Label>
                    <PasswordInput
                        id="u-password2"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                    />
                </div>
            </form>
            <DialogFooter>
                <Button variant="outline" type="button" @click="open = false"
                    >Odustani</Button
                >
                <Button
                    type="submit"
                    form="user-form"
                    :disabled="form.processing"
                    >Sačuvaj</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Delete -->
    <Dialog
        :open="deleting !== null"
        @update:open="(v: boolean) => !v && (deleting = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >Obrisati korisnika {{ deleting?.name }}?</DialogTitle
                >
                <DialogDescription
                    >Nalog se trajno briše i korisnik se više ne može
                    prijaviti.</DialogDescription
                >
            </DialogHeader>
            <div
                v-if="
                    deleting && (deleting.posts_count || deleting.pages_count)
                "
                class="grid gap-2"
            >
                <Label for="reassign">
                    Objave ({{ deleting.posts_count }}) i stranice ({{
                        deleting.pages_count
                    }}) prebaci na:
                </Label>
                <select
                    id="reassign"
                    v-model="reassignTo"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">— Nikoga (ostaju bez autora) —</option>
                    <option
                        v-for="u in others"
                        :key="u.id"
                        :value="String(u.id)"
                    >
                        {{ u.name }}
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    Sadržaj ostaje na sajtu u svakom slučaju.
                </p>
            </div>
            <DialogFooter>
                <Button variant="outline" type="button" @click="deleting = null"
                    >Odustani</Button
                >
                <Button
                    variant="destructive"
                    type="button"
                    :disabled="deleteBusy"
                    @click="confirmDelete"
                    >Obriši</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
