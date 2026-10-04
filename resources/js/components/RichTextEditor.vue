<script setup lang="ts">
import {
    Bold,
    Italic,
    Underline,
    Strikethrough,
    List,
    ListOrdered,
    Quote,
    Code,
    SquareCode,
    Undo2,
    Redo2,
    Link2,
    Unlink,
    Minus,
    Eraser,
    Ellipsis,
    Clock3,
} from '@lucide/vue';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = withDefaults(
    defineProps<{
        id: string;
        disabled?: boolean;
        invalid?: boolean;
        document?: boolean;
        preview?: boolean;
    }>(),
    { disabled: false, invalid: false, document: false, preview: false },
);
const model = defineModel<string>({ required: true });
const linkOpen = ref(false);
const linkUrl = ref('');
const linkError = ref('');
const editor = useEditor({
    content: model.value,
    editable: !props.disabled && !props.preview,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: {
                openOnClick: false,
                defaultProtocol: 'https',
                HTMLAttributes: { target: null, rel: 'noopener noreferrer' },
                isAllowedUri: (url, context) =>
                    context.defaultValidate(url) &&
                    /^(https?:\/\/|mailto:|\/(?!\/)|#)/i.test(url),
            },
        }),
    ],
    editorProps: {
        attributes: {
            id: props.id,
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-label': 'Contenu de la page',
            'aria-invalid': String(props.invalid),
            class: props.document
                ? 'rich-content min-h-[52vh] px-6 py-8 outline-none sm:px-10'
                : 'rich-content min-h-80 px-5 py-5 outline-none sm:px-6',
        },
    },
    onUpdate: ({ editor: instance }) => {
        model.value = instance.isEmpty ? '' : instance.getHTML();
    },
});
watch(model, (value) => {
    if (
        editor.value &&
        (editor.value.isEmpty ? '' : editor.value.getHTML()) !== value
    ) {
        editor.value.commands.setContent(value, { emitUpdate: false });
    }
});
watch(
    () => [props.disabled, props.preview],
    () => editor.value?.setEditable(!props.disabled && !props.preview),
);
watch(
    () => props.invalid,
    (value) =>
        editor.value?.setOptions({
            editorProps: { attributes: { 'aria-invalid': String(value) } },
        }),
);
const words = computed(
    () =>
        editor.value?.getText().trim().split(/\s+/).filter(Boolean).length ?? 0,
);
const block = computed(() =>
    editor.value?.isActive('heading', { level: 2 })
        ? 'h2'
        : editor.value?.isActive('heading', { level: 3 })
          ? 'h3'
          : 'p',
);
const tools = [
    {
        label: 'Gras (⌘/Ctrl+B)',
        icon: Bold,
        active: 'bold',
        run: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
        label: 'Italique (⌘/Ctrl+I)',
        icon: Italic,
        active: 'italic',
        run: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
        label: 'Souligné (⌘/Ctrl+U)',
        icon: Underline,
        active: 'underline',
        run: () => editor.value?.chain().focus().toggleUnderline().run(),
    },
    {
        label: 'Barré',
        icon: Strikethrough,
        active: 'strike',
        run: () => editor.value?.chain().focus().toggleStrike().run(),
    },
    {
        label: 'Liste à puces',
        icon: List,
        active: 'bulletList',
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
    },
    {
        label: 'Liste numérotée',
        icon: ListOrdered,
        active: 'orderedList',
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
    },
    {
        label: 'Citation',
        icon: Quote,
        active: 'blockquote',
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
    },
    {
        label: 'Code',
        icon: Code,
        active: 'code',
        run: () => editor.value?.chain().focus().toggleCode().run(),
    },
    {
        label: 'Bloc de code',
        icon: SquareCode,
        active: 'codeBlock',
        run: () => editor.value?.chain().focus().toggleCodeBlock().run(),
    },
];
function changeBlock(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    if (value === 'p') {
        editor.value?.chain().focus().setParagraph().run();
    } else {
        editor.value
            ?.chain()
            .focus()
            .setHeading({ level: value === 'h2' ? 2 : 3 })
            .run();
    }
}
function openLink(): void {
    linkUrl.value = String(editor.value?.getAttributes('link').href ?? '');
    linkError.value = '';
    linkOpen.value = true;
}
function saveLink(): void {
    const url = linkUrl.value.trim();

    if (!/^(https?:\/\/|mailto:|\/(?!\/)|#)/i.test(url) || /[\s\\]/.test(url)) {
        linkError.value =
            'Utilisez une URL https://, http://, mailto:, un chemin /page ou une ancre #section.';

        return;
    }

    editor.value
        ?.chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url })
        .run();
    linkOpen.value = false;
}
</script>

