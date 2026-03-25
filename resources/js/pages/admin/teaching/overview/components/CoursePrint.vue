<template>
    <ItsGridBox variant="overview" color="primary" title="Druck" icon="mdi-printer-outline" class="w-100">
        <v-card rounded="xl" variant="tonal" color="primary" class="pa-4">
            <div class="text-overline text-primary mb-1">Option 1</div>
            <div class="text-h6 mb-3">Leistungen drucken</div>

            <v-radio-group v-model="print_scope" inline color="primary" hide-details class="mb-3">
                <v-radio label="Alle Schüler:innen" value="all" />
                <v-radio label="Eine Schüler:in" value="single" />
            </v-radio-group>

            <v-select
                v-if="print_scope === 'single'"
                v-model="selected_course_student_id"
                label="Schüler:in auswählen"
                :items="studentOptions"
                item-title="title"
                item-value="value"
                variant="outlined"
                class="mb-3" />

            <div class="d-flex justify-end">
                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-printer-outline"
                    :disabled="!canPrint"
                    @click="downloadPerformancesPdf">
                    Leistungen drucken
                </v-btn>
            </div>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },
    data() {
        return {
            print_scope: 'all',
            selected_course_student_id: null,
        }
    },
    computed: {
        ...mapWritableState(useCourseStore, ['selected_course']),
        studentOptions() {
            const students = Array.isArray(this.selected_course?.students_info) ? this.selected_course.students_info : []
            return students
                .filter((student) => !!student?.course_student_id)
                .map((student) => ({
                    title: `${student.last_name}, ${student.first_name}${student.schoolclass || student.class ? ` (${student.schoolclass || student.class})` : ''}`,
                    value: student.course_student_id,
                }))
        },
        canPrint() {
            if (!this.selected_course?.id) {
                return false
            }

            if (this.print_scope === 'single') {
                return !!this.selected_course_student_id
            }

            return this.studentOptions.length > 0
        },
    },
    watch: {
        print_scope(value) {
            if (value === 'all') {
                this.selected_course_student_id = null
                return
            }

            if (!this.selected_course_student_id) {
                this.selected_course_student_id = this.studentOptions[0]?.value ?? null
            }
        },
        studentOptions: {
            immediate: true,
            handler(options) {
                if (this.print_scope === 'single' && !this.selected_course_student_id) {
                    this.selected_course_student_id = options[0]?.value ?? null
                }
            },
        },
    },
    methods: {
        performancesPdfUrl() {
            const courseId = this.selected_course?.id
            if (!courseId) {
                return null
            }

            const params = new URLSearchParams()
            if (this.print_scope === 'single' && this.selected_course_student_id) {
                params.set('course_student_id', String(this.selected_course_student_id))
            }

            const query = params.toString()

            return `/api/admin/teaching/courses/${courseId}/performances_pdf${query ? `?${query}` : ''}`
        },
        downloadPerformancesPdf() {
            const url = this.performancesPdfUrl()
            if (!url || !this.canPrint) {
                return
            }

            window.open(url, '_blank', 'noopener')
        },
    },
}
</script>
