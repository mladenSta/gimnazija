<script setup lang="ts">
import type { Component } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import { Placeholder } from '@tiptap/extensions';
import {
    Bold,
    Heading2,
    Heading3,
    Heading4,
    ImagePlus,
    Italic,
    Link2,
    Link2Off,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    Strikethrough,
    Table,
    Underline,
    Undo2,
} from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import MediaPicker from '@/components/media/MediaPicker.vue';
import type { MediaItem, UploadSettings } from '@/types/media';

const props = defineProps<{
    upload: UploadSettings;
    placeholder?: string;
}>();

const model = defineModel<string | null>({ default: '' });
const pickerOpen = ref(false);

const editor = useEditor({
    content: model.value ?? '',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
            },
            code: false,
            codeBlock: false,
        }),
        Image,
        TableKit.configure({ table: { resizable: false } }),
        Placeholder.configure({
            placeholder: props.placeholder ?? 'Počnite da pišete…',
        }),
    ],
    editorProps: {
        attributes: {
            class: 'rich-content min-h-80 px-4 py-3 focus:outline-none',
        },
    },
    onUpdate: ({ editor: e }) => {
        model.value = e.isEmpty ? '' : e.getHTML();
    },
});

watch(model, (value) => {
    if (
        editor.value &&
        value !== editor.value.getHTML() &&
        !(value === '' && editor.value.isEmpty)
    ) {
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
    }
});

onBeforeUnmount(() => editor.value?.destroy());

function setLink(): void {
    if (!editor.value) {
        return;
    }

    const previous = editor.value.getAttributes('link').href as
        | string
        | undefined;
    const url = window.prompt(
        'Adresa linka (npr. https://… ili /kontakt/)',
        previous ?? '',
    );

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url.trim() })
        .run();
}

function insertImages(media: MediaItem[]): void {
    for (const item of media) {
        editor.value
            ?.chain()
            .focus()
            .setImage({ src: item.medium_url, alt: item.alt ?? '' })
            .run();
    }
}

type Tool = {
    icon: Component;
    label: string;
    action: () => void;
    active?: () => boolean;
};

const c = () => editor.value!.chain().focus();

const groups: Tool[][] = [
    [
        {
            icon: Heading2,
            label: 'Naslov 2',
            action: () => c().toggleHeading({ level: 2 }).run(),
            active: () => !!editor.value?.isActive('heading', { level: 2 }),
        },
        {
            icon: Heading3,
            label: 'Naslov 3',
            action: () => c().toggleHeading({ level: 3 }).run(),
            active: () => !!editor.value?.isActive('heading', { level: 3 }),
        },
        {
            icon: Heading4,
            label: 'Naslov 4',
            action: () => c().toggleHeading({ level: 4 }).run(),
            active: () => !!editor.value?.isActive('heading', { level: 4 }),
        },
    ],
    [
        {
            icon: Bold,
            label: 'Podebljano',
            action: () => c().toggleBold().run(),
            active: () => !!editor.value?.isActive('bold'),
        },
        {
            icon: Italic,
            label: 'Kurziv',
            action: () => c().toggleItalic().run(),
            active: () => !!editor.value?.isActive('italic'),
        },
        {
            icon: Underline,
            label: 'Podvučeno',
            action: () => c().toggleUnderline().run(),
            active: () => !!editor.value?.isActive('underline'),
        },
        {
            icon: Strikethrough,
            label: 'Precrtano',
            action: () => c().toggleStrike().run(),
            active: () => !!editor.value?.isActive('strike'),
        },
    ],
    [
        {
            icon: Link2,
            label: 'Link',
            action: setLink,
            active: () => !!editor.value?.isActive('link'),
        },
        {
            icon: Link2Off,
            label: 'Ukloni link',
            action: () => c().unsetLink().run(),
        },
    ],
    [
        {
            icon: List,
            label: 'Lista',
            action: () => c().toggleBulletList().run(),
            active: () => !!editor.value?.isActive('bulletList'),
        },
        {
            icon: ListOrdered,
            label: 'Numerisana lista',
            action: () => c().toggleOrderedList().run(),
            active: () => !!editor.value?.isActive('orderedList'),
        },
        {
            icon: Quote,
            label: 'Citat',
            action: () => c().toggleBlockquote().run(),
            active: () => !!editor.value?.isActive('blockquote'),
        },
        {
            icon: Minus,
            label: 'Linija',
            action: () => c().setHorizontalRule().run(),
        },
    ],
    [
        {
            icon: ImagePlus,
            label: 'Slika u tekstu',
            action: () => (pickerOpen.value = true),
        },
        {
            icon: Table,
            label: 'Tabela',
            action: () =>
                c()
                    .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                    .run(),
        },
    ],
    [
        { icon: Undo2, label: 'Poništi', action: () => c().undo().run() },
        { icon: Redo2, label: 'Ponovi', action: () => c().redo().run() },
    ],
];

const inTable = () => !!editor.value?.isActive('table');
</script>

<template>
    <div class="overflow-hidden rounded-lg border bg-background">
        <div
            class="sticky top-0 z-10 flex flex-wrap items-center gap-1 border-b bg-muted/60 p-1.5 backdrop-blur"
        >
            <template v-for="(group, gi) in groups" :key="gi">
                <span v-if="gi > 0" class="mx-1 h-5 w-px bg-border" />
                <button
                    v-for="tool in group"
                    :key="tool.label"
                    type="button"
                    class="grid size-8 place-items-center rounded-md hover:bg-accent"
                    :class="
                        tool.active?.()
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground'
                    "
                    :title="tool.label"
                    :aria-label="tool.label"
                    @click="tool.action"
                >
                    <component :is="tool.icon" class="size-4" />
                </button>
            </template>
        </div>

        <div
            v-if="inTable()"
            class="flex flex-wrap gap-1 border-b bg-muted/30 p-1.5 text-xs"
        >
            <button
                type="button"
                class="rounded px-2 py-1 hover:bg-accent"
                @click="c().addRowAfter().run()"
            >
                + red
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 hover:bg-accent"
                @click="c().deleteRow().run()"
            >
                − red
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 hover:bg-accent"
                @click="c().addColumnAfter().run()"
            >
                + kolona
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 hover:bg-accent"
                @click="c().deleteColumn().run()"
            >
                − kolona
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 text-destructive hover:bg-accent"
                @click="c().deleteTable().run()"
            >
                Obriši tabelu
            </button>
        </div>

        <EditorContent :editor="editor" />

        <MediaPicker
            v-model:open="pickerOpen"
            :settings="upload"
            multiple
            title="Ubacite sliku u tekst"
            @select="insertImages"
        />
    </div>
</template>
