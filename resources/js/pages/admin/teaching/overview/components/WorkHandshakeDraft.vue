<template>
    <div class="handshake-draft">
        <file-pond :key="pondKey" name="handshake" :allow-multiple="false" :max-files="1"
            :instant-upload="false" :allow-process="false" :allow-revert="false" :credits="false"
            :allow-paste="false" :drop-validation="true" :before-drop-file="isLocalFile"
            :disabled="disabled" :accepted-file-types="['application/json']"
            label-idle='handshake.json hierher ziehen oder <span class="filepond--label-action" role="button">Datei auswählen</span>'
            label-button-remove-item="Datei entfernen" @updatefiles="selectFiles" @warning="rejectMultipleFiles" />
        <p class="text-caption mt-1">Nur eine handshake.json mit höchstens 4 KB auswählen.</p>
        <p v-if="reading" class="text-caption mt-1" role="status">Datei wird geprüft …</p>
        <p v-else-if="modelValue" class="text-caption mt-1" role="status">Datei geprüft: {{ modelValue.filename }}</p>
        <p v-if="errorMessage" class="text-error text-caption mt-1" role="alert">{{ errorMessage }}</p>
        <button v-if="modelValue || errorMessage" type="button" class="text-primary text-caption mt-1" :disabled="disabled"
            @click="clearSelection">Auswahl entfernen</button>
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue'
import vueFilePond from 'vue-filepond/dist/vue-filepond.js'
import 'filepond/dist/filepond.min.css'
import { readModuleHandshake } from '@/helpers/moduleHandshake'

const FilePond = vueFilePond()
const props = defineProps({ modelValue: { type: Object, default: null }, disabled: Boolean })
const emit = defineEmits(['update:modelValue', 'state'])
const reading = ref(false)
const errorMessage = ref('')
const pondKey = ref(0)
let sequence = 0

function setBusy(value) {
    reading.value = value
    emit('state', { busy: reading.value, error: errorMessage.value })
}

function setError(value) {
    errorMessage.value = value
    emit('state', { busy: reading.value, error: errorMessage.value })
}

function clearSelection() {
    sequence++
    pondKey.value++
    setBusy(false)
    setError('')
    emit('update:modelValue', null)
}

async function readFiles(files) {
    const current = ++sequence
    emit('update:modelValue', null)
    setError('')
    if (!files.length) {
        setBusy(false)
        return
    }
    if (files.length !== 1) {
        setBusy(false)
        setError('Bitte genau eine handshake.json auswählen.')
        return
    }
    setBusy(true)
    try {
        const selected = await readModuleHandshake(files[0])
        if (current === sequence) emit('update:modelValue', selected)
    } catch (exception) {
        if (current === sequence) setError(exception.message || 'Die Datei konnte nicht gelesen werden.')
    } finally {
        if (current === sequence) setBusy(false)
    }
}

function selectFiles(items) {
    if (props.disabled) return
    readFiles(items.map(item => item.file))
}

function isLocalFile(item) {
    return item instanceof File
}

function rejectMultipleFiles() {
    if (props.disabled) return
    sequence++
    pondKey.value++
    emit('update:modelValue', null)
    setBusy(false)
    setError('Bitte genau eine handshake.json auswählen.')
}

onBeforeUnmount(() => { sequence++ })
</script>

