<template>
    <span class="student-indicators d-inline-flex align-center ga-1">
        <v-btn v-for="indicator in indicators" :key="indicator.section" :color="indicator.color"
            size="x-small" variant="tonal" class="student-indicator" :aria-label="indicator.label"
            :title="indicator.label" @click.stop="$emit('select', indicator.section)">
            <v-icon size="15" :icon="indicator.icon" />
            <span v-if="indicator.count" class="ml-1">{{ indicator.count }}</span>
        </v-btn>
    </span>
</template>

<script>
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'

export default {
    props: {
        student: { type: Object, required: true },
        courseId: { type: [Number, String], required: true },
    },
    emits: ['select'],
    computed: {
        indicators() {
            const indicators = []
            const stars = this.student.stars?.length || 0
            if (stars) indicators.push({ section: 'star', icon: 'mdi-star', color: 'amber-darken-2', count: stars, label: `${stars} Star${stars === 1 ? '' : 's'} für besondere Leistungen` })
            const reminders = useCourseBehaviourEntryStore().courseEntries.filter((entry) =>
                this.student.user_id && String(entry.teaching_course_id) === String(this.courseId)
                && String(entry.user_id) === String(this.student.user_id) && entry.kind === 'notification' && !entry.done_date,
            ).length
            if (reminders) indicators.push({ section: 'reminder', icon: 'mdi-bell-alert-outline', color: 'orange-darken-3', count: reminders, label: `${reminders} offene Erinnerung${reminders === 1 ? '' : 'en'}` })
            if (this.student.comment?.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim()) {
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
.student-indicator {
    min-width: 26px;
    padding-inline: 5px;
}
</style>
