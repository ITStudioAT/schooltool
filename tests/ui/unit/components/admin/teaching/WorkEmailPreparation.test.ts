import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createVuetify } from 'vuetify'
import { VBtn, VBtnToggle } from 'vuetify/components'
import WorkEmailPreparation from '@/pages/admin/teaching/overview/components/WorkEmailPreparation.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

const work = { id: 65, teaching_course_id: 2, title: 'E-Mails', status: {} }
const state = { userId: 7, workId: 65, courseId: 2, date: '2026-10-20', status: 'found',
    folder: { id: 'id:exercise', name: 'LF' }, emailPreparation: { status: 'missing' } }
const originalExecCommand = Object.getOwnPropertyDescriptor(document, 'execCommand')

function render(props = {}, get = vi.fn()) {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText }, userAgent: 'Test' })
    vi.stubGlobal('axios', { get, post: vi.fn() })
    const wrapper = mount(WorkEmailPreparation, { attachTo: document.body, props: { work, state, date: state.date, ...props }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7, email: 'admin@example.test' } } } } }), createVuetify({ components: { VBtn, VBtnToggle } })],
        components: { 'v-btn': VBtn, 'v-btn-toggle': VBtnToggle }, stubs: {
            'v-btn': false, VBtn: false, 'v-btn-toggle': false, VBtnToggle: false,
            'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
        },
    } })
    return { wrapper, get, writeText }
}

function button(wrapper, text) {
    return wrapper.findAll('button').find(item => item.text() === text)!
}

function exported(addressForm = 'du') {
    return { data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        context: { purpose: 'prepare-exercise-emails', exercise_folder: { id: 'id:exercise' },
            email_address_form: addressForm, delivery: { send_authorized: false } }, text: `Auftrag zur Vorbereitung: ${addressForm}`,
    } } }
}

function dispatchExported(deliveryMode = 'live') {
    return { data: { data: { work_id: 65, course_id: 2, roster_complete: true,
        context: { purpose: 'send-exercise-emails', exercise_folder: { id: 'id:exercise' },
            email_preparation: { status: 'ready' }, delivery: { selection: deliveryMode,
                mode: deliveryMode === 'test' ? 'test_office_outlook' : 'live_office_outlook',
                record_dispatch: deliveryMode === 'live', send_authorized: true, requires_explicit_send_confirmation: false,
                ...(deliveryMode === 'test' ? { messages_count: 1, recipient_user_id: 7, recipient: 'admin@example.test' } : {}) } },
        text: deliveryMode === 'test' ? 'Geprüfter Testauftrag' : 'Geprüfter Versandauftrag',
    } } }
}

function preparedPreview(overrides = {}) {
    return { data: { data: { work_id: 65, course_id: 2, folder_id: 'id:exercise', prepared_at: '2026-10-20T05:26:00Z',
        from: 'teacher@example.test', to: 'student@example.test', subject: 'Leistungsfeststellung',
        html: '<html><body><p>Hallo! <a href="https://schooltool.example/personal-token" onclick="alert(1)">Zum Test</a></p><img src="https://external.example/tracker.png"><script>alert(1)</script><form action="https://external.example/send"><button>Absenden</button></form></body></html>',
        attachments: ['Aufgabe.pdf'], ...overrides,
    } } }
}

afterEach(() => {
    vi.restoreAllMocks(); vi.unstubAllGlobals(); document.body.innerHTML = ''
    if (originalExecCommand) Object.defineProperty(document, 'execCommand', originalExecCommand)
    else delete (document as any).execCommand
})

