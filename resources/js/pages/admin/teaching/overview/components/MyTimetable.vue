<template>
    <ItsGridBox variant="overview" color="primary" title="Stundenplan" icon="mdi-calendar-clock" class="w-100" :disabled="action != ''">
        <template #header-actions>
            <div class="d-flex flex-wrap align-center ga-2">
            <v-btn size="small" variant="tonal" color="primary" prepend-icon="mdi-calendar-plus" @click="$refs.personalAppointments.open()">Termin hinzufügen</v-btn>
            <v-btn-toggle v-if="tableViewAllowed" v-model="timetable_view_mode" mandatory color="primary" density="compact" class="timetable-view-toggle">
                <v-btn value="list" size="small" title="Listenansicht"><v-icon size="18">mdi-format-list-bulleted</v-icon></v-btn>
                <v-btn value="table" size="small" title="Tabellenansicht"><v-icon size="18">mdi-table</v-icon></v-btn>
            </v-btn-toggle>
            </div>
        </template>
        <PersonalAppointments ref="personalAppointments" :schoolyear="config?.selected_schoolyear" :school-hours="school_hours || []"
            :context-key="`${config?.user?.id}:${config?.selected_school?.id}:${config?.selected_schoolyear?.id}`"
            @loaded="appointmentDefinitions = $event" @load-error="appointmentLoadError = $event" />
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text class="text-body-1 d-flex flex-column ga-2">
                <v-btn-toggle v-model="rangeSelection" mandatory color="primary" class="w-100 timetable-range-toggle">
                    <v-btn :value="RANGE_TODAY">Heute</v-btn>
                    <v-btn :value="RANGE_WEEK">Diese Woche</v-btn>
                    <v-btn :value="RANGE_NEXT_WEEK">Nächster Unterricht</v-btn>
                    <v-btn :value="RANGE_MONTH">Dieser Monat</v-btn>
                    <v-btn :value="RANGE_CURRENT_SEMESTER">{{ currentSemesterButtonLabel }}</v-btn>
                </v-btn-toggle>

                <!-- Navigation -->
                <div class="d-flex align-center ga-2">
                    <v-btn
                        icon="mdi-chevron-left"
                        size="small"
                        variant="tonal"
                        :disabled="!canNavigatePrevious"
                        @click="navigatePrevious"
                    />
                    <div class="flex-grow-1 text-center text-caption">
                        <span v-if="dateRangeLabel">{{ dateRangeLabel }}</span>
                    </div>
                    <v-btn
                        icon="mdi-chevron-right"
                        size="small"
                        variant="tonal"
                        :disabled="!canNavigateNext"
                        @click="navigateNext"
                    />
                </div>

                <!-- List View -->
                <div v-if="appointmentLoadError" class="text-caption text-warning" role="status">{{ appointmentLoadError }}</div>
                <v-card v-if="activeViewMode === 'list'" variant="outlined" class="mt-2">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-format-list-bulleted</v-icon>
                        Unterricht
                        <v-chip size="x-small" color="primary" variant="tonal">{{ filteredItems.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-0">
                        <v-list density="compact">
                            <v-list-item v-for="appointment in personalAppointmentOccurrences" :key="`appointment-${appointment.id}-${appointment.occurrenceDate}-${appointment.occurrenceHour || 'all'}`" class="pa-2">
                                <PersonalAppointmentCard :appointment="appointment" show-date @edit="$refs.personalAppointments.open($event)" />
                            </v-list-item>
                            <v-list-item v-for="item in filteredItems" :key="item.key" class="cursor-pointer pa-0" @click="openCourse(item)">
                                <div :class="['d-flex flex-column ga-2 w-100 pa-3', ...getStatusClass(item), { 'timetable-item--upcoming': isUpcomingLesson(item) }]" :style="getDateBackgroundStyle(item)">
                                    <div class="d-flex flex-wrap align-center ga-2 w-100">
                                        <span v-if="hasFreeStatus(item)" class="timetable-cancelled-label">Entfallen</span>
                                        <v-chip v-if="isToday(item)" size="x-small" color="warning" variant="flat">Heute</v-chip>
                                        <v-icon
                                            v-if="isAttendanceChecked(item)"
                                            size="16"
                                            color="success"
                                            title="Anwesenheit geprüft">
                                            mdi-check-circle
                                        </v-icon>
                                        <v-chip v-if="!isToday(item)" size="x-small" variant="tonal" color="primary">{{ formatWeekdayDate(item.date) }}</v-chip>
                                        <v-chip v-for="hour in item.hours" :key="hour" size="x-small" variant="outlined"
                                            :color="hasFreeStatus(item, hour) ? 'success' : 'primary'"
                                            :class="{ 'timetable-item--free': hasFreeStatus(item, hour) }">{{ hour }}. Std</v-chip>
                                        <v-chip size="x-small" variant="outlined" color="primary">{{ item.timeRangeLabel }}</v-chip>
                                        <v-chip size="x-small" variant="outlined">{{ item.classLabel }}</v-chip>
                                        <v-chip size="x-small" variant="tonal" color="primary" class="chip-truncate">{{ item.courseTitle }}</v-chip>
                                        <v-icon
                                            v-if="item.hasCurriculumAssignment"
                                            size="10"
                                            color="green-darken-2"
                                            role="img"
                                            :aria-hidden="false"
                                            aria-label="Curriculum-Eintrag zugeordnet"
                                            title="Curriculum-Eintrag zugeordnet">mdi-circle</v-icon>
                                        <span
                                            v-if="item.curriculumAttachmentVisibility"
                                            class="d-inline-flex align-center ga-2"
                                            :title="item.curriculumAttachmentVisibility.label">
                                            <span
                                                v-for="indicator in item.curriculumAttachmentVisibility.indicators"
                                                :key="indicator.key"
                                                class="d-inline-flex align-center ga-1 text-caption"
                                                :class="`text-${indicator.color}`"
                                                role="img"
                                                :aria-label="indicator.label">
                                                <v-icon :icon="indicator.icon" :color="indicator.color" size="14" aria-hidden="true" />
                                                <span v-if="item.curriculumAttachmentVisibility.indicators.length > 1" aria-hidden="true">{{ indicator.count }}</span>
                                            </span>
                                        </span>
                                        <v-chip
                                            v-if="hasFreeStatus(item) && item.freeReason"
                                            size="x-small"
                                            variant="outlined"
                                            color="success"
                                        >
                                            {{ item.freeReason }}
                                        </v-chip>
                                    </div>
                                    <div v-if="item.content" class="text-caption timetable-content" v-html="contentHtml(item.content)"></div>
                                </div>
                            </v-list-item>
                            <v-list-item v-if="!filteredItems.length && !personalAppointmentOccurrences.length">
                                <v-list-item-title class="text-caption text-medium-emphasis">Keine Termine im gewaehlten Zeitraum.</v-list-item-title>
                            </v-list-item>
                        </v-list>
                    </v-card-text>
                </v-card>

                <!-- Table View -->
                <v-card v-else variant="outlined" class="mt-2 timetable-table-card">
                    <v-card-title class="text-subtitle-2 d-flex align-center ga-2">
                        <v-icon size="18">mdi-table</v-icon>
                        Stundenplan
                        <v-chip size="x-small" color="primary" variant="tonal">{{ filteredItems.length }}</v-chip>
                    </v-card-title>
                    <v-divider />
                    <v-card-text class="pa-1">
                        <div class="timetable-table-wrapper">
                            <div class="timetable-grid-width" :style="{ minWidth: `${44 + tableWeekDays.length * 130}px` }">
                            <table v-scale-appointments class="timetable-grid-table">
                                <colgroup>
                                    <col style="width: 44px">
                                    <col v-for="day in tableWeekDays" :key="normalizeDateToString(day)">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="timetable-hour-header-cell"></th>
                                        <th
                                            v-for="day in tableWeekDays"
                                            :key="normalizeDateToString(day)"
                                            :class="['timetable-day-header-cell', { 'day-today': isDayToday(day), 'day-highlighted': isHighlightedTeachingDay(day) }]"
                                            :aria-label="isHighlightedTeachingDay(day) ? `${isDayToday(day) ? 'Heutiger Unterrichtstag' : 'Nächster Unterrichtstag'}: ${formatDayDate(day)}` : undefined">
                                            <div>{{ formatDayOfWeek(day) }}</div>
                                            <div class="timetable-day-date">{{ formatDayDate(day) }}</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="outsideAppointmentPlacements.length">
                                        <td class="timetable-hour-cell"><span class="text-caption">Vor Unterricht</span></td>
                                        <td v-for="day in tableWeekDays" :key="normalizeDateToString(day)" class="timetable-grid-cell timetable-gap-cell" :class="{ 'day-highlighted': isHighlightedTeachingDay(day) }">
                                            <div class="personal-appointment-cell">
                                                <PersonalAppointmentCard v-for="placement in outsideAppointmentsOnDay(day)" :key="`${placement.appointment.id}-${placement.segments[0].starts_at}`"
                                                    class="timetable-duration-card" :data-duration="personalAppointmentDuration(placement.segments)" :data-gap-minutes="placement.gapMinutes"
                                                    :appointment="{ ...placement.appointment, time_segments: placement.segments }" @edit="$refs.personalAppointments.open(placement.appointment)" />
                                            </div>
                                        </td>
                                    </tr>
                                    <template v-for="hour in tableHours" :key="hour">
                                    <tr :data-hour="hour" :data-duration="personalAppointmentDuration([{ starts_at: schoolHoursByHour[hour]?.from, ends_at: schoolHoursByHour[hour]?.until }])">
                                        <td class="timetable-hour-cell">
                                            <div class="timetable-hour-num">{{ hour }}.</div>
                                            <div v-if="schoolHoursByHour[hour]" class="timetable-hour-time">
                                                {{ formatTimeValue(schoolHoursByHour[hour]?.from) }}<br>{{ formatTimeValue(schoolHoursByHour[hour]?.until) }}
                                            </div>
                                        </td>
                                        <template v-for="day in tableWeekDays" :key="normalizeDateToString(day)">
                                        <td v-if="!isTableCellContinuation(day, hour)" v-fill-cell :rowspan="tableRowSpan(day, hour)"
                                            :data-date="normalizeDateToString(day)" :data-hour="hour"
                                            :class="['timetable-grid-cell', { 'day-highlighted': isHighlightedTeachingDay(day) }]">
                                            <div class="timetable-cell-content">
                                            <div v-if="getTableCellItems(day, hour).length" class="timetable-cell-courses">
                                            <div
                                                v-for="item in getTableCellItems(day, hour)"
                                                :key="item.key"
                                                :class="['timetable-grid-item', ...getStatusClass(item, hour), { 'timetable-grid-item--block': tableCellSpan(day, hour) > 1, 'timetable-item--upcoming': tableBlockHours(day, hour).some((blockHour) => isUpcomingLesson(item, blockHour)) }]"
                                                :style="tableCellSpan(day, hour) > 1 ? { minHeight: `${tableCellSpan(day, hour) * 52}px` } : undefined"
                                                @click="openCourse(item)">
                                                <div v-if="hasFreeStatus(item, hour)" class="timetable-cancelled-label">Entfallen</div>
                                                <div class="d-flex align-center ga-1">
                                                    <v-icon
                                                        v-if="item.hasCurriculumAssignment"
                                                        size="10"
                                                        color="green-darken-2"
                                                        role="img"
                                                        :aria-hidden="false"
                                                        aria-label="Curriculum-Eintrag zugeordnet"
                                                        title="Curriculum-Eintrag zugeordnet">mdi-circle</v-icon>
                                                    <span
                                                        v-if="item.curriculumAttachmentVisibility"
                                                        class="d-inline-flex align-center ga-2"
                                                        :title="item.curriculumAttachmentVisibility.label">
                                                        <span
                                                            v-for="indicator in item.curriculumAttachmentVisibility.indicators"
                                                            :key="indicator.key"
                                                            class="d-inline-flex align-center ga-1 text-caption"
                                                            :class="`text-${indicator.color}`"
                                                            role="img"
                                                            :aria-label="indicator.label">
                                                            <v-icon :icon="indicator.icon" :color="indicator.color" size="14" aria-hidden="true" />
                                                            <span v-if="item.curriculumAttachmentVisibility.indicators.length > 1" aria-hidden="true">{{ indicator.count }}</span>
                                                        </span>
                                                    </span>
                                                    <div class="timetable-grid-course">{{ item.courseTitle }}</div>
                                                </div>
                                                <div class="timetable-grid-class">{{ item.classLabel }}</div>
                                                <div v-if="tableCellSpan(day, hour) > 1" class="timetable-grid-class">
                                                    {{ hour }}.–{{ Number(hour) + tableCellSpan(day, hour) - 1 }}. Std
                                                    <template v-if="formatHoursTimeRange(tableBlockHours(day, hour)) !== '-'"> · {{ formatHoursTimeRange(tableBlockHours(day, hour)) }}</template>
                                                </div>
                                                <div v-if="range === RANGE_TODAY && item.content" class="timetable-grid-content" v-html="contentHtml(item.content)"></div>
                                                <v-icon v-if="isAttendanceChecked(item)" size="12" color="success">mdi-check-circle</v-icon>
                                            </div>
                                            </div>
                                            <div v-for="column in tablePersonalAppointmentColumns(day, hour)" :key="column.appointment.id"
                                                class="timetable-cell-appointments" :style="{ gridTemplateRows: tableBlockSlots(day, hour).map((slot) => slot.gap ? 'minmax(28px, auto)' : 'minmax(0, 1fr)').join(' ') }">
                                                <PersonalAppointmentCard v-for="block in column.blocks" :key="block.start"
                                                    :class="{ 'timetable-duration-card': tableBlockSlots(day, hour)[block.start - 1]?.gap }"
                                                    :data-duration="tableBlockSlots(day, hour)[block.start - 1]?.gap ? personalAppointmentDuration(block.time_segments) : undefined"
                                                    :data-gap-minutes="block.gapMinutes"
                                                    :appointment="{ ...(block.appointment || column.appointment), time_segments: block.time_segments }"
                                                    :style="{ gridRow: `${block.start} / span ${block.span}` }"
                                                    @edit="$refs.personalAppointments.open({ ...(block.appointment || column.appointment), occurrenceHour: block.time_segments.length === 1 ? block.time_segments[0].hour : null })" />
                                            </div>
                                            </div>
                                        </td>
                                        </template>
                                    </tr>
                                    <tr v-if="hasAppointmentGapAfter(hour)" class="timetable-gap-row" :data-gap-after="hour">
                                        <td class="timetable-hour-cell"><span class="text-caption">{{ school_hours.some((entry) => Number(entry.hour) > Number(hour)) ? 'Pause' : '' }}</span></td>
                                        <template v-for="day in tableWeekDays" :key="normalizeDateToString(day)">
                                            <td v-if="!tableCellsConnect(day, hour, Number(hour) + 1)" class="timetable-grid-cell timetable-gap-cell" :class="{ 'day-highlighted': isHighlightedTeachingDay(day) }" :data-date="normalizeDateToString(day)">
                                                <div class="personal-appointment-cell">
                                                    <PersonalAppointmentCard v-for="placement in gapAppointmentsOnDay(day, hour)" :key="`${placement.appointment.id}-${placement.segments[0].starts_at}`"
                                                        class="timetable-duration-card" :data-duration="personalAppointmentDuration(placement.segments)" :data-gap-minutes="placement.gapMinutes"
                                                        :appointment="{ ...placement.appointment, time_segments: placement.segments }" @edit="$refs.personalAppointments.open(placement.appointment)" />
                                                </div>
                                            </td>
                                        </template>
                                    </tr>
                                    </template>
                                    <tr v-if="!tableHours.length">
                                        <td :colspan="tableWeekDays.length + 1" class="text-caption text-medium-emphasis pa-3">Keine Termine im gewählten Zeitraum.</td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </v-card-text>
                </v-card>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { applicationDate, parseLocalDate } from '@/helpers/date'
import { cancelledCourseDateHours, activeCourseDateHours, isCourseDateHourCancelled } from '@/helpers/courseDateHours'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseDateStore } from '@/stores/admin/teaching/CourseDateStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import PersonalAppointments from './PersonalAppointments.vue'
import PersonalAppointmentCard from './PersonalAppointmentCard.vue'
import { nextPersonalAppointmentDate, personalAppointmentDuration, personalAppointmentHeight, personalAppointmentGridPlacement, personalAppointmentOccurrences, personalAppointmentTimeline } from '@/helpers/teachingPersonalAppointments'

const RANGE_TODAY = 'today'
const RANGE_WEEK = 'week'
const RANGE_NEXT_WEEK = 'next_week'
const RANGE_MONTH = 'month'
const RANGE_CURRENT_SEMESTER = 'current_semester'

function curriculumAttachmentVisibility(courseDate, hasAssignment) {
    if (!hasAssignment) return null

    let sharedCount = Number(courseDate?.shared_curriculum_attachments_count ?? 0)
    let privateCount = Number(courseDate?.private_curriculum_attachments_count ?? 0)

    if (Array.isArray(courseDate?.adopted_materials)) {
        const attachments = courseDate.adopted_materials.flatMap((material) =>
            Array.isArray(material?.attachments) ? material.attachments : [],
        )
        const visibilityByFile = new Map()
        attachments.forEach((attachment, index) => {
            const key = attachment.source_teaching_curriculum_document_id
                ? `document-${attachment.source_teaching_curriculum_document_id}`
                : `attachment-${attachment.id ?? index}`
            visibilityByFile.set(key, visibilityByFile.get(key) === true || attachment.student_visible === true)
        })
        sharedCount = [...visibilityByFile.values()].filter(Boolean).length
        privateCount = visibilityByFile.size - sharedCount + Number(courseDate?.unadopted_curriculum_attachments_count ?? 0)
    }

    const indicators = [
        {
            key: 'shared', icon: 'mdi-eye', color: 'success', count: sharedCount,
            label: sharedCount === 1 ? '1 veröffentlichter Anhang' : `${sharedCount} veröffentlichte Anhänge`,
            summary: `${sharedCount} veröffentlicht`,
        },
        {
            key: 'private', icon: 'mdi-eye-off', color: 'grey-darken-1', count: privateCount,
            label: privateCount === 1 ? '1 verborgener Anhang' : `${privateCount} verborgene Anhänge`,
            summary: `${privateCount} verborgen`,
        },
    ].filter((indicator) => indicator.count > 0)

    if (indicators.length === 0) {
        return {
            indicators: [{ key: 'private', icon: 'mdi-eye-off', color: 'grey-darken-1', count: 0, label: 'Keine Anhänge veröffentlicht' }],
            label: 'Keine Anhänge veröffentlicht',
        }
    }

    return { indicators, label: indicators.map((indicator) => indicator.summary).join(', ') }
}

const timetableCellObservers = new WeakMap()
const timetableDurationObservers = new WeakMap()

const scaleAppointments = {
    mounted(table) {
        if (typeof ResizeObserver === 'undefined') return
        const update = () => {
            const measurements = [...table.querySelectorAll('tr[data-hour][data-duration]')].map((row) => ({
                minutes: Number(row.dataset.duration), height: row.getBoundingClientRect().height - 2,
            }))
            for (const card of table.querySelectorAll('.timetable-duration-card')) {
                const height = personalAppointmentHeight(Number(card.dataset.duration), measurements)
                const value = height === null ? '' : `${height}px`
                if (card.style.minHeight !== value) card.style.minHeight = value
                const gap = personalAppointmentHeight(Number(card.dataset.gapMinutes), measurements)
                const margin = `${gap || 0}px`
                if (card.style.marginTop !== margin) card.style.marginTop = margin
            }
        }
        const observer = new ResizeObserver(update)
        observer.observe(table)
        timetableDurationObservers.set(table, { observer, update })
        update()
    },
    updated(table) {
        timetableDurationObservers.get(table)?.update()
    },
    unmounted(table) {
        timetableDurationObservers.get(table)?.observer.disconnect()
        timetableDurationObservers.delete(table)
    },
}

const fillCell = {
    mounted(cell) {
        if (typeof ResizeObserver === 'undefined') return
        const content = cell.querySelector('.timetable-cell-content')
        if (!content) return
        const observer = new ResizeObserver(() => {
            const height = cell.getBoundingClientRect().height - 2
            const value = `${Math.max(0, height)}px`
            if (content.style.getPropertyValue('--timetable-cell-height') !== value) content.style.setProperty('--timetable-cell-height', value)
        })
        timetableCellObservers.set(cell, observer)
        observer.observe(cell)
    },
    unmounted(cell) {
        timetableCellObservers.get(cell)?.disconnect()
        timetableCellObservers.delete(cell)
    },
}

export default {
    directives: { fillCell, scaleAppointments },
    components: { ItsGridBox, PersonalAppointments, PersonalAppointmentCard },

    beforeMount() {
        const courseStore = useCourseStore()
        const returnState = courseStore.getTimetableReturn(this.$route?.path)
            || courseStore.getTimetableView(this.$route?.path, this.$route?.query)
        this.pendingTimetableRestore = returnState
        if (returnState) {
            this.range = returnState.range
            this.offset = returnState.offset
            this.timetable_view_mode = returnState.viewMode
        } else {
            this.range = this.defaultRange()
        }
        this.schoolHourStore = useSchoolHourStore()
        if (!Array.isArray(this.school_hours) || this.school_hours.length === 0) {
            this.schoolHoursRequest = this.schoolHourStore.index()
        }
    },

    async mounted() {
        this.nowTimer = setInterval(() => {
            this.nowTs = Date.now()
        }, 1000)
        const courseStore = useCourseStore()
        const returnState = this.pendingTimetableRestore
        const initialRange = this.range
        courseStore.timetable_return_state = null
        window.addEventListener('scroll', this.persistTimetableView, { capture: true, passive: true })
        window.addEventListener('pagehide', this.persistTimetableView)
        const loadResults = await Promise.all([courseStore.courses_request_promise, this.schoolHoursRequest])
        await this.$nextTick()
        if (this.timetableDisposed || this.selected_course || loadResults.includes(false)) return
        const canRestore = returnState && courseStore.isTimetableStateCurrent(returnState, this.$route?.path)
        if ((!returnState && this.range === initialRange && this.offset === 0) || (returnState && !canRestore)) {
            this.range = this.defaultRange()
            this.offset = 0
        }
        if (canRestore) {
            const table = this.$el.querySelector('.timetable-table-wrapper')
            if (table) {
                table.scrollLeft = returnState.tableLeft
                table.scrollTop = returnState.tableTop
            }
            window.scrollTo({ left: returnState.left, top: returnState.top, behavior: 'instant' })
        }
        this.pendingTimetableRestore = null
        this.timetableReady = true
        this.persistTimetableView()
    },

    beforeUnmount() {
        clearInterval(this.nowTimer)
        this.timetableDisposed = true
        window.removeEventListener('scroll', this.persistTimetableView, true)
        window.removeEventListener('pagehide', this.persistTimetableView)
    },

    data() {
        return {
            RANGE_TODAY,
            RANGE_WEEK,
            RANGE_NEXT_WEEK,
            RANGE_MONTH,
            RANGE_CURRENT_SEMESTER,
            range: RANGE_NEXT_WEEK,
            offset: 0,
            schoolHourStore: null,
            schoolHoursRequest: null,
            pendingTimetableRestore: null,
            timetableReady: false,
            timetableDisposed: false,
            nowTs: Date.now(),
            nowTimer: null,
            appointmentDefinitions: [],
            appointmentLoadError: '',
        }
    },

    computed: {
        personalAppointmentOccurrences() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return []
            return personalAppointmentOccurrences(this.appointmentDefinitions, this.normalizeDateToString(from), this.normalizeDateToString(until))
        },
        appointmentBoundaryItems() {
            return this.appointmentDefinitions.flatMap((appointment) => [
                { dateObj: parseLocalDate(appointment.date) },
                { dateObj: parseLocalDate(appointment.repeat_until || appointment.date) },
            ])
        },
        rangeSelection: {
            get() {
                return this.range === RANGE_NEXT_WEEK && this.offset !== 0 ? null : this.range
            },
            set(value) {
                if (!value) return
                this.range = value
                this.resetOffset()
            },
        },
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config']),
        ...mapWritableState(useCourseStore, [
            'courses',
            'selected_course',
            'selected_course_id',
            'selected_course_student',
            'show_students',
            'show_infos',
            'show_works',
            'show_print',
            'show_lists',
            'show_dates',
            'show_table',
            'show_curriculum',
            'show_attendance',
            'show_performances',
            'show_performances_plus',
            'timetable_view_mode',
        ]),
        ...mapWritableState(useCourseDateStore, ['selected_courseDate']),
        ...mapWritableState(useSchoolHourStore, ['school_hours']),
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
        myCourses() {
            const userId = this.config?.user?.id
            const list = Array.isArray(this.courses) ? this.courses : []
            if (!userId) return []
            return list.filter((course) => course?.user_id === userId)
        },
        timetableItems() {
            return (this.myCourses || [])
                .flatMap((course) => {
                    const classLabel = Array.isArray(course?.classes) ? course.classes.join(', ') : ''
                    const courseTitle = (course?.title || '').toString().trim()
                    const dates = Array.isArray(course?.course_dates) ? course.course_dates : []

                    return dates.map((courseDate) => {
                        const date = (courseDate?.date || '').toString().slice(0, 10)
                        const dateObj = parseLocalDate(date)
                        const hoursRaw = Array.isArray(courseDate?.hours) ? courseDate.hours : []
                        const hours = [...hoursRaw]
                            .map((h) => Number(h))
                            .filter((h) => Number.isFinite(h))
                            .sort((a, b) => a - b)
                        const hoursLabel = hours.length ? hours.map((h) => `${h}. Std`).join(', ') : '-'
                        const timeRangeLabel = this.formatHoursTimeRange(hours)

                        const status = Array.isArray(courseDate?.status) ? courseDate.status : []
                        const hasCurriculumAssignment = Array.isArray(courseDate?.adopted_materials)
                            ? courseDate.adopted_materials.length > 0
                            : courseDate?.has_curriculum_assignment === true

                        return {
                            key: `${course?.id || 'x'}-${courseDate?.id || date}-${hoursLabel}`,
                            courseId: course?.id || null,
                            courseDateId: courseDate?.id || null,
                            date,
                            dateObj,
                            hours,
                            cancelled_hours: cancelledCourseDateHours(courseDate),
                            hoursLabel,
                            timeRangeLabel,
                            classLabel: classLabel || '-',
                            courseTitle: courseTitle || '-',
                            content: (courseDate?.content || '').toString().trim(),
                            hasCurriculumAssignment,
                            curriculumAttachmentVisibility: curriculumAttachmentVisibility(courseDate, hasCurriculumAssignment),
                            freeReason: (courseDate?.free_reason || '').toString().trim(),
                            status,
                            attendanceChecked: typeof courseDate?.attendance_checked === 'boolean'
                                ? courseDate.attendance_checked
                                : status.includes('att_checked:1'),
                        }
                    })
                })
                .filter((item) => !isNaN(item.dateObj.getTime()))
                .sort((a, b) => {
                    const dateCmp = a.date.localeCompare(b.date)
                    if (dateCmp !== 0) return dateCmp
                    const hourA = a.hours[0] ?? 999
                    const hourB = b.hours[0] ?? 999
                    if (hourA !== hourB) return hourA - hourB
                    return a.courseTitle.localeCompare(b.courseTitle, 'de', { sensitivity: 'base' })
                })
        },
        viennaNow() {
            const now = new Date(this.nowTs)
            return {
                date: applicationDate(now),
                time: now.toLocaleTimeString('en-GB', { timeZone: 'Europe/Vienna', hourCycle: 'h23' }),
            }
        },
        highlightedTeachingDate() {
            const today = parseLocalDate(this.viennaNow.date)
            return this.timetableItems.find((item) => !this.hasFreeStatus(item) && item.dateObj >= today)?.date || null
        },
        filteredItems() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return this.timetableItems
            return this.timetableItems.filter((item) => item.dateObj >= from && item.dateObj <= until)
        },
        dateBackgroundByDate() {
            const accentColor = '#e3f2fd'
            const mapping = {}
            let dateIndex = 0

            for (const item of this.filteredItems) {
                const date = (item?.date || '').toString().slice(0, 10)
                if (!date || mapping[date]) continue
                if (dateIndex % 2 === 0) {
                    mapping[date] = '#ffffff'
                } else {
                    mapping[date] = accentColor
                }
                dateIndex++
            }

            return mapping
        },
        semesterMeta() {
            const schoolyear = this.config?.selected_schoolyear || {}
            const normalizeConfiguredDate = (value) => {
                if (!value) return null
                const parsed = this.normalizeDay(parseLocalDate(value))
                if (isNaN(parsed.getTime())) return null
                return parsed
            }

            const today = parseLocalDate(applicationDate())
            const schoolFrom = normalizeConfiguredDate(schoolyear?.from)
            const schoolUntil = normalizeConfiguredDate(schoolyear?.until)
            const sem2Start = normalizeConfiguredDate(
                schoolyear?.sem_2_start || this.config?.user?.teaching_count_for_semester_2_date
            )
            const baseSemester = sem2Start && today >= sem2Start ? 2 : 1

            return { today, schoolFrom, schoolUntil, sem2Start, baseSemester }
        },
        selectedSemesterNumber() {
            const base = this.semesterMeta.baseSemester
            const sem2Exists = !!this.semesterMeta.sem2Start
            if (!sem2Exists) return 1

            const delta = this.range === RANGE_CURRENT_SEMESTER ? this.offset : 0
            const target = base + delta
            return Math.min(2, Math.max(1, target))
        },
        currentSemesterButtonLabel() {
            return `${this.selectedSemesterNumber}. Semester`
        },
        dateRangeLabel() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return ''
            const formatDate = (d) => d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
            if (from.getTime() === until.getTime()) {
                return formatDate(from)
            }
            return `${formatDate(from)} - ${formatDate(until)}`
        },
        canNavigatePrevious() {
            if (this.range === RANGE_CURRENT_SEMESTER) {
                if (!this.semesterMeta.sem2Start) return false
                return this.selectedSemesterNumber > 1
            }
            const items = [...this.timetableItems, ...(this.appointmentBoundaryItems || [])].sort((left, right) => left.dateObj - right.dateObj)
            if (!items.length) return false
            const [from] = this.currentRangeBounds()
            if (!from) return false
            const earliestDate = items[0]?.dateObj
            if (!earliestDate) return false
            return earliestDate < from
        },
        canNavigateNext() {
            if (this.range === RANGE_CURRENT_SEMESTER) {
                if (!this.semesterMeta.sem2Start) return false
                return this.selectedSemesterNumber < 2
            }
            const items = [...this.timetableItems, ...(this.appointmentBoundaryItems || [])].sort((left, right) => left.dateObj - right.dateObj)
            if (!items.length) return false
            const [, until] = this.currentRangeBounds()
            if (!until) return false
            const latestDate = items[items.length - 1]?.dateObj
            if (!latestDate) return false
            return latestDate > until
        },
        tableViewAllowed() {
            return this.range === RANGE_TODAY || this.range === RANGE_WEEK || this.range === RANGE_NEXT_WEEK
        },
        activeViewMode() {
            return this.tableViewAllowed ? this.timetable_view_mode : 'list'
        },
        tableWeekDays() {
            const [from, until] = this.currentRangeBounds()
            if (!from || !until) return []
            const days = []
            const cur = new Date(from)
            while (cur <= until) {
                const dayOfWeek = cur.getDay()
                if ((dayOfWeek >= 1 && dayOfWeek <= 5) || (this.personalAppointmentOccurrences || []).some((appointment) => appointment.occurrenceDate === this.normalizeDateToString(cur))) {
                    days.push(new Date(cur))
                }
                cur.setDate(cur.getDate() + 1)
            }
            return days
        },
        tableHours() {
            const schoolHourList = Array.isArray(this.school_hours) ? this.school_hours : []
            const appointmentHours = (this.appointmentGridPlacements || []).flatMap((placement) => placement.blocks.flatMap((block) => block.hours))
            const gapHours = (this.appointmentGridPlacements || []).flatMap((placement) => placement.gaps.map((gap) => gap.afterHour))
            const itemHours = [...this.filteredItems.flatMap((i) => i.hours), ...appointmentHours, ...gapHours]
            if (!itemHours.length && !schoolHourList.length) return []
            if (!itemHours.length) {
                return schoolHourList.map((sh) => Number(sh.hour)).sort((a, b) => a - b)
            }
            const firstTeachingHour = Math.min(...itemHours)
            const isWeekRange = this.range === RANGE_WEEK || this.range === RANGE_NEXT_WEEK
            const minHour = isWeekRange && firstTeachingHour >= 1 && firstTeachingHour <= 6 ? 1 : firstTeachingHour
            const maxHour = Math.max(...itemHours)
            if (schoolHourList.length > 0) {
                return [...new Set([...schoolHourList
                    .map((sh) => Number(sh.hour))
                    .filter((h) => h >= minHour && h <= maxHour), ...appointmentHours])]
                    .sort((a, b) => a - b)
            }
            const hours = []
            for (let h = minHour; h <= maxHour; h++) {
                hours.push(h)
            }
            return hours
        },
        appointmentGridPlacements() {
            return this.personalAppointmentOccurrences.map((appointment) => personalAppointmentGridPlacement(appointment, this.school_hours || []))
        },
        outsideAppointmentPlacements() {
            return this.appointmentGridPlacements.filter((placement) => placement.outside.length)
        },
        tableCellItems() {
            const map = {}
            this.filteredItems.forEach((item) => {
                item.hours.forEach((h) => {
                    const key = `${item.date}-${h}`
                    if (!map[key]) map[key] = []
                    map[key].push(item)
                })
            })
            return map
        },
    },

    watch: {
        range: { handler: 'persistTimetableView', flush: 'post' },
        offset: { handler: 'persistTimetableView', flush: 'post' },
        timetable_view_mode: { handler: 'persistTimetableView', flush: 'post' },
        '$route.query': { handler: 'persistTimetableView', deep: true, flush: 'post' },
    },

    methods: {
        personalAppointmentDuration,
        personalAppointmentsOnDay(day) {
            return this.personalAppointmentOccurrences.filter((appointment) => appointment.occurrenceDate === this.normalizeDateToString(day))
        },
        outsideAppointmentsOnDay(day) {
            const startsAt = this.outsideAppointmentPlacements.flatMap((placement) => placement.outside.map((segment) => segment.starts_at)).sort()[0]
            const placements = this.outsideAppointmentPlacements.filter((placement) => placement.appointment.occurrenceDate === this.normalizeDateToString(day))
                .map((placement) => ({ appointment: placement.appointment, segments: placement.outside }))
            return personalAppointmentTimeline(placements, startsAt)
        },
        tablePersonalAppointmentBlocks(day, hour) {
            const date = this.normalizeDateToString(day)
            return (this.appointmentGridPlacements || []).filter((placement) => placement.appointment.occurrenceDate === date)
                .flatMap((placement) => placement.blocks.map((block, index) => ({ ...block, appointment: placement.appointment, key: `${placement.appointment.id}-${index}` })))
                .filter((block) => block.hours.includes(Number(hour)))
        },
        tablePersonalAppointmentColumns(day, hour) {
            const hours = this.tableBlockHours(day, hour)
            const slots = this.tableBlockSlots(day, hour)
            const columns = new Map()
            for (const cellHour of hours) {
                for (const block of this.tablePersonalAppointmentBlocks(day, cellHour)) {
                    const clipped = block.hours.filter((value) => hours.includes(value))
                    if (cellHour !== clipped[0]) continue
                    if (!columns.has(block.appointment.id)) columns.set(block.appointment.id, { appointment: block.appointment, blocks: [] })
                    const start = slots.findIndex((slot) => !slot.gap && slot.hour === clipped[0])
                    const end = slots.findIndex((slot) => !slot.gap && slot.hour === clipped.at(-1))
                    columns.get(block.appointment.id).blocks.push({ appointment: block.appointment, start: start + 1, span: end - start + 1,
                        time_segments: block.time_segments.filter((segment) => !segment.hour || clipped.includes(Number(segment.hour))) })
                }
            }
            for (const [index, slot] of slots.entries()) {
                if (!slot.gap) continue
                for (const placement of this.gapAppointmentsOnDay(day, slot.hour)) {
                    if (!columns.has(placement.appointment.id)) columns.set(placement.appointment.id, { appointment: placement.appointment, blocks: [] })
                    columns.get(placement.appointment.id).blocks.push({ start: index + 1, span: 1, time_segments: placement.segments, gapMinutes: placement.gapMinutes })
                }
            }
            return [...columns.values()]
        },
        hasAppointmentGapAfter(hour) {
            return this.appointmentGridPlacements.some((placement) => placement.gaps.some((gap) => gap.afterHour === Number(hour)))
        },
        gapAppointmentsOnDay(day, hour) {
            const placements = this.appointmentGridPlacements.filter((placement) => placement.appointment.occurrenceDate === this.normalizeDateToString(day))
                .map((placement) => ({ appointment: placement.appointment, segments: placement.gaps.filter((gap) => gap.afterHour === Number(hour)).map((gap) => gap.segment) }))
                .filter((placement) => placement.segments.length)
            return personalAppointmentTimeline(placements, this.schoolHoursByHour[hour]?.until?.slice(0, 5))
        },
        tableBlockSlots(day, hour) {
            const hours = this.tableBlockHours(day, hour)
            return hours.flatMap((value, index) => [
                { hour: value, gap: false },
                ...(index < hours.length - 1 && this.hasAppointmentGapAfter(value) ? [{ hour: value, gap: true }] : []),
            ])
        },
        tableRowSpan(day, hour) {
            return this.tableBlockSlots(day, hour).length
        },
        defaultRange() {
            const today = parseLocalDate(applicationDate())
            const weekEnd = this.endOfWeek(today)
            return this.timetableItems.some((item) =>
                !this.hasFreeStatus(item) && item.dateObj >= today && item.dateObj <= weekEnd
            ) ? RANGE_WEEK : RANGE_NEXT_WEEK
        },
        persistTimetableView() {
            if (!this.timetableReady || this.timetableDisposed || this.selected_course) return
            const table = this.$el.querySelector('.timetable-table-wrapper')
            useCourseStore().rememberTimetableView({
                path: this.$route?.path,
                query: { ...this.$route?.query },
                range: this.range,
                offset: this.offset,
                viewMode: this.timetable_view_mode,
                left: window.scrollX,
                top: window.scrollY,
                tableLeft: table?.scrollLeft || 0,
                tableTop: table?.scrollTop || 0,
            })
        },
        normalizeDay(date) {
            const d = new Date(date)
            d.setHours(0, 0, 0, 0)
            return d
        },
        startOfWeek(date) {
            const d = this.normalizeDay(date)
            const day = d.getDay()
            const diff = day === 0 ? -6 : 1 - day
            d.setDate(d.getDate() + diff)
            return d
        },
        endOfWeek(date) {
            const start = this.startOfWeek(date)
            const end = new Date(start)
            end.setDate(start.getDate() + 6)
            return end
        },
        currentRangeBounds() {
            const today = parseLocalDate(applicationDate())
            let referenceDate = new Date(today)

            if (this.range === RANGE_TODAY) {
                // Shift by days
                referenceDate.setDate(today.getDate() + this.offset)
                return [referenceDate, referenceDate]
            }
            if (this.range === RANGE_WEEK) {
                // Shift by weeks (7 days)
                referenceDate.setDate(today.getDate() + (this.offset * 7))
                return [this.startOfWeek(referenceDate), this.endOfWeek(referenceDate)]
            }
            if (this.range === RANGE_NEXT_WEEK) {
                const nextCourseWeekStart = this.nextCourseWeekStart(today)
                referenceDate = new Date(nextCourseWeekStart)
                referenceDate.setDate(nextCourseWeekStart.getDate() + (this.offset * 7))
                return [this.startOfWeek(referenceDate), this.endOfWeek(referenceDate)]
            }
            if (this.range === RANGE_MONTH) {
                // Shift by months
                referenceDate.setMonth(today.getMonth() + this.offset)
                const start = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), 1)
                const end = new Date(referenceDate.getFullYear(), referenceDate.getMonth() + 1, 0)
                end.setHours(0, 0, 0, 0)
                return [start, end]
            }
            if (this.range === RANGE_CURRENT_SEMESTER) {
                return this.currentSemesterBounds(this.selectedSemesterNumber)
            }
            return [null, null]
        },
        nextCourseWeekStart(today) {
            const currentWeekEnd = this.endOfWeek(today)
            const nextCourseDate = this.timetableItems
                .filter((item) => !this.hasFreeStatus(item))
                .map((item) => item?.dateObj)
                .find((date) => date instanceof Date && !isNaN(date.getTime()) && date > currentWeekEnd)

            const appointmentDate = this.appointmentDefinitions?.length
                ? nextPersonalAppointmentDate(this.appointmentDefinitions, this.normalizeDateToString(today)) : null
            if (appointmentDate && (!nextCourseDate || parseLocalDate(appointmentDate) < nextCourseDate)) {
                return this.startOfWeek(parseLocalDate(appointmentDate))
            }

            if (nextCourseDate) {
                return this.startOfWeek(nextCourseDate)
            }

            const nextCalendarWeek = new Date(today)
            nextCalendarWeek.setDate(today.getDate() + 7)

            return this.startOfWeek(nextCalendarWeek)
        },
        currentSemesterBounds(semesterNumber) {
            const { schoolFrom, schoolUntil, sem2Start } = this.semesterMeta
            if (sem2Start) {
                if (semesterNumber === 1) {
                    const start = schoolFrom || new Date(sem2Start.getFullYear(), 0, 1)
                    const end = new Date(sem2Start)
                    end.setDate(end.getDate() - 1)
                    return [start, end]
                }
                const start = new Date(sem2Start)
                const end = schoolUntil || new Date(sem2Start.getFullYear(), 11, 31)
                return [start, end]
            }

            if (schoolFrom && schoolUntil) return [schoolFrom, schoolUntil]
            return [null, null]
        },
        formatDate(date) {
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatWeekdayDate(date) {
            const d = parseLocalDate(date)
            if (isNaN(d.getTime())) return ''
            return d.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        formatHoursTimeRange(hours) {
            const sortedHours = Array.isArray(hours)
                ? [...hours]
                    .map((hour) => Number(hour))
                    .filter((hour) => Number.isFinite(hour))
                    .sort((a, b) => a - b)
                : []
            if (!sortedHours.length) return '-'

            const firstHourConfig = this.schoolHoursByHour[sortedHours[0]]
            const lastHourConfig = this.schoolHoursByHour[sortedHours[sortedHours.length - 1]]
            const from = this.formatTimeValue(firstHourConfig?.from)
            const until = this.formatTimeValue(lastHourConfig?.until)
            if (!from || !until) return '-'

            return `${from} - ${until}`
        },
        formatTimeValue(value) {
            const raw = (value || '').toString().trim()
            if (!raw) return ''
            return raw.slice(0, 5)
        },
        contentHtml(text) {
            if (!text) return ''
            if (text.includes('<p>') || text.includes('<br')) return text
            return text
                .split('\n')
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
        openCourse(item) {
            const courseId = item?.courseId
            if (!courseId) return
            const course = (this.courses || []).find((c) => c?.id === courseId)
            if (!course) return

            const courseStore = useCourseStore()
            const table = this.$el?.querySelector('.timetable-table-wrapper')
            courseStore.rememberTimetableReturn({
                path: this.$route.path,
                query: { ...this.$route.query },
                range: this.range,
                offset: this.offset,
                viewMode: this.timetable_view_mode,
                left: window.scrollX,
                top: window.scrollY,
                tableLeft: table?.scrollLeft || 0,
                tableTop: table?.scrollTop || 0,
            })
            courseStore.ensureCourseStudentCollections(course)

            this.selected_course = course
            this.selected_course_id = course.id
            this.selected_course_student = null
            this.action_2 = ''
            this.selected_courseDate = null
            this.show_students = false
            this.show_infos = false
            this.show_works = false
            this.show_print = false
            this.show_lists = false
            this.show_dates = false
            this.show_table = true
            this.show_curriculum = false
            this.show_attendance = false
            this.show_performances = false
            this.show_performances_plus = false

            const query = {
                ...this.$route.query,
                course: String(course.id),
                panel: 'table',
            }
            delete query.date
            delete query.work
            delete query.view
            this.$router.replace({ query }).catch(() => {})
        },
        normalizeDateToString(date) {
            const y = date.getFullYear()
            const m = String(date.getMonth() + 1).padStart(2, '0')
            const d = String(date.getDate()).padStart(2, '0')
            return `${y}-${m}-${d}`
        },
        isDayToday(day) {
            return this.normalizeDateToString(day) === this.viennaNow.date
        },
        isHighlightedTeachingDay(day) {
            return this.normalizeDateToString(day) === this.highlightedTeachingDate
        },
        formatDayOfWeek(day) {
            return day.toLocaleDateString('de-DE', { weekday: 'short' })
        },
        formatDayDate(day) {
            return day.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
        },
        getTableCellItems(day, hour) {
            const key = `${this.normalizeDateToString(day)}-${hour}`
            return this.tableCellItems[key] || []
        },
        tableCellsConnect(day, hour, adjacentHour) {
            const current = this.getTableCellItems(day, hour)
            const adjacent = this.getTableCellItems(day, adjacentHour)
            if (!current.length && !adjacent.length) {
                const currentBlocks = this.tablePersonalAppointmentBlocks(day, hour).map((block) => block.key).sort()
                const adjacentBlocks = this.tablePersonalAppointmentBlocks(day, adjacentHour).map((block) => block.key).sort()
                return currentBlocks.length > 0 && currentBlocks.join('|') === adjacentBlocks.join('|')
            }
            if (current.length !== 1 || adjacent.length !== 1) return false
            const item = current[0]
            const next = adjacent[0]
            return item.courseId != null && String(next.courseId) === String(item.courseId)
                && item.key === next.key
                && this.getStatusClass(item, hour).join(' ') === this.getStatusClass(next, adjacentHour).join(' ')
        },
        isTableCellContinuation(day, hour) {
            return this.tableHours.includes(Number(hour) - 1) && this.tableCellsConnect(day, hour, Number(hour) - 1)
        },
        tableCellSpan(day, hour) {
            let span = 1
            while (this.tableHours.includes(Number(hour) + span) && this.tableCellsConnect(day, Number(hour) + span - 1, Number(hour) + span)) {
                span++
            }
            return span
        },
        tableBlockHours(day, hour) {
            return Array.from({ length: this.tableCellSpan(day, hour) }, (_, index) => Number(hour) + index)
        },
        navigatePrevious() {
            if (this.range === RANGE_CURRENT_SEMESTER && !this.canNavigatePrevious) return
            this.offset--
        },
        navigateNext() {
            if (this.range === RANGE_CURRENT_SEMESTER && !this.canNavigateNext) return
            this.offset++
        },
        resetOffset() {
            this.offset = 0
        },
        getStatusClass(item, hour) {
            const classes = []
            if (this.hasExamStatus(item)) {
                classes.push('timetable-item--exam')
            }
            if (this.hasFreeStatus(item, hour)) {
                classes.push('timetable-item--free')
            }
            return classes
        },
        getDateBackgroundStyle(item) {
            if (this.hasExamStatus(item) || this.hasFreeStatus(item)) return {}
            const date = (item?.date || '').toString().slice(0, 10)
            if (!date) return {}
            const backgroundColor = this.dateBackgroundByDate[date]
            if (!backgroundColor) return {}
            return { backgroundColor }
        },
        hasExamStatus(item) {
            const status = Array.isArray(item?.status) ? item.status : []
            const statusStr = status.join(' ').toLowerCase()
            return statusStr.includes('pruefung') || statusStr.includes('prüfung')
        },
        hasFreeStatus(item, hour) {
            if (hour !== undefined && isCourseDateHourCancelled(item, hour)) return true
            if (item?.hours?.length && activeCourseDateHours(item).length === 0) return true
            const status = Array.isArray(item?.status) ? item.status : []
            const statusStr = status.join(' ').toLowerCase()
            return statusStr.includes('frei')
                || statusStr.includes('free')
                || statusStr.includes('entfaellt')
                || statusStr.includes('entfällt')
                || statusStr.includes('entfallen')
        },
        isToday(item) {
            return item.date === this.viennaNow.date
        },
        isUpcomingLesson(item, hour) {
            if (this.hasFreeStatus(item) || item.date !== this.highlightedTeachingDate) return false
            const hours = hour === undefined ? item.hours : [hour]
            return hours.some((lessonHour) => {
                if (isCourseDateHourCancelled(item, lessonHour)) return false
                const schoolHour = this.schoolHoursByHour[lessonHour]
                const from = this.lessonTime(schoolHour?.from)
                const until = this.lessonTime(schoolHour?.until)
                if (!from || !until || from >= until) return false
                return item.date > this.viennaNow.date
                    || (item.date === this.viennaNow.date && this.viennaNow.time < until)
            })
        },
        lessonTime(value) {
            const time = (value || '').toString().trim()
            if (!/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(time)) return null
            return time.length === 5 ? `${time}:00` : time
        },
        isAttendanceChecked(item) {
            if (!item) return false
            if (typeof item.attendanceChecked === 'boolean') return item.attendanceChecked
            const status = Array.isArray(item?.status) ? item.status : []
            return status.includes('att_checked:1')
        },
    },
}
</script>

<style scoped>
.timetable-range-toggle {
    flex-wrap: wrap;
    row-gap: 4px;
    height: auto !important;
}

.timetable-range-toggle :deep(.v-btn) {
    height: 52px !important;
}

.chip-truncate {
    max-width: 100%;
}

.chip-truncate :deep(.v-chip__content) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.timetable-content :deep(p) {
    margin: 0;
    min-height: 1.2em;
}

.timetable-item--exam {
    background-color: #ffebee !important;
    border-left: 4px solid #ff5722;
}

.timetable-item--free {
    background-color: #eff6f0 !important;
    color: #4d6956;
    border-left: 4px solid #8eb89a;
}

.timetable-cancelled-label {
    display: inline-block;
    width: fit-content;
    padding: 1px 5px;
    margin-bottom: 3px;
    border-radius: 4px;
    background-color: #2e7d46;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 1.4;
    letter-spacing: 0.04em;
}

.timetable-item--exam.timetable-item--free {
    background-color: #eff6f0 !important;
    border-left-color: #8eb89a;
}

.timetable-item--upcoming {
    border-left: 4px solid #ff9800 !important;
}

.timetable-view-toggle {
    height: 32px;
}

.timetable-table-wrapper {
    overflow-x: auto;
}

.personal-appointment-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 4px;
}

