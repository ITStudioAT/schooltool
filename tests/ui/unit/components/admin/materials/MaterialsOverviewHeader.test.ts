import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import MaterialsOverviewHeader from '@/pages/admin/materials/components/overview/MaterialsOverviewHeader.vue'

const stubs = {
    'v-row': { template: '<div v-bind="$attrs"><slot /></div>' },
    'v-col': { template: '<div v-bind="$attrs"><slot /></div>' },
    'v-btn-toggle': { template: '<div><slot /></div>' },
    'v-btn': {
        emits: ['click'],
        template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'v-icon': { template: '<i><slot /></i>' },
    'v-spacer': { template: '<div />' },
}

describe('MaterialsOverviewHeader', () => {
    it('keeps the refresh button right-aligned in the toolbar row', async () => {
        const wrapper = mount(MaterialsOverviewHeader, {
            props: {
                overviewViewMode: 'list',
                isLoading: false,
                actionDisabled: false,
                strukturModus: false,
                hideOverviewModeToggle: false,
            },
            slots: {
                default: '<div class="header-slot-content">Arbeitsblatt Auftrag</div>',
            },
            global: {
                stubs,
            },
        })

        const toolbar = wrapper.get('.materials-overview-header-actions')
        const refreshButton = toolbar.get('button.materials-overview-header-refresh-btn')
        const headerRow = wrapper.get('.materials-overview-header-row')

        expect(refreshButton.text()).toContain('Aktualisieren')
        expect(toolbar.find('.overview-mode-toggle').exists()).toBe(true)
        expect(headerRow.exists()).toBe(true)
        expect(headerRow.text()).toContain('Arbeitsblatt')
        expect(headerRow.text()).toContain('Auftrag')
        await refreshButton.trigger('click')
        expect(wrapper.emitted('refresh')).toHaveLength(1)
    })
})
