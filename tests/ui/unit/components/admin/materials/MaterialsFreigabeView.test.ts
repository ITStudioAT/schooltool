import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/vue'
import MaterialsFreigabeView from '@/pages/admin/materials/components/views/MaterialsFreigabeView.vue'

describe('MaterialsFreigabeView', () => {
    it('renders the real shares overview', () => {
        render(MaterialsFreigabeView, {
            global: {
                stubs: {
                    MaterialsSharesView: { template: '<div>Materials Shares Overview</div>' },
                },
            },
        })

        expect(screen.getByText('Materials Shares Overview')).toBeTruthy()
    })
})
