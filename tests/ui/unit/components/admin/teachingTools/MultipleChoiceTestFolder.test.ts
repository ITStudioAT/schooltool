import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import MultipleChoiceTestFolder from '@/pages/admin/teachingTools/MultipleChoiceTestFolder.vue'

const directories = vi.hoisted(() => ({ supported: false, handle: null as any }))
vi.mock('@/helpers/workImportDirectory', () => ({
    supportsWorkImportDirectory: () => directories.supported,
    loadWorkImportDirectory: vi.fn(async () => directories.handle),
    saveWorkImportDirectory: vi.fn(),
}))
let wrapper
let get
let post
let put
const state = () => ({ configured: true, connected: false, writable: false, folder: { provider: 'local', name: 'E-Mails' }, filename: 'MC-Test_7.json', identity: 'identity' })
const preview = () => ({ hash: 'reviewed-hash', changes: [{ attempt_id: 3, participant: 'student@example.test', before: { score: null }, after: { score: '2.00' } }], conflicts: [], unchanged: 0, pending: 0 })

async function render(status = state(), suggestion = null, suggestionFailure = null) {
    get = vi.fn().mockImplementation(async (url, options) => {
        if (options?.params?.suggest && suggestionFailure) throw suggestionFailure
        return { data: options?.params?.suggest && suggestion ? suggestion : status }
    })
    post = vi.fn()
    put = vi.fn().mockResolvedValue({ data: status })
    vi.stubGlobal('axios', { get, post, put })
    wrapper = mount(MultipleChoiceTestFolder, {
        props: { test: { id: 7, title: 'E-Mails' }, user: { id: 2, school_id: 1 } },
        global: { stubs: {
            'v-card': { template: '<div><slot /></div>' }, 'v-card-title': { template: '<h2><slot /></h2>' },
            'v-card-text': { template: '<div><slot /></div>' }, 'v-card-actions': { template: '<div><slot /></div>' },
            'v-alert': { template: '<div><slot /></div>' }, 'v-spacer': true,
            'v-btn-toggle': { template: '<div><slot /></div>' },
            'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
            'v-text-field': { props: ['modelValue'], template: '<input :value="modelValue" />' },
            FilePond: { name: 'ExchangeFilePond', emits: ['updatefiles'], template: '<div data-testid="file-picker" />' },
        } },
    })
    await flushPromises()
    return wrapper
}

async function click(text) {
    await wrapper.findAll('button').find(button => button.text() === text).trigger('click')
    await flushPromises()
}

beforeEach(() => { directories.supported = false; directories.handle = null })
afterEach(() => { wrapper?.unmount(); vi.unstubAllGlobals(); vi.restoreAllMocks() })