it.each(['live', 'test'])('shows the configured local Mailpit route and copies its authorized instruction: %s', async selection => {
    const response = dispatchExported(selection)
    Object.assign(response.data.data.context.delivery, { mode: `${selection === 'test' ? 'test_' : 'live_'}mailpit`,
        transport: 'mailpit', local_only: true, smtp_host: '127.0.0.1', smtp_port: 1025 })
    const { wrapper, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } }, vi.fn().mockResolvedValue(response))
    useAdminStore().config.teaching_email_delivery = { mode: 'mailpit', available: true, label: 'Testversand über Mailpit' }
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        expect(wrapper.text()).toContain('Testversand über Mailpit')
        await button(wrapper, selection === 'live' ? 'Tatsächlich versenden' : 'Test an mich').trigger('click')
        await flushPromises()
        await button(wrapper, 'Auftrag kopieren').trigger('click')
        expect(writeText).toHaveBeenCalledWith(response.data.data.text)
        expect(button(wrapper, 'Kopiert').exists()).toBe(true)
        expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it.each(['missing', 'unknown', 'ready'])('offers the separate send button only for confirmed readiness: %s', status => {
    const { wrapper, get } = render({ state: { ...state, emailPreparation: { status } } })
    try {
        expect(Boolean(button(wrapper, 'E-Mails versenden'))).toBe(status === 'ready')
        expect(Boolean(button(wrapper, 'E-Mails vorbereiten'))).toBe(status !== 'ready')
        expect(get).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('loads and copies the send instruction without asking for another address form or sending mail', async () => {
    const { wrapper, get, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } },
        vi.fn().mockResolvedValue(dispatchExported()))
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        expect(get).not.toHaveBeenCalled()
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
        await button(wrapper, 'Tatsächlich versenden').trigger('click')
        await flushPromises()

        expect(wrapper.get('[role="dialog"]').text()).toContain('Gespeicherte E-Mails verwenden')
        expect(button(wrapper, 'Du-Form')).toBeUndefined()
        expect(button(wrapper, 'Sie-Form')).toBeUndefined()
        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/ai-export', {
            params: { purpose: 'email_dispatch', delivery_mode: 'live', date: state.date }, signal: expect.any(AbortSignal),
        })
        expect(writeText).not.toHaveBeenCalled()

        await button(wrapper, 'Auftrag kopieren').trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith('Geprüfter Versandauftrag')
        expect(wrapper.get('[role="status"]').text()).toContain('Versandauftrag kopiert')
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('can switch from a preparation draft to sending without copying or reusing the earlier instruction', async () => {
    const get = vi.fn().mockResolvedValueOnce(exported()).mockResolvedValueOnce(dispatchExported())
    const { wrapper, writeText } = render({}, get)
    try {
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        await button(wrapper, 'Du-Form').trigger('click')
        await flushPromises()
        await button(wrapper, 'Schließen').trigger('click')
        await wrapper.setProps({ state: { ...state, emailPreparation: { status: 'ready' } } })
        expect(button(wrapper, 'E-Mails vorbereiten')).toBeUndefined()
        expect(button(wrapper, 'E-Mails versenden')).toBeDefined()
        await button(wrapper, 'E-Mails versenden').trigger('click')
        await button(wrapper, 'Tatsächlich versenden').trigger('click')
        await flushPromises()
        await button(wrapper, 'Auftrag kopieren').trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledTimes(2)
        expect(writeText).toHaveBeenCalledExactlyOnceWith('Geprüfter Versandauftrag')
    } finally { wrapper.unmount() }
})

it.each(['purpose', 'readiness', 'confirmation'])('rejects an unverified send instruction: %s', async invalid => {
    const response = dispatchExported()
    if (invalid === 'purpose') response.data.data.context.purpose = 'prepare-exercise-emails'
    if (invalid === 'readiness') response.data.data.context.email_preparation.status = 'unknown'
    if (invalid === 'confirmation') response.data.data.context.delivery.requires_explicit_send_confirmation = true
    const { wrapper, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } }, vi.fn().mockResolvedValue(response))
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        await button(wrapper, 'Tatsächlich versenden').trigger('click')
        await flushPromises()

        expect(wrapper.get('[role="alert"]').text()).toContain('nicht eindeutig bestätigt')
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
        expect(writeText).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('discards an outstanding send instruction when readiness is revoked', async () => {
    let finish: (value: unknown) => void = () => {}
    const { wrapper, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } },
        vi.fn(() => new Promise(resolve => { finish = resolve })))
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        await button(wrapper, 'Tatsächlich versenden').trigger('click')
        await wrapper.setProps({ state: { ...state, emailPreparation: { status: 'unknown' } } })
        finish(dispatchExported())
        await flushPromises()

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(button(wrapper, 'E-Mails versenden')).toBeUndefined()
        expect(writeText).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('shows a failed readiness recheck without enabling copying', async () => {
    const get = vi.fn().mockRejectedValue({ response: { data: { message: 'Bitte die Vorbereitung erneut prüfen.' } } })
    const { wrapper, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } }, get)
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        await button(wrapper, 'Tatsächlich versenden').trigger('click')
        await flushPromises()

        expect(wrapper.get('[role="alert"]').text()).toBe('Bitte die Vorbereitung erneut prüfen.')
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
        expect(writeText).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('copies a one-message test instruction addressed only to the logged-in admin without recording dispatch', async () => {
    const { wrapper, get, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } },
        vi.fn().mockResolvedValue(dispatchExported('test')))
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        await button(wrapper, 'Test an mich').trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/ai-export', {
            params: { purpose: 'email_dispatch', delivery_mode: 'test', date: state.date }, signal: expect.any(AbortSignal),
        })
        expect(wrapper.get('[role="dialog"]').text()).toContain('admin@example.test')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Ohne Versandaufzeichnung')
        await button(wrapper, 'Auftrag kopieren').trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith('Geprüfter Testauftrag')
        expect(axios.post).not.toHaveBeenCalled()
        await button(wrapper, 'Schließen').trigger('click')
        await button(wrapper, 'E-Mails versenden').trigger('click')
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
        expect(get).toHaveBeenCalledTimes(1)
    } finally { wrapper.unmount() }
})

