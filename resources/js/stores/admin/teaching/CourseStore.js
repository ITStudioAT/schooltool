import { defineStore } from 'pinia'
import {
    destroy as destroyCourse,
    index as coursesIndex,
    show as showCourse,
    store as storeCourse,
    update as updateCourse,
} from '@/actions/App/Http/Controllers/Admin/Teaching/TeachingCourseController'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

const timetableSessionKey = 'schooltool:teaching:timetable'

function timetableQueryKey(query = {}) {
    return JSON.stringify(Object.keys(query).sort().map((key) => [key, query[key]]))
}

export const useCourseStore = defineStore('AdminCourseStore', {
    state: () => {
        return {
            courses: [],
            classes: [],
            class_head_emails: [],
            class_head_teachers: [],
            entry_areas: [],
            uses_entry_areas_for_grading_schema: false,
            selected_course: null,
            selected_course_id: null,
            selected_course_student: null,
            previous_selected_student: null,
            previous_show_infos: null,
            previous_show_dates: null,
            show_my_courses: true,
            pending_edit_course_id: null,
            pending_new_course_token: 0,
            students_sort_mode: 'last_name_first_name',
            show_my_infos: true,
            show_students: true,
            show_infos: false,
            show_works: false,
            show_print: false,
            show_dates: false,
            show_table: false,
            show_curriculum: false,
            show_attendance: false,
            show_performances: false,
            show_performances_plus: false,
            infos_show_grade_sem1: false,
            infos_show_grade_sem2: false,
            infos_show_grade_year: false,
            timetable_view_mode: 'table',
            timetable_return_state: null,
            courses_request_promise: null,
            course_detail_request_promises: {},
        }
    },

    actions: {
        rememberTimetableReturn(state) {
            const config = useAdminStore().config
            this.timetable_return_state = {
                ...state,
                userId: config?.user?.id,
                schoolId: config?.school?.id,
                schoolyearId: config?.selected_schoolyear?.id,
                savedDate: new Date().toDateString(),
            }
            this.rememberTimetableView(state)
        },
        isTimetableStateCurrent(state, path) {
            const config = useAdminStore().config
            return state?.path === path
                && state?.savedDate === new Date().toDateString()
                && state?.userId === config?.user?.id
                && state?.schoolId === config?.school?.id
                && state?.schoolyearId === config?.selected_schoolyear?.id
        },
        getTimetableReturn(path) {
            const state = this.timetable_return_state
            if (state && !this.isTimetableStateCurrent(state, path)) {
                this.timetable_return_state = null
            }
            return this.timetable_return_state
        },
        rememberTimetableView(state) {
            if (!state.path) return
            const config = useAdminStore().config
            try {
                sessionStorage.setItem(timetableSessionKey, JSON.stringify({
                    ...state,
                    userId: config?.user?.id,
                    schoolId: config?.school?.id,
                    schoolyearId: config?.selected_schoolyear?.id,
                    savedDate: new Date().toDateString(),
                }))
            } catch {
                // The in-memory course return remains available if tab storage is disabled.
            }
        },
        getTimetableView(path, query) {
            try {
                const state = JSON.parse(sessionStorage.getItem(timetableSessionKey) || 'null')
                if (state && this.isTimetableStateCurrent(state, path)
                    && timetableQueryKey(state.query) === timetableQueryKey(query)
                    && ['today', 'week', 'next_week', 'month', 'current_semester'].includes(state.range)
                    && ['table', 'list'].includes(state.viewMode)
                    && ['offset', 'left', 'top', 'tableLeft', 'tableTop'].every((key) => Number.isFinite(state[key]))) {
                    return state
                }
                sessionStorage.removeItem(timetableSessionKey)
            } catch {
                return null
            }
            return null
        },
        clearTimetableNavigation() {
            this.timetable_return_state = null
            try {
                sessionStorage.removeItem(timetableSessionKey)
            } catch {
                // Tab storage may be unavailable in restricted browser sessions.
            }
        },
        applyStudentMetadata(courseId, studentId, changes) {
            const courses = [...this.courses, this.selected_course].filter(Boolean)
            for (const course of courses) {
                if (String(course.id) !== String(courseId)) continue
                course.students_info = (course.students_info || []).map((student) =>
                    String(student.id) === String(studentId) ? { ...student, ...changes } : student,
                )
            }
            if (String(this.selected_course?.id) === String(courseId)
                && String(this.selected_course_student?.id) === String(studentId)) {
                this.selected_course_student = { ...this.selected_course_student, ...changes }
            }
        },

        async updateStudentMetadata(courseId, studentId, changes) {
            const course = String(this.selected_course?.id) === String(courseId)
                ? this.selected_course
                : this.courses.find((item) => String(item.id) === String(courseId))
            if (!course?.students_info?.some((student) => String(student.id) === String(studentId))) return false

            const studentsInfo = course.students_info.map((student) =>
                String(student.id) === String(studentId) ? { ...student, ...changes } : { ...student },
            )
            const result = await this.update({ ...course, students_info: studentsInfo, students: studentsInfo })
            if (result) this.applyStudentMetadata(courseId, studentId, changes)
            return result
        },

        courseHasProblems(course) {
            return Array.isArray(course?.course_dates) && course.course_dates.length === 0
        },

        ensureCourseStudentCollections(course) {
            if (!course) return
            const normalizeIds = (items) =>
                (Array.isArray(items) ? items : [])
                    .map((item) => {
                        if (item == null) return null
                        if (typeof item === 'object') return item.id ?? null
                        return item
                    })
                    .filter((id) => id !== null && id !== undefined)

            // students -> students_info
            if (Array.isArray(course.students) && course.students.length && typeof course.students[0] === 'object') {
                course.students_info = course.students
                course.students = normalizeIds(course.students_info)
            } else if (course.students && typeof course.students === 'object' && !Array.isArray(course.students)) {
                course.students_info = Array.isArray(course.students.data) ? course.students.data : []
                course.students = normalizeIds(course.students_info)
            } else {
                if (!Array.isArray(course.students)) course.students = []
                if (!Array.isArray(course.students_info)) course.students_info = []
                // critical: backfill ids from info to avoid accidental empty payloads on save
                if (course.students.length === 0 && course.students_info.length > 0) {
                    course.students = normalizeIds(course.students_info)
                }
            }

            // students_deleted -> students_deleted_info
            if (Array.isArray(course.students_deleted) && course.students_deleted.length && typeof course.students_deleted[0] === 'object') {
                course.students_deleted_info = course.students_deleted
                course.students_deleted = normalizeIds(course.students_deleted_info)
            } else if (course.students_deleted && typeof course.students_deleted === 'object' && !Array.isArray(course.students_deleted)) {
                course.students_deleted_info = Array.isArray(course.students_deleted.data) ? course.students_deleted.data : []
                course.students_deleted = normalizeIds(course.students_deleted_info)
            } else {
                if (!Array.isArray(course.students_deleted)) course.students_deleted = []
                if (!Array.isArray(course.students_deleted_info)) course.students_deleted_info = []
                if (course.students_deleted.length === 0 && course.students_deleted_info.length > 0) {
                    course.students_deleted = normalizeIds(course.students_deleted_info)
                }
            }
        },

        syncEntryDefinition(entryDefinition) {
            if (!entryDefinition?.id || !entryDefinition?.teaching_entry_area_id) return

            const targetAreaId = String(entryDefinition.teaching_entry_area_id)
            const synchronizedAreas = new Set()
            const loadedAreas = [
                ...(Array.isArray(this.entry_areas) ? this.entry_areas : []),
                ...(Array.isArray(this.courses)
                    ? this.courses.map((course) => course?.teaching_entry_area)
                    : []),
                this.selected_course?.teaching_entry_area,
            ]

            loadedAreas.forEach((area) => {
                if (!area || synchronizedAreas.has(area)) return

                synchronizedAreas.add(area)
                const definitions = Array.isArray(area.entry_definitions) ? area.entry_definitions : []
                const existingIndex = definitions.findIndex((definition) => (
                    String(definition?.id || '') === String(entryDefinition.id)
                ))

                if (String(area.id || '') !== targetAreaId) {
                    if (existingIndex >= 0) definitions.splice(existingIndex, 1)
                    return
                }

                if (!Array.isArray(area.entry_definitions)) {
                    area.entry_definitions = definitions
                }

                if (existingIndex >= 0) {
                    definitions.splice(existingIndex, 1, entryDefinition)
                } else {
                    definitions.push(entryDefinition)
                }
            })
        },

        async index() {
            if (this.courses_request_promise) {
                return this.courses_request_promise
            }

            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const requestPromise = (async () => {
                adminStore.is_loading++
                try {
                    const selectedId = this.selected_course?.id
                    const response = await axios.get(coursesIndex.url(), {})
                    this.courses = Array.isArray(response.data.data) ? response.data.data : []
                    this.courses.forEach((course) => this.ensureCourseStudentCollections(course))
                    this.classes = response.data.classes
                    this.class_head_emails = Array.isArray(response.data.class_head_emails) ? response.data.class_head_emails : []
                    this.class_head_teachers = Array.isArray(response.data.class_head_teachers) ? response.data.class_head_teachers : []
                    this.entry_areas = response.data.entry_areas || []
                    this.uses_entry_areas_for_grading_schema = Boolean(response.data.uses_entry_areas_for_grading_schema)
                    if (selectedId) {
                        this.selected_course = this.courses.find((c) => c.id === selectedId) || null
                        this.ensureCourseStudentCollections(this.selected_course)
                    }
                    return true
                } catch (error) {
                    notification.notify({
                        status: error.response.status,
                        message: error.response.data.message || 'Fehler passiert.',
                        type: 'error',
                        timeout: 3000,
                    })
                    return false
                } finally {
                    adminStore.is_loading--
                }
            })()

            this.courses_request_promise = requestPromise

            try {
                return await requestPromise
            } finally {
                this.courses_request_promise = null
            }
        },

        async loadCourseDetails(courseId, { force = false } = {}) {
            const normalizedCourseId = Number(courseId)
            if (!Number.isInteger(normalizedCourseId) || normalizedCourseId <= 0) {
                return null
            }

            const existingCourse = this.courses.find((course) => Number(course?.id) === normalizedCourseId) || null
            if (!force && existingCourse?.details_loaded) {
                return existingCourse
            }

            const requestKey = String(normalizedCourseId)
            if (this.course_detail_request_promises[requestKey]) {
                return this.course_detail_request_promises[requestKey]
            }

            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const requestPromise = (async () => {
                adminStore.is_loading++
                try {
                    const response = await axios.get(showCourse.url(normalizedCourseId))
                    const detailedCourse = response.data?.data || response.data
                    if (!detailedCourse?.id) {
                        return null
                    }

                    this.ensureCourseStudentCollections(detailedCourse)

                    const courseIndex = this.courses.findIndex(
                        (course) => Number(course?.id) === normalizedCourseId,
                    )
                    const mergedCourse = courseIndex >= 0
                        ? { ...this.courses[courseIndex], ...detailedCourse }
                        : detailedCourse

                    if (courseIndex >= 0) {
                        this.courses.splice(courseIndex, 1, mergedCourse)
                    } else {
                        this.courses.push(mergedCourse)
                    }

                    if (Number(this.selected_course?.id) === normalizedCourseId) {
                        this.selected_course = mergedCourse
                        this.selected_course_id = mergedCourse.id
                    }

                    return mergedCourse
                } catch (error) {
                    notification.notify({
                        status: error.response?.status || 500,
                        message: error.response?.data?.message || 'Fehler passiert.',
                        type: 'error',
                        timeout: 3000,
                    })
                    return null
                } finally {
                    adminStore.is_loading--
                }
            })()

            this.course_detail_request_promises[requestKey] = requestPromise

            try {
                return await requestPromise
            } finally {
                delete this.course_detail_request_promises[requestKey]
            }
        },

        async refreshCourseById(courseId) {
            if (!courseId) {
                this.selected_course = null
                this.selected_course_id = null
                return null
            }

            const ok = await this.index()
            if (!ok) {
                return null
            }

            const courseSummary = this.courses.find((course) => Number(course?.id) === Number(courseId)) || null
            if (!courseSummary) {
                this.selected_course = null
                this.selected_course_id = null
                return null
            }

            const course = await this.loadCourseDetails(courseId, { force: true })
            this.ensureCourseStudentCollections(course)
            this.selected_course = course
            this.selected_course_id = course?.id || null
            return course
        },

        async update(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()

            if (!(this.uses_entry_areas_for_grading_schema ? data?.teaching_entry_area_id : data?.teaching_schema_id)) {
                notification.notify({
                    status: 422,
                    message: 'Bitte ein Benotungsschema auswählen.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }

            adminStore.is_loading++
            try {
                this.ensureCourseStudentCollections(data)
                const response = await axios.put(updateCourse.url(data.id), data)
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()

            if (!(this.uses_entry_areas_for_grading_schema ? data?.teaching_entry_area_id : data?.teaching_schema_id)) {
                notification.notify({
                    status: 422,
                    message: 'Bitte ein Benotungsschema auswählen.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            }

            adminStore.is_loading++
            try {
                this.ensureCourseStudentCollections(data)
                const response = await axios.post(storeCourse.url(), data)
                this.saved_offer = response.data
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: this.timeout,
                })
                this.error = error
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete(destroyCourse.url(id))
                return true
            } catch (error) {
                notification.notify({
                    status: error.response.status,
                    message: error.response.data.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
