import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import { VChip } from 'vuetify/components/VChip'
import { VTooltip } from 'vuetify/components/VTooltip'
import axios from 'axios'
import CourseStudentNotes from '@/pages/admin/teaching/overview/components/CourseStudentNotes.vue'
import CourseStudentIndicators from '@/pages/admin/teaching/overview/components/CourseStudentIndicators.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'

vi.mock('axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))

const ActivatorOnlyHoverDetails = defineComponent({
    setup(_, { slots }) {
        return () => slots.activator?.({ props: {} })
    },
})

function mountIndicators(options) {
    return mount(CourseStudentIndicators, {
        ...options,
        global: {
            ...options.global,
            stubs: { CourseStudentHoverDetails: ActivatorOnlyHoverDetails, ...options.global?.stubs },
        },
    })
}

function setupNotes(email = '') {
    const student = { id: 12, course_student_id: 71, user_id: 12, last_name: 'Test', first_name: 'Anna', email, comment: '<p>Bisher</p>', stars: [] }
    const course = { id: 18, students_info: [student], teacher_teaching_notifications: [{ short_name: 'E', name: 'Erinnerung' }] }
    const store = useCourseStore()
    store.selected_course = course as never
    store.courses = [course] as never
    const wrapper = mount(CourseStudentNotes, {
        props: { course: store.selected_course },
        global: { stubs: {
            ItsRichTextEditor: true,
            'v-textarea': true,
            'v-checkbox': {
                props: ['modelValue', 'label', 'disabled'],
                emits: ['update:modelValue'],
                template: '<label><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)">{{ label }}</label>',
            },
        } },
    })
    const vm = wrapper.vm as any
    return { wrapper, vm, student, store }
}

describe('Course student quick notes', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('shows the email below the name and copies it with one click', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        const { wrapper, vm, student } = setupNotes(' anna@example.test ')
        vm.open(student)
        await flushPromises()
        const emailButton = wrapper.get('[data-testid="student-notes-email"]')
        expect(emailButton.text()).toBe('anna@example.test')
        expect(emailButton.attributes('append-icon')).toBe('mdi-content-copy')
        expect(emailButton.element.previousElementSibling?.textContent).toBe('Test, Anna')
        await emailButton.trigger('click')
        await flushPromises()
        expect(writeText).toHaveBeenCalledExactlyOnceWith('anna@example.test')
        expect(wrapper.get('[role="status"]').text()).toBe('E-Mail-Adresse kopiert.')
        vm.close()
        expect(vm.emailCopyMessage).toBe('')
        wrapper.unmount()
    })

    it('omits the email control when the address is empty', async () => {
        const { wrapper, vm, student } = setupNotes('   ')
        vm.open(student)
        await flushPromises()
        expect(wrapper.find('[data-testid="student-notes-email"]').exists()).toBe(false)
        wrapper.unmount()
    })

    it('reports a clipboard failure without showing success', async () => {
        vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('Denied')) } })
        const { wrapper, vm, student } = setupNotes('anna@example.test')
        vm.open(student)
        await flushPromises()
        await wrapper.get('[data-testid="student-notes-email"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="status"]').text()).toBe('E-Mail-Adresse konnte nicht kopiert werden.')
        wrapper.unmount()
    })

    it('removes only the selected star and updates the visible list', async () => {
        const { wrapper, vm, student, store } = setupNotes()
        const first = { id: 'first', comment: 'Hilfsbereit' }
        const second = { id: 'second', comment: 'Gute Leistung' }
        student.stars.push(first as never, second as never)
        const save = vi.spyOn(store, 'updateStudentMetadata').mockImplementation(async (courseId, studentId, changes) => {
            store.applyStudentMetadata(courseId, studentId, changes)
            return true
        })
        vm.open(student, 'star')
        await flushPromises()
        await wrapper.findAll('[aria-label="Stern entfernen"]')[0].trigger('click')
        await flushPromises()
        expect(save).toHaveBeenCalledWith(18, 12, { stars: [second] })
        expect(vm.student.stars).toEqual([second])
        expect(vm.isOpen).toBe(true)
        expect(wrapper.findAll('[aria-label="Stern entfernen"]')).toHaveLength(1)
        wrapper.unmount()
    })

    it('removes an existing reminder without saving the new reminder draft', async () => {
        const { wrapper, vm, student } = setupNotes()
        const behaviourStore = useCourseBehaviourEntryStore()
        const entry = { id: 55, teaching_course_id: 18, user_id: 12, kind: 'notification', description: 'Unterlagen' }
        behaviourStore.courseEntries = [entry] as never
        const remove = vi.spyOn(behaviourStore, 'destroy').mockImplementation(async () => {
            behaviourStore.courseEntries = []
            return true
        })
        vm.open(student, 'reminder')
        await flushPromises()
        vm.reminder.description = 'Neuer Entwurf'
        await wrapper.find('[aria-label="Erinnerung entfernen"]').trigger('click')
        await flushPromises()
        expect(remove).toHaveBeenCalledWith(55)
        expect(vm.reminders).toEqual([])
        expect(vm.reminder.description).toBe('Neuer Entwurf')
        expect(vm.isOpen).toBe(true)
        wrapper.unmount()
    })

    it('keeps a star after a failed removal and blocks duplicate requests', async () => {
        const { wrapper, vm, student, store } = setupNotes()
        student.stars.push({ id: 'first', comment: 'Hilfsbereit' } as never)
        let finish: (value: boolean) => void = () => {}
        const save = vi.spyOn(store, 'updateStudentMetadata').mockReturnValue(new Promise(resolve => { finish = resolve }))
        vm.open(student, 'star')
        const star = vm.student.stars[0]
        const pending = vm.removeEntry('star', star, 0)
        await vm.removeEntry('star', star, 0)
        expect(save).toHaveBeenCalledTimes(1)
        finish(false)
        await pending
        expect(vm.student.stars).toHaveLength(1)
        expect(vm.error).toContain('fehlgeschlagen')
        expect(vm.saving).toBe(false)
        wrapper.unmount()
    })

    beforeEach(() => {
        setActivePinia(createPinia())
        vi.clearAllMocks()
        vi.spyOn(useCourseBehaviourEntryStore(), 'indexByCourse').mockResolvedValue(true)
    })

    it('opens a student draft with all four actions without changing saved data', async () => {
        const { wrapper, vm, student } = setupNotes()
        vm.open(student)
        await flushPromises()
        expect(wrapper.text()).toContain('Kommentar')
        expect(wrapper.text()).toContain('Erinnerung')
        expect(wrapper.text()).toContain('Star')
        expect(wrapper.text()).toContain('Besondere Informationen')
        vm.comment = 'Entwurf'
        expect(student.comment).toBe('<p>Bisher</p>')
        vm.close()
        expect(vm.isOpen).toBe(false)
        wrapper.unmount()
    })

    it('saves comments through the course metadata action', async () => {
        const { wrapper, vm, student, store } = setupNotes()
        const save = vi.spyOn(store, 'updateStudentMetadata').mockResolvedValue(true)
        vm.open(student)
        vm.comment = '<p>Neuer Kommentar</p>'
        await vm.save()
        expect(save).toHaveBeenCalledWith(18, 12, { comment: '<p>Neuer Kommentar</p>' })
        expect(vm.isOpen).toBe(false)
        wrapper.unmount()
    })

    it('requires a star reason and preserves previous stars', async () => {
        const { wrapper, vm, student, store } = setupNotes()
        const prior = { id: 'prior', value: 1, comment: 'Hilfreich', date: '2026-09-01' }
        student.stars.push(prior as never)
        const save = vi.spyOn(store, 'updateStudentMetadata').mockResolvedValue(true)
        vm.open(student, 'star')
        vm.starReason = '   '
        await vm.save()
        expect(save).not.toHaveBeenCalled()
        vm.starReason = '  Gute Präsentation  '
        vm.starDate = '2026-09-05'
        await vm.save()
        expect(save).toHaveBeenCalledWith(18, 12, { stars: [prior, expect.objectContaining({ value: 1, comment: 'Gute Präsentation', date: '2026-09-05' })] })
        expect(vm.starReason).toBe('')
        expect(vm.isOpen).toBe(false)
        wrapper.unmount()
    })

    it.each(['', '14:30'])('records a reminder without a type and with optional time %s', async (dueTime) => {
        const { wrapper, vm, student } = setupNotes()
        const save = vi.spyOn(useCourseBehaviourEntryStore(), 'store').mockResolvedValue({ data: { id: 1 } })
        vm.open(student, 'reminder')
        await flushPromises()
        vm.reminder = { description: '  Unterlagen mitbringen  ', dueDate: '2026-09-10', dueTime }
        await vm.save()
        expect(save).toHaveBeenCalledWith(expect.objectContaining({ teaching_course_id: 18, user_id: 12, kind: 'notification', description: 'Unterlagen mitbringen', is_due: true, due_date: '2026-09-10', due_time: dueTime || null, is_done: false, done_date: null }))
        expect(save.mock.calls[0][0]).not.toHaveProperty('type')
        expect(axios.put).not.toHaveBeenCalled()
        expect(vm.reminder.description).toBe('')
        expect(vm.isOpen).toBe(false)
        wrapper.unmount()
    })

    it.each([
        [false, false], [true, false], [false, true], [true, true],
    ])('saves independent email choices for student %s and teacher %s', async (emailStudent, emailTeacher) => {
        const { wrapper, vm, student } = setupNotes()
        const save = vi.spyOn(useCourseBehaviourEntryStore(), 'store').mockResolvedValue({ data: { id: 1 } })
        vm.open(student, 'reminder')
        await flushPromises()
        expect(vm.reminder.emailStudent).toBe(false)
        expect(vm.reminder.emailTeacher).toBe(true)
        const checkboxes = wrapper.findAll('input[type="checkbox"]')
        expect(checkboxes).toHaveLength(2)
        vm.reminder.description = 'Unterlagen mitbringen'
        await checkboxes[0].setValue(emailStudent)
        await checkboxes[1].setValue(emailTeacher)
        await vm.save()
        expect(save).toHaveBeenCalledWith(expect.objectContaining({
            remind_student_by_email: emailStudent,
            remind_teacher_by_email: emailTeacher,
        }))
        wrapper.unmount()
    })

    it('requires an account but no configured reminder types', async () => {
        const { wrapper, vm, student } = setupNotes()
        vm.open({ ...student, id: 'import:45', user_id: null }, 'reminder')
        expect(vm.canSave).toBe(false)
        expect(vm.reminderUnavailable).toContain('Benutzerkonto')
        await wrapper.setProps({ course: { id: 18, students_info: [student], teacher_teaching_notifications: [] } })
        vm.open(student, 'reminder')
        await flushPromises()
        vm.reminder.description = 'Unterlagen mitbringen'
        expect(vm.canSave).toBe(true)
        expect(vm.reminderUnavailable).toBe('')
        expect(wrapper.html()).not.toContain('Art der Erinnerung')
        expect(wrapper.html()).toContain('Uhrzeit (optional)')
        wrapper.unmount()
    })

    it('requires reminder text and a date and preserves a failed draft', async () => {
        const { wrapper, vm, student } = setupNotes()
        const save = vi.spyOn(useCourseBehaviourEntryStore(), 'store').mockResolvedValue(false)
        vm.open(student, 'reminder')
        await flushPromises()
        vm.reminder = { description: '   ', dueDate: '2026-09-10', dueTime: '' }
        expect(vm.canSave).toBe(false)
        vm.reminder = { description: 'Unterlagen', dueDate: '', dueTime: '12:00' }
        expect(vm.canSave).toBe(false)
        vm.reminder.dueDate = '2026-09-10'
        await vm.save()
        expect(save).toHaveBeenCalledTimes(1)
        expect(vm.reminder).toEqual({ description: 'Unterlagen', dueDate: '2026-09-10', dueTime: '12:00' })
        expect(vm.error).toContain('fehlgeschlagen')
        expect(vm.isOpen).toBe(true)
        wrapper.unmount()
    })

    it('loads confidential notes only on demand and only caches their presence flag', async () => {
        const { wrapper, vm, student, store } = setupNotes()
        vi.mocked(axios.get).mockResolvedValue({ data: { data: { special_information: 'Vertraulicher Hinweis', has_special_information: true } } })
        vi.mocked(axios.put).mockResolvedValue({ data: { data: { has_special_information: true } } })
        vm.open(student)
        expect(axios.get).not.toHaveBeenCalled()
        await vm.selectSection('special')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/courses/18/students/71/special-information')
        expect(vm.specialInformation).toBe('Vertraulicher Hinweis')
        vm.specialInformation = '  Neue Information  '
        await vm.save()
        expect(axios.put).toHaveBeenCalledWith('/api/admin/teaching/courses/18/students/71/special-information', { special_information: 'Neue Information' })
        expect(store.selected_course?.students_info[0].has_special_information).toBe(true)
        expect(JSON.stringify(store.$state)).not.toContain('Neue Information')
        expect(vm.isOpen).toBe(false)
        expect(vm.specialInformation).toBe('')
        wrapper.unmount()
    })

    it('clears the badge after the confidential field is emptied and saved', async () => {
        const { wrapper, vm, student, store } = setupNotes()
        vi.mocked(axios.get).mockResolvedValue({ data: { data: { special_information: 'Bisher' } } })
        vi.mocked(axios.put).mockResolvedValue({ data: { data: { has_special_information: false } } })
        vm.open(student)
        await vm.selectSection('special')
        vm.specialInformation = ''
        await vm.save()
        expect(store.selected_course?.students_info[0].has_special_information).toBe(false)
        wrapper.unmount()
    })

    it('does not overwrite confidential notes when loading failed', async () => {
        const { wrapper, vm, student } = setupNotes()
        vi.mocked(axios.get).mockRejectedValue(new Error('offline'))
        vm.open(student)
        await vm.selectSection('special')
        expect(vm.canSave).toBe(false)
        await vm.save()
        expect(axios.put).not.toHaveBeenCalled()
        expect(vm.error).toContain('geladen')
        wrapper.unmount()
    })

    it('ignores late confidential responses after changing students', async () => {
        const { wrapper, vm, student } = setupNotes()
        let resolve: any
        vi.mocked(axios.get).mockReturnValue(new Promise((done) => { resolve = done }))
        vm.open(student)
        const pending = vm.selectSection('special')
        vm.close()
        vm.open({ ...student, id: 99, course_student_id: 72 })
        resolve({ data: { data: { special_information: 'Belongs to previous student' } } })
        await pending
        expect(vm.specialInformation).toBe('')
        expect(vm.specialLoaded).toBe(false)
        wrapper.unmount()
    })

    it('retains a failed draft and prevents duplicate submissions', async () => {
        const { wrapper, vm, student, store } = setupNotes()
        let resolve: any
        const save = vi.spyOn(store, 'updateStudentMetadata').mockReturnValue(new Promise((done) => { resolve = done }))
        vm.open(student, 'star')
        vm.starReason = 'Hilft anderen'
        const pending = vm.save()
        await vm.save()
        expect(save).toHaveBeenCalledTimes(1)
        resolve(false)
        await pending
        expect(vm.starReason).toBe('Hilft anderen')
        expect(vm.error).toContain('fehlgeschlagen')
        expect(vm.isOpen).toBe(true)
        wrapper.unmount()
    })

    it('waits for existing reminders before allowing a new save', async () => {
        const { wrapper, vm, student } = setupNotes()
        let resolve: any
        vi.mocked(useCourseBehaviourEntryStore().indexByCourse).mockReturnValue(new Promise((done) => { resolve = done }))
        const save = vi.spyOn(useCourseBehaviourEntryStore(), 'store').mockResolvedValue(true)
        vm.open(student, 'reminder')
        vm.reminder.description = 'Nicht vergessen'
        expect(vm.reminderLoading).toBe(true)
        await vm.save()
        expect(save).not.toHaveBeenCalled()
        resolve(true)
        await flushPromises()
        expect(vm.canSave).toBe(true)
        wrapper.unmount()
    })
})

