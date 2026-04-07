import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import MaterialsOverviewFilters from '@/pages/admin/materials/components/overview/MaterialsOverviewFilters.vue'

const stubs = {
    'v-badge': {
        template: '<div class="v-badge"><slot /></div>',
    },
    'v-chip': {
        template: '<button type="button" class="v-chip" v-bind="$attrs"><slot /></button>',
    },
    'v-btn': {
        emits: ['click'],
        template: '<button type="button" class="v-btn" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'v-tooltip': {
        template: '<div class="v-tooltip"><slot name="activator" :props="{}" /><slot /></div>',
    },
}

describe('MaterialsOverviewFilters', () => {
    it('renders the subject filter chips in their own card', () => {
        const wrapper = mount(MaterialsOverviewFilters, {
            props: {
                actionDisabled: false,
                badgeCountContent: (count: number) => String(count),
                subjectAllCount: 3,
                hasActiveSubjectFilter: true,
                clearSubjectFilter: vi.fn(),
                subjectFilterOptions: ['Deutsch'],
                subjectFilterCount: vi.fn().mockReturnValue(1),
                isSubjectFilterActive: vi.fn().mockReturnValue(false),
                toggleSubjectFilter: vi.fn(),
                topicAllCount: 2,
                hasActiveTopicFilter: true,
                clearTopicFilter: vi.fn(),
                topicFilterOptions: ['Literatur'],
                topicFilterCount: vi.fn().mockReturnValue(1),
                isTopicFilterActive: vi.fn().mockReturnValue(false),
                toggleTopicFilter: vi.fn(),
                unitAllCount: 1,
                hasActiveUnitFilter: false,
                clearUnitFilter: vi.fn(),
                unitFilterOptions: ['Einheit 1'],
                unitFilterCount: vi.fn().mockReturnValue(1),
                isUnitFilterActive: vi.fn().mockReturnValue(false),
                toggleUnitFilter: vi.fn(),
            },
            global: {
                stubs,
            },
        })

        const card = wrapper.get('.materials-overview-filters-card')
        expect(card.exists()).toBe(true)
        expect(card.text()).toContain('Fächer:')
        expect(card.text()).toContain('Thema:')
        expect(card.text()).toContain('Bereich:')
        expect(card.text()).toContain('Deutsch')
    })
})
