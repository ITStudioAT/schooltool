<template>
    <span v-if="starsOnly" class="student-star-badges d-inline-flex flex-nowrap flex-shrink-0 align-center ga-1">
        <CourseStudentHoverDetails v-if="student.stars?.length || showEmptyStars" title="Sterne für besondere Leistungen" details-label="Vergebene Sterne">
            <template #activator="{ props: activatorProps }">
                <v-chip v-bind="activatorProps" size="x-small" color="amber-darken-2" variant="tonal" tabindex="0"
                    :aria-label="`${student.stars?.length || 0} Sterne für besondere Leistungen`"
                    @click.stop="$emit('select', 'star')">
                    <v-icon v-for="(star, index) in student.stars" :key="star.id || index" size="14" icon="mdi-star" />
                    <template v-if="!student.stars?.length"><v-icon size="14" icon="mdi-star-outline" /><span class="ml-1">0</span></template>
                </v-chip>
            </template>
            <div v-for="(star, index) in student.stars" :key="star.id || index" class="student-star-detail">
                <strong>{{ starDateLabel(star.date) }}</strong>
                <div class="student-comment-preview">{{ star.comment }}</div>
            </div>
            <div v-if="!student.stars?.length">Keine Sterne im ausgewählten Zeitraum.</div>
        </CourseStudentHoverDetails>
    </span>
    <span v-else class="student-indicators d-inline-flex align-center ga-1">
        <template v-for="indicator in indicators" :key="indicator.section">
            <CourseStudentHoverDetails v-if="indicator.section === 'comment'" title="Kommentar" :open-delay="0">
                <template #activator="{ props: activatorProps }">
                    <v-btn v-bind="activatorProps" :color="indicator.color" size="x-small" variant="tonal"
                        class="student-indicator" :aria-label="indicator.label" @click.stop="$emit('select', 'comment')">
                        <v-icon size="15" :icon="indicator.icon" />
                    </v-btn>
                </template>
                <div class="student-comment-preview">{{ commentText }}</div>
            </CourseStudentHoverDetails>
            <v-btn v-else :color="indicator.color" size="x-small" variant="tonal" class="student-indicator"
                :aria-label="indicator.label" :title="indicator.label" @click.stop="$emit('select', indicator.section)">
                <v-icon size="15" :icon="indicator.icon" />
                <span v-if="indicator.count" class="ml-1">{{ indicator.count }}</span>
            </v-btn>
        </template>
    </span>
</template>

<script>
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { isInTeachingSemester, teachingDateKey, teachingPerformanceDateScope } from '@/helpers/teachingSemester'
import CourseStudentHoverDetails from './CourseStudentHoverDetails.vue'

export default {
    components: { CourseStudentHoverDetails },
    props: {
        student: { type: Object, required: true },
        courseId: { type: [Number, String], required: true },
        starsOnly: { type: Boolean, default: false },
        showEmptyStars: { type: Boolean, default: false },
        activeSemester: { type: Number, default: 3 },
        semesterTwoStartDate: { type: String, default: null },
        schoolyear: { type: Object, default: null },
    },
    emits: ['select'],
    methods: {
        starDateLabel(value) {
            const date = teachingDateKey(value)
            return date ? date.split('-').reverse().join('.') : 'Datum nicht angegeben'
        },
    },
    computed: {
        commentText() {
            const comment = String(this.student.comment || '')
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<\/(?:p|div|li|h[1-6])>/gi, '\n')
            const document = new DOMParser().parseFromString(comment, 'text/html')
            document.querySelectorAll('script, style').forEach((element) => element.remove())

            return (document.body?.textContent || '').replace(/\u00a0/g, ' ').trim()
        },
        indicators() {
            const indicators = []
            const reminders = useCourseBehaviourEntryStore().courseEntries.filter((entry) =>
                this.student.user_id && String(entry.teaching_course_id) === String(this.courseId)
                && String(entry.user_id) === String(this.student.user_id) && entry.kind === 'notification' && !entry.done_date,
            ).filter((entry) => this.schoolyear
                ? teachingPerformanceDateScope(entry.date, this.activeSemester, this.semesterTwoStartDate, this.schoolyear) === 'included'
                : isInTeachingSemester(entry.date, this.activeSemester, this.semesterTwoStartDate)
            ).length
            if (reminders) indicators.push({ section: 'reminder', icon: 'mdi-bell-alert-outline', color: 'orange-darken-3', count: reminders, label: `${reminders} offene Erinnerung${reminders === 1 ? '' : 'en'}` })
            if (this.commentText) {
                indicators.push({ section: 'comment', icon: 'mdi-comment-text-outline', color: 'blue-darken-2', label: 'Kommentar vorhanden' })
            }
            if (this.student.has_special_information) {
                indicators.push({ section: 'special', icon: 'mdi-shield-alert-outline', color: 'deep-purple', label: 'Vertrauliche besondere Informationen vorhanden' })
            }
            return indicators
        },
    },
}
</script>

<style scoped>
.student-star-badges {
    transform: translateY(-7px);
}

.student-indicator {
    min-width: 26px;
    padding-inline: 5px;
}

.student-comment-preview {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.5;
    font-size: 0.875rem;
}

.student-star-detail + .student-star-detail {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
</style>
