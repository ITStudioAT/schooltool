import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/vue'
import MaterialsFreigabeView from '@/pages/admin/materials/components/views/MaterialsFreigabeView.vue'

describe('MaterialsFreigabeView', () => {
    it('shows the two dummy action buttons', () => {
        render(MaterialsFreigabeView, {
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    VCard: { template: '<div><slot /></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    VBtn: { template: '<button><slot /></button>' },
                    'v-alert': { template: '<div><slot /></div>' },
                    VAlert: { template: '<div><slot /></div>' },
                },
            },
        })

        expect(screen.getByRole('button', { name: 'Teilen' })).toBeTruthy()
        expect(screen.getByRole('button', { name: 'Geteiltes Materialien' })).toBeTruthy()
    })
})
