<template>
    <v-container fluid class="teaching-page ma-0 w-100 pa-2">
        <AdminPageHeader
            class="mb-3"
            :location="teachingHeaderTitle"
            :context-label="teacherCoursesLabel"
            :status-label="teacherMetricsLabel"
            status-icon="mdi-account-group-outline" />

        <section class="teaching-context mb-3" aria-label="Unterricht und Schuljahr">
            <div class="teaching-context__items">
                <div v-for="item in headerStatusItems" :key="item.key" class="teaching-context__item">
                    <v-icon :icon="item.icon" size="17" />
                    <span>{{ item.text }}</span>
                </div>
                <div v-if="!lessonStatusNote" class="teaching-context__empty">
                    <v-icon icon="mdi-calendar-clock" size="17" />
                    <span>{{ lessonAvailabilityLabel }}</span>
                </div>
            </div>
            <div class="teaching-context__progress">
                <div class="teaching-context__progress-heading">
                    <v-icon icon="mdi-chart-timeline-variant-shimmer" size="19" />
                    <div>
                        <div class="teaching-context__progress-label">{{ schoolyearProgressLabel }}</div>
                        <div class="teaching-context__semester">{{ currentSemesterProgressLabel || 'Semesterfortschritt nicht verfügbar' }}</div>
                    </div>
                </div>
                <div v-if="schoolyearStats" class="teaching-context__progress-track">
                    <v-progress-linear
                        :model-value="schoolyearStats.progress"
                        :aria-label="schoolyearProgressLabel"
                        color="#86efac"
                        bg-color="#ef4444"
                        :bg-opacity="0.62"
                        height="9"
                        rounded
                        rounded-bar />
                    <span v-if="schoolyearSemesterStartProgress !== null"
                        class="teaching-context__progress-marker"
                        :style="{ left: `${schoolyearSemesterStartProgress}%` }"
                        aria-label="Beginn des 2. Semesters"
                        title="Beginn des 2. Semesters"></span>
                </div>
            </div>
        </section>

        <TeachingDueReminders />

        <v-sheet v-if="!selected_course && main_action !== 'administration'" class="teaching-nav mb-2" :class="{ 'is-locked': isNavigationLocked }">
            <v-btn-toggle
                :model-value="main_action"
                mandatory
                divided
                color="primary"
                class="teaching-nav__buttons"
                :disabled="isNavigationLocked"
                @update:model-value="handleNavigation">
                <v-btn
                    v-for="item in visibleNavigationItems"
                    :key="item.key"
                    :data-testid="`teaching-nav-${item.key}`"
                    :value="item.key"
                    :prepend-icon="item.icon"
                    :aria-pressed="main_action === item.key"
                    class="teaching-nav__button"
                    :class="{ 'teaching-nav__button--admin': item.key === 'administration' }"
                    :disabled="isNavigationLocked">
                    <span class="teaching-nav__button-copy">
                        <span class="teaching-nav__button-title">{{ item.label }}</span>
                        <span class="teaching-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
            </v-btn-toggle>
        </v-sheet>

        <TeacherAdministration
            v-if="main_action === 'administration' && canManageTeachingAdministration"
            :navigation-locked="isNavigationLocked">
            <template #navigation="{ panels, selectedPanel, activatePanel, activeSchoolyearLabel, hasMissingSchoolHours }">
                <v-sheet class="teaching-nav mb-2" :class="{ 'is-locked': isNavigationLocked }" data-testid="teaching-administration-nav">
                    <v-btn-toggle
                        :model-value="selectedPanel"
                        mandatory
                        divided
                        color="primary"
                        class="teaching-nav__buttons"
                        :disabled="isNavigationLocked"
                        @update:model-value="activatePanel">
                        <v-btn
                            v-for="panel in panels"
                            :key="panel.id"
                            :value="panel.id"
                            :prepend-icon="panel.icon"
                            :aria-pressed="selectedPanel === panel.id"
                            :data-testid="`teaching-administration-${panel.id}`"
                            :disabled="isNavigationLocked"
                            class="teaching-nav__button">
                            <span class="teaching-nav__button-copy">
                                <span class="teaching-nav__button-title">{{ panel.label }}</span>
                                <span class="teaching-nav__button-meta">{{ activeSchoolyearLabel }}</span>
                            </span>
                            <span v-if="panel.id === 'school_hours' && hasMissingSchoolHours" class="ml-2 text-error font-weight-black" aria-label="Keine Schulstunden vorhanden">!</span>
                        </v-btn>
                    </v-btn-toggle>
                    <v-btn-toggle :model-value="main_action" mandatory color="primary" class="teaching-nav__return" :disabled="isNavigationLocked">
                        <v-btn
                            value="overview"
                            prepend-icon="mdi-school-outline"
                            class="teaching-nav__button"
                            data-testid="teaching-administration-back"
                            :disabled="isNavigationLocked"
                            @click="handleNavigation('overview')">
                            <span class="teaching-nav__button-copy">
                                <span class="teaching-nav__button-title">Unterricht</span>
                                <span class="teaching-nav__button-meta">Stundenplan &amp; Kurse</span>
                            </span>
                        </v-btn>
                    </v-btn-toggle>
                </v-sheet>
            </template>
        </TeacherAdministration>

        <v-sheet
            v-if="main_action === 'overview'"
            rounded="xl"
            class="teaching-subnav mb-2"
            :class="{ 'is-locked': isNavigationLocked || isStudentDetailActive }">
            <div class="teaching-subnav__inner">
                <div class="teaching-subnav__courses">
                    <div v-if="!courses.length" class="teaching-subnav__empty" role="status">
                        <v-icon icon="mdi-invoice-list-outline" color="primary" size="20" />
                        <span>Noch keine Fächer vorhanden.</span>
                    </div>
                    <v-btn
                        v-if="selected_course"
                        size="small"
                        rounded="xl"
                        variant="tonal"
                        class="teaching-subnav__course-btn teaching-subnav__course-btn--overview"
                        prepend-icon="mdi-arrow-left"
                        :disabled="isNavigationLocked || isStudentDetailActive"
                        @click="handleCourseClear">
                        Übersicht
                    </v-btn>
                    <v-btn
                        v-for="course in courses"
                        :key="course.id"
                        size="small"
                        rounded="xl"
                        :variant="selected_course?.id === course.id ? 'flat' : 'tonal'"
                        :class="selected_course?.id === course.id ? 'teaching-subnav__course-btn--active' : 'teaching-subnav__course-btn--idle'"
                        class="teaching-subnav__course-btn"
                        :disabled="isNavigationLocked || isStudentDetailActive"
                        @click="handleCourseSelect(course)">
                        {{ course.title }}
                        <span
                            v-if="courseStore?.courseHasProblems(course)"
                            class="ml-1 text-error font-weight-black"
                            aria-label="Probleme im Fach">!</span>
                    </v-btn>
                </div>
                <div class="teaching-subnav__actions ml-auto d-flex ga-2">
                    <v-btn
                        v-if="selected_course"
                        icon="mdi-pencil-outline"
                        variant="tonal"
                        color="primary"
                        title="Fach bearbeiten"
                        :disabled="isNavigationLocked || isStudentDetailActive"
                        @click="handleEditCourse" />
                    <v-btn
                        v-if="selected_course"
                        icon="mdi-delete-outline"
                        variant="tonal"
                        color="warning"
                        title="Fach löschen"
                        :disabled="isNavigationLocked || isStudentDetailActive"
                        @click="show_delete_confirm = true" />
                    <v-btn
                        v-if="!selected_course"
                        icon="mdi-plus"
                        variant="tonal"
                        color="success"
                        title="Neues Fach anlegen"
                        class="teaching-subnav__add-btn"
                        :disabled="isNavigationLocked || isStudentDetailActive"
                        @click="handleNewCourse" />
                </div>
            </div>
        </v-sheet>

        <v-dialog v-model="show_delete_confirm" max-width="420" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                    Fach löschen
                </v-card-title>
                <v-card-text class="px-4">
                    Soll das Fach <strong>„{{ selected_course?.title }}"</strong> wirklich gelöscht werden? Diese Aktion kann nicht rückgängig gemacht werden.
                </v-card-text>
                <v-card-actions class="teaching-dialog-actions px-4 pb-4">
                    <v-btn variant="tonal" @click="show_delete_confirm = false">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="error" variant="flat" :loading="delete_loading" @click="handleDeleteCourse">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-row v-if="main_action !== 'administration'" class="w-100 teaching-content" dense>
            <Overview v-if="main_action === 'overview'" />
            <Settings v-if="main_action === 'settings'" :key="`settings-${settings_view_key}`" />
            <Admin v-if="main_action === 'admin'" />
            <Search v-if="main_action === 'search'" />
            <Schoolyear v-if="main_action === 'schoolyear'" />
            <DataBackup v-if="main_action === 'datensicherung'" />
            <Curricula v-if="main_action === 'curricula'" />
        </v-row>
    </v-container>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { parseLocalDate } from '@/helpers/date'
