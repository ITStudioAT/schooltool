import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkCellStatus from '@/pages/admin/teaching/overview/components/WorkCellStatus.vue'
import WorkDispatchStatus from '@/pages/admin/teaching/overview/components/WorkDispatchStatus.vue'
import WorkImportActions from '@/pages/admin/teaching/overview/components/WorkImportActions.vue'
import WorkAiExport from '@/pages/admin/teaching/overview/components/WorkAiExport.vue'
import WorkFolderStatus from '@/pages/admin/teaching/overview/components/WorkFolderStatus.vue'
import { workAutomationEnabled } from '@/helpers/workAutomation'
import { workDropboxStates, clearWorkDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2, type: 'A5', title: 'Manuelle Leistung',
    status: { folder_imported_at: '2026-10-02T10:00:00Z', automation_enabled: false } }

function mountWork(component, props = {}) {
    return mount(component, { props: { work, ...props }, global: { stubs: { 'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' } }, plugins: [createTestingPinia({ createSpy: vi.fn,
        initialState: { AdminAdminStore: { config: { user: { id: 7 } } }, AdminCourseStore: { selected_course: { id: 2 } } },
    })] } })
}

afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('keeps legacy works automatic and respects the saved mode and the current form value', () => {
    expect(workAutomationEnabled({})).toBe(true)
    expect(workAutomationEnabled(work)).toBe(false)
    expect(workAutomationEnabled({ ...work, automation_enabled: true })).toBe(true)
})

it('hides personal automation statuses in manual mode and restores them when enabled', async () => {
    const wrapper = mountWork(WorkCellStatus, { studentId: 12 })
    try {
        expect(wrapper.findAll('[data-work-status], [data-submission-state]')).toHaveLength(0)
        await wrapper.setProps({ work: { ...work, automation_enabled: true } })
        expect(wrapper.findAll('[data-work-status], [data-submission-state]')).toHaveLength(4)
        expect(work.status.folder_imported_at).toBe('2026-10-02T10:00:00Z')
    } finally { wrapper.unmount() }
})

it('does not show assessment timestamps or dispatch rows for manual work', () => {
    const wrapper = mountWork(WorkDispatchStatus, { studentId: 12, showResultTime: true })
    try { expect(wrapper.text()).toBe('') } finally { wrapper.unmount() }
})

it('hides folder import and AI actions without querying folder state for manual work', async () => {
    const get = vi.fn()
    vi.stubGlobal('axios', { get })
    workDropboxStates.set('7:2:65', { folder: { id: 'id:saved', name: 'Historischer Ordner' }, can_quick_import: true })
    const wrappers = [WorkFolderStatus, WorkImportActions, WorkAiExport].map(component => mountWork(component))
    try {
        await flushPromises()
        expect(wrappers.map(wrapper => wrapper.text())).toEqual(['', '', ''])
        expect(wrappers[0].find('v-icon').exists()).toBe(false)
        expect(wrappers[1].find('v-btn').exists()).toBe(false)
        expect(wrappers[2].findAll('v-btn').some(button => button.text() === 'KI-Export')).toBe(false)
        expect(get).not.toHaveBeenCalled()
    } finally { wrappers.forEach(wrapper => wrapper.unmount()) }
})
