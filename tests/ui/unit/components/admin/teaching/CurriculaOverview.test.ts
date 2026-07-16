import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import CurriculaOverview from '@/pages/admin/teaching/curricula/CurriculaOverview.vue'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'

vi.mock('@/stores/admin/teaching/CurriculumStore', () => ({
    useCurriculumStore: vi.fn(),
}))

function buildStore() {
    return {
        curricula: [
            {
                id: 15,
                title: 'Deutsch',
                description: 'Lehrplan',
                semester_count: 2,
                free_weeks: [],
                topics: [],
            },
        ],
        imported_curricula: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 1,
        },
        search: '',
        is_loading: false,
        index: vi.fn().mockResolvedValue(true),
        loadImportedCurricula: vi.fn().mockResolvedValue(true),
        store: vi.fn(),
        update: vi.fn(),
        destroy: vi.fn(),
    }
}

function mountCurriculaOverview(store = buildStore()) {
    vi.mocked(useCurriculumStore).mockReturnValue(store as never)

    return {
        store,
        wrapper: mount(CurriculaOverview, {
            global: {
                plugins: [createPinia()],
                stubs: {
                    FilePond: { template: '<div />' },
                    'v-btn': { template: '<button @click="$emit(\'click\', $event)"><slot /></button>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-actions': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    'v-chip': { template: '<span><slot /></span>' },
                    'v-dialog': { template: '<div><slot /></div>' },
                    'v-icon': { template: '<i><slot /></i>' },
                    'v-list': { template: '<div><slot /></div>' },
                    'v-list-item': { template: '<div class="v-list-item" @click="$emit(\'click\', $event)"><slot name="prepend" /><slot /><slot name="append" /></div>' },
                    'v-list-item-subtitle': { template: '<div><slot /></div>' },
                    'v-list-item-title': { template: '<div><slot /></div>' },
                    'v-pagination': { template: '<div />' },
                    'v-progress-circular': { template: '<div />' },
                    'v-sheet': { template: '<div><slot /></div>' },
                    'v-spacer': { template: '<div />' },
                    'v-text-field': { template: '<input />' },
                    'v-textarea': { template: '<textarea></textarea>' },
                    'v-btn-toggle': { template: '<div><slot /></div>' },
                },
            },
        }),
    }
}

describe('Teaching curricula overview actions', () => {
    beforeEach(() => {
        vi.mocked(useCurriculumStore).mockReset()
    })

    it('opens the edit dialog without selecting the curriculum row', async () => {
        const { wrapper, store } = mountCurriculaOverview()

        await wrapper.vm.$nextTick()

        expect(store.index).toHaveBeenCalledTimes(1)

        const editButton = wrapper.find('.curricula-overview__item-actions button')

        expect(editButton.exists()).toBe(true)
        expect((wrapper.vm as any).dialogOpen).toBe(false)

        await editButton.trigger('click')

        expect(wrapper.emitted('select')).toBeUndefined()
        expect((wrapper.vm as any).dialogOpen).toBe(true)
        expect((wrapper.vm as any).editing).toMatchObject({
            id: 15,
            title: 'Deutsch',
        })
    })

    it('keeps the action container click-stopped in the component source', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/CurriculaOverview.vue', 'utf8')
        )

        expect(source).toContain('<div class="curricula-overview__item-actions" @click.stop>')
        expect(source).toContain('<v-dialog v-model="dialogOpen" max-width="560" persistent>')
    })
})
