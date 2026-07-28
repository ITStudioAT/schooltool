import { describe, expect, it } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import MaterialsV2Calendar from '@/pages/admin/materialsV2/components/MaterialsV2Calendar.vue'
import MaterialsV2Header from '@/pages/admin/materialsV2/components/MaterialsV2Header.vue'

describe('Materials V2 extracted components', () => {
    it('renders school identity and exposes search changes from the header', async () => {
        const wrapper = shallowMount(MaterialsV2Header, {
            props: {
                schoolLogoSrc: '/storage/images/school.svg',
                schoolName: 'Testschule',
                search: '',
                loading: false,
            },
            global: {
                stubs: {
                    'v-text-field': {
                        name: 'VTextField',
                        emits: ['update:modelValue'],
                        template: '<input class="search-input" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                    },
                },
            },
        })

        expect(wrapper.find('.materials-v2-school-name').text()).toBe('Testschule')
        expect(wrapper.find('.materials-v2-school-logo').attributes('src')).toBe('/storage/images/school.svg')

        await wrapper.find('.search-input').setValue('Biologie')

        expect(wrapper.emitted('update:search')).toEqual([['Biologie']])
    })

    it('renders calendar days and emits item selection', async () => {
        const item = {
            id: 7,
            title: 'Elternabend',
            reminder_time: '18:30',
        }
        const wrapper = shallowMount(MaterialsV2Calendar, {
            props: {
                periodLabel: 'September 2026',
                previousPeriodLabel: 'Vorheriger Monat',
                nextPeriodLabel: 'Nächster Monat',
                weekdays: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
                displayMode: 'month',
                days: [{
                    key: '2026-09-15',
                    dayNumber: 15,
                    weekday: 'Di',
                    isCurrentMonth: true,
                    isToday: true,
                    items: [item],
                }],
            },
        })

        expect(wrapper.find('.materials-v2-calendar-event').text()).toContain('Elternabend')
        await wrapper.find('.materials-v2-calendar-event').trigger('click')

        expect(wrapper.emitted('edit')).toEqual([[item]])
    })
})
