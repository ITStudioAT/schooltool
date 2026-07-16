<template>
    <ItsGridBox
        variant="overview"
        color="primary"
        :title="selected_course.title + ' (' + selectedCourseClasses + ')'"
        icon="mdi-information-box"
        class="w-100"
        v-if="selected_course"
        :disabled="isInfoLocked">
        <template #header-actions>
            <v-btn v-if="action !== 'edit_description'" icon="mdi-pencil" size="small" variant="tonal" :disabled="isSavingInfo" @click="editDescription" />
        </template>
        <v-card tile flat color="transparent" class="w-100" :disabled="isSavingInfo">
            <v-card-text class="text-body-1 d-flex flex-column ga-2" v-if="action != 'edit_description'">
                <div class="course-infos-grid mt-2">
                    <v-card variant="outlined" v-if="openNotifications.length" class="course-info-card">
                        <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                            <v-icon size="18">mdi-bell-alert</v-icon>
                            Offene Verständigungen
                            <v-chip size="x-small" color="warning" variant="flat">{{ openNotifications.length }}</v-chip>
                        </v-card-title>
                        <v-divider />
                        <v-card-text class="pa-0">
                            <v-list density="compact" class="open-notifications-list">
                                <v-list-item v-for="entry in openNotifications" :key="entry.id" class="open-notification-item">
                                    <div class="notification-row open-notification-block d-flex flex-wrap align-start ga-2 w-100" :class="notificationEntryBackgroundClass(entry)">
                                        <div class="open-notification-student-name">
                                            {{ studentLabel(entry.user_id) }}
                                        </div>
                                        <v-chip v-if="entry.date" size="x-small" variant="tonal" color="primary">{{ formatDate(entry.date) }}</v-chip>
                                        <v-chip v-if="entry.due_date" size="x-small" variant="tonal" :color="dueDateColor(entry.due_date)">Fällig bis {{ formatDate(entry.due_date) }}</v-chip>
                                        <v-chip v-if="entry.type" size="x-small" variant="outlined" color="secondary" class="chip-truncate">{{ notificationTypeLabel(entry.type) }}</v-chip>
                                        <v-btn
                                            icon="mdi-check"
                                            size="x-small"
                                            color="success"
                                            variant="tonal"
                                            :disabled="isSavingInfo"
                                            :loading="saving_notification_id === entry.id"
                                            class="notification-action"
                                            @click.stop="completeNotification(entry)" />
                                        <div v-if="entry.description" class="notification-description text-caption w-100">{{ entry.description }}</div>
                                    </div>
                                </v-list-item>
                                <v-list-item v-if="!openNotifications.length">
                                    <v-list-item-title class="text-caption text-medium-emphasis">Keine offenen Verständigungen.</v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </v-card>

                    <v-card variant="outlined" class="course-info-card course-info-summary-card">
                        <v-card-text class="course-info-summary-card__content">
                            <div class="course-info-block course-info-block--countdown">
                                <div class="text-caption text-medium-emphasis">{{ representativeCountdownLabel }}</div>
                                <div :class="representativeCountdownValueClass">{{ representativeCountdownValue }}</div>
                            </div>
                            <div class="course-info-block course-info-block--schema">
                                <div class="text-caption text-medium-emphasis">Benotungsschema</div>
                                <div class="text-body-2">{{ schemaName }}</div>
                            </div>
                            <div v-if="selected_course.description" class="course-info-block course-info-block--description">
                                <div class="text-caption text-medium-emphasis mb-1">Fachinfos</div>
                                <div class="text-body-2 course-description" v-html="descriptionHtml"></div>
                            </div>
                        </v-card-text>
                    </v-card>
                </div>

                <v-card variant="outlined" class="mt-3 course-student-display-card">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-account-eye-outline</v-icon>
                        Schüler:innen-Anzeige
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="d-flex flex-wrap ga-3 py-2">
                        <v-checkbox
                            :model-value="student_display_show_age"
                            label="Alter"
                            density="compact"
                            hide-details
                            :disabled="isSavingInfo"
                            @update:model-value="saveStudentDisplaySetting('teaching_show_student_age', $event)" />
                        <v-checkbox
                            :model-value="student_display_show_last_login"
                            label="Last Login"
                            density="compact"
                            hide-details
                            :disabled="isSavingInfo"
                            @update:model-value="saveStudentDisplaySetting('teaching_show_student_last_login', $event)" />
                    </v-card-text>
                </v-card>

                <v-card variant="outlined" class="mt-3 course-infos-grades-card">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2 flex-wrap">
                        <v-icon size="18">mdi-calculator</v-icon>
                        Berechnete Noten
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pt-2">
                        <div class="d-flex flex-wrap ga-3 mb-2">
                            <v-checkbox
                                v-model="infos_show_grade_sem1"
                                :label="hasTwoSemesters ? '1. Semester' : 'Gesamt'"
                                density="compact"
                                hide-details
                                :disabled="isSavingInfo"
                                class="grade-checkbox" />
                            <v-checkbox
                                v-if="hasTwoSemesters"
                                v-model="infos_show_grade_sem2"
                                label="2. Semester"
                                density="compact"
                                hide-details
                                :disabled="isSavingInfo"
                                class="grade-checkbox" />
                            <v-checkbox
                                v-if="hasTwoSemesters"
                                v-model="infos_show_grade_year"
                                label="Gesamt (1+2)"
                                density="compact"
                                hide-details
                                :disabled="isSavingInfo"
                                class="grade-checkbox" />
                        </div>
                        <v-divider class="my-3" />
                        <div class="text-caption text-medium-emphasis mb-2">Für Schüler:innen sichtbar</div>
                        <div class="d-flex flex-wrap ga-3 mb-2">
                            <v-checkbox
                                v-model="student_grade_visibility_show_sem1"
                                :label="hasTwoSemesters ? '1. Semester' : 'Gesamt'"
                                density="compact"
                                hide-details
                                :disabled="isSavingInfo"
                                class="grade-checkbox" />
                            <v-checkbox
                                v-if="hasTwoSemesters"
                                v-model="student_grade_visibility_show_sem2"
                                label="2. Semester"
                                density="compact"
                                hide-details
                                :disabled="isSavingInfo"
                                class="grade-checkbox" />
                            <v-checkbox
                                v-if="hasTwoSemesters"
                                v-model="student_grade_visibility_show_year"
                                label="Gesamt (1+2)"
                                density="compact"
                                hide-details
                                :disabled="isSavingInfo"
                                class="grade-checkbox" />
                        </div>
                        <div class="text-caption text-medium-emphasis mb-2">
                            Diese Auswahl steuert ausschließlich die Berechnungen im Schüler:innen-Bereich.
                        </div>
                        <v-alert v-if="gradesLoading" type="info" variant="tonal" density="compact" class="mb-0">
                            Noten werden geladen…
                        </v-alert>
                        <div v-else-if="anyGradeColumnVisible && gradeRows.length" class="grades-table-wrap">
                            <table class="grades-table">
                                <thead>
                                    <tr>
                                        <th class="grades-student-col">Schüler:in</th>
                                        <th v-if="infos_show_grade_sem1" class="grades-grade-col">{{ hasTwoSemesters ? 'Sem 1' : 'Gesamt' }}</th>
                                        <th v-if="hasTwoSemesters && infos_show_grade_sem2" class="grades-grade-col">Sem 2</th>
                                        <th v-if="hasTwoSemesters && infos_show_grade_year" class="grades-grade-col">1+2</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in gradeRows" :key="row.student_id" :class="{ 'row--canceled': row.is_canceled }">
                                        <td class="grades-student-cell" :class="{ 'grades-student-cell--canceled': row.is_canceled }">{{ row.label }}</td>
                                        <td v-if="infos_show_grade_sem1" class="grades-grade-cell" :class="gradeClass(row.grades.sem1)">{{ formatGrade(row.grades.sem1) }}</td>
                                        <td v-if="hasTwoSemesters && infos_show_grade_sem2" class="grades-grade-cell" :class="gradeClass(row.grades.sem2)">{{ formatGrade(row.grades.sem2) }}</td>
                                        <td v-if="hasTwoSemesters && infos_show_grade_year" class="grades-grade-cell" :class="gradeClass(row.grades.year)">{{ formatGrade(row.grades.year) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else-if="anyGradeColumnVisible && gradesDataLoaded && !gradeRows.length" class="text-caption text-medium-emphasis">
                            Keine Schüler:innen im Kurs.
                        </div>
                    </v-card-text>
                </v-card>

            </v-card-text>
            <v-card-text v-if="action == 'edit_description'">
                <v-form ref="form" @submit.prevent="saveDescription">
                    <div class="mb-4">
                        <label class="text-caption text-medium-emphasis">Fachinfos</label>
                        <its-rich-text-editor v-model="edit_description" :disabled="isSavingDescription" />
                    </div>

                    <div class="d-flex flex-row align-center justify-space-between mt-4">
                        <v-btn color="warning" flat tile :disabled="isSavingDescription" @click="abortEditDescription">Abbruch</v-btn>
                        <v-btn color="success" flat tile type="submit" :loading="isSavingDescription" :disabled="isSavingDescription">Speichern</v-btn>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { parseLocalDate } from '@/helpers/date'
import { computeStudentGrades, formatGrade, gradeClass } from '@/helpers/gradeCalculation'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'
import { useCourseStudentEntryStore } from '@/stores/admin/teaching/CourseStudentEntryStore'
import { useCourseWorkStore } from '@/stores/admin/teaching/CourseWorkStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton, ItsRichTextEditor },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.courseStore = useCourseStore()
        this.behaviourEntryStore = useCourseBehaviourEntryStore()
        this.entryStore = useCourseStudentEntryStore()
        this.courseWorkStore = useCourseWorkStore()
        this.schoolHourStore = useSchoolHourStore()
        this.teachingStore = useTeachingStore()
        if (!Array.isArray(this.school_hours) || this.school_hours.length === 0) {
            await this.schoolHourStore.index()
        }
        if (this.selected_course?.id) {
            await this.behaviourEntryStore.indexByCourse(this.selected_course.id)
        }
        this.restoreStudentDisplaySettings(this.selected_course)
    },

    async mounted() {
        const routeGrades = this.normalizeGradeQueryValue(this.$route?.query?.grades)

        if (routeGrades !== '') {
            this.restoreGradeColumnsFromRoute(routeGrades)
        } else {
            this.restoreGradeColumns(this.persistedGradeColumns)
        }

        this.restoreStudentGradeColumns(this.persistedStudentGradeColumns)

        await this.initializeGradeState()

        this.nowTimer = setInterval(() => {
            this.nowTs = Date.now()
        }, 1000)
    },
    unmounted() {
        if (this.nowTimer) clearInterval(this.nowTimer)
    },

    data() {
        return {
            adminStore: null,
            courseStore: null,
            behaviourEntryStore: null,
            entryStore: null,
            courseWorkStore: null,
            schoolHourStore: null,
            teachingStore: null,
            is_valid: false,
            data: {
                selected_classes: [],
            },
            edit_description: '',
            delete_level: 0,
            nowTs: Date.now(),
            nowTimer: null,
            gradesLoading: false,
            gradeEntries: [],
            gradesDataLoaded: false,
            restoringGradeColumns: false,
            restoringStudentGradeColumns: false,
            saving_info_action: null,
            saving_notification_id: null,
            student_grade_visibility_show_sem1: false,
            student_grade_visibility_show_sem2: false,
            student_grade_visibility_show_year: false,
            student_display_show_age: false,
            student_display_show_last_login: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, [
            'courses',
            'classes',
            'selected_course',
            'show_infos',
            'infos_show_grade_sem1',
            'infos_show_grade_sem2',
            'infos_show_grade_year',
        ]),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),
        isSavingInfo() {
            return this.saving_info_action !== null
        },
        isSavingDescription() {
            return this.saving_info_action === 'save-description'
        },
        isInfoLocked() {
            return this.isSavingInfo || (this.action != '' && this.action != 'edit_description')
        },

        schoolHoursByHour() {
            const entries = Array.isArray(this.school_hours) ? this.school_hours : []

            return entries.reduce((carry, item) => {
                const hour = Number(item?.hour)
                if (!Number.isFinite(hour)) {
                    return carry
                }

                carry[hour] = item
                return carry
            }, {})
        },
        nextCourseStartAt() {
            const now = new Date(this.nowTs)
            const dates = Array.isArray(this.selected_course?.course_dates) ? this.selected_course.course_dates : []
            let nearestStartTs = null

            dates.forEach((courseDate) => {
                const date = (courseDate?.date || '').toString().slice(0, 10)
                const hours = Array.isArray(courseDate?.hours)
                    ? [...courseDate.hours]
                        .map((hour) => Number(hour))
                        .filter((hour) => Number.isFinite(hour))
                        .sort((a, b) => a - b)
                    : []
                if (!hours.length) {
                    return
                }

                const start = this.lessonStartFromHour(date, hours[0])
                if (!start || start.getTime() <= now.getTime()) {
                    return
                }

                if (nearestStartTs === null || start.getTime() < nearestStartTs) {
                    nearestStartTs = start.getTime()
                }
            })

            return nearestStartTs ? new Date(nearestStartTs) : null
        },
        activeCourseEndAt() {
            const now = new Date(this.nowTs)
            const dates = Array.isArray(this.selected_course?.course_dates) ? this.selected_course.course_dates : []
            let nearestEndTs = null

            dates.forEach((courseDate) => {
                const date = (courseDate?.date || '').toString().slice(0, 10)
                const hours = Array.isArray(courseDate?.hours)
                    ? [...courseDate.hours]
                        .map((hour) => Number(hour))
                        .filter((hour) => Number.isFinite(hour))
                        .sort((a, b) => a - b)
                    : []
                if (!hours.length) {
                    return
                }

                const start = this.lessonStartFromHour(date, hours[0])
                const end = this.lessonEndFromHour(date, hours[hours.length - 1])
                if (!start || !end || end.getTime() <= start.getTime()) {
                    return
                }

                if (now.getTime() < start.getTime() || now.getTime() >= end.getTime()) {
                    return
                }

                if (nearestEndTs === null || end.getTime() < nearestEndTs) {
                    nearestEndTs = end.getTime()
                }
            })

            return nearestEndTs ? new Date(nearestEndTs) : null
        },
        startsInLabel() {
            if (!this.nextCourseStartAt) {
                return '–'
            }

            const diffSeconds = Math.max(0, Math.floor((this.nextCourseStartAt.getTime() - this.nowTs) / 1000))
            const days = Math.floor(diffSeconds / 86400)
            const hours = Math.floor((diffSeconds % 86400) / 3600)
            const minutes = Math.floor((diffSeconds % 3600) / 60)

            const parts = []
            if (days > 0) {
                parts.push(`${days}d`)
            }
            if (hours > 0) {
                parts.push(`${this.padTwo(hours)}h`)
            }
            parts.push(`${this.padTwo(minutes)}m`)

            return parts.join(' ')
        },
        endsInLabel() {
            if (!this.activeCourseEndAt) {
                return '–'
            }

            const diffSeconds = Math.max(0, Math.floor((this.activeCourseEndAt.getTime() - this.nowTs) / 1000))
            const hours = Math.floor(diffSeconds / 3600)
            const minutes = Math.floor((diffSeconds % 3600) / 60)

            if (hours > 0) {
                return `${this.padTwo(hours)}h ${this.padTwo(minutes)}m`
            }

            return `${this.padTwo(minutes)}m`
        },
        isCourseActiveNow() {
            return !!this.activeCourseEndAt
        },
        representativeCountdownLabel() {
            return this.isCourseActiveNow ? 'Endet in:' : 'Findet statt in:'
        },
        representativeCountdownValue() {
            return this.isCourseActiveNow ? this.endsInLabel : this.startsInLabel
        },
        representativeCountdownValueClass() {
            if (this.isCourseActiveNow) {
                return 'text-body-1 font-weight-medium text-primary'
            }

            return 'text-body-2 font-weight-medium'
        },
        selectedCourseSchema() {
            const courseSchema = this.selected_course?.teacher_teaching_schema
            if (courseSchema?.id) {
                return courseSchema
            }

            const schemaId = this.selected_course?.teaching_schema_id
            return schemaId ? this.teachingStore?.schemaById(schemaId) : null
        },
        selectedCourseEntryArea() {
            const entryArea = this.selected_course?.teaching_entry_area

            return entryArea?.id ? entryArea : null
        },
        schemaName() {
            if (this.selectedCourseEntryArea) {
                return this.selectedCourseEntryArea.name || 'Kein Schema zugewiesen'
            }

            const schema = this.selectedCourseSchema
            return schema ? schema.name : 'Kein Schema zugewiesen'
        },
        selectedCourseClasses() {
            if (!this.selected_course?.classes?.length) return ''
            // If it's already a string with commas
            if (typeof this.selected_course.classes === 'string') {
                return this.selected_course.classes.replace(/,/g, ', ')
            }
            return this.selected_course.classes.join(', ')
        },

        descriptionHtml() {
            const text = this.selected_course?.description
            if (!text) return ''
            // If already HTML, return as-is
            if (text.includes('<p>') || text.includes('<br')) return text
            // Convert plain text line breaks to HTML paragraphs
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        openNotifications() {
            const entries = this.behaviourEntryStore?.courseEntries || []
            return entries
                .filter((entry) => entry.kind === 'notification' && !!entry.due_date && !entry.done_date)
                .sort((a, b) => {
                    const dueA = a.due_date || '9999-12-31'
                    const dueB = b.due_date || '9999-12-31'
                    if (dueA !== dueB) return dueA.localeCompare(dueB)
                    const dateA = a.date || '9999-12-31'
                    const dateB = b.date || '9999-12-31'
                    return dateA.localeCompare(dateB)
                })
        },
        notificationTypesByShort() {
            const map = new Map()
            const list = this.selected_course?.teacher_teaching_notifications || this.teachingStore?.settings?.teaching_notifications || []
            list.forEach((item) => {
                if (item?.short_name) map.set(item.short_name, item.name || '')
            })
            return map
        },
        gradingSchema() {
            if (this.selectedCourseEntryArea) {
                return null
            }

            return this.selectedCourseSchema
        },
        grading() {
            return this.gradingSchema?.grading || {}
        },
        teachingWorks() {
            return this.gradingSchema?.works || []
        },
        hasTwoSemesters() {
            return Number(this.grading?.semester_count) === 2
        },
        sem2StartDate() {
            return this.config?.selected_schoolyear?.sem_2_start || this.config?.user?.teaching_count_for_semester_2_date || null
        },
        persistedGradeColumns() {
            return this.teachingStore?.settings?.teaching_grade_columns || this.config?.user?.teaching_grade_columns || {
                show_sem1: false,
                show_sem2: false,
                show_year: false,
            }
        },
        persistedStudentGradeColumns() {
            return this.selected_course?.teaching_student_grade_columns
                || this.teachingStore?.settings?.teaching_student_grade_columns
                || this.config?.user?.teaching_student_grade_columns
                || this.defaultGradeColumns()
        },
        anyGradeColumnVisible() {
            return this.infos_show_grade_sem1 || (this.hasTwoSemesters && (this.infos_show_grade_sem2 || this.infos_show_grade_year))
        },
        gradeStudents() {
            const list = Array.isArray(this.selected_course?.students_info) ? [...this.selected_course.students_info] : []
            return list.filter((s) => !!s?.id).sort((a, b) => {
                const canceledA = this.isStudentCanceled(a) ? 1 : 0
                const canceledB = this.isStudentCanceled(b) ? 1 : 0
                if (canceledA !== canceledB) return canceledA - canceledB
                const lastCmp = String(a?.last_name || '').localeCompare(String(b?.last_name || ''), 'de', { sensitivity: 'base' })
                if (lastCmp !== 0) return lastCmp
                return String(a?.first_name || '').localeCompare(String(b?.first_name || ''), 'de', { sensitivity: 'base' })
            })
        },
        gradeEntriesByStudent() {
            const map = {}
            ;(this.gradeEntries || []).forEach((entry) => {
                const uid = String(entry?.user_id || '')
                if (!uid) return
                if (!map[uid]) map[uid] = []
                map[uid].push(entry)
            })
            return map
        },
        gradeRows() {
            if (!this.gradesDataLoaded) return []
            return this.gradeStudents.map((student) => {
                const studentId = String(student.id)
                const studentEntries = this.gradeEntriesByStudent[studentId] || []
                const grades = computeStudentGrades(
                    student,
                    studentEntries,
                    this.teachingWorks,
                    this.grading,
                    this.hasTwoSemesters ? 2 : 1,
                    this.sem2StartDate,
                )
                const last = String(student?.last_name || '').trim()
                const first = String(student?.first_name || '').trim()
                const cls = String(student?.schoolclass || student?.class || '').trim()
                const name = `${last}, ${first}`.replace(/^,\s*/, '').trim() || '–'
                const label = cls ? `${name} (${cls})` : name
                return {
                    student_id: studentId,
                    label,
                    is_canceled: this.isStudentCanceled(student),
                    grades,
                }
            })
        },
    },

    watch: {
        selected_course: {
            async handler(course) {
                if (!course?.id) {
                    if (this.behaviourEntryStore) this.behaviourEntryStore.courseEntries = []
                    this.gradesDataLoaded = false
                    this.gradeEntries = []
                    this.restoreStudentGradeColumns(this.defaultGradeColumns())
                    this.restoreStudentDisplaySettings(null)
                    return
                }
                this.courseStore?.ensureCourseStudentCollections?.(course)
                this.restoreStudentGradeColumns(this.persistedStudentGradeColumns)
                this.restoreStudentDisplaySettings(course)
                await this.behaviourEntryStore?.indexByCourse(course.id)
                if (this.anyGradeColumnVisible) {
                    await this.loadGradeData()
                } else {
                    this.gradesDataLoaded = false
                    this.gradeEntries = []
                }
            },
        },
        anyGradeColumnVisible: {
            async handler(visible) {
                if (visible && !this.gradesDataLoaded && this.selected_course?.id) {
                    await this.loadGradeData()
                }
            },
        },
        infos_show_grade_sem1() {
            if (this.restoringGradeColumns) {
                return
            }
            this.syncGradeColumnsToRoute()
            this.persistGradeColumns()
        },
        infos_show_grade_sem2() {
            if (this.restoringGradeColumns) {
                return
            }
            this.syncGradeColumnsToRoute()
            this.persistGradeColumns()
        },
        infos_show_grade_year() {
            if (this.restoringGradeColumns) {
                return
            }
            this.syncGradeColumnsToRoute()
            this.persistGradeColumns()
        },
        student_grade_visibility_show_sem1() {
            if (this.restoringStudentGradeColumns) {
                return
            }
            this.persistStudentGradeColumns()
        },
        student_grade_visibility_show_sem2() {
            if (this.restoringStudentGradeColumns) {
                return
            }
            this.persistStudentGradeColumns()
        },
        student_grade_visibility_show_year() {
            if (this.restoringStudentGradeColumns) {
                return
            }
            this.persistStudentGradeColumns()
        },
        '$route.query.grades'(value) {
            const routeGrades = this.normalizeGradeQueryValue(value)

            if (routeGrades !== '') {
                this.restoreGradeColumnsFromRoute(routeGrades)
                return
            }

            this.restoreGradeColumns(this.persistedGradeColumns)
        },
    },

    methods: {
        formatGrade,
        gradeClass,
        async runInfoMutation(action, callback, options = {}) {
            if (this.isSavingInfo) {
                return false
            }

            this.saving_info_action = action

            if (!options.skipTick) {
                await this.$nextTick()
            }

            try {
                return await callback()
            } finally {
                this.saving_info_action = null
            }
        },
        normalizeGradeQueryValue(value) {
            if (Array.isArray(value)) {
                return typeof value[0] === 'string' ? value[0] : ''
            }

            return typeof value === 'string' ? value : ''
        },
        parseGradeColumns(value) {
            const tokens = this.normalizeGradeQueryValue(value)
                .split(',')
                .map((token) => token.trim().toLowerCase())
                .filter(Boolean)

            return {
                sem1: tokens.includes('sem1'),
                sem2: tokens.includes('sem2'),
                year: tokens.includes('year'),
            }
        },
        gradeColumnsQueryValue() {
            const tokens = []

            if (this.infos_show_grade_sem1) {
                tokens.push('sem1')
            }
            if (this.infos_show_grade_sem2) {
                tokens.push('sem2')
            }
            if (this.infos_show_grade_year) {
                tokens.push('year')
            }

            return tokens.length ? tokens.join(',') : null
        },
        restoreGradeColumnsFromRoute(value) {
            const normalizedValue = this.normalizeGradeQueryValue(value)

            if (normalizedValue === '') {
                return
            }

            const columns = this.parseGradeColumns(normalizedValue)
            this.restoreGradeColumns({
                show_sem1: columns.sem1,
                show_sem2: columns.sem2,
                show_year: columns.year,
            })
        },
        restoreGradeColumns(columns) {
            const normalizedColumns = {
                show_sem1: Boolean(columns?.show_sem1),
                show_sem2: Boolean(columns?.show_sem2),
                show_year: Boolean(columns?.show_year),
            }

            this.restoringGradeColumns = true
            this.infos_show_grade_sem1 = normalizedColumns.show_sem1
            this.infos_show_grade_sem2 = normalizedColumns.show_sem2
            this.infos_show_grade_year = normalizedColumns.show_year
            this.restoringGradeColumns = false
        },
        currentGradeColumns() {
            return {
                show_sem1: Boolean(this.infos_show_grade_sem1),
                show_sem2: Boolean(this.infos_show_grade_sem2),
                show_year: Boolean(this.infos_show_grade_year),
            }
        },
        defaultGradeColumns() {
            return {
                show_sem1: false,
                show_sem2: false,
                show_year: false,
            }
        },
        restoreStudentDisplaySettings(course) {
            this.student_display_show_age = Boolean(course?.teaching_show_student_age)
            this.student_display_show_last_login = Boolean(course?.teaching_show_student_last_login)
        },
        async saveStudentDisplaySetting(attribute, value) {
            if (!this.selected_course?.id || this.isSavingInfo) {
                return false
            }

            const localAttribute = attribute === 'teaching_show_student_age'
                ? 'student_display_show_age'
                : 'student_display_show_last_login'
            const previousValue = Boolean(this.selected_course?.[attribute])
            const normalizedValue = Boolean(value)

            this[localAttribute] = normalizedValue

            return this.runInfoMutation('save-student-display', async () => {
                const saved = await this.courseStore.update({
                    ...this.selected_course,
                    [attribute]: normalizedValue,
                })

                if (!saved) {
                    this[localAttribute] = previousValue
                    return false
                }

                const refreshedCourse = await this.courseStore.refreshCourseById(this.selected_course.id)
                if (refreshedCourse) {
                    this.restoreStudentDisplaySettings(refreshedCourse)
                }

                return true
            })
        },
        restoreStudentGradeColumns(columns) {
            const normalizedColumns = {
                show_sem1: Boolean(columns?.show_sem1),
                show_sem2: Boolean(columns?.show_sem2),
                show_year: Boolean(columns?.show_year),
            }

            this.restoringStudentGradeColumns = true
            this.student_grade_visibility_show_sem1 = normalizedColumns.show_sem1
            this.student_grade_visibility_show_sem2 = normalizedColumns.show_sem2
            this.student_grade_visibility_show_year = normalizedColumns.show_year
            this.restoringStudentGradeColumns = false
        },
        currentStudentGradeColumns() {
            return {
                show_sem1: Boolean(this.student_grade_visibility_show_sem1),
                show_sem2: Boolean(this.student_grade_visibility_show_sem2),
                show_year: Boolean(this.student_grade_visibility_show_year),
            }
        },
        gradeColumnsMatch(left, right) {
            return Boolean(left?.show_sem1) === Boolean(right?.show_sem1)
                && Boolean(left?.show_sem2) === Boolean(right?.show_sem2)
                && Boolean(left?.show_year) === Boolean(right?.show_year)
        },
        async persistGradeColumns() {
            if (!this.teachingStore) {
                return
            }

            const columns = this.currentGradeColumns()
            if (this.gradeColumnsMatch(columns, this.persistedGradeColumns)) {
                return
            }

            await this.teachingStore.saveSettings({
                teaching_grade_columns: columns,
            }, {
                notifySuccess: false,
            })
        },
        async persistStudentGradeColumns() {
            if (!this.teachingStore) {
                return
            }

            const columns = this.currentStudentGradeColumns()
            if (this.gradeColumnsMatch(columns, this.persistedStudentGradeColumns)) {
                return
            }

            const saved = await this.teachingStore.saveSettings({
                teaching_course_id: this.selected_course?.id || null,
                teaching_student_grade_columns: columns,
            }, {
                notifySuccess: false,
            })

            if (saved && this.selected_course) {
                this.selected_course.teaching_student_grade_columns = columns
            }
        },
        syncGradeColumnsToRoute() {
            if (!this.$router || !this.$route) {
                return
            }

            const columns = this.currentGradeColumns()
            const grades = this.gradeColumnsQueryValue()
            const currentGrades = this.normalizeGradeQueryValue(this.$route.query?.grades)
            const nextGrades = grades ?? ''

            if (currentGrades === nextGrades) {
                return
            }

            const query = { ...this.$route.query }

            if (grades) {
                query.grades = grades
            } else {
                delete query.grades
            }

            if (!this.hasTwoSemesters && !columns.show_sem1) {
                delete query.grades
            }

            this.$router.replace({
                path: this.$route.path,
                query,
            }).catch(() => {})
        },
        isStudentCanceled(student) {
            return !!student?.canceled_at || !!student?.deleted_at
        },
        async initializeGradeState() {
            this.courseStore?.ensureCourseStudentCollections?.(this.selected_course)

            if (this.anyGradeColumnVisible && this.selected_course?.id && !this.gradesDataLoaded) {
                await this.loadGradeData()
            }
        },
        async loadGradeData() {
            if (!this.selected_course?.id || !this.entryStore) return
            this.gradesLoading = true
            await Promise.allSettled([
                this.entryStore.indexByCourse(this.selected_course.id),
                this.courseWorkStore.index(this.selected_course.id),
            ])
            this.gradeEntries = [...(this.entryStore.courseEntries || [])]
            this.gradesDataLoaded = true
            this.gradesLoading = false
        },
        editDescription() {
            if (this.isSavingInfo) {
                return
            }
            this.edit_description = this.selected_course.description || ''
            this.action = 'edit_description'
        },
        abortEditDescription() {
            if (this.isSavingDescription) {
                return
            }
            this.action = ''
            this.edit_description = ''
        },
        async saveDescription() {
            await this.runInfoMutation('save-description', async () => {
                const data = {
                    ...this.selected_course,
                    description: this.edit_description,
                }
                if (await this.courseStore.update(data)) {
                    this.selected_course.description = this.edit_description
                    this.action = ''
                    this.edit_description = ''
                }
            })
        },
        notificationTypeLabel(type) {
            if (!type) return ''
            const name = this.notificationTypesByShort.get(type)
            return name ? `${type} - ${name}` : type
        },
        notificationEntryBackgroundClass(entry) {
            const type = String(entry?.type || '').trim()
            if (type === '') {
                return 'open-notification-block--type-empty'
            }

            let hash = 0
            for (const character of type) {
                hash = (hash + character.charCodeAt(0)) % 6
            }

            return `open-notification-block--type-${hash + 1}`
        },
        studentLabel(userId) {
            const student = (this.selected_course?.students_info || []).find((s) => s.id === userId)
            if (!student) return 'Schüler:in'
            return `${student.last_name || ''}, ${student.first_name || ''}`.trim().replace(/^,\s*/, '')
        },
        formatDate(date) {
            if (!date) return ''
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        dueDateColor(date) {
            const due = parseLocalDate(date)
            if (isNaN(due.getTime())) return 'warning'
            due.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            return due <= today ? 'error' : 'warning'
        },
        toDateString(date) {
            const d = parseLocalDate(date)
            const year = d.getFullYear()
            const month = String(d.getMonth() + 1).padStart(2, '0')
            const day = String(d.getDate()).padStart(2, '0')
            return `${year}-${month}-${day}`
        },
        lessonStartFromHour(dateStr, hour) {
            if (!dateStr) return null

            const schoolHour = this.schoolHoursByHour[Number(hour)]
            const fromValue = (schoolHour?.from || '').toString().trim()
            if (!fromValue) return null

            const date = parseLocalDate(dateStr)
            if (isNaN(date.getTime())) return null

            const parts = fromValue.split(':').map((part) => Number(part))
            if (!Number.isFinite(parts[0]) || !Number.isFinite(parts[1])) return null

            date.setHours(parts[0], parts[1], Number.isFinite(parts[2]) ? parts[2] : 0, 0)
            return date
        },
        lessonEndFromHour(dateStr, hour) {
            if (!dateStr) return null

            const schoolHour = this.schoolHoursByHour[Number(hour)]
            const untilValue = (schoolHour?.until || '').toString().trim()
            if (!untilValue) return null

            const date = parseLocalDate(dateStr)
            if (isNaN(date.getTime())) return null

            const parts = untilValue.split(':').map((part) => Number(part))
            if (!Number.isFinite(parts[0]) || !Number.isFinite(parts[1])) return null

            date.setHours(parts[0], parts[1], Number.isFinite(parts[2]) ? parts[2] : 0, 0)
            return date
        },
        padTwo(value) {
            return String(value).padStart(2, '0')
        },
        async completeNotification(entry) {
            if (!entry?.id || !entry?.type) return
            this.saving_notification_id = entry.id

            try {
                await this.runInfoMutation('complete-notification', async () => {
                    const payload = {
                        id: entry.id,
                        kind: 'notification',
                        type: entry.type,
                        date: entry.date || null,
                        description: entry.description || null,
                        is_due: !!entry.due_date,
                        due_date: entry.due_date || null,
                        is_done: true,
                        done_date: this.toDateString(new Date()),
                    }
                    await this.behaviourEntryStore.update(payload)
                })
            } finally {
                this.saving_notification_id = null
            }
        },
    },
}
</script>

<style scoped>
.course-description :deep(p) {
    margin: 0;
    min-height: 1.2em;
}

.notification-row {
    min-width: 0;
}

.open-notification-item {
    align-items: stretch;
}

.open-notification-item :deep(.v-list-item__content) {
    width: 100%;
}

.open-notification-block {
    border: 1px solid transparent;
    border-radius: 8px;
    box-sizing: border-box;
    min-height: 100%;
    padding: 8px;
}

.open-notification-student-name {
    color: #0f172a;
    flex-basis: 100%;
    font-size: 0.9rem;
    font-weight: 750;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.open-notification-block--type-empty {
    background-color: #ffffff !important;
    border-color: rgba(148, 163, 184, 0.18);
}

.open-notification-block--type-1 {
    background-color: #eef6ff !important;
    border-color: rgba(37, 99, 235, 0.16);
}

.open-notification-block--type-2 {
    background-color: #f0fdf4 !important;
    border-color: rgba(22, 163, 74, 0.16);
}

.open-notification-block--type-3 {
    background-color: #fff7ed !important;
    border-color: rgba(234, 88, 12, 0.16);
}

.open-notification-block--type-4 {
    background-color: #f5f3ff !important;
    border-color: rgba(124, 58, 237, 0.16);
}

.open-notification-block--type-5 {
    background-color: #fef2f2 !important;
    border-color: rgba(220, 38, 38, 0.14);
}

.open-notification-block--type-6 {
    background-color: #ecfeff !important;
    border-color: rgba(8, 145, 178, 0.16);
}

.chip-truncate {
    max-width: 100%;
}

.chip-truncate :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.notification-description {
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

.notification-action {
    margin-left: auto;
}

.course-infos-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.course-info-card {
    min-width: 0;
}

.course-info-summary-card__content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
}

.course-info-block {
    border: 1px solid transparent;
    border-radius: 8px;
    min-width: 0;
    padding: 10px 12px;
}

.course-info-block--countdown {
    background-color: #eef6ff;
    border-color: rgba(37, 99, 235, 0.16);
}

.course-info-block--schema {
    background-color: #f0fdf4;
    border-color: rgba(22, 163, 74, 0.16);
}

.course-info-block--description {
    background-color: #fff7ed;
    border-color: rgba(234, 88, 12, 0.16);
}

.grade-checkbox {
    flex: none;
}

.grade-checkbox :deep(.v-label) {
    font-size: 0.82rem;
}

.grades-table-wrap {
    overflow-x: auto;
}

.grades-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
}

.grades-table th,
.grades-table td {
    border: 1px solid rgba(16, 38, 58, 0.1);
    padding: 5px 8px;
    vertical-align: middle;
}

.grades-table th {
    background: rgba(15, 23, 42, 0.06);
    font-weight: 600;
    white-space: nowrap;
    text-align: center;
}

.grades-table tbody tr:nth-child(even) td {
    background-color: rgba(37, 99, 235, 0.045);
}

.grades-student-col {
    text-align: left !important;
    min-width: 160px;
}

.grades-grade-col {
    width: 80px;
    text-align: center;
}

.grades-student-cell {
    font-weight: 500;
}

.grades-student-cell--canceled {
    text-decoration: line-through;
    opacity: 0.75;
}

.grades-grade-cell {
    text-align: center;
    font-weight: 700;
}

.row--canceled td {
    opacity: 0.6;
}

.grade--ok {
    color: #1e40af;
}

.grade--na {
    color: #c62828;
}

.grade--nb {
    color: #e65100;
}

.grade--empty {
    color: rgba(16, 38, 58, 0.35);
}

@media (max-width: 700px) {
    .course-infos-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