<template>
    <div
        class="bg-background"
        :class="
            document
                ? 'rounded-b-xl'
                : [
                      'focus-within:ring-ring/40 overflow-hidden rounded-xl border shadow-xs focus-within:ring-2',
                      invalid ? 'border-destructive' : 'border-input',
                  ]
        "
    >
        <div
            v-show="!preview"
            role="group"
            aria-label="Mise en forme du contenu"
            class="bg-muted/40 flex flex-wrap items-center gap-0.5 border-y px-3 py-2 sm:px-5"
        >
            <select
                :value="block"
                :disabled="disabled || !editor"
                aria-label="Style de paragraphe"
                class="bg-background mr-1 h-8 rounded-md border px-2 text-sm"
                @change="changeBlock"
            >
                <option value="p">Paragraphe</option>
                <option value="h2">Titre 2</option>
                <option value="h3">Titre 3</option>
            </select>
            <Button
                v-for="tool in tools.filter(
                    (item) =>
                        !['strike', 'code', 'codeBlock'].includes(item.active),
                )"
                :key="tool.active"
                type="button"
                size="icon"
                class="size-8"
                :variant="editor?.isActive(tool.active) ? 'secondary' : 'ghost'"
                :aria-pressed="editor?.isActive(tool.active) ?? false"
                :title="tool.label"
                :aria-label="tool.label"
                :disabled="disabled || !editor"
                @click="tool.run"
            >
                <component :is="tool.icon" class="size-4" />
            </Button>
            <span class="mx-1 h-5 border-l" aria-hidden="true" />
            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="size-8"
                title="Ajouter ou modifier un lien"
                aria-label="Ajouter ou modifier un lien"
                :disabled="disabled || !editor"
                @click="openLink"
                ><Link2 class="size-4"
            /></Button>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        aria-label="Plus de mise en forme"
                        title="Plus de mise en forme"
                        :disabled="disabled || !editor"
                        ><Ellipsis class="size-4"
                    /></Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                    <DropdownMenuItem
                        v-for="tool in tools.filter((item) =>
                            ['strike', 'code', 'codeBlock'].includes(
                                item.active,
                            ),
                        )"
                        :key="tool.active"
                        @select="tool.run"
                        ><component :is="tool.icon" class="size-4" />{{
                            tool.label
                        }}</DropdownMenuItem
                    >
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        @select="
                            editor?.chain().focus().setHorizontalRule().run()
                        "
                        ><Minus class="size-4" />Insérer un
                        séparateur</DropdownMenuItem
                    >
                    <DropdownMenuItem
                        :disabled="!editor?.isActive('link')"
                        @select="
                            editor
                                ?.chain()
                                .focus()
                                .extendMarkRange('link')
                                .unsetLink()
                                .run()
                        "
                        ><Unlink class="size-4" />Retirer le
                        lien</DropdownMenuItem
                    >
                    <DropdownMenuItem
                        @select="
                            editor
                                ?.chain()
                                .focus()
                                .unsetAllMarks()
                                .clearNodes()
                                .run()
                        "
                        ><Eraser class="size-4" />Effacer la mise en
                        forme</DropdownMenuItem
                    >
                </DropdownMenuContent>
            </DropdownMenu>
            <span class="mx-1 h-5 border-l" aria-hidden="true" />
            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="size-8"
                title="Annuler (⌘/Ctrl+Z)"
                aria-label="Annuler la dernière modification"
                :disabled="disabled || !editor?.can().undo()"
                @click="editor?.chain().focus().undo().run()"
                ><Undo2 class="size-4"
            /></Button>
            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="size-8"
                title="Rétablir (⌘/Ctrl+Maj+Z)"
                aria-label="Rétablir la modification"
                :disabled="disabled || !editor?.can().redo()"
                @click="editor?.chain().focus().redo().run()"
                ><Redo2 class="size-4"
            /></Button>
        </div>
        <div class="relative">
            <span
                v-if="editor?.isEmpty"
                class="text-muted-foreground/60 pointer-events-none absolute"
                :class="
                    document
                        ? 'top-8 left-6 sm:left-10'
                        : 'top-5 left-5 sm:left-6'
                "
                >{{
                    preview
                        ? 'Aucun contenu pour le moment.'
                        : 'Commencez à écrire votre page…'
                }}</span
            >
            <EditorContent :editor="editor" />
        </div>
        <div
            class="text-muted-foreground flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2 text-xs"
        >
            <span>{{ words }} {{ words === 1 ? 'mot' : 'mots' }}</span>
            <span class="flex items-center gap-1.5"
                ><Clock3 class="size-3" />{{
                    words > 0 ? Math.max(1, Math.ceil(words / 200)) : 0
                }}
                min de lecture</span
            >
        </div>
        <Dialog v-model:open="linkOpen">
            <DialogContent>
                <DialogHeader
                    ><DialogTitle>Ajouter un lien</DialogTitle
                    ><DialogDescription
                        >Sélectionnez du texte dans l’éditeur, puis indiquez sa
                        destination.</DialogDescription
                    ></DialogHeader
                >
                <div class="space-y-3" @keydown.enter.prevent="saveLink">
                    <label for="editor-link" class="text-sm font-medium"
                        >Destination</label
                    >
                    <Input
                        id="editor-link"
                        v-model="linkUrl"
                        placeholder="https://exemple.com"
                        :aria-invalid="!!linkError"
                    />
                    <p
                        v-if="linkError"
                        role="alert"
                        class="text-destructive text-sm"
                    >
                        {{ linkError }}
                    </p>
                    <Button type="button" @click="saveLink"
                        >Appliquer le lien</Button
                    >
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
