import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createMemoryHistory, createRouter, RouterLink, RouterView } from 'vue-router'
import { routes } from '../../../../../../resources/routes/admin.js'

vi.mock('@/pages/admin/teachingTools/MultipleChoiceWorkspace.vue', () => ({
    default: { template: '<div data-test="mc-workspace">Vorhandener MC-Workflow</div>' },
}))
vi.mock('@/pages/admin/teachingTools/NumberSystemsGame.vue', () => ({
    default: { template: '<div data-test="number-systems">Signal Null · Neues Abenteuer</div>' },
}))

const wrappers = []
const sharingState = { available: true, published: false, access_mode: 'authenticated', invitation: null, courses: [{ id: 21, title: '2A' }], groups: [] }
beforeEach(() => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: sharingState }), post: vi.fn(), delete: vi.fn() })
})
const navigationButton = defineComponent({
    props: ['to'],
    setup(props, { slots, attrs }) {
        return () => h(RouterLink, { ...attrs, to: props.to }, slots.default)
    },
})

afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.unstubAllGlobals() })

async function renderTools(path, deliveryMode = 'mailpit') {
    const router = createRouter({ history: createMemoryHistory(), routes: routes.filter(route => route.path.startsWith('/admin/teaching-tools')) })
    await router.push(path)
    await router.isReady()
    const wrapper = mount(RouterView, {
        global: { plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: {
            AdminAdminStore: { config: { teaching_email_delivery: { mode: deliveryMode } } },
        } })], stubs: { AdminPageHeader: true, 'v-btn': navigationButton,
            'v-sheet': { template: '<div><slot /></div>' }, 'v-container': { template: '<div><slot /></div>' },
            'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' } } },
    })
    wrappers.push(wrapper)
    await flushPromises()
    return { wrapper, router }
}

