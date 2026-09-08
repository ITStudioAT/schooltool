import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createMemoryHistory, createRouter } from 'vue-router'
import { defineComponent, h, inject, provide } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'
import TeacherAdministration from '@/pages/admin/teaching/admin/TeacherAdministration.vue'
import Teaching from '@/pages/admin/teaching/Teaching.vue'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

let wrapper

const VBtnToggleStub = defineComponent({
    props: ['modelValue', 'disabled'],
    emits: ['update:modelValue'],
    setup(props, { slots, emit }) {
        provide('selectPanel', (value: string) => {
            if (!props.disabled) {
                emit('update:modelValue', value)
            }
        })
        return () => h('div', slots.default?.())
    },
})

const VBtnStub = defineComponent({
    props: ['value', 'disabled'],
    setup(props, { slots }) {
        const selectPanel = inject<(value: string) => void>('selectPanel')
        return () => h('button', { disabled: props.disabled, onClick: () => selectPanel?.(props.value) }, slots.default?.())
    },
})

afterEach(() => wrapper?.unmount())

async function mountAdministration(query = '?panel=teachers', withTeaching = false) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/admin/teaching/:section?', component: Teaching }],
    })
    await router.push(`/admin/teaching/administration${query}`)
    wrapper = mount(withTeaching ? Teaching : TeacherAdministration, {
        global: {
            plugins: [createTestingPinia({
                initialState: { AdminAdminStore: { config: { roles: ['super_admin'], is_auth: true } } },
            }), router],
            stubs: {
                'v-container': { template: '<div><slot /></div>' },
                'v-row': { template: '<div><slot /></div>' },
                'v-col': { template: '<div><slot /></div>' },
                'v-icon': true,
                'v-card': true,
                'v-sheet': { template: '<section><slot /></section>' },
                'v-dialog': { template: '<div />' },
                'v-card-title': true,
                'v-card-text': true,
                'v-card-actions': true,
                'v-spacer': true,
                'v-btn': VBtnStub,
                'v-btn-toggle': VBtnToggleStub,
                Teachers: { props: ['hideBackButton'], template: '<div data-panel="teachers">Teacher accounts</div>' },
                Import116: { template: '<div data-panel="import">Import content</div>' },
                Holidays: { template: '<div data-panel="holidays">Holiday content</div>' },
                SchoolHours: { template: '<div data-panel="school_hours">School hours content</div>' },
                Overview: { template: '<div data-panel="overview">Teaching overview</div>' },
                AdminCompactSectionHero: true,
                TeachingDueReminders: true,
            },
        },
    })
    await flushPromises()
    return router
}

describe('Teaching administration panels', () => {
    it('replaces the teaching menu with administration and switches back through Unterricht', async () => {
        const router = await mountAdministration('?panel=teachers', true)

        expect(wrapper.findAll('.teaching-nav')).toHaveLength(1)
        expect(wrapper.find('[data-testid="teaching-nav-search"]').exists()).toBe(false)
        expect(wrapper.find('.teaching-administration-toolbar').exists()).toBe(false)
        expect(wrapper.get('[data-testid="teaching-administration-nav"]').text()).toContain('Import 116')
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe('teachers')

        await wrapper.get('[data-testid="teaching-administration-holidays"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.query.panel).toBe('holidays')
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe('holidays')

        await wrapper.get('[data-testid="teaching-administration-back"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.fullPath).toBe('/admin/teaching?panel=table')
        expect(wrapper.findAll('.teaching-nav')).toHaveLength(1)
        expect(wrapper.find('[data-testid="teaching-administration-nav"]').exists()).toBe(false)
        expect(wrapper.get('[data-testid="teaching-nav-administration"]').classes()).toContain('teaching-nav__button--admin')

        await wrapper.get('[data-testid="teaching-nav-administration"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.fullPath).toBe('/admin/teaching/administration?panel=teachers')
        expect(wrapper.findAll('.teaching-nav')).toHaveLength(1)
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe('teachers')
    })

    it('blocks panel changes and returning to Unterricht during editing', async () => {
        const router = await mountAdministration('?panel=teachers', true)
        useAdminStore().action = 'editing'
        await flushPromises()

        expect(wrapper.get('[data-testid="teaching-administration-back"]').attributes('disabled')).toBeDefined()
        await wrapper.get('[data-testid="teaching-administration-back"]').trigger('click')
        await wrapper.findAllComponents(VBtnToggleStub)[0].vm.$emit('update:modelValue', 'holidays')
        await flushPromises()
        expect(router.currentRoute.value.fullPath).toBe('/admin/teaching/administration?panel=teachers')
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe('teachers')
    })

    it('shows the active schoolyear beneath each menu title like teaching settings', async () => {
        await mountAdministration()
        const store = useAdminStore()
        store.config = { selected_schoolyear: { name: '2026/2027' } }
        await flushPromises()

        expect(wrapper.findAll('.teaching-administration-button-meta').map((label) => label.text())).toEqual([
            '2026/2027', '2026/2027', '2026/2027', '2026/2027',
        ])

        store.config.selected_schoolyear = { concerns: '2027/2028' }
        await flushPromises()
        expect(wrapper.findAll('.teaching-administration-button-meta').every((label) => label.text() === '2027/2028')).toBe(true)
    })

    it.each(['teachers', 'import', 'holidays', 'school_hours'])('restores %s directly from the URL', async (panel) => {
        await mountAdministration(`?panel=${panel}`)

        expect(wrapper.findAll('[data-panel]')).toHaveLength(1)
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe(panel)
        expect(wrapper.findComponent(VBtnToggleStub).props('modelValue')).toBe(panel)
    })

    it('updates the URL on selection and restores the previous panel with browser back', async () => {
        const router = await mountAdministration('?panel=teachers&keep=value')

        expect(wrapper.findAll('.teaching-administration-button-title').map((title) => title.text())).toEqual(['Lehrer', 'Import 116', 'Ferien', 'Schulstunden'])
        await wrapper.findAll('button')[2].trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.query).toEqual({ panel: 'holidays', keep: 'value' })
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe('holidays')

        const navigationFinished = new Promise<void>((resolve) => {
            const removeHook = router.afterEach(() => {
                removeHook()
                resolve()
            })
        })
        router.back()
        await navigationFinished
        await flushPromises()
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe('teachers')
        expect(router.currentRoute.value.query.panel).toBe('teachers')
    })

    it.each(['', '?panel=invalid', '?panel=teachers&panel=holidays'])('normalizes an invalid or missing panel (%s)', async (query) => {
        const router = await mountAdministration(query)

        expect(router.currentRoute.value.query.panel).toBe('teachers')
        expect(wrapper.get('[data-panel]').attributes('data-panel')).toBe('teachers')
    })

    it('shows the school hours warning only after an empty list has loaded', async () => {
        await mountAdministration()
        const store = useSchoolHourStore()
        expect(wrapper.find('[aria-label="Keine Schulstunden vorhanden"]').exists()).toBe(false)

        store.school_hours_loaded = true
        await flushPromises()
        expect(wrapper.find('[aria-label="Keine Schulstunden vorhanden"]').exists()).toBe(true)

        store.school_hours = [{ id: 1 }]
        await flushPromises()
        expect(wrapper.find('[aria-label="Keine Schulstunden vorhanden"]').exists()).toBe(false)
    })
})
