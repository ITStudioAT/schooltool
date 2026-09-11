import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Holidays from '@/pages/admin/teaching/admin/holidays/Holidays.vue'
import { useHolidayStore } from '@/stores/admin/teaching/HolidayStore'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'

let wrapper

afterEach(() => {
    wrapper?.unmount()
    vi.restoreAllMocks()
    vi.useRealTimers()
})

async function mountHolidays() {
    wrapper = mount(Holidays, {
        global: {
            plugins: [createTestingPinia({ createSpy: vi.fn })],
            stubs: {
                ItsGridBox: { template: '<section><slot /></section>' },
                'v-col': { template: '<div><slot /></div>' },
                'v-btn': { props: ['disabled', 'loading'], template: '<button :disabled="disabled" :aria-busy="loading"><slot /></button>' },
                'v-expand-transition': { template: '<div><slot /></div>' },
                'v-file-input': { props: ['modelValue', 'disabled'], emits: ['update:modelValue'], template: '<input type="file" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.files[0])" />' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-list': { template: '<div><slot /></div>' },
                'v-list-item': { template: '<div><slot /></div>' },
                'v-list-item-title': { template: '<div><slot /></div>' },
                'v-divider': true,
                'v-checkbox': true,
                'v-checkbox-btn': true,
                'v-avatar': true,
                'v-icon': true,
                'v-form': true,
                'v-date-input': true,
                'v-text-field': true,
            },
        },
    })
    await flushPromises()
    vi.mocked(useHolidayStore().index).mockClear()
    return wrapper
}

function button(label: string) {
    const result = wrapper.findAll('button').find((candidate) => candidate.text() === label)
    if (!result) throw new Error(`Button fehlt: ${label}`)
    return result
}

async function selectImportFile() {
    await button('Ferien importieren').trigger('click')
    const file = new File(['{}'], 'ferien.json', { type: 'application/json' })
    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
    await input.trigger('change')
    return file
}

describe('Holiday file transfer', () => {
    it('requires a file and explains the selected schoolyear and merge behavior', async () => {
        await mountHolidays()
        await button('Ferien importieren').trigger('click')

        expect(button('Datei importieren').attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain('ausgewählte Schuljahr')
        expect(wrapper.text()).toContain('andere Termine bleiben erhalten')
    })

    it('shows import counters and refreshes holidays and course dates after success', async () => {
        await mountHolidays()
        const store = useHolidayStore()
        const counts = { created: 2, updated: 1, unchanged: 3 }
        vi.mocked(store.importHolidays).mockResolvedValue(counts)
        const file = await selectImportFile()
        await button('Datei importieren').trigger('click')
        await flushPromises()

        expect(store.importHolidays).toHaveBeenCalledWith(file)
        expect(store.index).toHaveBeenCalledOnce()
        expect(useCourseStore().index).toHaveBeenCalledOnce()
        expect(wrapper.get('[role="status"]').text()).toContain('2 freie Tage ergänzt, 1 aktualisiert, 3 unverändert')
        expect(wrapper.vm.import_file).toBeNull()
        expect(button('Datei importieren').attributes('disabled')).toBeDefined()
    })

    it('keeps the failed file selected and displays server errors without refreshing data', async () => {
        await mountHolidays()
        const store = useHolidayStore()
        vi.mocked(store.importHolidays).mockImplementation(async () => {
            store.import_errors = ['Termin 1: Das Datum ist ungültig.']
            return false
        })
        const file = await selectImportFile()
        await button('Datei importieren').trigger('click')
        await flushPromises()

        expect(wrapper.get('[role="alert"]').text()).toContain('Termin 1: Das Datum ist ungültig.')
        expect(wrapper.vm.import_file).toBe(file)
        expect(button('Datei importieren').attributes('disabled')).toBeUndefined()
        expect(store.index).not.toHaveBeenCalled()
        expect(useCourseStore().index).not.toHaveBeenCalled()
    })

    it('blocks duplicate imports and exports until the running import finishes', async () => {
        await mountHolidays()
        const store = useHolidayStore()
        let finishImport: (result: object) => void
        vi.mocked(store.importHolidays).mockReturnValue(new Promise((resolve) => { finishImport = resolve }))
        await selectImportFile()
        await button('Datei importieren').trigger('click')
        await flushPromises()

        expect(button('Datei importieren').attributes('aria-busy')).toBe('true')
        expect(button('Ferien exportieren').attributes('disabled')).toBeDefined()
        expect(wrapper.get('input[type="file"]').attributes('disabled')).toBeDefined()
        await wrapper.vm.importHolidays()
        await wrapper.vm.exportHolidays()
        expect(store.importHolidays).toHaveBeenCalledOnce()
        expect(store.exportHolidays).not.toHaveBeenCalled()

        finishImport({ created: 0, updated: 0, unchanged: 1 })
        await flushPromises()
        expect(button('Ferien exportieren').attributes('disabled')).toBeUndefined()
    })

    it('downloads the export and releases its temporary object URL', async () => {
        await mountHolidays()
        const blob = new Blob(['{}'], { type: 'application/json' })
        vi.mocked(useHolidayStore().exportHolidays).mockResolvedValue(blob)
        const createUrl = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:holidays-test')
        const revokeUrl = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
        const downloads: { name: string; href: string; attached: boolean }[] = []
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
            downloads.push({ name: this.download, href: this.href, attached: document.body.contains(this) })
        })
        vi.useFakeTimers()

        await wrapper.vm.exportHolidays()

        expect(createUrl).toHaveBeenCalledWith(blob)
        expect(downloads).toEqual([{ name: 'ferien.json', href: 'blob:holidays-test', attached: true }])
        expect(document.querySelector('a[download="ferien.json"]')).toBeNull()
        await vi.advanceTimersByTimeAsync(1000)
        expect(revokeUrl).toHaveBeenCalledWith('blob:holidays-test')
    })
})