describe('Teaching tools games navigation', () => {
    it('keeps the local-only simulator out of Office navigation and explains direct links without contacting Mailpit', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/simulator', 'office_outlook')
        expect(wrapper.find('a[href="/admin/teaching-tools/simulator"]').exists()).toBe(false)
        expect(wrapper.text()).toContain('nur in der lokalen Testumgebung verfügbar')
        expect(axios.get).not.toHaveBeenCalled()
    })
    it('keeps Multiple Choice as the default and opens the existing workspace', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools')

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/multiple-choice')
        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(true)
        expect(wrapper.find('a[href="/admin/teaching-tools/multiple-choice"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.find('a[href="/admin/teaching-tools/games"]').text()).toBe('Spiele')
    })

    it('offers Zahlensysteme and opens the playable adventure workspace', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/games')

        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(false)
        expect(wrapper.find('a[href="/admin/teaching-tools/games"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.findAll('[aria-label="Verfügbare Spiele"] a')).toHaveLength(1)
        expect(wrapper.find('[aria-label="Zahlensysteme öffnen"]').text()).toContain('Zahlensysteme')
        await wrapper.find('[aria-label="Zahlensysteme öffnen"]').trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/games/number-systems')
        expect(wrapper.get('[data-test="number-systems"]').text()).toContain('Neues Abenteuer')
        const back = wrapper.findAll('a').find(link => link.text() === 'Zur Spieleübersicht')
        await back.trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Spiele')
        expect(wrapper.find('[aria-label="Zahlensysteme öffnen"]').exists()).toBe(true)
    })

    it('navigates from categories through number systems to management and starts the student view only on request', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools')

        await wrapper.get('a[href="/admin/teaching-tools/explanations"]').trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations')
        expect(wrapper.get('a[href="/admin/teaching-tools/explanations"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.get('h1').text()).toBe('Erklärungen')
        expect(wrapper.find('[aria-label="Dezimal auf Dual öffnen"]').exists()).toBe(false)
        expect(wrapper.get('[aria-label="Verfügbare Kategorien"]').findAll('a')).toHaveLength(2)
        await wrapper.get('[aria-label="Zahlensysteme öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/number-systems')
        expect(wrapper.get('h1').text()).toBe('Zahlensysteme')
        expect(wrapper.get('[aria-label="Dezimal auf Dual öffnen"]').text()).toContain('Dezimal auf Dual')
        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Verfügbare Spiele"]').exists()).toBe(false)

        await wrapper.get('[aria-label="Dezimal auf Dual öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(wrapper.get('h1').text()).toBe('Dezimal auf Dual')
        expect(wrapper.find('.binary-explanation').exists()).toBe(false)
        expect(wrapper.find('img[alt="QR-Code zur freigegebenen Erklärung"]').exists()).toBe(false)
        expect(window.axios.post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await flushPromises()
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] input').element.value).toBe('13'))
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Veröffentlichen')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Freigabe widerrufen')
        await wrapper.findAll('button').find(button => button.text() === 'Vorschau beenden').trigger('click')
        expect(wrapper.find('.binary-explanation').exists()).toBe(false)
        await wrapper.get('.explanations__back').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Zahlensysteme')
        await wrapper.get('.explanations__back').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Erklärungen')
        const direct = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(direct.wrapper.get('h1').text()).toBe('Dezimal auf Dual')
        expect(direct.wrapper.find('.binary-explanation').exists()).toBe(false)
        expect(direct.wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations/number-systems')
    })

    it('opens the number systems category with binary and hexadecimal explanations', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        expect(wrapper.get('h1').text()).toBe('Zahlensysteme')
        expect(wrapper.get('[aria-label="Verfügbare Erklärungen"]').findAll('a')).toHaveLength(4)
        expect(wrapper.get('[aria-label="Dezimal auf Dual öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('shows the confirmed availability and an unknown status when the status refresh fails', async () => {
        const url = 'https://schooltool.example/explanations/active'
        window.axios.get.mockImplementation(path => {
            if (path.endsWith('/binary-to-hexadecimal')) return Promise.reject(new Error('Unavailable'))
            if (path.endsWith('/decimal-to-binary')) return Promise.resolve({ data: { ...sharingState, published: true, invitation: { url } } })
            if (path.endsWith('/hexadecimal-to-binary')) return Promise.resolve({ data: { ...sharingState, available: false, published: true, invitation: { url } } })
            return Promise.resolve({ data: sharingState })
        })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        expect(wrapper.find('[aria-label="Dezimal auf Dual: veröffentlicht"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Dezimal auf Hexadezimal: nicht verfügbar"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Dual auf Hexadezimal: Status unbekannt"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Hexadezimal auf Dual: nicht verfügbar"]').exists()).toBe(true)

        window.axios.get.mockRejectedValueOnce(new Error('Unavailable'))
        await wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').trigger('click')
        await flushPromises()
        expect(wrapper.find('[aria-label="Dezimal auf Dual: veröffentlicht"]').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Dezimal auf Dual: Status unbekannt"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Der aktuelle Link konnte nicht geladen werden')
    })

    it('opens the network learning area, student preview and sharing setup', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations')
        expect(wrapper.get('[aria-label="Netzwerke öffnen"]').text()).toContain('1 Erklärung')
        expect(wrapper.get('[aria-label="Verfügbare Kategorien"]').find('.explanation-tile__preview').exists()).toBe(false)
        await wrapper.get('[aria-label="Netzwerke öffnen"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Netzwerke')
        expect(wrapper.get('[aria-label="Verfügbare Erklärungen"]').findAll('a')).toHaveLength(1)
        expect(wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations')
        await wrapper.get('[aria-label="Netzwerkgrundlagen und Geräte öffnen"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Netzwerkgrundlagen und Geräte')
        expect(wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations/networks')
        expect(wrapper.text()).not.toContain('Mit eigener Zahl')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-fundamentals')
        expect(window.axios.post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] h1').text()).toContain('Netzwerkgrundlagen'))
        await wrapper.findAll('button').find(button => button.text() === 'Vorschau beenden').trigger('click')
        window.axios.post.mockResolvedValueOnce({ data: { ...sharingState, published: true, access_mode: 'public', invitation: { url: 'https://schooltool.example/explanations/network', qr_data_url: 'data:image/svg+xml;base64,test' } } })
        await wrapper.get('input[value="public"]').setValue(true)
        await wrapper.findAll('button').find(button => button.text() === 'Veröffentlichen').trigger('click')
        await flushPromises()
        expect(window.axios.post).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-fundamentals/publication', { access_mode: 'public' })
        expect(wrapper.get('.explanation-detail__link input').element.value).toBe('https://schooltool.example/explanations/network')
    })

    it.each([
        ['decimal-to-binary', 'Dezimal auf Dual'],
        ['decimal-to-hexadecimal', 'Dezimal auf Hexadezimal'],
        ['binary-to-hexadecimal', 'Dual auf Hexadezimal'],
        ['hexadecimal-to-binary', 'Hexadezimal auf Dual'],
    ])('copies the current student link for %s directly from its card', async (key, title) => {
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        window.axios.get.mockImplementation(url => Promise.resolve({ data: { ...sharingState, published: true,
            invitation: { url: `https://schooltool.example/explanations/${url.split('/').at(-1)}` } } }))
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/number-systems')
        const currentUrl = `https://schooltool.example/explanations/${key}-current`
        window.axios.get.mockResolvedValueOnce({ data: { ...sharingState, published: true,
            invitation: { url: currentUrl } } })

        await wrapper.get(`button[aria-label="${title}: Link kopieren"]`).trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith(currentUrl)
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/number-systems')
        expect(wrapper.get(`button[aria-label="${title}: Link kopieren"]`).text()).toContain('Link kopiert')
        expect(window.axios.post).not.toHaveBeenCalled()
        expect(window.axios.delete).not.toHaveBeenCalled()
    })

    it('opens sharing setup for an unpublished card without publishing it', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        await wrapper.get('button[aria-label="Dezimal auf Dual: Link einrichten"]').trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('offers the student link for manual copying when clipboard access fails', async () => {
        vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('Denied')) } })
        const url = 'https://schooltool.example/explanations/manual'
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true,
            invitation: { url, local_only: true } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        await wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').trigger('click')
        await flushPromises()

        const card = wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').element.closest('li')!
        expect(card.querySelector('input')?.value).toBe(url)
        expect(card.textContent).toContain('Textfeld markieren und kopieren')
        expect(card.textContent).toContain('nur auf diesem Rechner erreichbar')
        expect(card.querySelector('[aria-label="Dezimal auf Dual: veröffentlicht"]')).not.toBeNull()
        expect(card.querySelector('button')?.textContent).not.toContain('Link kopiert')
    })

    it.each(['revoked', 'unavailable'])('does not copy a stale link when sharing is %s', async condition => {
        const writeText = vi.fn()
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true,
            invitation: { url: 'https://schooltool.example/explanations/stale' } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')
        if (condition === 'revoked') window.axios.get.mockResolvedValueOnce({ data: sharingState })
        else window.axios.get.mockRejectedValueOnce(new Error('Offline'))

        await wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').trigger('click')
        await flushPromises()

        expect(writeText).not.toHaveBeenCalled()
        expect(wrapper.get('[role="alert"]').text()).toContain(condition === 'revoked' ? 'nicht freigegeben' : 'nicht geladen')
        expect(wrapper.find(`[aria-label="Dezimal auf Dual: ${condition === 'revoked' ? 'nicht verfügbar' : 'Status unbekannt'}"]`).exists()).toBe(true)
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('distinguishes unsaved access selection and replaces link and QR only after a successful save', async () => {
        const previous = { ...sharingState, published: true, invitation: { url: 'https://schooltool.example/explanations/old', qr_data_url: 'data:image/svg+xml;base64,old' } }
        window.axios.get.mockResolvedValue({ data: previous })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-hexadecimal')
        expect(wrapper.get('h1').text()).toBe('Dezimal auf Hexadezimal')
        await wrapper.get('input[value="public"]').setValue(true)
        expect(window.axios.post).not.toHaveBeenCalled()
        expect(wrapper.get('.explanation-unsaved').text()).toContain('bisherige Link und QR-Code ungültig')
        expect(wrapper.get('.explanation-detail__link input').element.value).toBe(previous.invitation.url)
        const save = wrapper.findAll('button').find(button => button.text() === 'Zugangsänderung speichern')!
        window.axios.post.mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen' } } })
        await save.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Speichern fehlgeschlagen')
        expect(wrapper.find('.explanation-success').exists()).toBe(false)
        expect(wrapper.get('.explanation-detail__link input').element.value).toBe(previous.invitation.url)
        window.axios.post.mockResolvedValueOnce({ data: { ...previous, access_mode: 'public', invitation: { url: 'https://schooltool.example/explanations/new', qr_data_url: 'data:image/svg+xml;base64,new' } } })
        await save.trigger('click')
        await flushPromises()
        expect(window.axios.post).toHaveBeenLastCalledWith('/api/admin/teaching-explanations/decimal-to-hexadecimal/publication', { access_mode: 'public' })
        expect(wrapper.find('.explanation-unsaved').exists()).toBe(false)
        expect(wrapper.get('.explanation-success').text()).toContain('Zugang gespeichert')
        expect(wrapper.get('.explanation-detail__link input').element.value).toContain('/new')
        expect(wrapper.get('.explanation-detail__qr').attributes('src')).toBe('data:image/svg+xml;base64,new')
    })

    it('publishes only on explicit action with the chosen access and can revoke the share', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(window.axios.post).not.toHaveBeenCalled()
        window.axios.post.mockResolvedValue({ data: { ...sharingState, published: true, access_mode: 'public', invitation: { url: 'https://schooltool.example/explanations/token', qr_data_url: 'data:image/svg+xml;base64,test' } } })
        await wrapper.get('input[value="public"]').setValue(true)
        await wrapper.findAll('button').find(button => button.text() === 'Veröffentlichen').trigger('click')
        await flushPromises()
        expect(window.axios.post).toHaveBeenCalledWith('/api/admin/teaching-explanations/decimal-to-binary/publication', { access_mode: 'public' })
        expect(wrapper.get('img[alt="QR-Code zur freigegebenen Erklärung"]').attributes('src')).toBe('data:image/svg+xml;base64,test')
        window.axios.delete.mockResolvedValue({ data: sharingState })
        await wrapper.findAll('button').find(button => button.text() === 'Freigabe widerrufen').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Nicht veröffentlicht')
        expect(wrapper.find('.explanation-detail__link').exists()).toBe(false)
        expect(wrapper.find('img[alt="QR-Code zur freigegebenen Erklärung"]').exists()).toBe(false)
    })

    it.each(['public', 'authenticated'])('shows the existing QR immediately with %s access without creating a publication', async accessMode => {
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true, access_mode: accessMode,
            invitation: { url: 'https://schooltool.example/explanations/existing', qr_data_url: 'data:image/svg+xml;base64,existing' } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')

        expect(wrapper.get('img[alt="QR-Code zur freigegebenen Erklärung"]').attributes('src')).toBe('data:image/svg+xml;base64,existing')
        expect(wrapper.get('input[name="explanation-access"]:checked').element.value).toBe(accessMode)
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('offers only link copying and the visible QR for sharing without an email entry or recipient picker', async () => {
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true, invitation: { url: 'https://schooltool.example/explanations/token', qr_data_url: 'data:image/svg+xml;base64,existing' } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')

        expect(wrapper.findAll('button').some(button => button.text() === 'Link kopieren')).toBe(true)
        expect(wrapper.find('img[alt="QR-Code zur freigegebenen Erklärung"]').exists()).toBe(true)
        expect(wrapper.text()).not.toContain('E-Mail')
        expect(wrapper.find('select').exists()).toBe(false)
        expect(window.axios.post).not.toHaveBeenCalled()
    })
})
