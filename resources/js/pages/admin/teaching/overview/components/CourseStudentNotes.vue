<template>
    <v-dialog v-model="isOpen" persistent max-width="700" scrollable>
        <v-card v-if="student">
            <v-card-title class="d-flex align-center ga-2 flex-wrap">
                <div class="d-flex flex-column align-start">
                    <span>{{ student.last_name }}, {{ student.first_name }}</span>
                    <v-btn v-if="studentEmail" variant="text" size="small" class="text-none px-0"
                        append-icon="mdi-content-copy" :aria-label="`E-Mail-Adresse kopieren: ${studentEmail}`"
                        data-testid="student-notes-email" @click="copyStudentEmail">{{ studentEmail }}</v-btn>
                    <span v-if="emailCopyMessage" class="text-caption" role="status">{{ emailCopyMessage }}</span>
                </div>
                <v-spacer />
                <v-btn icon="mdi-close" aria-label="Schließen" variant="text" size="small" :disabled="saving" @click="close" />
            </v-card-title>
            <v-card-text>
                <div class="d-flex flex-wrap ga-2 mb-4" aria-label="Information zum Studierenden auswählen">
                    <v-btn v-for="item in sections" :key="item.value" size="small"
                        :prepend-icon="item.icon" :variant="section === item.value ? 'flat' : 'tonal'"
                        color="primary" :disabled="saving" :aria-pressed="section === item.value"
                        @click="selectSection(item.value)">{{ item.title }}</v-btn>
                </div>
                <v-alert v-if="error" type="error" variant="tonal" class="mb-3" role="alert">{{ error }}</v-alert>
                <v-alert v-if="success" type="success" variant="tonal" class="mb-3" role="status">{{ success }}</v-alert>

                <section v-if="section === 'comment'">
                    <h3 class="text-subtitle-1 mb-2">Kommentar zum Studierenden</h3>
                    <ItsRichTextEditor v-model="comment" :disabled="saving" />
                </section>
                <section v-if="section === 'special'">
                    <h3 class="text-subtitle-1 mb-2">Besondere Informationen</h3>
                    <p class="text-body-2 mb-3">Vertrauliche Hinweise, beispielsweise vom Klassenvorstand. Bitte nur schulisch notwendige Informationen festhalten. In der Übersicht erscheint nur eine Kennzeichnung, nicht der Inhalt.</p>
                    <v-progress-linear v-if="specialLoading" indeterminate class="mb-3" />
                    <v-btn v-if="!specialLoaded && !specialLoading" variant="tonal" class="mb-3" @click="loadSpecialInformation">Erneut laden</v-btn>
                    <v-textarea v-model="specialInformation" label="Besondere Informationen" rows="5" counter="4096"
                        maxlength="4096" :disabled="saving || !specialLoaded" />
                    <p class="text-caption">Zum Entfernen das Feld leeren und speichern. Es wird keine Nachricht versendet.</p>
                </section>
                <section v-if="section === 'reminder'">
                    <h3 class="text-subtitle-1 mb-2">Erinnerung</h3>
                    <v-progress-linear v-if="reminderLoading" indeterminate class="mb-3" />
                    <v-alert v-if="reminderUnavailable" type="info" variant="tonal" class="mb-3">{{ reminderUnavailable }}</v-alert>
                    <v-textarea v-model="reminder.description" label="Woran soll erinnert werden?" rows="3"
                        counter="1024" maxlength="1024" :disabled="saving || !!reminderUnavailable" />
                    <v-text-field v-model="reminder.dueDate" label="Erinnern am" type="date" :disabled="saving || !!reminderUnavailable" />
                    <v-text-field v-model="reminder.dueTime" label="Uhrzeit (optional)" type="time" :disabled="saving || !!reminderUnavailable" />
                    <v-checkbox v-model="reminder.emailStudent" label="Schüler/in per E-Mail erinnern"
                        density="compact" hide-details :disabled="saving || !!reminderUnavailable" />
                    <v-checkbox v-model="reminder.emailTeacher" label="Lehrperson/Benutzer per E-Mail erinnern"
                        density="compact" hide-details :disabled="saving || !!reminderUnavailable" />
                    <p class="text-caption mb-3">E-Mails werden zum gewählten Termin an die ausgewählten Empfänger gesendet. Ohne Uhrzeit gilt der Tagesbeginn. Hinweise im Unterricht und freigegebene Browser-Erinnerungen bleiben aktiv.</p>
                    <div v-for="entry in reminders" :key="entry.id" class="text-body-2 mb-2">
                        <v-icon size="16">mdi-bell-outline</v-icon>
                        {{ entry.description || entry.type }}<span v-if="entry.due_date"> · Erinnern am: {{ entry.due_date.slice(0, 10) }}<span v-if="entry.due_time"> um {{ entry.due_time.slice(0, 5) }}</span></span>
                        <span v-if="entry.done_date"> · Erledigt</span>
                        <v-btn icon="mdi-delete-outline" color="error" variant="text" size="x-small"
                            aria-label="Erinnerung entfernen" title="Erinnerung entfernen" :disabled="saving"
                            @click="removeEntry('reminder', entry)" />
                    </div>
                </section>
                <section v-if="section === 'star'">
                    <h3 class="text-subtitle-1 mb-2">Star für besondere Leistungen</h3>
                    <v-textarea v-model="starReason" label="Wofür erhält der Schüler / die Schülerin den Stern?"
                        rows="3" counter="1024" maxlength="1024" :disabled="saving" />
                    <v-text-field v-model="starDate" label="Datum" type="date" :disabled="saving" />
                    <div v-for="(star, index) in student.stars || []" :key="star.id || index" class="text-body-2 mb-2">
                        <v-icon color="amber-darken-2" size="18">mdi-star</v-icon>
                        {{ star.comment }} · {{ star.date }}
                        <v-btn icon="mdi-delete-outline" color="error" variant="text" size="x-small"
                            aria-label="Stern entfernen" title="Stern entfernen" :disabled="saving"
                            @click="removeEntry('star', star, index)" />
                    </div>
                </section>
            </v-card-text>
            <v-card-actions>
                <v-btn :disabled="saving" variant="text" @click="close">Schließen</v-btn>
                <v-spacer />
                <v-btn color="primary" variant="flat" :loading="saving" :disabled="!canSave" @click="save">
                    {{ section === 'star' ? 'Stern vergeben' : section === 'reminder' ? 'Erinnerung speichern' : 'Speichern' }}
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>