import { administration as teachingAdministration } from '@/routes/admin/teaching'
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'
import TeachingDueReminders from './overview/components/TeachingDueReminders.vue'

function calendarDateValue(date) {
    return Date.UTC(date.getFullYear(), date.getMonth(), date.getDate())
}

const Overview = defineAsyncComponent(() => import('./overview/Overview.vue'))
const Settings = defineAsyncComponent(() => import('./settings/Settings.vue'))
const Admin = defineAsyncComponent(() => import('./admin/Admin.vue'))
const TeacherAdministration = defineAsyncComponent(() => import('./admin/TeacherAdministration.vue'))
const Search = defineAsyncComponent(() => import('./search/Search.vue'))
const Schoolyear = defineAsyncComponent(() => import('./schoolyear/Schoolyear.vue'))
const DataBackup = defineAsyncComponent(() => import('./backup/DataBackup.vue'))
const Curricula = defineAsyncComponent(() => import('./curricula/Curricula.vue'))
const teachingSections = ['overview', 'settings', 'admin', 'administration', 'search', 'schoolyear', 'datensicherung', 'curricula']

function normalizeTeachingSection(section) {
    return teachingSections.includes(section) ? section : 'overview'
}

export default {
    components: { AdminPageHeader, TeachingDueReminders, Overview, Settings, Admin, TeacherAdministration, Search, Schoolyear, DataBackup, Curricula },

    created() {
        this.syncSection(this.$route.params.section)
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        this.courseStore = this.ensureCourseStore()
        this.schoolHourStore = useSchoolHourStore()

        if (this.lessonContextKey && this.courseStore.courses.some((course) =>
            Number(course.school_id) !== Number(this.config.selected_school.id)
            || Number(course.schoolyear_id) !== Number(this.config.selected_schoolyear.id))) {
            await this.reloadLessonContext(this.lessonContextKey)
            return
        }

        const requests = []
        if (!this.courseStore.courses.length) {
            requests.push(this.courseStore.index())
        }
        if (!this.schoolHourStore.school_hours.length) {
            requests.push(this.schoolHourStore.index())
        }

        const results = await Promise.all(requests)
        this.teacherMetricsStatus = results.includes(false) ? 'error' : 'ready'
    },

    mounted() {
        this.nowTimer = setInterval(() => { this.nowTs = Date.now() }, 1000)
    },

    unmounted() {
        useCourseStore().clearTimetableNavigation()
        this.action = ''
        this.action_2 = ''
        if (this.nowTimer) clearInterval(this.nowTimer)
    },

    data() {
        return {
            adminStore: null,
            schoolStore: null,
            courseStore: null,
            schoolHourStore: null,
            main_action: normalizeTeachingSection(this.$route.params.section),
            settings_view_key: 0,
            show_delete_confirm: false,
            delete_loading: false,
            hopper_switching_id: null,
            nowTs: Date.now(),
            nowTimer: null,
            lessonContextLoading: false,
            teacherMetricsStatus: 'loading',
            _urlRestored: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'action', 'action_2']),
        ...mapWritableState(useSchoolStore, ['hopper_accounts']),
        ...mapWritableState(useCourseStore, ['courses', 'courses_request_promise', 'selected_course', 'selected_course_id', 'selected_course_student', 'pending_edit_course_id', 'pending_new_course_token']),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),
        isNavigationLocked() {
            return this.action != '' || this.isStudentDetailActive
        },
        canManageTeachingAdministration() {
            return this.hasAnyRole(['admin', 'super_admin', 'teaching_admin'])
        },
        isStudentDetailActive() {
            return this.action_2 === 'course_student_view' || !!this.selected_course_student
        },
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule gewählt'
        },
        selectedSchoolyearLabel() {
            return this.config?.selected_schoolyear?.name || 'Kein Schuljahr gewählt'
        },
        selectedSchoolyearShortLabel() {
            const schoolyear = this.config?.selected_schoolyear
            const startYear = String(schoolyear?.from || '').match(/^\d{4}/)?.[0]
            const endYear = String(schoolyear?.until || '').match(/^\d{4}/)?.[0]

            if (startYear && endYear) {
                return `${startYear.slice(-2)}/${endYear.slice(-2)}`
            }

            const label = schoolyear?.name || schoolyear?.concerns || ''
            const yearMatch = String(label).match(/(\d{2}|\d{4})\s*\/\s*(\d{2}|\d{4})/)

            if (!yearMatch) {
                return ''
            }

            return `${yearMatch[1].slice(-2)}/${yearMatch[2].slice(-2)}`
        },
        teachingHeaderTitle() {
            return this.selectedSchoolyearShortLabel
                ? `Lehrerbereich ${this.selectedSchoolyearShortLabel}`
                : 'Lehrerbereich'
        },
        selectedRoleLabel() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            if (!roles.length) {
                return 'Keine Rolle'
            }
            return roles.slice(0, 2).join(' / ')
        },
        myCourses() {
            if (!this.lessonContextKey) return []
            const { user, selected_school: school, selected_schoolyear: schoolyear } = this.config
            const list = Array.isArray(this.courses) ? this.courses : []
            return list.filter((course) => Number(course?.user_id) === Number(user.id)
                && Number(course?.school_id) === Number(school.id)
                && Number(course?.schoolyear_id) === Number(schoolyear.id))
        },
        teacherMetricsAvailable() {
            return !!this.lessonContextKey && !this.lessonContextLoading
                && !this.courses_request_promise && this.teacherMetricsStatus === 'ready'
        },
        teacherCoursesLabel() {
            if (!this.teacherMetricsAvailable) return 'Kurse und Schüler:innen'

            return this.myCourses.length === 1 ? '1 Kurs' : `${this.myCourses.length} Kurse`
        },
        teacherMetricsLabel() {
            if (this.teacherMetricsAvailable) {
                return this.myStudentCount === 1 ? '1 Schüler:in' : `${this.myStudentCount} Schüler:innen`
            }
            if (!this.lessonContextKey || this.teacherMetricsStatus === 'error') return 'Kennzahlen nicht verfügbar'

            return 'Kennzahlen werden geladen …'
        },
        lessonAvailabilityLabel() {
            if (!this.lessonContextKey || this.teacherMetricsStatus === 'error') return 'Unterrichtszeiten nicht verfügbar'
            if (this.lessonContextLoading || this.teacherMetricsStatus === 'loading') return 'Unterrichtszeiten werden geladen …'

            return 'Kein weiterer Unterricht geplant'
        },
        myStudentCount() {
            const ids = new Set()
            this.myCourses.forEach((course) => {
                const info = Array.isArray(course?.students_info) ? course.students_info : []
                if (info.length) {
                    info.forEach((s) => { if (!s?.canceled_at && s?.id != null) ids.add(String(s.id)) })
                    return
                }
                ;(Array.isArray(course?.students) ? course.students : []).forEach((s) => {
                    if (s == null) return
                    ids.add(String(typeof s === 'object' ? s.id : s))
                })
            })
            return ids.size
        },
        nowLabel() {
            return new Date(this.nowTs).toLocaleString('de-DE', {
                weekday: 'long',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            })
        },
        schoolHoursByHour() {
            return (Array.isArray(this.school_hours) ? this.school_hours : []).reduce((carry, item) => {
                const hour = Number(item?.hour)
                if (Number.isFinite(hour)) carry[hour] = item
                return carry
            }, {})
        },
        hasFreeLessonToday() {
            const todayKey = this.todayDateKey()
            if (!todayKey) return false

            return this.isFreeCourseDate(this.selected_courseDate) && this.isCourseDateToday(this.selected_courseDate, todayKey)
        },
        lessonContextKey() {
            const userId = this.config?.user?.id
            const schoolId = this.config?.selected_school?.id
            const schoolyearId = this.config?.selected_schoolyear?.id
            return userId && schoolId && schoolyearId ? `${userId}:${schoolId}:${schoolyearId}` : null
        },
        lessonSlots() {
            if (!this.lessonContextKey || this.lessonContextLoading) return []
            const { user, selected_school: school, selected_schoolyear: schoolyear } = this.config
            return (Array.isArray(this.courses) ? this.courses : [])
                .filter((course) => Number(course.user_id) === Number(user.id)
                    && Number(course.school_id) === Number(school.id)
                    && Number(course.schoolyear_id) === Number(schoolyear.id))
                .flatMap((course) => (Array.isArray(course.course_dates) ? course.course_dates : []).flatMap((courseDate) => {
                    if (this.isFreeCourseDate(courseDate)) return []
                    const date = String(courseDate.date || '').slice(0, 10)
                    if (!date || (schoolyear.from && date < schoolyear.from.slice(0, 10))
                        || (schoolyear.until && date > schoolyear.until.slice(0, 10))) return []
                    // Inspect actual hours so breaks and non-consecutive hours are not treated as teaching.
                    return (Array.isArray(courseDate.hours) ? courseDate.hours : []).map((hour) => ({
                        start: this.lessonStartFromHour(date, hour),
                        end: this.lessonEndFromHour(date, hour),
                    })).filter((slot) => slot.start && (!slot.end || slot.end > slot.start))
                }))
        },
        nextLessonStartAt() {
            const starts = this.lessonSlots.filter((slot) => slot.start.getTime() > this.nowTs)
                .map((slot) => slot.start.getTime())
            return starts.length ? new Date(Math.min(...starts)) : null
        },
        activeLessonEndAt() {
            const ends = this.lessonSlots.filter((slot) => slot.end && slot.start.getTime() <= this.nowTs && slot.end.getTime() > this.nowTs)
                .map((slot) => slot.end.getTime())
            return ends.length ? new Date(Math.min(...ends)) : null
        },
        lessonStatusNote() {
            if (this.activeLessonEndAt) {
                return `Unterricht läuft – noch ${this.formatLessonDuration(this.activeLessonEndAt)}`
            }
            if (this.nextLessonStartAt) {
                return `Nächster Unterricht in ${this.formatLessonDuration(this.nextLessonStartAt)}`
            }
            return null
        },
        schoolyearStats() {
            const from = this.config?.selected_schoolyear?.from
            const until = this.config?.selected_schoolyear?.until
            if (!from || !until) return null
            const start = parseLocalDate(from)
            const end = parseLocalDate(until)
            if (isNaN(start.getTime()) || isNaN(end.getTime())) return null
            start.setHours(0, 0, 0, 0)
            end.setHours(0, 0, 0, 0)
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            const total = Math.max(1, Math.floor((end - start) / 86400000) + 1)
            const elapsed = Math.min(total, Math.max(0, Math.floor((today - start) / 86400000) + 1))
            const remaining = Math.max(0, Math.floor((end - today) / 86400000))
            const progress = Math.round((elapsed / total) * 100)
            return { elapsed, remaining, progress }
        },
        schoolyearSemesterStartProgress() {
            const schoolyear = this.config?.selected_schoolyear
            if (!schoolyear?.from || !schoolyear?.until || !schoolyear?.sem_2_start) {
                return null
            }

            const start = parseLocalDate(schoolyear.from)
            const end = parseLocalDate(schoolyear.until)
            const semesterStart = parseLocalDate(schoolyear.sem_2_start)
            const startDateValue = calendarDateValue(start)
            const endDateValue = calendarDateValue(end)
            const semesterStartDateValue = calendarDateValue(semesterStart)
            const totalMilliseconds = endDateValue - startDateValue
            const semesterStartMilliseconds = semesterStartDateValue - startDateValue

            if (
                !Number.isFinite(totalMilliseconds)
                || !Number.isFinite(semesterStartMilliseconds)
                || totalMilliseconds <= 0
                || semesterStartMilliseconds < 0
                || semesterStartMilliseconds > totalMilliseconds
            ) {
                return null
            }

            return (semesterStartMilliseconds / totalMilliseconds) * 100
        },
        currentSemesterStats() {
            const schoolyear = this.config?.selected_schoolyear
            if (!schoolyear?.from || !schoolyear?.until || !schoolyear?.sem_2_start) {
                return null
            }

            const schoolyearStartValue = calendarDateValue(parseLocalDate(schoolyear.from))
            const schoolyearEndValue = calendarDateValue(parseLocalDate(schoolyear.until))
            const semesterTwoStartValue = calendarDateValue(parseLocalDate(schoolyear.sem_2_start))

            if (
                !Number.isFinite(schoolyearStartValue)
                || !Number.isFinite(schoolyearEndValue)
                || !Number.isFinite(semesterTwoStartValue)
                || semesterTwoStartValue <= schoolyearStartValue
                || semesterTwoStartValue > schoolyearEndValue
            ) {
                return null
            }

            const currentDate = Number.isFinite(this.nowTs) ? new Date(this.nowTs) : new Date()
            const currentDateValue = calendarDateValue(currentDate)
            const millisecondsPerDay = 86400000
            const semester = currentDateValue < semesterTwoStartValue ? 1 : 2
            const semesterStartValue = semester === 1 ? schoolyearStartValue : semesterTwoStartValue
            const semesterEndValue = semester === 1 ? semesterTwoStartValue - millisecondsPerDay : schoolyearEndValue
            const totalDays = Math.floor((semesterEndValue - semesterStartValue) / millisecondsPerDay) + 1
            const elapsedDays = Math.min(
                totalDays,
                Math.max(0, Math.floor((currentDateValue - semesterStartValue) / millisecondsPerDay) + 1)
            )

            return {
                semester,
                progress: Math.round((elapsedDays / totalDays) * 100),
            }
        },
        headerStatusItems() {
            const items = [
                { key: 'current-date-time', text: this.nowLabel, icon: 'mdi-clock-outline' },
            ]
            if (this.lessonStatusNote) {
                items.push({ key: 'next-lesson', text: this.lessonStatusNote, icon: 'mdi-calendar-clock' })
            }
            return items
        },
        schoolyearProgressLabel() {
            return this.schoolyearStats !== null
                ? `Schuljahr: ${this.schoolyearStats.progress}% abgeschlossen`
                : 'Schuljahrfortschritt nicht verfügbar'
        },
        currentSemesterProgressLabel() {
            return this.currentSemesterStats !== null
                ? `${this.currentSemesterStats.semester}. Semester: ${this.currentSemesterStats.progress}% abgeschlossen`
                : ''
        },
        activeSection() {
            const progressNote = this.schoolyearStats !== null
                ? `Schuljahr: ${this.schoolyearStats.progress}% abgeschlossen`
                : ''
            const lessonNote = this.lessonStatusNote || ''
            const overviewNote = [lessonNote, progressNote].filter(Boolean).join(' · ')
            const progressValue = this.schoolyearStats !== null ? this.schoolyearStats.progress : null
            const sections = {
                overview: {
                    label: 'Übersicht',
                    icon: 'mdi-view-dashboard-outline',
                    note: overviewNote,
                    progress: progressValue,
                },
                settings: {
                    label: 'Einstellungen',
                    icon: 'mdi-cog-outline',
                    note: 'Schemas, Gewichtungen und Regeln anpassen.',
                },
                admin: {
                    label: 'Admin',
                    icon: 'mdi-shield-crown-outline',
                    note: 'Importe und Ferienverwaltung steuern.',
                },
                administration: {
                    label: 'Admin',
                    icon: 'mdi-shield-account-outline',
                    note: '',
                },
                search: {
                    label: 'Suche',
                    icon: 'mdi-magnify',
                    note: 'Schüler:innen und Klassen schnell finden.',
                },
                schoolyear: {
                    label: 'Schuljahr',
                    icon: 'mdi-calendar-month-outline',
                    note: 'Aktives Schuljahr prüfen und wechseln.',
                },
                datensicherung: {
                    label: 'Datensicherung',
                    icon: 'mdi-database-arrow-down-outline',
                    note: 'Sicherungen vorbereiten und verwalten.',
                },
                curricula: {
                    label: 'Curricula',
                    icon: 'mdi-book-education-outline',
                    note: 'Lehrpläne und Kompetenzraster verwalten.',
                },
            }
            return sections[this.main_action] || sections.overview
        },
        visibleNavigationItems() {
            return [
                {
                    key: 'overview',
                    label: 'Unterricht',
                    meta: 'Stundenplan & Kurse',
                    icon: 'mdi-school-outline',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher']),
                },
                {
                    key: 'search',
                    label: 'Suche',
                    meta: 'Personen & Klassen',
                    icon: 'mdi-magnify',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher']),
                },
                {
                    key: 'curricula',
                    label: 'Curricula',
                    meta: 'Lehrpläne & Raster',
                    icon: 'mdi-book-education-outline',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher']),
                },
                {
                    key: 'datensicherung',
                    label: 'Datensicherung',
                    meta: 'Export & Sicherung',
                    icon: 'mdi-database-arrow-down-outline',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin']),
                },
                {
                    key: 'settings',
                    label: 'Einstellungen',
                    meta: 'Schemas & Einträge',
                    icon: 'mdi-cog-outline',
                    visible: this.hasAnyRole(['super_admin', 'admin', 'teaching_admin', 'teacher']),
                },
                {
                    key: 'administration',
                    label: 'Admin',
                    meta: 'Lehrer, Import & Schulzeiten',
                    icon: 'mdi-shield-account-outline',
                    visible: this.canManageTeachingAdministration,
                },
            ].filter((item) => item.visible)
        },
    },

    watch: {
        lessonContextKey: 'reloadLessonContext',
        main_action(section) {
            if (section !== 'overview') {
                useCourseStore().clearTimetableNavigation()
            }
        },
        '$route.params.section'(section) {
            this.syncSection(section)
        },
        canManageTeachingAdministration(allowed) {
            if (!allowed && this.main_action === 'administration') {
                this.syncSection('administration')
            }
        },
        courses: {
            immediate: true,
            handler(courses) {
                const courseList = Array.isArray(courses) ? courses : []
                if (this._urlRestored || !courseList.length) return

                const courseStore = this.ensureCourseStore()
                const query = this.$route?.query || {}
                const courseId = Number(query.course)
                if (!courseId) {
                    const gradesParam = query.grades
                    if (gradesParam) {
                        this._urlRestored = true
                        const gradeValues = String(gradesParam).split(',').map(v => v.trim())
                        courseStore.infos_show_grade_sem1 = gradeValues.includes('sem1')
                        courseStore.infos_show_grade_sem2 = gradeValues.includes('sem2')
                        courseStore.infos_show_grade_year = gradeValues.includes('year')
                        const firstCourse = courseList[0]
                        if (firstCourse) {
                            courseStore.selected_course = firstCourse
                            courseStore.selected_course_id = firstCourse.id
                        }
                        return
                    }
                    this.resetTeachingOverviewSelection()
                    this._urlRestored = true
                    return
                }
                const course = courseList.find((c) => c.id === courseId)
                if (!course) return
                this._urlRestored = true
                courseStore.selected_course = course
                courseStore.selected_course_id = course.id
                const dateId = Number(query.date)
                if (dateId) {
                    const courseDateStore = useCourseDateStore()
                    const date = (course.course_dates || []).find((d) => d.id === dateId) || null
                    if (date) courseDateStore.selected_courseDate = date
                }
            },
        },
        selected_course(course) {
            if (!course?.details_loaded || !this.selected_courseDate?.id) {
                return
            }

            this.selected_courseDate = (course.course_dates || [])
                .find((courseDate) => courseDate.id === this.selected_courseDate.id) || null
        },
    },

    methods: {
        async reloadLessonContext(contextKey) {
            this.lessonContextLoading = true
            this.teacherMetricsStatus = 'loading'
            if (!contextKey) return
            const courses = useCourseStore()
            const schoolHours = useSchoolHourStore()
            // Finish old-context requests before the stores start requests for the new context.
            await Promise.all([courses.courses_request_promise, schoolHours.school_hours_request_promise])
            if (contextKey !== this.lessonContextKey) return
            const results = await Promise.all([courses.index(), schoolHours.index()])
            if (contextKey === this.lessonContextKey) {
                this.teacherMetricsStatus = results.includes(false) ? 'error' : 'ready'
                if (!results.includes(false)) this.lessonContextLoading = false
            }
        },
        formatLessonDuration(until) {
            const remaining = until.getTime() - this.nowTs
            if (remaining < 60_000) return 'weniger als 1 Minute'
            const minutes = Math.floor(remaining / 60_000)
            const days = remaining > 86_400_000 ? Math.floor(minutes / 1440) : 0
            const hours = Math.floor((minutes - days * 1440) / 60)
            const parts = []
            if (days) parts.push(`${days} ${days === 1 ? 'Tag' : 'Tagen'}`)
            if (hours) parts.push(`${hours} Std.`)
            parts.push(`${minutes % 60} Min.`)
            return parts.join(' ')
        },
        normalizedSection(section) {
            if (section === 'administration' && !this.canManageTeachingAdministration) {
                return 'overview'
            }

            return normalizeTeachingSection(section)
        },
        syncSection(section) {
            this.main_action = this.normalizedSection(section)
            if (section === 'administration' && this.main_action !== 'administration') {
                this.$router.replace({ path: '/admin/teaching', query: { panel: 'table' } })
            }
        },
        ensureCourseStore() {
            if (!this.courseStore) {
                this.courseStore = useCourseStore()
            }

            return this.courseStore
        },
        hasAnyRole(requiredRoles) {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            return roles.some((role) => requiredRoles.includes(role))
        },
        handleNavigation(target) {
            if (this.isNavigationLocked) {
                return
            }
            if (target === 'overview') {
                this.openTeachingTable()
                return
            }
            if (target === 'settings') {
                this.openSettings()
                return
            }
            this.navigateTo(target)
        },
        openTeachingTable() {
            this.resetTeachingOverviewSelection()
            this.main_action = 'overview'
            this.$router.replace({ path: '/admin/teaching', query: { panel: 'table' } })
        },
        navigateTo(section) {
            if (section === 'administration') {
                if (!this.canManageTeachingAdministration) {
                    return
                }

                this.main_action = section
                this.$router.replace({ path: teachingAdministration.url(), query: { panel: 'teachers' } })
                return
            }

            this.main_action = section
            const path = section === 'overview' ? '/admin/teaching' : `/admin/teaching/${section}`
            this.$router.replace({ path, query: { ...this.$route.query } })
        },
        openSettings() {
            this.settings_view_key++
            this.navigateTo('settings')
        },
        resetTeachingOverviewSelection() {
            const courseStore = this.ensureCourseStore()

            courseStore.selected_course = null
            courseStore.selected_course_id = null
            courseStore.selected_course_student = null
            courseStore.show_students = true
            courseStore.show_infos = false
            courseStore.show_works = false
            courseStore.show_print = false
            courseStore.show_lists = false
            courseStore.show_dates = false
            courseStore.show_table = false
            courseStore.show_curriculum = false
            courseStore.show_attendance = false
            courseStore.show_performances = false
            courseStore.show_performances_plus = false
            this.selected_courseDate = null
            this.action_2 = ''
        },
        lessonStartFromHour(dateStr, hour) {
            return this.lessonTimeFromHour(dateStr, hour, 'from')
        },
        lessonEndFromHour(dateStr, hour) {
            return this.lessonTimeFromHour(dateStr, hour, 'until')
        },
        lessonTimeFromHour(dateStr, hour, field) {
            const schoolHour = this.schoolHoursByHour[Number(hour)]
            const time = String(schoolHour?.[field] || '').trim()
            if (!dateStr || !/^\d{1,2}:\d{2}(:\d{2})?$/.test(time)) return null
            const date = parseLocalDate(dateStr)
            if (isNaN(date.getTime())) return null
            const [hours, minutes, seconds = 0] = time.split(':').map(Number)
            if (hours > 23 || minutes > 59 || seconds > 59) return null
            date.setHours(hours, minutes, seconds, 0)
            return date
        },
        padTwo(v) {
            return String(v).padStart(2, '0')
        },
        todayDateKey() {
            const today = new Date(this.nowTs)
            return `${today.getFullYear()}-${this.padTwo(today.getMonth() + 1)}-${this.padTwo(today.getDate())}`
        },
        isCourseDateToday(courseDate, todayKey = null) {
            const date = (courseDate?.date || '').toString().slice(0, 10)
            if (!date) return false
            return date === (todayKey || this.todayDateKey())
        },
        isFreeCourseDate(courseDate) {
            const status = Array.isArray(courseDate?.status) ? courseDate.status : []
            const statusStr = status.join(' ').toLowerCase()
            return statusStr.includes('frei')
                || statusStr.includes('free')
                || statusStr.includes('entfaellt')
                || statusStr.includes('entfällt')
                || statusStr.includes('entfallen')
        },
        handleCourseSelect(course) {
            if (this.selected_course?.id === course.id) {
                this.handleCourseClear()
                return
            }
            if (this.isStudentDetailActive) {
                return
            }
            this.selected_courseDate = null
            this.selected_course = course
            this.selected_course_id = course.id
            const query = { course: String(course.id), panel: 'table' }
            if (this.$route.query.grades) query.grades = this.$route.query.grades
            this.$router.replace({ query }).catch(() => {})
        },
        handleCourseClear() {
            const returnState = useCourseStore().getTimetableReturn(this.$route.path)
            this.selected_course = null
            this.selected_course_id = null
            this.selected_courseDate = null
            const query = returnState ? { ...returnState.query } : {}
            if (!returnState && this.$route.query.grades) query.grades = this.$route.query.grades
            this.$router.replace({ query }).catch(() => {})
        },
        async handleDeleteCourse() {
            if (!this.selected_course) {
                return
            }
            this.delete_loading = true
            const ok = await this.courseStore.destroy(this.selected_course.id)
            if (ok) {
                await this.courseStore.index()
                this.selected_course = null
                this.selected_course_id = null
                this.selected_course_student = null
                this.action_2 = ''
            }
            this.delete_loading = false
            this.show_delete_confirm = false
        },
        handleEditCourse() {
            if (!this.selected_course) {
                return
            }
            this.navigateTo('overview')
            this.pending_edit_course_id = this.selected_course.id
            this.action = 'teaching_course_new_or_edit'
        },
        handleNewCourse() {
            this.selected_course = null
            this.selected_course_id = null
            this.selected_course_student = null
            this.pending_edit_course_id = null
            this.pending_new_course_token = Number(this.pending_new_course_token || 0) + 1
            this.action_2 = ''
            this.navigateTo('overview')
            this.action = 'teaching_course_new_or_edit'
        },
        async switchTeachingHopperAccount(account) {
            const targetUserId = Number(account?.id)
            if (!Number.isInteger(targetUserId) || targetUserId <= 0 || this.isNavigationLocked) {
                return
            }

            this.hopper_switching_id = targetUserId
            const switched = await this.schoolStore.switchHopperAccount(targetUserId)
            if (switched) {
                this.redirectToTeachingAfterHopperSwitch()
            }
            this.hopper_switching_id = null
        },
        redirectToTeachingAfterHopperSwitch() {
            window.location.assign('/admin/teaching')
        },
        hopperAccountLabel(account) {
            const schoolLabel = String(account?.school_label || '').trim()
            if (schoolLabel !== '') {
                return schoolLabel
            }

            return String(account?.email || 'Hopper-Konto').trim()
        },
    },
}
</script>