it.each(['selection', 'recipient', 'user', 'count', 'recording'])('rejects a test instruction with an incorrect %s', async invalid => {
    const response = dispatchExported('test')
    const delivery = response.data.data.context.delivery
    if (invalid === 'selection') delivery.selection = 'live'
    if (invalid === 'recipient') delivery.recipient = 'student@example.test'
    if (invalid === 'user') delivery.recipient_user_id = 8
    if (invalid === 'count') delivery.messages_count = 15
    if (invalid === 'recording') delivery.record_dispatch = true
    const { wrapper, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } }, vi.fn().mockResolvedValue(response))
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        await button(wrapper, 'Test an mich').trigger('click')
        await flushPromises()

        expect(wrapper.get('[role="alert"]').text()).toContain('nicht eindeutig bestätigt')
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
        expect(writeText).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('discards a late live instruction after switching to a test', async () => {
    let finish: (value: unknown) => void = () => {}
    const get = vi.fn().mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
        .mockResolvedValueOnce(dispatchExported('test'))
    const { wrapper, writeText } = render({ state: { ...state, emailPreparation: { status: 'ready' } } }, get)
    try {
        await button(wrapper, 'E-Mails versenden').trigger('click')
        await button(wrapper, 'Tatsächlich versenden').trigger('click')
        await button(wrapper, 'Test an mich').trigger('click')
        await flushPromises()
        finish(dispatchExported())
        await flushPromises()
        await button(wrapper, 'Auftrag kopieren').trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledTimes(2)
        expect(writeText).toHaveBeenCalledExactlyOnceWith('Geprüfter Testauftrag')
    } finally { wrapper.unmount() }
})