<script>
import axios from 'axios'
import { defineAsyncComponent } from 'vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'

function today() {
    const date = new Date()
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export default {
    components: { ItsRichTextEditor: defineAsyncComponent(() => import('@/components/ItsRichTextEditor.vue')) },
    props: { course: { type: Object, default: null } },
    data() {
        return {
            isOpen: false,
            selectedStudent: null,
            courseId: null,
            section: 'comment',
            saving: false,
            error: '',
            success: '',
            emailCopyMessage: '',
            comment: '',
            specialInformation: '',
            specialLoaded: false,
            specialLoading: false,
            reminderLoading: false,
            requestVersion: 0,
            starReason: '',
            starDate: '',
            reminder: { description: '', dueDate: '', dueTime: '', emailStudent: false, emailTeacher: true },
            sections: [
                { value: 'comment', title: 'Kommentar', icon: 'mdi-comment-text-outline' },
                { value: 'reminder', title: 'Erinnerung', icon: 'mdi-bell-outline' },
                { value: 'star', title: 'Star', icon: 'mdi-star-outline' },
                { value: 'special', title: 'Besondere Informationen', icon: 'mdi-information-outline' },
            ],
        }
    },
    computed: {
        studentEmail() {
            return typeof this.student?.email === 'string' ? this.student.email.trim() : ''
        },
        student() {
            return this.course?.students_info?.find((student) => String(student.id) === String(this.selectedStudent?.id))
                || this.selectedStudent
        },
        reminderUnavailable() {
            if (!this.student?.user_id) return 'Erinnerungen sind erst verfügbar, wenn ein Benutzerkonto zugeordnet ist.'
            return ''
        },
        reminders() {
            return useCourseBehaviourEntryStore().courseEntries.filter((entry) =>
                String(entry.teaching_course_id) === String(this.courseId)
                && String(entry.user_id) === String(this.student?.user_id) && entry.kind === 'notification',
            )
        },
        canSave() {
            if (this.saving || !this.student || !this.isOpen) return false
            if (this.section === 'special') return this.specialLoaded && !this.specialLoading && this.specialInformation.length <= 4096
            if (this.section === 'reminder') return !this.reminderLoading && !this.reminderUnavailable && !!this.reminder.dueDate
                && !!this.reminder.description.trim() && this.reminder.description.length <= 1024
            if (this.section === 'star') return !!this.starReason.trim() && this.starReason.length <= 1024 && !!this.starDate
            return true
        },
    },
    watch: {
        'course.id'() {
            this.reset()
        },
    },
    beforeUnmount() {
        this.reset()
    },
    methods: {
        async copyStudentEmail() {
            if (!this.studentEmail) return
            const version = this.requestVersion
            this.emailCopyMessage = ''
            try {
                await navigator.clipboard.writeText(this.studentEmail)
                if (version === this.requestVersion) this.emailCopyMessage = 'E-Mail-Adresse kopiert.'
            } catch {
                if (version === this.requestVersion) this.emailCopyMessage = 'E-Mail-Adresse konnte nicht kopiert werden.'
            }
        },
        open(student, section = 'comment') {
            if (this.saving || !this.course?.id || !student) return
            this.reset()
            this.courseId = this.course.id
            this.selectedStudent = { ...student }
            this.comment = student.comment || ''
            this.starReason = ''
            this.starDate = today()
            this.reminder = { description: '', dueDate: today(), dueTime: '', emailStudent: false, emailTeacher: true }
            this.isOpen = true
            this.selectSection(section)
        },
        reset() {
            this.requestVersion++
            this.emailCopyMessage = ''
            this.isOpen = false
            this.selectedStudent = null
            this.specialInformation = ''
            this.specialLoaded = false
            this.specialLoading = false
            this.reminderLoading = false
            this.error = ''
            this.success = ''
        },
        close() {
            if (!this.saving) this.reset()
        },
        async selectSection(section) {
            this.section = section
            this.error = ''
            this.success = ''
            if (section === 'special' && !this.specialLoaded && !this.specialLoading) await this.loadSpecialInformation()
            if (section === 'reminder' && !this.reminderLoading) {
                const version = this.requestVersion
                this.reminderLoading = true
                try {
                    const result = await useCourseBehaviourEntryStore().indexByCourse(this.courseId)
                    if (!result && version === this.requestVersion) this.error = 'Bestehende Erinnerungen konnten nicht geladen werden.'
                } finally {
                    if (version === this.requestVersion) this.reminderLoading = false
                }
            }
        },
        specialInformationUrl() {
            return `/api/admin/teaching/courses/${this.courseId}/students/${this.student.course_student_id}/special-information`
        },
        async loadSpecialInformation() {
            if (!this.student?.course_student_id) {
                this.error = 'Bitte den Unterricht neu laden, bevor besondere Informationen bearbeitet werden.'
                return
            }
            const version = this.requestVersion
            this.specialLoading = true
            this.error = ''
            try {
                const response = await axios.get(this.specialInformationUrl())
                if (version !== this.requestVersion) return
                this.specialInformation = response.data.data.special_information || ''
                this.specialLoaded = true
            } catch {
                if (version === this.requestVersion) this.error = 'Besondere Informationen konnten nicht geladen werden.'
            } finally {
                if (version === this.requestVersion) this.specialLoading = false
            }
        },
        async removeEntry(section, entry, index) {
            if (this.saving || !this.isOpen || !this.student) return
            if (section === 'reminder' && !this.reminders.includes(entry)) return
            if (section === 'star' && this.student.stars?.[index] !== entry) return
            if (!['reminder', 'star'].includes(section)) return
            this.saving = true
            this.error = ''
            this.success = ''
            const version = this.requestVersion
            try {
                const result = section === 'reminder'
                    ? await useCourseBehaviourEntryStore().destroy(entry.id)
                    : await useCourseStore().updateStudentMetadata(this.courseId, this.student.id, {
                        stars: this.student.stars.filter((star, starIndex) => starIndex !== index),
                    })
                if (version !== this.requestVersion) return
                if (!result) {
                    this.error = 'Entfernen fehlgeschlagen. Der Eintrag bleibt erhalten.'
                    return
                }
                this.success = section === 'star' ? 'Stern entfernt.' : 'Erinnerung entfernt.'
            } catch {
                if (version === this.requestVersion) this.error = 'Entfernen fehlgeschlagen. Der Eintrag bleibt erhalten.'
            } finally {
                this.saving = false
            }
        },
        async save() {
            if (!this.canSave) return
            this.saving = true
            this.error = ''
            this.success = ''
            const version = this.requestVersion
            const courseId = this.courseId
            const student = this.student
            const section = this.section
            const courseStore = useCourseStore()
            try {
                let result
                if (section === 'special') {
                    const response = await axios.put(this.specialInformationUrl(), { special_information: this.specialInformation.trim() })
                    result = response.data
                    courseStore.applyStudentMetadata(courseId, student.id, { has_special_information: response.data.data.has_special_information })
                } else if (section === 'reminder') {
                    result = await useCourseBehaviourEntryStore().store({
                        teaching_course_id: courseId,
                        user_id: student.user_id,
                        kind: 'notification',
                        description: this.reminder.description.trim(),
                        date: today(),
                        is_due: !!this.reminder.dueDate,
                        due_date: this.reminder.dueDate || null,
                        due_time: this.reminder.dueTime || null,
                        remind_student_by_email: this.reminder.emailStudent === true,
                        remind_teacher_by_email: this.reminder.emailTeacher !== false,
                        is_done: false,
                        done_date: null,
                    })
                } else {
                    const changes = section === 'star'
                        ? { stars: [...(student.stars || []), { id: crypto.randomUUID(), value: 1, comment: this.starReason.trim(), date: this.starDate }] }
                        : { comment: this.comment }
                    result = await courseStore.updateStudentMetadata(courseId, student.id, changes)
                }
                if (version !== this.requestVersion) return
                if (!result) {
                    this.error = 'Speichern fehlgeschlagen. Deine Eingabe bleibt erhalten.'
                    return
                }
                if (section === 'star') this.starReason = ''
                if (section === 'reminder') this.reminder = { description: '', dueDate: today(), dueTime: '', emailStudent: false, emailTeacher: true }
                this.reset()
            } catch {
                if (version === this.requestVersion) this.error = 'Speichern fehlgeschlagen. Deine Eingabe bleibt erhalten.'
            } finally {
                this.saving = false
            }
        },
    },
}
</script>
