import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WorkResultNotification from '@/pages/admin/teaching/overview/components/WorkResultNotification.vue'

const work = { id: 86, teaching_course_id: 16, title: 'E-Mails' }
const data = {
    work_id: 86, course_id: 16, simulation: true, transport: 'mailpit', subject: 'Ergebnisse E-Mails', body: '{PersönlicheAnrede}\n\nDie Testauswertung ist verfügbar.',
    hash: 'a'.repeat(64), student_ids: [1], teacher: { first_name: 'Günther', last_name: 'Kron' },
    rows: [
        { student_id: 1, first_name: 'Anna', last_name: 'Zeller', email: 'anna@example.test', eligible: true, status: 'Ergebnis verfügbar' },
        { student_id: 2, first_name: 'Emil', last_name: 'Alpha', email: 'emil@example.test', eligible: false, status: 'Bewertung noch offen' },
    ],
    messages: [
        { student_id: 1, subject: '[Simulation] Ergebnisse E-Mails', text: 'Guten Tag Anna Zeller,\n\ndie Testauswertung ist verfügbar.', html: '<p>Anna Zeller</p>' },
        { student_id: null, subject: '[Simulation] Ergebnisse E-Mails', text: 'Guten Tag Günther Kron,', html: '<p>Günther Kron</p>' },
    ],
}

