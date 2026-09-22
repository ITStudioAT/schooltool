import { test } from '@playwright/test'
import { expectRemovedTutoringEndpoint } from './helpers/tutoring'

test('removed tutoring offers cannot be listed or created', async ({ request }) => {
    await expectRemovedTutoringEndpoint(request, '/api/admin/tutoring/offers')
    await expectRemovedTutoringEndpoint(request, '/api/admin/tutoring/offers', 'POST')
    await expectRemovedTutoringEndpoint(request, '/api/homepage/tutoring/offers', 'POST')
})
