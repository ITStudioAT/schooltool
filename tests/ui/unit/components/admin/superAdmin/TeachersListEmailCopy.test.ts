import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CopyEmailButton from '@/pages/admin/superAdmin/components/CopyEmailButton.vue'
import Teachers from '@/pages/admin/superAdmin/components/Teachers.vue'
import TeachersList from '@/pages/admin/superAdmin/components/TeachersList.vue'
import { useTeacherStore } from '@/stores/admin/TeacherStore'
import { useTeachersListStore } from '@/stores/admin/TeachersListStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

let wrapper

beforeEach(() => {
    setActivePinia(createPinia())
})

afterEach(() => {
    wrapper?.unmount()
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
})

describe('Teacher email copying', () => {
    it('shows each teacher role as a chip and handles missing roles', async () => {
        const store = useTeacherStore()
        store.teachers = [
            { id: 1, last_name: 'Example', first_name: 'Anna', roles: ['teacher', 'teaching_admin'], email: '' },
            { id: 2, last_name: 'Example', first_name: 'Ben', roles: ['admin'], email: '' },
            { id: 3, last_name: 'Example', first_name: 'Cara', roles: [], email: '' },
            { id: 4, last_name: 'Example', first_name: 'David', email: '' },
        ]
        vi.spyOn(store, 'index').mockResolvedValue(true)
        wrapper = mount(Teachers, {
            global: {
                stubs: {
                    SearchField: true,
                    Pagination: true,
                    TeachersListImportDialog: true,
                    'v-divider': true,
                    'v-list-item': { template: '<div class="teacher-row"><slot name="title" /></div>' },
                    'v-chip': { template: '<span class="role-chip"><slot /></span>' },
                },
            },
        })
        await flushPromises()

        const rows = wrapper.findAll('.teacher-row')
        expect(rows.map((row) => row.findAll('.role-chip').map((chip) => chip.text()))).toEqual([
            ['teacher', 'teaching_admin'], ['admin'], [], [],
        ])
    })

    it.each(['EXA', null])('shows the teacher short code beside the name when present (%s)', async (short) => {
        const store = useTeacherStore()
        store.teachers = [{ id: 1, last_name: 'Example', first_name: 'Teacher', short, email: '' }]
        vi.spyOn(store, 'index').mockResolvedValue(true)
        wrapper = mount(Teachers, {
            global: {
                stubs: {
                    SearchField: true,
                    Pagination: true,
                    TeachersListImportDialog: true,
                    'v-divider': true,
                    'v-list-item': { template: '<div class="teacher-row"><slot name="title" /></div>' },
                },
            },
        })
        await flushPromises()

        expect(wrapper.get('.person-name').text().replace(/\s+/g, ' ')).toBe(
            short ? 'Example Teacher (EXA)' : 'Example Teacher',
        )
    })

    it.each([
        ['Lehrer', Teachers, useTeacherStore],
        ['Lehrerliste', TeachersList, useTeachersListStore],
    ])('copies an email in %s with one click without selecting the row', async (_, component, useStore) => {
        const store = useStore()
        store.teachers = [{ id: 1, last_name: 'Example', first_name: 'Teacher', email: 'teacher@example.test' }]
        vi.spyOn(store, 'index').mockResolvedValue(true)
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        wrapper = mount(component, {
            global: {
                stubs: {
                    SearchField: true,
                    Pagination: true,
                    TeachersListImportDialog: true,
                    'v-divider': true,
                    'v-list-item': { template: '<div class="teacher-row"><slot name="title" /></div>' },
                },
            },
        })
        await flushPromises()
        const rowClick = vi.fn()
        wrapper.get('.teacher-row').element.addEventListener('click', rowClick)

        await wrapper.get('button[aria-label="E-Mail-Adresse kopieren: teacher@example.test"]').trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith('teacher@example.test')
        expect(useNotificationStore().message).toBe('E-Mail-Adresse kopiert.')
        expect(useNotificationStore().type).toBe('success')
        expect(rowClick).not.toHaveBeenCalled()
        expect(store.selected_teachers).toEqual([])
    })

    it.each(['', '   ', null])('renders missing email %s without a copy action', (email) => {
        wrapper = mount(CopyEmailButton, { props: { email } })

        expect(wrapper.text()).toBe('-')
        expect(wrapper.find('button').exists()).toBe(false)
    })

    it('reports a denied clipboard request without claiming success', async () => {
        vi.stubGlobal('navigator', {
            clipboard: { writeText: vi.fn().mockRejectedValue(new Error('Permission denied')) },
        })
        wrapper = mount(CopyEmailButton, { props: { email: 'teacher@example.test' } })

        await wrapper.get('button').trigger('click')
        await flushPromises()

        expect(useNotificationStore().type).toBe('error')
        expect(useNotificationStore().message).toBe('Die E-Mail-Adresse konnte nicht kopiert werden.')
    })
})
