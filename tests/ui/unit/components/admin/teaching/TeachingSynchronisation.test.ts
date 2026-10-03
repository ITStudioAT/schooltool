import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import Synchronisation from '@/pages/admin/teaching/admin/Synchronisation.vue'

const adminStore = { action: '' }
vi.mock('@/stores/admin/AdminStore', () => ({ useAdminStore: () => adminStore }))
let wrapper
afterEach(() => {
    wrapper?.unmount()
    vi.unstubAllGlobals()
})

async function mountSynchronisation(available = true) {
    const post = vi.fn()
    const get = vi.fn().mockResolvedValue({ data: { available } })
    vi.stubGlobal('axios', { get, post })
    wrapper = mount(Synchronisation, {
        global: {
            stubs: {
                ItsGridBox: { template: '<div><slot /></div>' },
                'v-col': { template: '<div><slot /></div>' },
                'v-alert': { template: '<div><slot /></div>' },
                'v-table': { template: '<table><slot /></table>' },
                'v-btn': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'v-checkbox': {
                    props: ['modelValue', 'disabled'], emits: ['update:modelValue'],
                    template: '<input type="checkbox" :disabled="disabled" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
                },
            },
        },
    })
    await flushPromises()
    return { post, get }
}

function reviewedPreview(conflicts: string[] = []) {
    return { data: { data: {
        token: conflicts.length ? null : 'reviewed-token', school: 'Fixture School', captured_at: '2026-10-03T12:00:00Z',
        expires_in_minutes: 15, schoolyears: [{ name: '2025/26', from: '2025-09-01', until: '2026-08-31' }],
        summary: [{ table: 'teaching_courses', live: 2, replaced: 1, bytes: 500 }], files: 2, file_bytes: 100,
        local_files: 1, new_schoolyears: 0, new_accounts: 0, new_students: 0, new_teachers: 0, updated_teachers: 0,
        shared: { users: 1, import116: 2, settings: 1, bytes: 300 },
        changed_contacts: [{ id: 2, student_code: 'S1', fields: ['mother_email'] }], conflicts,
        changed_teachers: [{ id: 3, email: 'teacher@example.test', changes: [{ field: 'short', before: 'ALT', after: 'NEU' }] }],
        updated_schoolyears: [{ id: 4, values: { name: '2025/26' }, changes: [{ field: 'sem_2_start', before: '2026-02-09', after: '2026-02-16' }] }],
        file_warnings: ['Historischer Importdateiname ohne verfügbare Quelldatei: Liste.xlsx'],
    } } }
}

describe('Teaching synchronisation', () => {
    it('checks availability without reading cloud data automatically', async () => {
        const { post } = await mountSynchronisation(false)
        expect(post).not.toHaveBeenCalled()
        expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    })

    it('requires both confirmations and displays understandable scope and shared contact changes', async () => {
        const { post } = await mountSynchronisation()
        post.mockResolvedValueOnce(reviewedPreview())
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Fächer und Kurse')
        expect(wrapper.text()).toContain('E-Mail der Mutter')
        expect(wrapper.text()).toContain('Kürzel: ALT → NEU')
        expect(wrapper.text()).toContain('Beginn des 2. Semesters: 2026-02-09 → 2026-02-16')
        expect(wrapper.text()).toContain('Historischer Importdateiname ohne verfügbare Quelldatei: Liste.xlsx')
        expect(wrapper.text()).not.toContain('teaching_courses')
        expect(wrapper.findAll('button')[1].attributes('disabled')).toBeDefined()
        await wrapper.findAll('input')[0].setValue(true)
        expect(wrapper.findAll('button')[1].attributes('disabled')).toBeDefined()
        await wrapper.findAll('input')[1].setValue(true)
        post.mockResolvedValueOnce({ data: { message: 'Übernommen', backup: 'saved-backup' } })
        await wrapper.findAll('button')[1].trigger('click')
        await flushPromises()
        expect(post).toHaveBeenLastCalledWith(expect.stringContaining('/apply'), {
            token: 'reviewed-token', replace_confirmed: true, contacts_confirmed: true,
        }, expect.any(Object))
        expect(wrapper.text()).toContain('Sicherheitsbackup herunterladen')
        expect(adminStore.action).toBe('')
    })

    it('shows all conflicts alongside the inventory and blocks the start', async () => {
        const { post } = await mountSynchronisation()
        post.mockResolvedValueOnce(reviewedPreview(['Schuljahr mehrdeutig.', 'Cloud-Datei fehlt.']))
        await wrapper.get('button').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Fächer und Kurse')
        expect(wrapper.text()).toContain('Schuljahr mehrdeutig.')
        expect(wrapper.text()).toContain('Cloud-Datei fehlt.')
        expect(wrapper.findAll('button')[1].attributes('disabled')).toBeDefined()
        expect(post).toHaveBeenCalledTimes(1)
    })

    it('invalidates confirmation when the cloud recheck reports changes', async () => {
        const { post } = await mountSynchronisation()
        post.mockResolvedValueOnce(reviewedPreview())
        await wrapper.get('button').trigger('click')
        await flushPromises()
        await wrapper.findAll('input')[0].setValue(true)
        await wrapper.findAll('input')[1].setValue(true)
        post.mockRejectedValueOnce({ response: { data: { message: 'Cloud-Stand wurde geändert.' } } })
        await wrapper.findAll('button')[1].trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Cloud-Stand wurde geändert.')
        expect(wrapper.findAll('button')[1].attributes('disabled')).toBeDefined()
        expect(wrapper.findAll('input').every(input => !input.element.checked)).toBe(true)
    })
})
