import { render, screen } from '@testing-library/vue'
import { createPinia } from 'pinia'
import { afterEach, describe, expect, it, vi } from 'vitest'
import HomepageIndex from '@/pages/homepage/index/Index.vue'

describe('public homepage design', () => {
    afterEach(() => vi.unstubAllGlobals())

    it.each([
        [true, '/images/school-logo-cdgym-positive.png'],
        [false, '/storage/images/logos/logo_1.png'],
    ])('renders the school selection and introduction link according to design setting %s', async (homepageDesignEnabled, logoUrl) => {
        vi.stubGlobal('axios', {
            get: vi.fn((url) => Promise.resolve({
                data: url.endsWith('/login_schools') ? {
                    schools: [{
                        id: 1,
                        long_name: 'Christian-Doppler-Gymnasium Salzburg',
                        short_name: 'CDGym',
                        logo: 'logo_1.png',
                    }],
                    homepage_design_enabled: homepageDesignEnabled,
                } : {},
            })),
        })

        render(HomepageIndex, {
            global: {
                plugins: [createPinia()],
                mocks: { $route: { path: '/', query: {} } },
                stubs: {
                    RouterLink: { template: '<a><slot /></a>' },
                    SchoolEntryHeader: true,
                    VAlert: true,
                    VAutocomplete: true,
                    VBtn: true,
                    VCheckbox: true,
                    VForm: true,
                    VIcon: true,
                    VOtpInput: true,
                    VProgressCircular: true,
                    VTextField: true,
                },
            },
        })

        const school = await screen.findByRole('button', { name: 'Christian-Doppler-Gymnasium Salzburg auswählen' })

        expect(school.querySelector('img')).toHaveAttribute('src', logoUrl)
        expect(screen.queryAllByText('Schooltool entdecken')).toHaveLength(homepageDesignEnabled ? 1 : 0)
    })
})
