<template>
    <div class="its-rich-text-editor" @keydown.stop>
        <div class="toolbar d-flex flex-wrap ga-1 mb-2">
            <v-btn-group density="compact" variant="outlined">
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('bold') ? 'flat' : 'outlined'"
                    :disabled="!editor?.can()?.chain().focus().toggleBold().run()"
                    @click="editor?.chain().focus().toggleBold().run()"
                    title="Fett">
                    <v-icon>mdi-format-bold</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('italic') ? 'flat' : 'outlined'"
                    :disabled="!editor?.can()?.chain().focus().toggleItalic().run()"
                    @click="editor?.chain().focus().toggleItalic().run()"
                    title="Kursiv">
                    <v-icon>mdi-format-italic</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('underline') ? 'flat' : 'outlined'"
                    :disabled="!editor?.can()?.chain().focus().toggleUnderline().run()"
                    @click="editor?.chain().focus().toggleUnderline().run()"
                    title="Unterstrichen">
                    <v-icon>mdi-format-underline</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('strike') ? 'flat' : 'outlined'"
                    :disabled="!editor?.can()?.chain().focus().toggleStrike().run()"
                    @click="editor?.chain().focus().toggleStrike().run()"
                    title="Durchgestrichen">
                    <v-icon>mdi-format-strikethrough-variant</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('code') ? 'flat' : 'outlined'"
                    :disabled="!editor?.can()?.chain().focus().toggleCode().run()"
                    @click="editor?.chain().focus().toggleCode().run()"
                    title="Inline-Code">
                    <v-icon>mdi-code-tags</v-icon>
                </v-btn>
            </v-btn-group>

            <v-btn-group density="compact" variant="outlined">
                <v-btn
                    size="small"
                    min-width="40"
                    :variant="editor?.isActive('heading', { level: 1 }) ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
                    title="Überschrift 1">
                    H1
                </v-btn>
                <v-btn
                    size="small"
                    min-width="40"
                    :variant="editor?.isActive('heading', { level: 2 }) ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
                    title="Überschrift 2">
                    H2
                </v-btn>
                <v-btn
                    size="small"
                    min-width="40"
                    :variant="editor?.isActive('heading', { level: 3 }) ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
                    title="Überschrift 3">
                    H3
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('paragraph') ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().setParagraph().run()"
                    title="Absatz">
                    <v-icon>mdi-format-paragraph</v-icon>
                </v-btn>
            </v-btn-group>

            <v-btn-group density="compact" variant="outlined">
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('bulletList') ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleBulletList().run()"
                    title="Aufzählung">
                    <v-icon>mdi-format-list-bulleted</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('orderedList') ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleOrderedList().run()"
                    title="Nummerierte Liste">
                    <v-icon>mdi-format-list-numbered</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :disabled="!editor?.can()?.chain().focus().sinkListItem('listItem').run()"
                    @click="editor?.chain().focus().sinkListItem('listItem').run()"
                    title="Einrücken">
                    <v-icon>mdi-format-indent-increase</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :disabled="!editor?.can()?.chain().focus().liftListItem('listItem').run()"
                    @click="editor?.chain().focus().liftListItem('listItem').run()"
                    title="Ausrücken">
                    <v-icon>mdi-format-indent-decrease</v-icon>
                </v-btn>
            </v-btn-group>

            <v-btn-group density="compact" variant="outlined">
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('blockquote') ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleBlockquote().run()"
                    title="Zitat">
                    <v-icon>mdi-format-quote-close</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('codeBlock') ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleCodeBlock().run()"
                    title="Code-Block">
                    <v-icon>mdi-code-braces-box</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    @click="editor?.chain().focus().setHorizontalRule().run()"
                    title="Trennlinie">
                    <v-icon>mdi-minus</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    @click="editor?.chain().focus().unsetAllMarks().clearNodes().run()"
                    title="Formatierung löschen">
                    <v-icon>mdi-format-clear</v-icon>
                </v-btn>
            </v-btn-group>

            <v-btn-group density="compact" variant="outlined">
                <v-btn
                    icon
                    size="small"
                    :disabled="!editor?.can()?.chain().focus().undo().run()"
                    @click="editor?.chain().focus().undo().run()"
                    title="Rückgängig">
                    <v-icon>mdi-undo</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :disabled="!editor?.can()?.chain().focus().redo().run()"
                    @click="editor?.chain().focus().redo().run()"
                    title="Wiederholen">
                    <v-icon>mdi-redo</v-icon>
                </v-btn>
            </v-btn-group>
        </div>
        <editor-content :editor="editor" class="editor-content" />
    </div>
</template>

<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'

const props = defineProps({
    modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const convertToHtml = (text) => {
    if (!text) return ''
    // Wenn schon HTML, nicht konvertieren
    if (text.includes('<p>') || text.includes('<br')) return text
    // Zeilenumbrüche zu HTML konvertieren
    return text
        .split('\n')
        .map((line) => `<p>${line || '<br>'}</p>`)
        .join('')
}

const editor = useEditor({
    extensions: [StarterKit, Underline],
    content: convertToHtml(props.modelValue),
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML())
    },
})

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.getHTML() !== value) {
            editor.value.commands.setContent(convertToHtml(value), false)
        }
    }
)

onBeforeUnmount(() => {
    editor.value?.destroy()
})
</script>

<style scoped>
.editor-content {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 4px;
    padding: 8px 12px;
    min-height: 140px;
}

.editor-content :deep(.ProseMirror) {
    outline: none;
    min-height: 120px;
}

.editor-content :deep(.ProseMirror p) {
    margin: 0;
}

.editor-content :deep(.ProseMirror ul),
.editor-content :deep(.ProseMirror ol) {
    margin: 0.45rem 0;
    padding-inline-start: 0;
    list-style-position: inside;
}

.editor-content :deep(.ProseMirror li) {
    margin: 0.2rem 0;
}

.editor-content :deep(.ProseMirror li p) {
    display: inline;
}

.editor-content :deep(.ProseMirror blockquote) {
    margin: 0.6rem 0;
    padding: 0.45rem 0.7rem;
    border-left: 3px solid rgba(253, 128, 46, 0.8);
    background: rgba(253, 128, 46, 0.08);
    border-radius: 0 6px 6px 0;
}

.toolbar {
    align-items: center;
}
</style>