it.each(['du', 'sie'])('offers a separate preparation button and requires the explicit %s form before copying', async addressForm => {
    const get = vi.fn().mockResolvedValue(exported(addressForm))
    const { wrapper, writeText } = render({}, get)
    try {
        expect(wrapper.find('[role="img"]').exists()).toBe(false)
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Wie sollen die Schüler:innen angesprochen werden?')
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
        expect(get).not.toHaveBeenCalled()
        await button(wrapper, addressForm === 'du' ? 'Du-Form' : 'Sie-Form').trigger('click')
        await flushPromises()
        expect(writeText).not.toHaveBeenCalled()
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeUndefined()
        await button(wrapper, 'Auftrag kopieren').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/ai-export', {
            params: { purpose: 'email_preparation', address_form: addressForm, date: state.date }, signal: expect.any(AbortSignal),
        })
        expect(writeText).toHaveBeenCalledExactlyOnceWith(`Auftrag zur Vorbereitung: ${addressForm}`)
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each([
    { status: 'missing', color: 'error', label: 'E-Mails vorbereiten' },
    { status: 'ready', color: 'success', label: 'Versandbereit – noch nicht versandt' },
    { status: 'unknown', color: 'grey', label: 'E-Mail-Vorbereitung konnte nicht bestätigt werden' },
])('shows the $status icon and offers information only for confirmed readiness', ({ status, color, label }) => {
    const { wrapper, get } = render({ statusOnly: true, state: { ...state, emailPreparation: { status } } })
    try {
        expect(wrapper.get('[role="img"]').attributes('color')).toBe(color)
        expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe(label)
        expect(wrapper.find('button').exists()).toBe(false)
        expect(wrapper.find('[role="button"]').exists()).toBe(status === 'ready')
        expect(get).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each([null, 'missing', 'checking', 'unknown'])('keeps the preparation icon visible and inactive without a confirmed exercise: %s', async status => {
    const { wrapper, get, writeText } = render({ statusOnly: true,
        state: status === null ? null : { ...state, status, emailPreparation: { status: 'ready' } } })
    try {
        expect(wrapper.get('[role="img"]').attributes('color')).toBe('grey')
        expect(wrapper.get('[role="img"]').attributes('aria-label')).toContain('E-Mails vorbereiten')
        expect(wrapper.find('[role="button"]').exists()).toBe(false)

        await wrapper.get('span[aria-disabled="true"]').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(get).not.toHaveBeenCalled()
        expect(writeText).not.toHaveBeenCalled()

        await wrapper.setProps({ state: { ...state, emailPreparation: { status: 'ready' } } })
        expect(wrapper.get('[role="img"]').attributes('color')).toBe('success')
        await wrapper.get('[role="button"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Versandbereitschaft')
    } finally { wrapper.unmount() }
})

it.each(['click', 'keydown.enter', 'keydown.space'])('opens preparation information through %s without fetching or copying an instruction', async event => {
    const { wrapper, get, writeText } = render({ statusOnly: true, state: { ...state,
        checkedAt: '2026-10-23T05:36:00Z', folder: { ...state.folder, path: '/Materialien/Modul/Leistungsfeststellung/LF' },
        emailPreparation: { status: 'ready', prepared_at: '2026-10-20T05:26:00Z', recipients_count: 15 } } })
    try {
        const icon = wrapper.get('[role="button"]')
        expect(icon.attributes('tabindex')).toBe('0')
        await icon.trigger(event)

        const dialog = wrapper.get('[role="dialog"]')
        expect(dialog.text()).toContain('Versandbereitschaft · E-Mails')
        expect(dialog.findAll('dd').map(item => item.text())).toEqual([
            '20.10.26, 07:26', '15', '23.10.26, 07:36', '/Materialien/Modul/Leistungsfeststellung/LF',
        ])
        expect(button(wrapper, 'Auftrag kopieren')).toBeUndefined()
        expect(get).not.toHaveBeenCalled()
        expect(writeText).not.toHaveBeenCalled()
        expect(axios.post).not.toHaveBeenCalled()

        await button(wrapper, 'Schließen').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('shows the stored personal email safely without sending or opening personal links', async () => {
    const get = vi.fn().mockResolvedValue(preparedPreview())
    const { wrapper } = render({ statusOnly: true, state: { ...state,
        emailPreparation: { status: 'ready', prepared_at: '2026-10-20T05:26:00Z' } } }, get)
    try {
        await wrapper.get('[role="button"]').trigger('click')
        expect(get).not.toHaveBeenCalled()
        await button(wrapper, 'E-Mail ansehen').trigger('click')
        await flushPromises()

        expect(get).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/email-preparation-preview', {
            params: { date: state.date }, signal: expect.any(AbortSignal),
        })
        const dialog = wrapper.get('[role="dialog"]')
        expect(dialog.text()).toContain('teacher@example.test')
        expect(dialog.text()).toContain('student@example.test')
        expect(dialog.text()).toContain('Leistungsfeststellung')
        expect(dialog.text()).toContain('Aufgabe.pdf')
        expect(dialog.text()).toContain('Persönliche Links und externe Inhalte sind in dieser Vorschau deaktiviert')
        const iframe = dialog.get('iframe')
        expect(iframe.attributes('sandbox')).toBe('')
        expect(iframe.attributes('referrerpolicy')).toBe('no-referrer')
        const content = iframe.attributes('srcdoc')!
        expect(content).toContain('Hallo!')
        expect(content).toContain('Zum Test')
        expect(content).toContain("default-src 'none'")
        expect(content).toContain("form-action 'none'")
        expect(content).not.toContain('personal-token')
        expect(content).not.toContain('tracker.png')
        expect(content).not.toContain('onclick')
        expect(content).not.toContain('<script')
        expect(content).not.toContain('<form')
        expect(axios.post).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('does not show a late email response after the preparation context changes', async () => {
    let finish: (value: unknown) => void = () => {}
    const get = vi.fn(() => new Promise(resolve => { finish = resolve }))
    const { wrapper } = render({ statusOnly: true, state: { ...state, emailPreparation: { status: 'ready' } } }, get)
    try {
        await wrapper.get('[role="button"]').trigger('click')
        await button(wrapper, 'E-Mail ansehen').trigger('click')
        const signal = get.mock.calls[0][1].signal as AbortSignal
        await wrapper.setProps({ state: { ...state, folder: { id: 'id:other' }, emailPreparation: { status: 'ready' } } })
        finish(preparedPreview())
        await flushPromises()
        expect(signal.aborted).toBe(true)
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(wrapper.find('iframe').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it.each([{ work_id: 66 }, { course_id: 3 }, { folder_id: 'id:other' }, { prepared_at: '2026-10-21T05:26:00Z' }, { html: '' }])(
    'does not display a prepared email with invalid identity or content: %j', async invalid => {
        const get = vi.fn().mockResolvedValue(preparedPreview(invalid))
        const { wrapper } = render({ statusOnly: true, state: { ...state,
            emailPreparation: { status: 'ready', prepared_at: '2026-10-20T05:26:00Z' } } }, get)
        try {
            await wrapper.get('[role="button"]').trigger('click')
            await button(wrapper, 'E-Mail ansehen').trigger('click')
            await flushPromises()
            expect(wrapper.get('[role="alert"]').text()).toContain('nicht eindeutig zugeordnet')
            expect(wrapper.find('iframe').exists()).toBe(false)
        } finally { wrapper.unmount() }
    },
)

it('shows the server error and allows retrying the preview', async () => {
    const get = vi.fn().mockRejectedValueOnce({ response: { data: { message: 'Die Vorbereitung wurde geändert.' } } })
        .mockResolvedValueOnce(preparedPreview())
    const { wrapper } = render({ statusOnly: true, state: { ...state, emailPreparation: { status: 'ready' } } }, get)
    try {
        await wrapper.get('[role="button"]').trigger('click')
        await button(wrapper, 'E-Mail ansehen').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Die Vorbereitung wurde geändert')
        await button(wrapper, 'E-Mail ansehen').trigger('click')
        await flushPromises()
        expect(wrapper.find('[role="alert"]').exists()).toBe(false)
        expect(wrapper.find('iframe').exists()).toBe(true)
    } finally { wrapper.unmount() }
})

it('does not invent missing preparation dates or counts in the information dialog', async () => {
    const { wrapper } = render({ statusOnly: true, state: { ...state,
        checkedAt: 'invalid', emailPreparation: { status: 'ready', prepared_at: 'invalid' } } })
    try {
        await wrapper.get('[role="button"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').findAll('dd').map(item => item.text())).toEqual([
            'Nicht angegeben', 'Nicht angegeben', 'Nicht angegeben', 'LF',
        ])
    } finally { wrapper.unmount() }
})

it.each(['readiness', 'user', 'work', 'folder'])('closes preparation information when its %s context changes', async change => {
    const { wrapper, get } = render({ statusOnly: true, state: { ...state, emailPreparation: { status: 'ready' } } })
    try {
        await wrapper.get('[role="button"]').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

        if (change === 'readiness') await wrapper.setProps({ state: { ...state, emailPreparation: { status: 'unknown' } } })
        if (change === 'user') useAdminStore().config.user.id = 8
        if (change === 'work') await wrapper.setProps({ work: { ...work, id: 66 } })
        if (change === 'folder') await wrapper.setProps({ state: { ...state, folder: { id: 'id:other' }, emailPreparation: { status: 'ready' } } })
        await flushPromises()

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(get).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it.each([
    { state: { ...state, status: 'missing' } },
    { state: { ...state, userId: 8 } },
    { state: { ...state, courseId: 3 } },
    { state: { ...state, date: '2026-10-21' } },
    { work: { ...work, status: { folder_imported_at: '2026-10-13T12:54:00Z' } } },
])('does not offer preparation before creation or for a different context or existing work: %j', props => {
    const { wrapper, get } = render(props)
    try {
        expect(wrapper.find('button').exists()).toBe(false)
        expect(get).not.toHaveBeenCalled()
    } finally { wrapper.unmount() }
})

it('does not copy a late response after switching the user', async () => {
    let finish: (value: unknown) => void = () => {}
    const { wrapper, writeText } = render({}, vi.fn(() => new Promise(resolve => { finish = resolve })))
    try {
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        await button(wrapper, 'Du-Form').trigger('click')
        useAdminStore().config.user.id = 8
        await flushPromises()
        finish(exported())
        await flushPromises()
        expect(writeText).not.toHaveBeenCalled()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('rejects a response using another address form and keeps the copy unavailable', async () => {
    const { wrapper, writeText } = render({}, vi.fn().mockResolvedValue(exported('sie')))
    try {
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        await button(wrapper, 'Du-Form').trigger('click')
        await flushPromises()
        expect(writeText).not.toHaveBeenCalled()
        expect(wrapper.get('[role="alert"]').text()).toContain('nicht eindeutig bestätigt')
    } finally { wrapper.unmount() }
})

it('keeps preparation available with a saved communicated deadline and resets the form choice when reopened', async () => {
    const { wrapper } = render({ work: { ...work, status: { communicated_deadline_at: '2026-10-21T12:00:00+02:00' } } }, vi.fn().mockResolvedValue(exported('sie')))
    try {
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        await button(wrapper, 'Sie-Form').trigger('click')
        await flushPromises()
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeUndefined()
        await button(wrapper, 'Schließen').trigger('click')
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        expect(button(wrapper, 'Auftrag kopieren').attributes('disabled')).toBeDefined()
    } finally { wrapper.unmount() }
})

it('copies the already loaded instruction directly and falls back when the clipboard API is blocked', async () => {
    const { wrapper, writeText, get } = render({}, vi.fn().mockResolvedValue(exported()))
    writeText.mockRejectedValue(new Error('NotAllowedError'))
    const copy = vi.fn(() => {
        expect((document.activeElement as HTMLTextAreaElement).value).toBe('Auftrag zur Vorbereitung: du')
        return true
    })
    Object.defineProperty(document, 'execCommand', { configurable: true, value: copy })
    try {
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        await button(wrapper, 'Du-Form').trigger('click')
        await flushPromises()
        expect(get).toHaveBeenCalledTimes(1)
        await button(wrapper, 'Auftrag kopieren').trigger('click')
        await flushPromises()
        expect(copy).toHaveBeenCalledExactlyOnceWith('copy')
        expect(get).toHaveBeenCalledTimes(1)
        expect(wrapper.get('[role="status"]').text()).toContain('Vorbereitungsauftrag kopiert')
        expect(wrapper.text()).not.toContain('mit Strg+C')
    } finally { wrapper.unmount() }
})

it('does not claim copying succeeded when both clipboard methods are blocked', async () => {
    const { wrapper, writeText } = render({}, vi.fn().mockResolvedValue(exported()))
    writeText.mockRejectedValue(new Error('NotAllowedError'))
    Object.defineProperty(document, 'execCommand', { configurable: true, value: vi.fn().mockReturnValue(false) })
    try {
        await button(wrapper, 'E-Mails vorbereiten').trigger('click')
        await button(wrapper, 'Du-Form').trigger('click')
        await flushPromises()
        await button(wrapper, 'Auftrag kopieren').trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Kopieren blockiert')
        expect(wrapper.text()).not.toContain('Vorbereitungsauftrag kopiert')
    } finally { wrapper.unmount() }
})
