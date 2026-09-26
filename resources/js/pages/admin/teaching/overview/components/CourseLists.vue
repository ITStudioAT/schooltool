<template>
    <ItsGridBox variant="overview" color="primary" title="Listen" icon="mdi-format-list-bulleted" class="w-100">
        <v-card rounded="xl" variant="tonal" color="primary" class="pa-4">
            <div class="text-overline text-primary mb-1">Option 1</div>
            <div class="text-h6 mb-2">Schülerliste</div>
            <p class="text-body-2 mb-3">Vorname, Nachname, E-Mail, Kurs und Klasse als CSV-Datei herunterladen.</p>

            <div class="d-flex justify-end">
                <v-btn
                    color="primary"
                    variant="flat"
                    prepend-icon="mdi-download"
                    :disabled="!selected_course?.id || !activeStudents.length"
                    @click="downloadStudentList">
                    Liste erstellen
                </v-btn>
            </div>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { mapWritableState } from 'pinia'
import { applicationDate } from '@/helpers/date'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

function csvCell(value) {
    const text = String(value ?? '').trim()
    const safeText = /^[=+\-@\t\r\n]/.test(text) ? `'${text}` : text

    return `"${safeText.replaceAll('"', '""')}"`
}

export default {
    components: { ItsGridBox },
    computed: {
        ...mapWritableState(useCourseStore, ['selected_course']),
        courseName() {
            return String(this.selected_course?.title || `Kurs ${this.selected_course?.id}`)
        },
        activeStudents() {
            const students = Array.isArray(this.selected_course?.students_info) ? this.selected_course.students_info : []

            return students
                .filter((student) => student?.course_student_id && !student.canceled_at && !student.deleted_at)
                .sort((first, second) => String(first.last_name || '').localeCompare(String(second.last_name || ''), 'de')
                    || String(first.first_name || '').localeCompare(String(second.first_name || ''), 'de'))
        },
    },
    methods: {
        studentListCsv() {
            const rows = [
                ['Vorname', 'Nachname', 'E-Mail', 'Kurs', 'Klasse'],
                ...this.activeStudents.map((student) => [
                    student.first_name,
                    student.last_name,
                    student.email,
                    this.courseName,
                    student.schoolclass || student.class,
                ]),
            ]

            return `\uFEFF${rows.map((row) => row.map(csvCell).join(',')).join('\r\n')}\r\n`
        },
        studentListFilename() {
            const courseTitle = this.courseName
                .replace(/ß/g, 'ss')
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-zA-Z0-9]+/g, '-')
                .replace(/^-|-$/g, '')
                .toLowerCase()
                .slice(0, 60)
                .replace(/-$/g, '') || `kurs-${this.selected_course?.id}`

            return `schuelerliste-${courseTitle}-${applicationDate()}.csv`
        },
        downloadStudentList() {
            if (!this.selected_course?.id || !this.activeStudents.length) return

            const blob = new Blob([this.studentListCsv()], { type: 'text/csv;charset=utf-8' })
            const url = URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = url
            link.download = this.studentListFilename()
            document.body.appendChild(link)
            link.click()
            link.remove()
            window.setTimeout(() => URL.revokeObjectURL(url), 1000)
        },
    },
}
</script>
