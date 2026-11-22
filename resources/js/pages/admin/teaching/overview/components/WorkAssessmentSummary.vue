<template>
    <section v-if="rows.length || folderPath" class="work-assessment-facts" aria-label="Prüfungsdetails">
        <h3>Leistungsfeststellung</h3>
        <dl class="work-assessment-ways">
            <div><dt>Bereitstellung</dt>
                <dd v-for="item in provision" :key="item.key">{{ item.label }}<template v-if="item.state"> · {{ item.state }}</template></dd>
            </div>
            <div><dt>Abgabeweg</dt><dd>{{ submission }}</dd></div>
        </dl>
        <dl v-if="rows.length">
            <div v-for="row in rows" :key="row.label">
                <dt :title="row.title">{{ row.label }}</dt>
                <dd class="font-weight-medium">{{ row.value }}</dd>
                <dd v-for="detail in row.details" :key="detail" class="text-caption text-medium-emphasis">{{ detail }}</dd>
                <dd v-if="row.addTest && canAddTest">
                    <v-btn size="small" min-height="44" variant="text" prepend-icon="mdi-plus"
                        aria-label="Multiple-Choice-Test hinzufügen" :disabled="disabled" @click="$emit('add-test')">
                        Test hinzufügen
                    </v-btn>
                </dd>
            </div>
        </dl>
        <details v-if="folderPath">
            <summary>Prüfungsordner</summary>
            <p>{{ folderPath }}</p>
        </details>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { workProvisionEvidence } from '@/helpers/workDispatch'

const props = defineProps({
    work: { type: Object, required: true },
    course: Object,
    date: String,
    assessment: Object,
    assessmentRun: Object,
    canAddTest: Boolean,
    disabled: Boolean,
})
defineEmits(['add-test'])
const numberFormat = new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 })
const folderPath = computed(() => props.assessment?.status === 'found' ? props.assessment.folder?.path : null)
const provision = computed(() => {
    const evidence = workProvisionEvidence(props.work, props.date, props.assessment?.emailDispatch ?? {})
    return evidence.some(item => item.key === 'email') ? evidence.filter(item => !['email-pending', 'mailpit'].includes(item.key)) : evidence
})
const submission = computed(() => {
    const method = props.work.status?.submission_method
    if (method?.channel === 'portal') return 'Datei-Upload unter „Meine Aufgaben“'
    if (method?.channel === 'email') return method.email ? `Datei per E-Mail an ${method.email}` : 'Datei per E-Mail · Adresse fehlt'
    return 'Noch nicht festgelegt'
})

function pointsLabel(value) {
    return `${numberFormat.format(value)} ${value === 1 ? 'Punkt' : 'Punkte'}`
}