<style scoped>
.teaching-page {
    background: linear-gradient(180deg, #f1f6fd 0%, #e8f1fb 100%);
    min-height: 100vh;
}

.teaching-context {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px 32px;
    padding: 16px 20px;
    border: 1px solid #e7e9ef;
    border-radius: 12px;
    background: #ffffff;
    color: #25332c;
}

.teaching-context__items { flex: 1 1 320px; min-width: 0; }
.teaching-context__item,
.teaching-context__empty {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    line-height: 1.6;
    font-variant-numeric: tabular-nums;
}
.teaching-context__item + .teaching-context__item,
.teaching-context__empty { margin-top: 5px; }
.teaching-context__empty { color: #65716c; }
.teaching-context__progress { flex: 1 1 420px; min-width: 0; }
.teaching-context__progress-heading { display: flex; align-items: center; gap: 9px; }
.teaching-context__progress-label { font-size: 0.78rem; font-weight: 700; }
.teaching-context__semester { margin-top: 3px; font-size: 0.72rem; color: #65716c; }
.teaching-context__progress-track { position: relative; margin-top: 10px; }
.teaching-context__progress-marker {
    position: absolute;
    top: -3px;
    bottom: -3px;
    width: 2px;
    transform: translateX(-50%);
    background: #25332c;
}

@media (max-width: 599px) {
    .teaching-context { padding: 14px; }
    .teaching-context__progress { flex-basis: 100%; }
}

.teaching-nav {
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.teaching-nav__buttons {
    width: 100%;
    flex-wrap: wrap;
    row-gap: 6px;
    height: auto !important;
    min-width: 0;
}

.teaching-nav__return {
    flex-shrink: 0;
    height: auto !important;
    margin-inline-start: auto;
}

.teaching-nav__button {
    min-height: 56px !important;
    height: auto !important;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 650;
}

.teaching-nav__button--admin {
    margin-inline-start: auto !important;
}

.teaching-nav__button-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.15;
    gap: 4px;
}

.teaching-nav__button-title {
    font-weight: 650;
}

.teaching-nav__button-meta {
    color: rgba(255, 255, 255, 0.98);
    font-size: 0.76rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    padding: 2px 8px;
}

.teaching-nav.is-locked {
    opacity: 0.68;
}

.teaching-subnav {
    border: 1px solid rgba(37, 99, 235, 0.18);
    background: rgba(255, 255, 255, 0.84);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    padding: 8px 12px;
}

.teaching-subnav.is-locked {
    opacity: 0.68;
    pointer-events: none;
}

.teaching-subnav__inner {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
}

.teaching-subnav__courses {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
}

.teaching-subnav__empty {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 40px;
    color: rgba(30, 58, 138, 0.82);
    font-size: 0.88rem;
    font-weight: 600;
}

.teaching-subnav__course-btn {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
    height: 30px !important;
    font-size: 0.82rem;
}

.teaching-subnav__course-btn--idle {
    background: rgba(219, 234, 254, 0.72) !important;
    color: #1e40af !important;
    border: 1px solid rgba(59, 130, 246, 0.28) !important;
}

.teaching-subnav__course-btn--idle:hover {
    background: rgba(191, 219, 254, 0.78) !important;
    color: #1e3a8a !important;
}

.teaching-subnav__course-btn--active {
    background: linear-gradient(135deg, #4f46e5, #6366f1) !important;
    color: #fff !important;
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.45) !important;
}

.teaching-subnav__course-btn--overview {
    background: linear-gradient(135deg, #ea580c, #f97316) !important;
    color: #fff !important;
    border: 1px solid rgba(234, 88, 12, 0.5) !important;
    font-weight: 650;
}

.teaching-subnav__add-btn {
    color: #1e40af !important;
}

@media (max-width: 960px) {
    .teaching-subnav__inner {
        align-items: stretch;
        flex-direction: column;
    }

    .teaching-subnav__courses {
        display: grid;
        flex: none;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
    }

    .teaching-subnav__course-btn {
        justify-content: center;
        min-height: 44px !important;
        min-width: 0 !important;
        width: 100%;
    }

    .teaching-subnav__course-btn :deep(.v-btn__content) {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .teaching-subnav__course-btn--overview {
        grid-column: 1 / -1;
        justify-self: start;
        width: auto;
    }

    .teaching-subnav__empty {
        grid-column: 1 / -1;
        justify-content: center;
    }

    .teaching-subnav__actions {
        justify-content: center;
        margin-left: 0 !important;
        width: 100%;
    }

    .teaching-subnav__actions :deep(.v-btn) {
        min-height: 44px;
        min-width: 44px;
    }
}

@media (max-width: 700px) {
    .teaching-nav {
        align-items: stretch;
        flex-direction: column;
    }

    .teaching-nav__buttons {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
    }

    .teaching-nav__button {
        min-width: 0 !important;
        width: 100%;
    }

    .teaching-nav__button--admin {
        grid-column: 1 / -1;
        justify-self: end;
        width: auto;
    }

    .teaching-nav__button :deep(.v-btn__content),
    .teaching-nav__button-copy {
        min-width: 0;
    }

    .teaching-nav__button-title,
    .teaching-nav__button-meta {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

}

@media (max-width: 480px) {
    .teaching-nav__buttons {
        grid-template-columns: 1fr;
    }

    .teaching-dialog-actions {
        align-items: stretch;
        flex-direction: column;
        gap: 8px;
    }

    .teaching-dialog-actions :deep(.v-spacer) {
        display: none;
    }

    .teaching-dialog-actions :deep(.v-btn) {
        margin-inline: 0 !important;
        min-height: 44px;
        width: 100%;
    }
}

.teaching-content {
    margin-top: 2px;
}

</style>
