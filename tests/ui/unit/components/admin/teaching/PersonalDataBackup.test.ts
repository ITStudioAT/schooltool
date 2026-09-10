import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import PersonalDataBackup from '@/pages/admin/teaching/settings/components/PersonalDataBackup.vue'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))

const backup = { id: 17, created_at: '2026-09-10T10:00:00Z', summary: { courses: 2 }, mail_message: 'E-Mail versendet.' }

function renderPage() {
    const go = vi.fn()
    const wrapper = mount(PersonalDataBackup, {
        global: {
            mocks: { $router: { go } },
            stubs: {
                ItsGridBox: { template: '<section><slot /></section>' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-btn': {
                    props: ['disabled', 'href'],
                    template: '<a v-if="href" :href="href"><slot /></a><button v-else :disabled="disabled"><slot /></button>',
                },
                'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
                'v-card': { template: '<div><slot /></div>' },
                'v-card-title': { template: '<h2><slot /></h2>' },
                'v-card-text': { template: '<div><slot /></div>' },
                'v-card-actions': { template: '<div><slot /></div>' },
                'v-file-input': true,
            },
        },
    })

    function button(label: string) {
        return wrapper.findAll('button').find((entry) => entry.text() === label)!
    }

    return { wrapper, go, button }
}

describe('Personal teaching backups', () => {
    beforeEach(() => {
        vi.resetAllMocks()
        vi.mocked(axios.get).mockResolvedValue({ data: { data: [backup] } })
    })

    it('lists own backups with download link and opens a cancellable overwrite confirmation', async () => {
        const { wrapper, button, go } = renderPage()
        await flushPromises()

        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/personal-backups')
        expect(wrapper.text()).toContain('2 Kurse')
        expect(wrapper.find('a').attributes('href')).toBe('/api/admin/teaching/personal-backups/17/download')
        await button('Wiederherstellen').trigger('click')
        expect(wrapper.find('[role="dialog"]').text()).toContain('Spätere Änderungen werden überschrieben')
        await button('Abbrechen').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(axios.post).not.toHaveBeenCalled()
        expect(go).not.toHaveBeenCalled()
        wrapper.unmount()
    })

    it('sends confirmation once and reloads all teaching state after successful restore', async () => {
        const { wrapper, button, go } = renderPage()
        await flushPromises()
        vi.mocked(axios.post).mockResolvedValue({ data: { data: { restored: true } } })
        await button('Wiederherstellen').trigger('click')
        await button('Jetzt ersetzen und wiederherstellen').trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/personal-backups/17/restore', { confirm_restore: true })
        expect(go).toHaveBeenCalledExactlyOnceWith(0)
        expect(button('Jetzt ersetzen und wiederherstellen').attributes('disabled')).toBeDefined()
        wrapper.unmount()
    })

    it('keeps confirmation open with an understandable validation failure and does not reload', async () => {
        const { wrapper, button, go } = renderPage()
        await flushPromises()
        vi.mocked(axios.post).mockRejectedValue({ response: { data: { errors: { backup: ['Ein benötigtes Schülerkonto fehlt.'] } } } })
        await button('Wiederherstellen').trigger('click')
        await button('Jetzt ersetzen und wiederherstellen').trigger('click')
        await flushPromises()

        expect(wrapper.find('[role="dialog"]').text()).toContain('Ein benötigtes Schülerkonto fehlt.')
        expect(go).not.toHaveBeenCalled()
        expect(button('Abbrechen').attributes('disabled')).toBeUndefined()
        wrapper.unmount()
    })

    it('retains the created backup when email fails and offers a manual download', async () => {
        const { wrapper, button } = renderPage()
        await flushPromises()
        vi.mocked(axios.post).mockResolvedValue({ data: { data: { ...backup, id: 18, mail_status: 'failed', mail_message: 'E-Mail fehlgeschlagen. Bitte herunterladen.' } } })
        await button('Sicherung erstellen').trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/personal-backups')
        expect(wrapper.text()).toContain('E-Mail fehlgeschlagen. Bitte herunterladen.')
        expect(wrapper.find('a').attributes('href')).toBe('/api/admin/teaching/personal-backups/18/download')
        wrapper.unmount()
    })

    it('uploads a saved file without restoring it automatically', async () => {
        const { wrapper, button, go } = renderPage()
        await flushPromises()
        const file = new File(['encrypted'], 'unterricht.schooltool')
        wrapper.vm.file = file
        await wrapper.vm.$nextTick()
        vi.mocked(axios.post).mockResolvedValue({ data: { data: { ...backup, id: 19 } } })
        await button('Sicherungsdatei hochladen').trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenCalledTimes(1)
        const [url, form] = vi.mocked(axios.post).mock.calls[0]
        expect(url).toBe('/api/admin/teaching/personal-backups/import')
        const uploaded = (form as FormData).get('backup') as File
        expect(uploaded.name).toBe('unterricht.schooltool')
        expect(await uploaded.text()).toBe('encrypted')
        expect(go).not.toHaveBeenCalled()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        wrapper.unmount()
    })

    it('requests administrative student mapping without restoring or sending a message automatically', async () => {
        const { wrapper, button, go } = renderPage()
        await flushPromises()
        vi.mocked(axios.post).mockResolvedValue({ data: { data: { recovery_requested_at: '2026-09-10T12:00:00Z' } } })
        await button('Schülerzuordnung anfragen').trigger('click')
        await flushPromises()

        expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/personal-backups/17/request-recovery')
        expect(wrapper.text()).toContain('Die Sicherung steht der Administration zur Prüfung')
        expect(go).not.toHaveBeenCalled()
        wrapper.unmount()
    })
})
