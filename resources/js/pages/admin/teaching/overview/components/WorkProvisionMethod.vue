<template>
    <v-btn min-height="44" size="small" variant="tonal" prepend-icon="mdi-share-variant-outline"
        :disabled="disabled || !work.id" @click.stop="open">Bereitstellungsweg vorbereiten</v-btn>
    <v-dialog v-model="opened" max-width="510" scrollable :persistent="saving">
        <v-card v-if="opened">
            <v-card-title class="text-wrap">Bereitstellungsweg vorbereiten</v-card-title>
            <v-card-text>
                <fieldset class="provision-choices" :disabled="saving || disabled">
                    <legend>Wie sollen die Schüler:innen die Aufgabe erhalten?</legend>
                    <label v-for="option in options" :key="option.value" :class="{ 'is-selected': selected === option.value }">
                        <input v-model="selected" type="radio" :name="group" :value="option.value" />
                        <span><strong>{{ option.label }}</strong><small>{{ option.detail }}</small></span>
                    </label>
                </fieldset>
                <p v-if="selected === 'portal' && !portalAvailable" class="text-body-2 mt-3">
                    Du kannst den Weg schon festlegen. Die Freigabe wird nach der Vorbereitung der Leistungsfeststellung verfügbar.
                </p>
                <v-alert v-if="error" type="error" variant="tonal" class="mt-3" role="alert">{{ error }}</v-alert>
            </v-card-text>
            <v-card-actions class="flex-wrap">
                <v-btn v-if="current" min-height="44" variant="text" :disabled="saving || disabled" @click="apply(null)">Auswahl entfernen</v-btn>
                <v-spacer />
                <v-btn min-height="44" variant="text" :disabled="saving" @click="opened = false">Schließen</v-btn>
                <v-btn min-height="44" color="primary" variant="flat" :disabled="!selected || saving || disabled"
                    :loading="saving" @click="apply(selected)">
                    {{ selected === 'portal' && !portalAvailable ? 'Auswahl speichern' : 'Weiter' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue'
import { updateProvisionMethod } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkSubmissionController'

const props = defineProps({ work: { type: Object, required: true }, disabled: Boolean,
    date: { type: String, default: '' }, portalAvailable: Boolean })
const emit = defineEmits(['saved', 'portal', 'mail'])
const options = [
    { value: 'portal', label: 'Im Schülerbereich', detail: 'Aufgabe unter „Meine Aufgaben“ vorbereiten und freigeben.' },
    { value: 'email', label: 'Per E-Mail', detail: 'Persönliche Aufgabenmails vorbereiten und den Versand prüfen.' },
]
const group = useId()
const opened = ref(false)
const saving = ref(false)
const error = ref('')
const selected = ref(null)
const expected = ref(null)
const current = computed(() => props.work.status?.provision_method?.channel || null)
let context = 0

function open() {
    if (props.disabled || !props.work.id) return
    expected.value = current.value
    selected.value = current.value
    error.value = ''
    opened.value = true
}

async function apply(channel) {
    if (saving.value || props.disabled) return
    const version = context
    saving.value = true
    error.value = ''
    try {
        const { data } = await axios.put(updateProvisionMethod.url(props.work.id), { channel, expected: expected.value })
        if (version !== context) return
        emit('saved', data.data)
        opened.value = false
        await nextTick()
        if (version === context && !props.disabled) {
            if (channel === 'email') emit('mail')
            if (channel === 'portal' && props.portalAvailable) emit('portal')
        }
    } catch (failure) {
        if (version !== context) return
        const response = failure.response
        if (response?.status === 409 && Object.hasOwn(response.data || {}, 'current')) {
            expected.value = response.data.current
            selected.value = response.data.current
        }
        error.value = response?.data?.message || 'Der Bereitstellungsweg konnte nicht gespeichert werden.'
    } finally {
        if (version === context) saving.value = false
    }
}

watch([() => props.work.id, () => props.date], () => {
    context++
    opened.value = false
    saving.value = false
})

defineExpose({ open })
</script>

<style scoped>
.provision-choices { display: grid; gap: .6rem; min-width: 0; border: 0; padding: 0; }
.provision-choices legend { padding: 0 0 .85rem; font-weight: 600; }
.provision-choices label { display: flex; align-items: center; gap: .8rem; min-height: 58px; padding: .55rem .8rem; border: 1px solid rgba(var(--v-theme-on-surface), .22); border-radius: .5rem; cursor: pointer; }
.provision-choices label.is-selected { border-color: rgb(var(--v-theme-primary)); background: rgba(var(--v-theme-primary), .06); }
.provision-choices label:focus-within { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 2px; }
.provision-choices input { accent-color: rgb(var(--v-theme-primary)); }
.provision-choices small { display: block; line-height: 1.45; color: rgba(var(--v-theme-on-surface), .78); }
</style>