.timetable-cell-content { display: flex; gap: 2px; align-items: stretch; height: var(--timetable-cell-height, auto); min-height: min-content; }
.timetable-cell-courses, .timetable-cell-appointments { flex: 1; min-width: 84px; }
.timetable-cell-courses { display: flex; flex-direction: column; gap: 2px; }
.timetable-cell-courses > .timetable-grid-item { flex: 1; min-height: 52px; margin-bottom: 0; }
.timetable-cell-appointments { display: grid; gap: 0; }
.timetable-cell-appointments :deep(.personal-appointment-card) { padding: 4px 5px; gap: 0; }
.timetable-gap-cell { height: auto !important; }
.timetable-gap-cell .personal-appointment-cell { padding: 0; gap: 2px; }
.personal-appointment-cell :deep(.timetable-duration-card) { flex: none; }

.timetable-table-card {
    width: 100%;
    max-width: 100%;
}

.timetable-grid-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 0.8rem;
}

.timetable-grid-width {
    width: 100%;
}

.timetable-grid-table th,
.timetable-grid-table td {
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 0;
    vertical-align: top;
}

.timetable-hour-header-cell {
    width: 44px;
    min-width: 44px;
    background-color: #f5f5f5;
}

.timetable-day-header-cell {
    text-align: center;
    min-width: 130px;
    background-color: #f5f5f5;
    font-weight: 600;
    padding: 6px 4px;
}

