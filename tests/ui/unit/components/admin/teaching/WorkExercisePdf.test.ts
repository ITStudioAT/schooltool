import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkExercisePdf from '@/pages/admin/teaching/overview/components/WorkExercisePdf.vue'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails' }
const pdf = { id: 'id:pdf', name: 'Aufgabenblatt.pdf', type: 'file' }

function render(get: ReturnType<typeof vi.fn>) {
    vi.stubGlobal('axios', { get })
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:task-pdf')
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
    return mount(WorkExercisePdf, { props: { work, date: '2026-10-23', section: 'Angaben' }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
        stubs: {
            'v-dialog': { name: 'VDialog', props: { persistent: Boolean }, template: '<section role="dialog"><slot /></section>' },
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            CurriculumPdfPreview: { name: 'CurriculumPdfPreview', props: ['src', 'documentId'], template: '<div />' },
        },
    } })
}

afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals() })

it('downloads the single PDF with its original filename without opening a preview', async () => {
    const get = vi.fn().mockResolvedValueOnce({ data: { entries: [pdf, { name: 'Quelle.md', type: 'file' }], cursor: null } })
        .mockResolvedValueOnce({ data: new Blob(['%PDF-1.7'], { type: 'application/pdf' }) })
    const wrapper = render(get)
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
        expect(this.download).toBe('Aufgabenblatt.pdf')
        expect(this.href).toBe('blob:task-pdf')
    })
    expect(get).not.toHaveBeenCalled()
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(get.mock.calls[1][1]).toMatchObject({ params: { contents: 1, exercise: 1, section: 'Angaben', date: '2026-10-23', pdf: 1, file_id: 'id:pdf' }, responseType: 'blob' })
    expect(click).toHaveBeenCalledOnce()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Quelle.md')
    wrapper.unmount()
})

it('offers only PDFs when several documents exist and fetches the selected file', async () => {
    const second = { ...pdf, id: 'id:second', name: 'Beilage.pdf' }
    const get = vi.fn().mockResolvedValueOnce({ data: { entries: [pdf, second, { type: 'folder', name: 'Quellen' }], cursor: null } })
        .mockResolvedValueOnce({ data: new Blob(['%PDF-1.7'], { type: 'application/pdf' }) })
    const wrapper = render(get)
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(get).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).not.toContain('Quellen')
    await wrapper.findAll('button').find(button => button.text() === 'Beilage.pdf')!.trigger('click')
    await flushPromises()
    expect(get.mock.calls[1][1].params.file_id).toBe('id:second')
    expect(click).toHaveBeenCalledOnce()
    wrapper.unmount()
})

it('reports absent PDFs without opening folders and ignores late results after changing work', async () => {
    let finish: (value: unknown) => void = () => {}
    const get = vi.fn().mockResolvedValueOnce({ data: { entries: [], cursor: null } })
        .mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
    const wrapper = render(get)
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Keine PDF')
    await wrapper.get('button').trigger('click')
    await wrapper.setProps({ work: { ...work, id: 66 } })
    finish({ data: { entries: [pdf], cursor: null } })
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(get).toHaveBeenCalledTimes(2)
    wrapper.unmount()
})
