import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import axios from 'axios'
import CourseTable from '@/pages/admin/teaching/overview/components/CourseTable.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { clearWorkDropboxStates, workDropboxStates } from '@/helpers/workDropbox'
import WorkTitleEditor from '@/pages/admin/teaching/overview/components/WorkTitleEditor.vue'

vi.mock('vue-filepond/dist/vue-filepond.js', () => ({ default: () => ({
    name: 'FilePond', props: ['disabled'], emits: ['updatefiles'], template: '<div data-testid="filepond" />',
}) }))

afterEach(() => {
    clearWorkDropboxStates()
    sessionStorage.clear()
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
})

it.each([false, true])('creates once and continues or retries a partial folder in the table: %s', async partial => {
    const pinia = createTestingPinia({ createSpy: vi.fn })
    useAdminStore(pinia).config = { user: { id: 7 }, roles: ['teacher'], selected_school: { id: 1 }, selected_schoolyear: { id: 2 } } as never
    const date = { id: 1, date: '2026-10-20' }
    useCourseStore(pinia).selected_course = {
        id: 20, title: 'Kurs', school_id: 1, schoolyear_id: 2, user_id: 7, students_info: [{ id: 12, user_id: 12 }], course_dates: [date],
        teacher_teaching_schema: { works: [{ short_name: 'SA', name: 'Schularbeit' }] },
    } as never
    const handshake = JSON.stringify({ schema_version: 1, module_id: 'a263b873-4f27-4167-90f0-7d94ea0f67fa' })
    const saved = { id: 42, teaching_course_id: 20, type: 'SA', title: 'Beispiel', groups: [], status: {}, automation_enabled: true,
        is_group_work: false, date_for_all_groups: date.date }
    const linkedState = { connected: true, module_link: { folder: { id: 'id:module' } }, link_preparation: { status: 'initialized' } }
    let linked = false
    let finishCreate!: (value: unknown) => void
    const post = vi.fn().mockImplementation((url, data) => {
        if (url === '/api/admin/teaching/course_works') return new Promise(resolve => { finishCreate = resolve })
        if (data.resolve) return Promise.resolve({ data: { can_link: true, proof: 'proof', module_id: 'module',
            candidate: { folder_path: '/Materialien/Beispiel' }, message: 'Online geprüft.' } })
        linked = true
        if (partial && data.proof) return Promise.resolve({ data: { ...linkedState, link_preparation: { status: 'partial', message: 'Ordnerprüfung fehlgeschlagen' } } })
        return Promise.resolve({ data: linkedState })
    })
    vi.spyOn(axios, 'post').mockImplementation(post)
    vi.spyOn(axios, 'get').mockImplementation(async () => ({ data: linked ? linkedState : { connected: true, configured: true, installed: true, module_link: null } }))
    vi.stubGlobal('axios', axios)
    const wrapper = mount(CourseTable, {
        props: { view: 'entries' },
        global: { plugins: [pinia], stubs: {
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-text-field': { props: ['modelValue', 'label'], emits: ['update:modelValue'],
                template: '<label>{{ label }}<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>' },
            ItsGridBox: { template: '<div><slot /></div>' }, WorkExerciseExport: true, WorkDeliveryStatus: true,
            WorkFolderStatus: true, WorkExerciseStatus: true, WorkEvaluationImport: true, WorkStudentDeadline: true,
            CourseStudentNotes: true, CourseStudentIndicators: true, CurriculumPdfPreview: true, ItsRichTextEditor: true,
            'v-tab': true, 'v-tabs': true, 'v-textarea': true, 'v-date-input': true, 'v-list-subheader': true,
            'v-divider': true, 'v-checkbox': true,
        } },
    })
    try {
        const vm = wrapper.vm as any
        vm.loadCourseTableData = vi.fn().mockResolvedValue(true)
        vm.openNewWorkDialog(date)
        await flushPromises()
        const form = wrapper.get('.course-table-date-work-form')
        await form.findAll('button').find(button => button.text() === 'SA - Schularbeit')!.trigger('click')
        await form.findAll('label').find(label => label.text().startsWith('Titel'))!.get('input').setValue('Beispiel')
        form.getComponent({ name: 'FilePond' }).vm.$emit('updatefiles', [{ file: {
            name: 'handshake.json', size: handshake.length, text: async () => handshake,
        } }])
        await flushPromises()
        expect(post).not.toHaveBeenCalled()
        const create = form.findAll('button').find(button => button.text() === 'Arbeit und Prüfungsordner anlegen')!
        expect(create.attributes('disabled')).toBeUndefined()
        await create.trigger('click')
        await flushPromises()
        expect(create.attributes('disabled')).toBeDefined()
        await create.trigger('click')
        expect(post.mock.calls.filter(([url]) => url === '/api/admin/teaching/course_works')).toHaveLength(1)
        finishCreate({ data: { data: saved } })
        await flushPromises()
        expect(form.getComponent(WorkTitleEditor).props('canEdit')).toBe(true)
        expect(form.getComponent(WorkTitleEditor).find('input').exists()).toBe(false)
        expect(post.mock.calls.filter(([url]) => url === '/api/admin/teaching/course_works')).toHaveLength(1)
        const checks = post.mock.calls.filter(([url]) => url.endsWith('/dropbox/folder'))
        expect(checks.map(([, body]) => body)).toEqual([
            { handshake, filename: 'handshake.json', resolve: true },
            { handshake, filename: 'handshake.json', proof: 'proof' },
        ])
        expect(workDropboxStates.get('7:20:42')?.module_link.folder.id).toBe('id:module')
        if (partial) {
            expect(wrapper.text()).toContain('Ordnerprüfung fehlgeschlagen')
            expect(vm.dateHandshakeNotice).toContain('Lernmodul ist verknüpft')
            await wrapper.findAll('button').find(button => button.text() === 'Prüfungsordner erneut anlegen')!.trigger('click')
            await flushPromises()
            expect(post.mock.calls.at(-1)?.[1]).toEqual({ retry_preparation: true })
            expect(post.mock.calls.filter(([url]) => url === '/api/admin/teaching/course_works')).toHaveLength(1)
        }
        expect(wrapper.find('[data-testid="work-module-inline"]').exists()).toBe(false)
        expect(wrapper.findAll('button').some(button => button.text() === 'Verknüpfen und Prüfungsordner anlegen')).toBe(false)
        expect(vm.dateHandshakeNotice).toBe('')
    } finally { wrapper.unmount() }
})