.timetable-day-header-cell.day-today {
    background-color: #fff3e0;
    color: #e65100;
}

.timetable-grid-table .day-highlighted {
    --frame-top: 0px;
    --frame-bottom: 0px;
    position: relative;
}

.timetable-grid-table .day-highlighted::after {
    content: '';
    position: absolute;
    inset: 0;
    border: solid #4b5563;
    border-width: var(--frame-top) 2px var(--frame-bottom);
    pointer-events: none;
}

.timetable-grid-table th.day-highlighted {
    --frame-top: 2px;
}

.timetable-grid-table tbody tr:last-child .day-highlighted {
    --frame-bottom: 2px;
}

.timetable-day-date {
    font-size: 0.75rem;
    font-weight: 400;
    opacity: 0.7;
}

.timetable-hour-cell {
    text-align: center;
    background-color: #f5f5f5;
    padding: 6px 4px;
    white-space: nowrap;
    min-width: 44px;
}

.timetable-hour-num {
    font-weight: 600;
    font-size: 0.8rem;
}

.timetable-hour-time {
    font-size: 0.7rem;
    opacity: 0.65;
}

.timetable-grid-table td.timetable-grid-cell {
    position: relative;
    min-width: 130px;
    height: 52px;
    padding: 0.5px 1px;
}

