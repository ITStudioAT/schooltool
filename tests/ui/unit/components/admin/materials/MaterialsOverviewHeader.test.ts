import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MaterialsOverviewHeader from '@/pages/admin/materials/components/overview/MaterialsOverviewHeader.vue'

const stubs = {
    'v-row': { template: '<div v-bind="$attrs"><slot /></div>' },
    'v-col': {
        props: ['cols', 'md', 'lg', 'xl'],
        template: '<div class="v-col-stub" :data-cols="cols" :data-md="md" :data-lg="lg" :data-xl="xl"><slot /></div>',
    },
    'v-btn': {
        emits: ['click'],
        template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'v-icon': {
        props: ['icon'],
        template: '<i :data-icon="icon"><slot /></i>',
    },
    'v-spacer': { template: '<div />' },
}

const mountHeader = (overrides = {}) => mount(MaterialsOverviewHeader, {
    props: {
        overviewViewMode: 'list',
        isLoading: false,
        actionDisabled: false,
        strukturModus: false,
        hideOverviewModeToggle: false,
        ...overrides,
    },
    slots: {
        default: '<div class="header-slot-content">Arbeitsblatt Auftrag</div>',
    },
    global: {
        stubs,
    },
})

describe('MaterialsOverviewHeader', () => {
    it('renders all four view modes and the refresh button', async () => {
        const wrapper = mountHeader()

        const toolbar = wrapper.get('.materials-overview-header-actions')
        const switcher = toolbar.get('.overview-mode-switcher')
        const items = switcher.findAll('.overview-mode-switcher__item')
        const refreshButton = toolbar.get('button.materials-overview-header-refresh-btn')
        const headerRow = wrapper.get('.materials-overview-header-row')

        expect(items).toHaveLength(4)
        expect(items.map(item => item.text())).toEqual([
            'Liste',
            'Karten',
            'A-Z',
            'Fächer/Inhalte',
        ])
        expect(switcher.attributes('role')).toBe('tablist')
        expect(refreshButton.text()).toContain('Aktualisieren')
        expect(headerRow.text()).toContain('Arbeitsblatt')
        expect(headerRow.text()).toContain('Auftrag')

        await refreshButton.trigger('click')
        expect(wrapper.emitted('refresh')).toHaveLength(1)
    })

    it('marks the active view mode and emits update:overviewViewMode on click', async () => {
        const wrapper = mountHeader({ overviewViewMode: 'grid' })

        const items = wrapper.findAll('.overview-mode-switcher__item')
        const active = items.filter(item => item.classes().includes('is-active'))

        expect(active).toHaveLength(1)
        expect(active[0].text()).toBe('Karten')
        expect(active[0].attributes('aria-selected')).toBe('true')

        await items[3].trigger('click')

        const emitted = wrapper.emitted('update:overviewViewMode')
        expect(emitted).toHaveLength(1)
        expect(emitted?.[0]).toEqual(['subjects_contents'])
    })

    it('hides the switcher when hideOverviewModeToggle is true', () => {
        const wrapper = mountHeader({ hideOverviewModeToggle: true })

        expect(wrapper.find('.overview-mode-switcher').exists()).toBe(false)
        expect(wrapper.find('button.materials-overview-header-refresh-btn').exists()).toBe(true)
    })

    it('hides the action column when struktur modus is active', () => {
        const wrapper = mountHeader({ strukturModus: true })

        expect(wrapper.find('.materials-overview-header-actions').exists()).toBe(false)
    })

    it('stacks the action controls below the title before large desktop widths', () => {
        const wrapper = mountHeader()

        const columns = wrapper.findAll('.v-col-stub')

        expect(columns.length).toBeGreaterThanOrEqual(3)
        expect(columns[0].attributes('data-cols')).toBe('12')
        expect(columns[0].attributes('data-lg')).toBe('5')
        expect(columns[1].attributes('data-cols')).toBe('12')
        expect(columns[1].attributes('data-lg')).toBe('7')
    })
})
