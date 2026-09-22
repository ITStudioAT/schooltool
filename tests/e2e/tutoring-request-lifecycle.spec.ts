import { test } from '@playwright/test'
import { expectRemovedTutoringEndpoint } from './helpers/tutoring'

test('removed tutoring requests cannot be listed or submitted', async ({ request }) => {
    await expectRemovedTutoringEndpoint(request, '/api/admin/tutoring/requests')
    await expectRemovedTutoringEndpoint(request, '/api/homepage/tutoring/offer_requests', 'POST')
    await expectRemovedTutoringEndpoint(request, '/api/homepage/tutoring/request_mail_clicked', 'POST')
})