.timetable-grid-item {
    padding: 4px 5px;
    border-radius: 3px;
    background-color: #bbd9f4;
    border-left: 0 !important;
    margin-bottom: 2px;
    cursor: pointer;
    transition: opacity 0.15s;
}

.timetable-grid-item:hover {
    opacity: 0.8;
}

.timetable-grid-item.timetable-item--upcoming {
    background-color: #ffcc80;
}

.timetable-grid-item--block {
    margin-bottom: 0;
}

.timetable-grid-course {
    font-weight: 600;
    font-size: 0.75rem;
    white-space: normal;
    overflow-wrap: anywhere;
    flex-shrink: 1;
}

.timetable-grid-class {
    font-size: 0.7rem;
    opacity: 0.7;
    overflow-wrap: anywhere;
}

.timetable-grid-item > .d-flex {
    flex-wrap: wrap;
}

.timetable-grid-content {
    font-size: 0.7rem;
    margin-top: 2px;
    opacity: 0.85;
    white-space: normal;
}

.timetable-grid-content :deep(p) {
    margin: 0;
    min-height: 1em;
}

.timetable-grid-item.timetable-item--exam {
    background-color: #ffebee;
    border-left-color: #ff5722;
}

.timetable-grid-item.timetable-item--free {
    background-color: #eff6f0;
    border-left-color: #8eb89a;
}

.timetable-grid-item.timetable-item--exam.timetable-item--free {
    background-color: #eff6f0;
    border-left-color: #8eb89a;
}

.timetable-grid-item.timetable-item--upcoming {
    border-left-color: #ff9800;
}

</style>
