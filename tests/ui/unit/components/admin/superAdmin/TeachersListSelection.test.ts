import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { makeNestedProps, useNested, useNestedItem } from 'vuetify/lib/composables/nested/nested.js'
import Teachers from '@/pages/admin/superAdmin/components/Teachers.vue'
import TeachersList from '@/pages/admin/superAdmin/components/TeachersList.vue'
import { useTeacherStore } from '@/stores/admin/TeacherStore'
import { useTeachersListStore } from '@/stores/admin/TeachersListStore'

let wrapper

// Keep Vuetify's selection behavior while stubbing its CSS-dependent rendering.
const SelectionList = defineComponent({
    props: makeNestedProps(),
    emits: ['update:selected', 'click:select'],
    setup(props, { slots }) {
        useNested(props, { items: ref([]), returnObject: ref(false), scrollToActive: ref(false) })
        return () => h('div', slots.default?.())
    },
})

const SelectionListItem = defineComponent({
    props: { value: Number },
    setup(props, { slots }) {
        const item = useNestedItem(() => props.value, false, false)
        return () => h('div', {
            onClick: (event) => item.select(!item.isSelected.value, event),
        }, slots.title?.())
    },
})

beforeEach(() => {
    setActivePinia(createPinia())
})

afterEach(() => {
    wrapper?.unmount()
    vi.restoreAllMocks()
})

describe('Teacher single selection', () => {
    it.each([
        ['Lehrer', Teachers, useTeacherStore],
        ['Lehrerliste', TeachersList, useTeachersListStore],
    ])('allows selecting only one row at a time in %s', async (_, component, useStore) => {
        const store = useStore()
        store.teachers = [
            { id: 1, last_name: 'First', first_name: 'Teacher' },
            { id: 2, last_name: 'Second', first_name: 'Teacher' },
        ]
        store.selected_teachers = [1, 2]
        vi.spyOn(store, 'index').mockResolvedValue(true)
        wrapper = mount(component, {
            global: {
                stubs: {
                    SearchField: true,
                    Pagination: true,
                    TeachersListImportDialog: true,
                    'v-divider': true,
                    'v-list': SelectionList,
                    'v-list-item': SelectionListItem,
                },
            },
        })
        await flushPromises()

        expect(store.selected_teachers).toEqual([])
        expect(wrapper.text()).not.toContain('Alle auswählen')
        expect(wrapper.text()).not.toContain('Alle abwählen')
        const rows = wrapper.findAll('.crud-list-item')

        await rows[0].trigger('click')
        expect(store.selected_teachers).toEqual([1])

        await rows[1].trigger('click')
        expect(store.selected_teachers).toEqual([2])
        expect(rows[0].classes()).not.toContain('is-selected')
        expect(rows[1].classes()).toContain('is-selected')

        await rows[1].trigger('click')
        expect(store.selected_teachers).toEqual([])
    })
})