const mountDialog = () => mount(WorkResultNotification, { props: { work, date: '2026-10-16' }, global: { stubs: {
    'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
    'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
    'v-text-field': { props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<input :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    'v-textarea': { props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<textarea :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
} } })
const button = (wrapper: any, label: string) => wrapper.findAll('button').find((item: any) => item.text() === label)!
afterEach(() => vi.unstubAllGlobals())

it('shows a personal preview, surname sorting, excluded open work and a separate teacher copy before sending', async () => {
    const post = vi.fn().mockResolvedValue({ data: { data } })
    vi.stubGlobal('axios', { post })
    const wrapper = mountDialog()
    try {
        await button(wrapper, 'Benachrichtigung vorbereiten').trigger('click'); await flushPromises()
        expect(post).toHaveBeenCalledTimes(1)
        expect(post.mock.calls[0][0]).toContain('/result-notifications/preview')
        expect(wrapper.text()).toContain('separate Nachricht an die Lehrperson')
        expect(wrapper.get('[aria-label="Persönliche Nachricht"]').text()).toContain('Guten Tag Anna Zeller,')
        const rows = wrapper.get('[aria-label="Empfänger der Ergebnisbenachrichtigung"]').findAll('tbody tr')
        expect(rows[0].text()).toContain('Alpha, Emil')
        expect(rows[0].get('input').attributes('disabled')).toBeDefined()
        expect(rows[1].get('input').element.checked).toBe(true)
        expect(wrapper.get('iframe').attributes('sandbox')).toBe('')
    } finally { wrapper.unmount() }
})

it('requires a refreshed preview after edits and sends only the selected confirmed snapshot', async () => {
    const updated = { ...data, subject: 'Angepasster Betreff', hash: 'b'.repeat(64) }
    const after = { ...updated, student_ids: [], messages: [], rows: data.rows.map(row => ({ ...row, eligible: false, status: 'Bereits informiert' })) }
    const saved = { ...work, simulation_notification: { rows: [{ student_id: 1 }] } }
    const post = vi.fn().mockResolvedValueOnce({ data: { data } }).mockResolvedValueOnce({ data: { data: updated } })
        .mockResolvedValueOnce({ data: { data: saved } }).mockResolvedValueOnce({ data: { data: after } })
    vi.stubGlobal('axios', { post })
    const wrapper = mountDialog()
    try {
        await button(wrapper, 'Benachrichtigung vorbereiten').trigger('click'); await flushPromises()
        await wrapper.get('[aria-label="Betreff"]').setValue('Angepasster Betreff')
        expect(button(wrapper, 'Testbenachrichtigungen senden').element.disabled).toBe(true)
        await button(wrapper, 'Vorschau aktualisieren').trigger('click'); await flushPromises()
        expect(button(wrapper, 'Testbenachrichtigungen senden').element.disabled).toBe(false)
        await button(wrapper, 'Testbenachrichtigungen senden').trigger('click'); await flushPromises()
        expect(post.mock.calls[2][0]).toContain('/result-notifications/send')
        expect(post.mock.calls[2][1]).toEqual({ student_ids: [1], subject: updated.subject, body: updated.body, date: '2026-10-16', hash: updated.hash })
        expect(wrapper.emitted('saved')).toEqual([[saved]])
        expect(button(wrapper, 'Testbenachrichtigungen senden').element.disabled).toBe(true)
    } finally { wrapper.unmount() }
})

it('copies an Office preparation order without posting to the local send route', async () => {
    const office = { ...data, simulation: false, transport: 'office_outlook' }
    const post = vi.fn().mockResolvedValueOnce({ data: { data: office } }).mockResolvedValueOnce({ data: { text: 'Geprüfter Vorbereitungsauftrag' } })
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('axios', { post }); vi.stubGlobal('navigator', { clipboard: { writeText } })
    const wrapper = mountDialog()
    try {
        await button(wrapper, 'Benachrichtigung vorbereiten').trigger('click'); await flushPromises()
        await button(wrapper, 'Office-Auftrag kopieren').trigger('click'); await flushPromises()
        expect(post.mock.calls[1][0]).toContain('/result-notifications/office-order')
        expect(writeText).toHaveBeenCalledWith('Geprüfter Vorbereitungsauftrag')
        expect(wrapper.emitted('saved')).toBeUndefined()
        expect(wrapper.text()).toContain('versendet noch keine Nachrichten')
    } finally { wrapper.unmount() }
})

it('does not publish a preview returned for another work', async () => {
    vi.stubGlobal('axios', { post: vi.fn().mockResolvedValue({ data: { data: { ...data, work_id: 99 } } }) })
    const wrapper = mountDialog()
    try {
        await button(wrapper, 'Benachrichtigung vorbereiten').trigger('click'); await flushPromises()
        expect(wrapper.text()).toContain('gehört nicht zu dieser Arbeit')
        expect(wrapper.find('[aria-label="Persönliche Nachricht"]').exists()).toBe(false)
    } finally { wrapper.unmount() }
})

it('refreshes the default text for Du-Form and preserves manually edited text when switching back', async () => {
    const initial = { ...data, default_body: data.body, address_form: 'sie' }
    const informal = { ...initial, address_form: 'du', body: 'Lieber Günther,\n\ndeine Auswertung ist verfügbar.', default_body: 'Lieber Günther,\n\ndeine Auswertung ist verfügbar.' }
    const post = vi.fn().mockResolvedValueOnce({ data: { data: initial } }).mockResolvedValueOnce({ data: { data: informal } })
        .mockResolvedValueOnce({ data: { data: { ...informal, address_form: 'sie', body: 'Mein eigener Nachrichtentext' } } })
    vi.stubGlobal('axios', { post })
    const wrapper = mountDialog()
    try {
        await button(wrapper, 'Benachrichtigung vorbereiten').trigger('click'); await flushPromises()
        await wrapper.get('input[type="radio"][value="du"]').setValue(true)
        expect(button(wrapper, 'Testbenachrichtigungen senden').element.disabled).toBe(true)
        await button(wrapper, 'Vorschau aktualisieren').trigger('click'); await flushPromises()
        expect(post.mock.calls[1][1]).toMatchObject({ address_form: 'du' })
        expect(post.mock.calls[1][1]).not.toHaveProperty('body')
        await wrapper.get('[aria-label="Nachrichtentext"]').setValue('Mein eigener Nachrichtentext')
        await wrapper.get('input[type="radio"][value="sie"]').setValue(true)
        await button(wrapper, 'Vorschau aktualisieren').trigger('click'); await flushPromises()
        expect(post.mock.calls[2][1].body).toBe('Mein eigener Nachrichtentext')
    } finally { wrapper.unmount() }
})

it('shows the actual completion in Vienna time and sends a separate teacher-only styling preview', async () => {
    const previewId = '44e828ba-e391-470d-ab66-ee2e784e9810'
    const initial = { ...data, last_completed_at: '2026-10-24T18:00:00Z', student_ids: [], messages: [],
        rows: data.rows.map(row => ({ ...row, eligible: false, status: 'Bereits informiert' })) }
    const test = { ...initial, teacher_preview: true, preview_id: previewId, messages: [data.messages[1]] }
    const post = vi.fn().mockResolvedValueOnce({ data: { data: initial } }).mockResolvedValueOnce({ data: { data: test } })
        .mockResolvedValueOnce({ data: { data: work } }).mockResolvedValueOnce({ data: { data: test } })
    vi.stubGlobal('axios', { post })
    const wrapper = mountDialog()
    try {
        await button(wrapper, 'Benachrichtigung vorbereiten').trigger('click'); await flushPromises()
        expect(wrapper.text()).toContain('24.10.2026, 20:00 Uhr')
        expect(button(wrapper, 'Simulator-Mailpit öffnen').attributes('href')).toBe('http://127.0.0.1:8025')
        await wrapper.findAll('label').find(label => label.text().includes('Nur eine Testvorschau'))!.get('input').setValue(true)
        expect(button(wrapper, 'Testvorschau an mich senden').element.disabled).toBe(true)
        await button(wrapper, 'Vorschau aktualisieren').trigger('click'); await flushPromises()
        await button(wrapper, 'Testvorschau an mich senden').trigger('click'); await flushPromises()
        expect(post.mock.calls[2][1]).toMatchObject({ student_ids: [], teacher_preview: true, preview_id: previewId, hash: test.hash })
        expect(wrapper.text()).toContain('Schülernachrichten wurden nicht wiederholt')
    } finally { wrapper.unmount() }
})