describe('Remembered MC test folder', () => {
    it('automatically offers ordered suggestions without assigning one until the user chooses it', async () => {
        await render({ ...state(), connected: true, writable: true, folder: null }, {
            folder: null, suggested_folder: null, suggestions: [
                { id: 'id:first', name: 'First matching folder', path: '/learning/first', reason: 'Thema und Klasse passen.' },
                { id: 'id:second', name: 'Second matching folder', path: '/learning/second', reason: 'Thema und Klasse passen.' },
            ], message: 'Bitte einen der Vorschläge wählen.',
        })
        expect(get).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/folder/folders', { params: { suggest: 1 } })
        expect(wrapper.text()).toContain('First matching folder')
        expect(wrapper.text()).toContain('Second matching folder')
        expect(wrapper.text()).toContain('/learning/first')
        expect(wrapper.text()).toContain('/learning/second')
        expect(wrapper.text()).toContain('noch nicht zugeordnet')
        expect(put).not.toHaveBeenCalled()
        expect(post).not.toHaveBeenCalled()

        await wrapper.find('[aria-label="Vorgeschlagene Testordner"]').findAll('button')[1].trigger('click')
        await flushPromises()

        expect(put).toHaveBeenCalledWith('/api/admin/multiple-choice/tests/7/folder', { provider: 'dropbox', folder_id: 'id:second' })
        expect(post).not.toHaveBeenCalled()
    })

    it('keeps a deliberately saved local folder without starting a Dropbox search', async () => {
        await render({ ...state(), connected: true })
        expect(get).toHaveBeenCalledTimes(1)
        expect(wrapper.text()).toContain('E-Mails · lokal')
        expect(put).not.toHaveBeenCalled()
    })

    it('distinguishes a failed search from no matches and keeps manual browsing available', async () => {
        const message = 'Dropbox begrenzt momentan die Anfragen. Bitte kurz warten.'
        await render({ ...state(), connected: true, folder: null }, null, { response: { data: { errors: { dropbox: [message] } } } })
        expect(wrapper.text()).toContain('Die Ordnersuche konnte nicht abgeschlossen werden.')
        expect(wrapper.text()).toContain(message)
        expect(wrapper.text()).not.toContain('konnte nicht automatisch gefunden werden')
        get.mockResolvedValueOnce({ data: { folders: [], current: { id: 'id:school', name: 'Schule', path: '/Schule' }, parent: null } })

        await click('Dropbox-Ordner auswählen')

        expect(wrapper.text()).toContain('/Schule')
        expect(put).not.toHaveBeenCalled()
        expect(post).not.toHaveBeenCalled()
    })

    it('shows the confirmed full Dropbox path and offers a read-only refresh of an already open dialog', async () => {
        const folder = { provider: 'dropbox', folder_id: 'id:saved', name: 'E-Mails' }
        await render({ ...state(), connected: true, folder }, {
            folder: { ...folder, path: '/school/materials/learning/emails/exam/E-Mails' }, reused: true, suggestions: [],
        })
        expect(wrapper.text()).toContain('/school/materials/learning/emails/exam/E-Mails')
        expect(wrapper.text()).not.toContain('noch nicht zugeordnet')

        await click('Ordnerempfehlung anzeigen')

        expect(get).toHaveBeenCalledTimes(3)
        expect(put).not.toHaveBeenCalled()
        expect(post).not.toHaveBeenCalled()
    })

    it('provides a file import and honest remembered-name fallback without browser folder rights', async () => {
        await render()
        expect(wrapper.text()).toContain('E-Mails · lokal')
        expect(wrapper.text()).toContain('keinen dauerhaften direkten Ordnerzugriff')
        expect(wrapper.find('[data-testid="file-picker"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Aktuellen Stand herunterladen')
        expect(post).not.toHaveBeenCalled()
    })

    it('previews an uploaded file and requires a separate deliberate result import', async () => {
        await render()
        const packageData = { exchange: { identity: 'identity', results: [] } }
        wrapper.findComponent({ name: 'ExchangeFilePond' }).vm.$emit('updatefiles', [{ file: { size: 50, text: async () => JSON.stringify(packageData) } }])
        await flushPromises()
        expect(post).not.toHaveBeenCalled()
        post.mockResolvedValueOnce({ data: preview() })

        await click('Datei prüfen')

        expect(post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7/folder/import', { package: packageData })
        expect(wrapper.text()).toContain('student@example.test')
        expect(wrapper.text()).toContain('offen → 2.00 Punkte')
        post.mockResolvedValueOnce({ data: { imported: 1 } })
        await click('Geprüfte Ergebnisse übernehmen')
        expect(post).toHaveBeenLastCalledWith('/api/admin/multiple-choice/tests/7/folder/import', { package: packageData, apply: true, hash: 'reviewed-hash' })
        expect(wrapper.emitted('changed')).toHaveLength(1)
    })

    it('blocks conflicting imports and clears a stale file read after file removal', async () => {
        await render()
        let finish
        wrapper.findComponent({ name: 'ExchangeFilePond' }).vm.$emit('updatefiles', [{ file: { size: 30, text: () => new Promise(resolve => { finish = resolve }) } }])
        wrapper.findComponent({ name: 'ExchangeFilePond' }).vm.$emit('updatefiles', [])
        finish('{"exchange":{}}')
        await flushPromises()
        expect(wrapper.text()).not.toContain('Datei prüfen')
        wrapper.findComponent({ name: 'ExchangeFilePond' }).vm.$emit('updatefiles', [{ file: { size: 30, text: async () => '{"exchange":{}}' } }])
        await flushPromises()
        post.mockResolvedValueOnce({ data: { ...preview(), conflicts: ['Beide Seiten wurden geändert.'] } })
        await click('Datei prüfen')
        expect(wrapper.findAll('button').find(button => button.text() === 'Geprüfte Ergebnisse übernehmen').attributes('disabled')).toBeDefined()
    })

    it('offers additional consent for read-only Dropbox without writing or reading test files automatically', async () => {
        await render({ ...state(), connected: true, folder: { provider: 'dropbox', name: 'E-Mails' } })
        expect(wrapper.text()).toContain('Dropbox zum Lesen und Schreiben verbinden')
        expect(wrapper.findAll('button').find(button => button.text() === 'Aktuellen Stand in Dropbox speichern').attributes('disabled')).toBeDefined()
        expect(post).not.toHaveBeenCalled()
    })

    it('rechecks a remembered local file before writing and preserves a concurrent change', async () => {
        directories.supported = true
        const createWritable = vi.fn()
        let reads = 0
        directories.handle = { name: 'E-Mails', queryPermission: vi.fn().mockResolvedValue('granted'),
            getFileHandle: vi.fn(async () => ({ createWritable, getFile: async () => ({ text: async () => ++reads === 1 ? '{"exchange":{"identity":"identity"}}' : '{"changed":true}' }) })) }
        await render()
        post.mockResolvedValueOnce({ data: { filename: 'MC-Test_7.json', package: { exchange: { identity: 'identity' } } } })

        await click('Aktuellen Stand im Ordner speichern')

        expect(createWritable).not.toHaveBeenCalled()
        expect(wrapper.text()).toContain('inzwischen geändert')
        expect(post).toHaveBeenCalledTimes(1)
    })
})