const rows = computed(() => {
    const assessment = props.assessment
    const preparation = assessment?.status === 'found' && assessment.preparation?.status === 'ready' ? assessment.preparation : null
    const work = props.work
    const groupDates = [...new Set((work.groups || []).map(group => group.date).filter(Boolean))]
    const date = props.date || work.date_for_all_groups || (groupDates.length === 1 ? groupDates[0] : '')
    const bindings = work.status?.multiple_choice_tests || {}
    const testIds = [...new Set([bindings[date], bindings.all]
        .filter(binding => binding?.test_id && String(binding.work_id) === String(work.id)).map(binding => String(binding.test_id)))]
    const tests = testIds.map(id => work.multiple_choice_test_details?.[id] || {})
    const run = props.assessmentRun ?? work.assessment_run
    const runPoints = run?.state === 'completed' && run.date === date && assessment?.status !== 'missing' && !assessment?.deletionPending
        ? run.assignment_points : null
    const assignment = preparation?.components?.aufgabenblatt
    const folderMc = preparation?.components?.multiple_choice
    const usable = value => Number.isFinite(value) && value >= 0
    const assignmentPoints = usable(assignment?.points) ? assignment.points : usable(runPoints) ? runPoints : null
    const release = work.status?.student_distribution
    const assignmentReady = assessment?.assignmentStatus === 'ready' || preparation?.document_sections?.includes('Angaben')
        || !!release?.instructions?.trim() || !!release?.file_ids?.length
    const hasAssignment = assignment?.included === true || assignmentPoints !== null || assignmentReady
    const noAssignment = assignment?.included === false && !hasAssignment
    const hasMc = tests.length > 0 || folderMc?.included === true
    if (!preparation && !hasMc && !hasAssignment) return []

    const mcPoints = tests.length > 0 && tests.every(test => usable(test.points))
        ? tests.reduce((sum, test) => sum + test.points, 0)
        : tests.length <= 1 && folderMc?.included && usable(folderMc.points) ? folderMc.points : null
    const facts = []
    if (hasAssignment) {
        facts.push({ label: 'Aufgabenblatt', value: assignmentPoints === null ? 'Punkte offen' : pointsLabel(assignmentPoints) })
    }
    if (hasMc) {
        const test = tests.length === 1 ? tests[0] : null
        const details = [
            tests.length > 1 && `${tests.length} Tests`,
            [
                test?.question_count && `${test.question_count} Fr.`,
                test?.duration_seconds && `${numberFormat.format(test.duration_seconds / 60)} min.`,
            ].filter(Boolean).join('/'),
        ].filter(Boolean)
        facts.push({ label: 'Multiple-Choice-Test', value: mcPoints === null ? 'Punkte offen' : pointsLabel(mcPoints), details })
    } else if (folderMc?.included === false || (assignmentReady && props.canAddTest)) {
        facts.push({ label: 'Multiple-Choice-Test', value: 'Keiner', addTest: true })
    }

    const maximum = Number(props.course?.teaching_entry_area?.entry_definitions?.find(
        entry => (entry.category ?? 'Benotung') === 'Benotung' && entry.short_name === work.type,
    )?.maximum_points)
    const total = (noAssignment || assignmentPoints !== null) && (!hasMc || mcPoints !== null)
        ? (assignmentPoints ?? 0) + (mcPoints ?? 0) : null
    const missing = hasAssignment && assignmentPoints === null ? 'Punkte des Aufgabenblatts fehlen.'
        : hasMc && mcPoints === null ? 'Punkte des MC-Tests fehlen.'
            : !hasAssignment && !noAssignment ? 'Ein Aufgabenblatt ist nicht bestätigt.' : null
    facts.push({ label: 'Gesamtpunkte', value: total === null ? 'Noch offen' : pointsLabel(total), details: total === null && missing ? [missing] : [] })
    if (Number.isFinite(maximum) && maximum > 0) facts.push({ label: 'Schooltool', title: 'Zielwert auf der Schooltool-Bewertungsskala', value: pointsLabel(maximum) })
    return facts
})
</script>

<style scoped>
.work-assessment-facts {
    background: rgb(var(--v-theme-surface));
    border: 1px solid rgba(var(--v-theme-primary), .18);
    border-inline-start: 3px solid rgb(var(--v-theme-primary));
    border-radius: .5rem;
    margin-block-end: 1rem;
    padding: .75rem;
}
.work-assessment-facts h3 { font-size: 1rem; font-weight: 700; }
.work-assessment-facts dl {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 8.5rem), 1fr));
    gap: .5rem 1rem;
    margin-block-start: .375rem;
}
.work-assessment-facts .work-assessment-ways {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-block-end: 1px solid rgba(var(--v-border-color), .2);
    padding-block: .25rem .6rem;
    margin-block-end: .6rem;
}
.work-assessment-facts dt,
.work-assessment-facts details { font-size: .8125rem; color: rgba(var(--v-theme-on-surface), .74); }
.work-assessment-facts details { margin-block-start: .375rem; }
.work-assessment-facts summary { cursor: pointer; }
.work-assessment-facts dd,
.work-assessment-facts p { overflow-wrap: anywhere; }
</style>
