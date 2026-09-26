import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { makeNestedProps, useNested, useNestedItem } from 'vuetify/lib/composables/nested/nested.js'
import Teachers from '@/pages/admin/superAdmin/components/Teachers.vue'
import TeachersList from '@/pages/admin/superAdmin/components/TeachersList.vue'
import { useTeacherStore } from '@/stores/admin/TeacherStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
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

const ClassHeadSelect = defineComponent({
    props: { modelValue: Array },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('button', {
            class: 'class-head-select',
            onClick: (event: MouseEvent) => {
                event.stopPropagation()
                emit('update:modelValue', ['1A', '1B'])
            },
        }, props.modelValue?.join(', ') || 'Klassen wählen')
    },
})

beforeEach(() => {
    setActivePinia(createPinia())
})

afterEach(() => {
    wrapper?.unmount()
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
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
                    'v-autocomplete': ClassHeadSelect,
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

    it('saves multiple class head classes directly from the teacher row', async () => {
        useAdminStore().config = { selected_schoolyear: { id: 5 } } as never
        const store = useTeacherStore()
        store.teachers = [{ id: 1, last_name: 'Huber', first_name: 'Anna', class_head_classes: [] }]
        store.classes = ['1A', '1B']
        vi.spyOn(store, 'index').mockResolvedValue(true)
        const saveClassHeads = vi.spyOn(store, 'saveClassHeads').mockResolvedValue(true)
        wrapper = mount(Teachers, {
            global: {
                stubs: {
                    SearchField: true,
                    Pagination: true,
                    TeachersListImportDialog: true,
                    'v-divider': true,
                    'v-list': SelectionList,
                    'v-list-item': SelectionListItem,
                    'v-autocomplete': ClassHeadSelect,
                },
            },
        })
        await flushPromises()

        await wrapper.find('.class-head-select').trigger('click')
        await flushPromises()

        expect(saveClassHeads).toHaveBeenCalledWith(1, ['1A', '1B'])
        expect(store.selected_teachers).toEqual([])
    })

    it('sends all selected classes to the class head endpoint and updates the row', async () => {
        const put = vi.fn().mockResolvedValue({ data: { class_names: ['1A', '1B'] } })
        vi.stubGlobal('axios', { put })
        const store = useTeacherStore()
        store.teachers = [{ id: 1, class_head_classes: [] }]

        expect(await store.saveClassHeads(1, ['1A', '1B'])).toBe(true)
        expect(put).toHaveBeenCalledWith('/api/admin/teachers/1/class-head', { class_names: ['1A', '1B'] })
        expect(store.teachers[0].class_head_classes).toEqual(['1A', '1B'])
    })
})
