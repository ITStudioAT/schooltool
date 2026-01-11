<template>
    <div class="its-rich-text-editor">
        <div class="toolbar d-flex ga-1 mb-1">
            <v-btn-group density="compact" variant="outlined">
                <v-btn icon size="small" :variant="editor?.isActive('bold') ? 'flat' : 'outlined'" @click="editor?.chain().focus().toggleBold().run()" title="Fett">
                    <v-icon>mdi-format-bold</v-icon>
                </v-btn>
                <v-btn
                    icon
                    size="small"
                    :variant="editor?.isActive('underline') ? 'flat' : 'outlined'"
                    @click="editor?.chain().focus().toggleUnderline().run()"
                    title="Unterstrichen">
                    <v-icon>mdi-format-underline</v-icon>
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
    min-height: 100px;
}

.editor-content :deep(.ProseMirror) {
    outline: none;
    min-height: 80px;
}

.editor-content :deep(.ProseMirror p) {
    margin: 0;
}
</style>