describe('Student indicator symbols', () => {
    beforeEach(() => setActivePinia(createPinia()))

    it('reveals the complete normal comment on hover and focus while confidential information stays click-only', async () => {
        vi.stubGlobal('visualViewport', Object.assign(new EventTarget(), { width: 1024, height: 768, offsetLeft: 0, offsetTop: 0, scale: 1 }))
        vi.mocked(axios.get).mockClear()
        const wrapper = mountIndicators({
            attachTo: document.body,
            props: { courseId: 18, student: {
                comment: '<p>Erster Absatz &amp; Details</p><p>Zweiter Absatz<br>Letzte Zeile</p><script>BAD_SCRIPT</script>',
                has_special_information: true, special_information: 'CONFIDENTIAL_ONLY_AFTER_CLICK',
            } },
            global: {
                plugins: [createVuetify({ components: { VTooltip, VBtn } })],
                components: { 'v-tooltip': VTooltip, 'v-btn': VBtn },
                stubs: { CourseStudentHoverDetails: false, 'v-tooltip': false, 'v-btn': false, VTooltip: false, VBtn: false },
            },
        })
        const waitForTooltip = async () => {
            await new Promise(resolve => setTimeout(resolve, 250))
            await flushPromises()
        }
        try {
            await flushPromises()
            expect(document.querySelector('[role="tooltip"]')).toBeNull()
            const comment = wrapper.get('[aria-label="Kommentar vorhanden"]')
            expect(comment.element.tagName).toBe('BUTTON')
            await comment.trigger('mouseenter')
            await waitForTooltip()
            const tooltip = document.querySelector('.v-overlay--active[role="tooltip"]')
            expect(tooltip?.textContent).toContain('Erster Absatz & Details\nZweiter Absatz\nLetzte Zeile')
            expect(tooltip?.textContent).not.toContain('BAD_SCRIPT')
            expect(document.body.textContent).not.toContain('CONFIDENTIAL_ONLY_AFTER_CLICK')
            await comment.trigger('mouseleave')
            tooltip?.querySelector('.v-overlay__content')?.dispatchEvent(new MouseEvent('mouseenter'))
            await waitForTooltip()
            expect(tooltip?.classList.contains('v-overlay--active')).toBe(true)
            tooltip?.querySelector('.v-overlay__content')?.dispatchEvent(new MouseEvent('mouseleave'))
            await waitForTooltip()
            ;(comment.element as HTMLElement).focus()
            await waitForTooltip()
            expect(document.activeElement).toBe(comment.element)
            expect(document.querySelector('.v-overlay--active[role="tooltip"]')?.textContent).toContain('Letzte Zeile')
            ;(document.querySelector('.v-overlay--active [aria-label="Detailfenster schließen"]') as HTMLButtonElement).click()
            await waitForTooltip()
            expect(document.querySelector('.v-overlay--active[role="tooltip"]')).toBeNull()
            expect(document.activeElement).toBe(comment.element)
            ;(comment.element as HTMLElement).blur()
            const special = wrapper.get('[aria-label="Vertrauliche besondere Informationen vorhanden"]')
            await special.trigger('mouseenter')
            await waitForTooltip()
            expect(document.querySelector('.v-overlay--active[role="tooltip"]')).toBeNull()
            expect(axios.get).not.toHaveBeenCalled()
            await special.trigger('click')
            await comment.trigger('click')
            expect(wrapper.emitted('select')).toEqual([['special'], ['comment']])
            expect(document.body.textContent).not.toContain('CONFIDENTIAL_ONLY_AFTER_CLICK')
        } finally {
            wrapper.unmount()
            document.querySelectorAll('.v-overlay-container').forEach(container => container.remove())
            vi.unstubAllGlobals()
        }
    })

    it('lists all supplied stars with dates and comments on hover and keeps the close action dismissed', async () => {
        vi.stubGlobal('visualViewport', Object.assign(new EventTarget(), { width: 1024, height: 768, offsetLeft: 0, offsetTop: 0, scale: 1 }))
        const wrapper = mountIndicators({
            attachTo: document.body,
            props: { courseId: 18, starsOnly: true, showEmptyStars: true, student: {
                stars: [
                    ...Array.from({ length: 35 }, (_, index) => ({ id: String(index), date: '2026-09-05', comment: `Sternkommentar ${index + 1}` })),
                    { id: 'undated', date: null, comment: 'Stern ohne Datumsangabe' },
                ],
            } },
            global: {
                plugins: [createVuetify({ components: { VTooltip, VChip } })],
                components: { 'v-tooltip': VTooltip, 'v-chip': VChip },
                stubs: { CourseStudentHoverDetails: false, 'v-tooltip': false, 'v-chip': false, VTooltip: false, VChip: false },
            },
        })
        const waitForTooltip = async () => {
            await new Promise(resolve => setTimeout(resolve, 320))
            await flushPromises()
        }
        try {
            await flushPromises()
            const stars = wrapper.get('[aria-label="36 Sterne für besondere Leistungen"]')
            expect(stars.attributes('title')).toBeUndefined()
            expect(document.querySelector('[role="tooltip"]')).toBeNull()
            await stars.trigger('mouseenter')
            await waitForTooltip()
            const tooltip = document.querySelector('.v-overlay--active[role="tooltip"]')
            expect(tooltip?.querySelectorAll('.student-star-detail')).toHaveLength(36)
            expect(tooltip?.textContent).toContain('05.09.2026')
            expect(tooltip?.textContent).toContain('Sternkommentar 35')
            expect(tooltip?.textContent).toContain('Datum nicht angegeben')
            expect(tooltip?.querySelector('[role="region"]')?.getAttribute('tabindex')).toBe('0')
            ;(tooltip?.querySelector('[aria-label="Detailfenster schließen"]') as HTMLButtonElement).click()
            await waitForTooltip()
            expect(document.querySelector('.v-overlay--active[role="tooltip"]')).toBeNull()
            expect(document.activeElement).toBe(stars.element)
            expect(wrapper.emitted('select')).toBeUndefined()
            ;(stars.element as HTMLElement).blur()
            ;(stars.element as HTMLElement).focus()
            await waitForTooltip()
            expect(document.querySelector('.v-overlay--active[role="tooltip"]')?.textContent).toContain('Sternkommentar 35')
            await wrapper.setProps({ student: { stars: [] } })
            expect(document.querySelector('.v-overlay--active[role="tooltip"]')?.textContent).toContain('Keine Sterne im ausgewählten Zeitraum.')
            expect(document.querySelector('.v-overlay--active[role="tooltip"]')?.textContent).not.toContain('Sternkommentar')
            await wrapper.get('[aria-label="0 Sterne für besondere Leistungen"]').trigger('click')
            expect(wrapper.emitted('select')).toEqual([['star']])
        } finally {
            wrapper.unmount()
            document.querySelectorAll('.v-overlay-container').forEach(container => container.remove())
            vi.unstubAllGlobals()
        }
    })

    it('can show zero stars for the selected overview period without restoring other stars', async () => {
        const wrapper = mountIndicators({ props: { courseId: 18, starsOnly: true, showEmptyStars: true, student: { stars: [] } } })
        expect(wrapper.get('v-chip').text()).toBe('0')
        expect(wrapper.get('v-chip').attributes('aria-label')).toBe('0 Sterne für besondere Leistungen')
        expect(wrapper.get('v-icon').attributes('icon')).toBe('mdi-star-outline')
        await wrapper.get('v-chip').trigger('click')
        expect(wrapper.emitted('select')).toEqual([['star']])
        await wrapper.setProps({ student: { stars: [{ id: 'included', comment: 'Aktueller Stern' }] } })
        expect(wrapper.get('v-chip').attributes('aria-label')).toBe('1 Sterne für besondere Leistungen')
        expect(wrapper.get('v-icon').attributes('icon')).toBe('mdi-star')
        wrapper.unmount()
    })

    it('counts only safely dated reminders inside the selected schoolyear when year scope is supplied', () => {
        useCourseBehaviourEntryStore().courseEntries = ['2025-08-31', '2026-09-01', null, '2026-01-01'].map((date, id) => ({
            id, date, teaching_course_id: 18, user_id: 12, kind: 'notification', done_date: null,
        })) as never
        const wrapper = mountIndicators({ props: {
            student: { id: 12, user_id: 12 }, courseId: 18, activeSemester: 3,
            schoolyear: { id: 3, from: '2025-09-01', until: '2026-08-31' },
        } })
        expect(wrapper.get('v-btn').attributes('aria-label')).toBe('1 offene Erinnerung')
        wrapper.unmount()
    })

    it.each([1, 2, 3])('limits the reminder counter to semester %s including undated reminders', (activeSemester) => {
        useCourseBehaviourEntryStore().courseEntries = ['2026-02-08', '2026-02-09', null].map((date, id) => ({
            id, date, teaching_course_id: 18, user_id: 12, kind: 'notification', done_date: null,
        })) as never
        const wrapper = mountIndicators({ props: {
            student: { id: 12, user_id: 12 }, courseId: 18, activeSemester, semesterTwoStartDate: '2026-02-09',
        } })
        expect(wrapper.get('v-btn').attributes('aria-label')).toBe(`${activeSemester === 3 ? 3 : 2} offene Erinnerungen`)
        wrapper.unmount()
    })

    it('shows counts and generic labels, never the confidential content', async () => {
        useCourseBehaviourEntryStore().courseEntries = [
            { id: 1, teaching_course_id: 18, user_id: 12, kind: 'notification', done_date: null },
            { id: 2, teaching_course_id: 18, user_id: 12, kind: 'notification', done_date: '2026-09-01' },
            { id: 3, teaching_course_id: 19, user_id: 12, kind: 'notification', done_date: null },
            { id: 4, teaching_course_id: 18, user_id: 99, kind: 'notification', done_date: null },
        ] as never
        const wrapper = mountIndicators({ props: { courseId: 18, student: { id: 12, user_id: 12, stars: [{ id: '1' }, { id: '2' }], comment: '<p>Kommentar</p>', has_special_information: true, special_information: 'SECRET' } } })
        expect(wrapper.findAll('v-btn')).toHaveLength(3)
        expect(wrapper.html()).not.toContain('mdi-star')
        expect(wrapper.html()).toContain('1 offene Erinnerung')
        expect(wrapper.html()).not.toContain('SECRET')
        await wrapper.find('[aria-label="Vertrauliche besondere Informationen vorhanden"]').trigger('click')
        expect(wrapper.emitted('select')).toEqual([['special']])
        wrapper.unmount()
    })

    it('does not show empty indicators', () => {
        const wrapper = mountIndicators({ props: { courseId: 18, student: { stars: [], comment: '<p>&nbsp;</p>', has_special_information: false } } })
        expect(wrapper.findAll('v-btn')).toHaveLength(0)
        wrapper.unmount()
    })

    it('groups all stars in one clickable badge in the name row', async () => {
        const wrapper = mountIndicators({ props: {
            courseId: 18, starsOnly: true,
            student: { stars: [{ id: '1', comment: 'Hilfsbereit' }, { id: '2', comment: 'Gute Leistung' }], comment: 'Notiz' },
        } })
        expect(wrapper.findAll('v-chip')).toHaveLength(1)
        expect(wrapper.find('v-chip').findAll('v-icon')).toHaveLength(2)
        expect(wrapper.findAll('v-btn')).toHaveLength(0)
        expect(wrapper.find('v-chip').attributes('title')).toBeUndefined()
        await wrapper.find('v-chip').trigger('click')
        expect(wrapper.emitted('select')).toEqual([['star']])
        await wrapper.setProps({ student: { stars: [{ id: '1', comment: 'Hilfsbereit' }] } })
        expect(wrapper.findAll('v-chip')).toHaveLength(1)
        expect(wrapper.find('v-chip').findAll('v-icon')).toHaveLength(1)
        await wrapper.setProps({ student: { stars: [] } })
        expect(wrapper.findAll('v-chip')).toHaveLength(0)
        wrapper.unmount()
    })
})
