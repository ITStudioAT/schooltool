<template>
    <div v-if="work.id" class="result-actions">
        <section v-if="mode !== 'notice'" aria-label="Sichtbarkeit der Bewertungen">
            <p class="result-status"><v-icon :icon="visible ? 'mdi-eye-outline' : 'mdi-eye-off-outline'" size="18" />
                {{ visible ? 'Bewertungen im Schülerbereich sichtbar' : 'Bewertungen nur für die Lehrkraft sichtbar' }}
            </p>
            <v-btn variant="tonal" :color="visible ? undefined : 'primary'" :disabled="disabled || busy"
                :prepend-icon="visible ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                @click.stop="choose(visible ? 'hide' : 'release')">
                {{ visible ? 'Bewertungen verbergen' : 'Bewertungen freigeben' }}
            </v-btn>
        </section>
        <section v-if="mode !== 'visibility'" aria-label="Verständigung im Schülerbereich">
            <p class="result-status"><v-icon icon="mdi-bell-outline" size="18" />
                {{ noticeSize ? 'Mitteilung im Kurs für ' + noticeSize + (noticeSize === 1 ? ' Person' : ' Personen') : 'Keine Mitteilung im Kurs' }}
            </p>
            <div class="notice-actions">
                <v-btn variant="tonal" prepend-icon="mdi-bell-outline" :disabled="disabled || busy || !visible" @click.stop="choose('announce')">
                    {{ noticeSize ? 'Mitteilung aktualisieren' : 'Im Kurs verständigen' }}
                </v-btn>
                <v-btn v-if="noticeSize" variant="text" prepend-icon="mdi-bell-off-outline" :disabled="disabled || busy" @click.stop="choose('retract')">
                    Mitteilung zurückziehen
                </v-btn>
            </div>
            <p v-if="!visible" class="text-caption mt-1">Zuerst die Bewertungen freigeben.</p>
        </section>
        <p v-if="feedback" role="status" class="text-caption text-success">{{ feedback }}</p>
        <v-dialog :model-value="!!selected" max-width="440" :persistent="busy" @update:model-value="value => { if (!value && !busy) selected = '' }">
            <v-card v-if="selected">
                <v-card-title class="text-wrap">{{ confirmText[selected].title }}</v-card-title>
                <v-card-text>
                    <p>{{ confirmText[selected].detail }}</p>
                    <v-alert v-if="error" class="mt-3" type="error" variant="tonal" role="alert">{{ error }}</v-alert>
                </v-card-text>
                <v-card-actions class="flex-wrap">
                    <v-spacer />
                    <v-btn :disabled="busy" @click.stop="selected = ''">Abbrechen</v-btn>
                    <v-btn variant="flat" color="primary" :loading="busy" :disabled="busy" @click.stop="save">{{ confirmText[selected].action }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { notice, update } from '@/actions/App/Http/Controllers/Admin/Teaching/WorkGradeVisibilityController'

const props = defineProps({
    work: { type: Object, required: true },
    mode: { type: String, default: 'both' },
    disabled: Boolean,
})
const emit = defineEmits(['saved'])
const state = computed(() => props.work.status?.grade_visibility)
const visible = computed(() => !Object.hasOwn(props.work.status ?? {}, 'grade_visibility') || state.value?.visible === true)
const noticeSize = computed(() => state.value?.student_notice?.student_ids?.length || 0)
const selected = ref('')
const busy = ref(false)
const error = ref('')
const feedback = ref('')
let expectedChangedAt = null
let expectedNoticeAt = null
let workRevision = 0

const confirmText = {
    release: { title: 'Bewertungen freigeben?', detail: 'Zugeordnete Schüler:innen sehen ihre gespeicherte Bewertung und ihre persönliche Auswertung unter „Leistungen“.', action: 'Jetzt freigeben', success: 'Die Bewertungen sind im Schülerbereich sichtbar.' },
    hide: { title: 'Bewertungen verbergen?', detail: 'Die Bewertungen, ihre PDF-Links und eine zugehörige Kursmitteilung werden im Schülerbereich ausgeblendet. Bereits versandte E-Mails lassen sich nicht zurückholen.', action: 'Jetzt verbergen', success: 'Die Bewertungen sind im Schülerbereich verborgen.' },
    announce: { title: 'Im Kurs verständigen?', detail: 'Alle aktuell zugeordneten Schüler:innen mit einer gespeicherten Bewertung sehen in ihrer Kursübersicht einen Hinweis auf ihr Ergebnis. Bereits verständigte Personen sehen weiterhin nur eine Mitteilung.', action: 'Mitteilung anzeigen', success: 'Die Kursmitteilung ist für die bewerteten Schüler:innen sichtbar.' },
    retract: { title: 'Mitteilung zurückziehen?', detail: 'Die Mitteilung verschwindet aus der Kursübersicht. Die Bewertung bleibt unter „Leistungen“ sichtbar.', action: 'Jetzt zurückziehen', success: 'Die Kursmitteilung wurde zurückgezogen.' },
}

function choose(action) {
    if (props.disabled || busy.value) return
    expectedChangedAt = state.value?.changed_at ?? null
    expectedNoticeAt = state.value?.student_notice?.changed_at ?? null
    error.value = ''
    feedback.value = ''
    selected.value = action
}

async function save() {
    if (props.disabled || busy.value || !selected.value) return
    const id = props.work.id
    const revision = workRevision
    const action = selected.value
    busy.value = true
    error.value = ''
    try {
        const payload = { expected_changed_at: expectedChangedAt }
        const response = ['release', 'hide'].includes(action)
            ? await axios.patch(update.url(id), { ...payload, visible: action === 'release' })
            : await axios.put(notice.url(id), { ...payload, expected_notice_at: expectedNoticeAt, publish: action === 'announce' })
        if (revision !== workRevision) return
        const saved = response.data?.data
        if (Number(saved?.id) !== Number(id) || Number(saved.teaching_course_id) !== Number(props.work.teaching_course_id)) {
            throw new Error('Die Antwort passt nicht zu dieser Arbeit. Bitte die Arbeit erneut laden.')
        }
        emit('saved', saved)
        feedback.value = confirmText[action].success
        selected.value = ''
    } catch (failure) {
        if (revision === workRevision) error.value = failure.response?.data?.message || failure.message || 'Die Änderung konnte nicht gespeichert werden.'
    } finally {
        if (revision === workRevision) busy.value = false
    }
}

watch(() => props.work.id, () => {
    workRevision++
    selected.value = ''
    busy.value = false
    error.value = ''
    feedback.value = ''
})
</script>

<style scoped>
.result-actions { display: grid; gap: 12px; }
.result-status, .notice-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.result-status { margin: 0 0 5px; font-size: .875rem; }
.result-actions :deep(.v-btn) { min-height: 44px; white-space: normal; }
</style>
