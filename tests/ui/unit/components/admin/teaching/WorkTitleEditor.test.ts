import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import WorkTitleEditor from '@/pages/admin/teaching/overview/components/WorkTitleEditor.vue'
import { canEditWorkTitle } from '@/helpers/workTitlePermission'

function mountEditor() {
    return mount(WorkTitleEditor, {
        props: { workId: 65, title: 'Alter Titel', canEdit: true },
        global: { stubs: {
            'v-btn': { props: ['disabled', 'title'], template: '<button :disabled="disabled" :title="title"><slot /></button>' },
            'v-text-field': { props: ['modelValue', 'errorMessages'], emits: ['update:modelValue'], template: '<div><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span>{{ errorMessages }}</span></div>' },
        } },
    })
}

afterEach(() => vi.unstubAllGlobals())

it('keeps the title locked, discards cancellation and saves only the title explicitly', async () => {
    const put = vi.fn().mockResolvedValue({ data: { data: { id: 65, title: 'Neuer Titel' } } })
    vi.stubGlobal('axios', { put })
    const wrapper = mountEditor()
    expect(wrapper.find('input').exists()).toBe(false)
    await wrapper.get('[title="Titel bearbeiten"]').trigger('click')
    await wrapper.get('input').setValue('Verwerfen')
    await wrapper.findAll('button').find(button => button.text() === 'Abbrechen')!.trigger('click')
    expect(wrapper.text()).toContain('Alter Titel')
    expect(put).not.toHaveBeenCalled()
    await wrapper.get('[title="Titel bearbeiten"]').trigger('click')
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('Alter Titel')
    await wrapper.get('input').setValue('  Neuer Titel  ')
    await wrapper.findAll('button').find(button => button.text() === 'Titel speichern')!.trigger('click')
    await flushPromises()
    expect(put).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65', { title: 'Neuer Titel' })
    expect(wrapper.emitted('saved')?.[0]).toEqual([{ id: 65, title: 'Neuer Titel' }])
})

it('does not offer editing without permission and checks the course, school and year', async () => {
    const wrapper = mountEditor()
    await wrapper.setProps({ canEdit: false })
    expect(wrapper.get('span').text()).toBe('Alter Titel')
    expect(wrapper.find('[title="Titel bearbeiten"]').exists()).toBe(false)
    const config = { user: { id: 7 }, roles: ['teacher'], selected_school: { id: 1 }, selected_schoolyear: { id: 2 } }
    const course = { id: 3, school_id: 1, schoolyear_id: 2, user_id: 7 }
    const work = { id: 4, teaching_course_id: 3 }
    expect(canEditWorkTitle(config, course, work)).toBe(true)
    expect(canEditWorkTitle(config, { ...course, user_id: 8 }, work)).toBe(false)
    expect(canEditWorkTitle({ ...config, roles: ['admin'] }, { ...course, user_id: 8 }, work)).toBe(true)
    expect(canEditWorkTitle(config, { ...course, schoolyear_id: 9 }, work)).toBe(false)
    expect(canEditWorkTitle(config, course, { ...work, teaching_course_id: 9 })).toBe(false)
    expect(canEditWorkTitle({ ...config, roles: ['student'] }, course, work)).toBe(false)
    wrapper.unmount()
})

it('keeps the draft for correction on validation or server errors', async () => {
    const put = vi.fn().mockRejectedValue({ response: { data: { message: 'Prüfungsordner ist nicht eindeutig.' } } })
    vi.stubGlobal('axios', { put })
    const wrapper = mountEditor()
    await wrapper.get('[title="Titel bearbeiten"]').trigger('click')
    await wrapper.get('input').setValue('  ')
    await wrapper.findAll('button').find(button => button.text() === 'Titel speichern')!.trigger('click')
    expect(wrapper.text()).toContain('Bitte einen Titel eingeben.')
    expect(put).not.toHaveBeenCalled()
    await wrapper.get('input').setValue('Korrektur')
    await wrapper.findAll('button').find(button => button.text() === 'Titel speichern')!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Prüfungsordner ist nicht eindeutig.')
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('Korrektur')
})
