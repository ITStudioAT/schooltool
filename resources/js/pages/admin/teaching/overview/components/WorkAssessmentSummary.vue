<template>
    <section v-if="rows.length || folderPath" class="work-assessment-facts" aria-label="Prüfungsdetails">
        <h3>Leistungsfeststellung</h3>
        <dl v-if="rows.length">
            <div v-for="row in rows" :key="row.label">
                <dt>{{ row.label }}</dt>
                <dd class="font-weight-medium">
                    <WorkPointScale v-if="row.workPoints" :assessment-points="row.value" :work-points="row.workPoints" />
                    <template v-else>{{ row.value }}</template>
                    <span v-if="row.workPoints" class="text-caption text-medium-emphasis d-block">Wert in Schooltool</span>
                </dd>
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
import { workLinkedMcTest } from '@/helpers/workOverview'
import WorkPointScale from './WorkPointScale.vue'

const props = defineProps({
    work: { type: Object, required: true },
    course: Object,
    date: String,
    assessment: Object,
    canAddTest: Boolean,
    disabled: Boolean,
})
defineEmits(['add-test'])
const numberFormat = new Intl.NumberFormat('de-AT', { maximumFractionDigits: 2 })
const folderPath = computed(() => props.assessment?.status === 'found' ? props.assessment.folder?.path : null)

function pointsLabel(value) {
    return `${numberFormat.format(value)} ${value === 1 ? 'Punkt' : 'Punkte'}`
}

const rows = computed(() => {
    const assessment = props.assessment
    const preparation = assessment?.status === 'found' && assessment.preparation?.status === 'ready' ? assessment.preparation : null
    const test = workLinkedMcTest(props.work, props.date)
    const assignmentReady = assessment?.assignmentStatus === 'ready'
    if (!preparation && !test && !assignmentReady) return []

    const assignment = preparation?.components?.aufgabenblatt
    const folderMc = preparation?.components?.multiple_choice
    const mcPoints = folderMc?.included ? (folderMc.points ?? test?.points) : test?.points
    const facts = []
    if (assignment?.included || Number.isFinite(assignment?.points) || assignmentReady) {
        facts.push({ label: 'Aufgabenblatt', value: Number.isFinite(assignment?.points) ? pointsLabel(assignment.points) : 'Punkte offen' })
    }
    if (test || folderMc?.included) {
        const details = [
            test?.question_count && `${test.question_count} ${test.question_count === 1 ? 'Frage' : 'Fragen'}`,
            test?.duration_seconds && `${numberFormat.format(test.duration_seconds / 60)} ${test.duration_seconds === 60 ? 'Minute' : 'Minuten'}`,
        ].filter(Boolean)
        facts.push({ label: 'Multiple-Choice-Test', value: Number.isFinite(mcPoints) ? pointsLabel(mcPoints) : 'Punkte offen', details })
    } else if (folderMc?.included === false) {
        facts.push({ label: 'Multiple-Choice-Test', value: 'Keiner', addTest: true })
    } else if (assignmentReady && props.canAddTest) {
        facts.push({ label: 'Multiple-Choice-Test', value: 'Keiner', addTest: true })
    }

    const maximum = Number(props.course?.teaching_entry_area?.entry_definitions?.find(
        entry => entry.category === 'Benotung' && entry.short_name === props.work.type,
    )?.maximum_points)
    const workPoints = Number.isFinite(maximum) && maximum > 0 ? pointsLabel(maximum) : null
    let total = preparation?.total_points
    if (Number.isFinite(total) && test && !folderMc?.included) {
        total = Number.isFinite(mcPoints) ? total + mcPoints : null
    }
    if (Number.isFinite(total)) {
        facts.push({ label: 'Gesamtpunkte', value: pointsLabel(total), workPoints })
    } else if (facts.length) {
        facts.push({ label: 'Gesamtpunkte', value: 'Noch offen' })
        if (workPoints) facts.push({ label: 'Wert in Schooltool', value: workPoints })
    }
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
.work-assessment-facts dt,
.work-assessment-facts details { font-size: .8125rem; color: rgba(var(--v-theme-on-surface), .74); }
.work-assessment-facts details { margin-block-start: .375rem; }
.work-assessment-facts summary { cursor: pointer; }
.work-assessment-facts dd,
.work-assessment-facts p { overflow-wrap: anywhere; }
</style>
