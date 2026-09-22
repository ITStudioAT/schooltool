import { test } from '@playwright/test'
import { expectRemovedTutoringEndpoint } from './helpers/tutoring'

test('removed tutoring authentication endpoints cannot open a session', async ({ request }) => {
    for (const path of [
        '/api/homepage/tutoring/login_with_password',
        '/api/homepage/tutoring/login_with_token',
        '/api/homepage/tutoring/unknown_password',
    ]) {
        await expectRemovedTutoringEndpoint(request, path, 'POST')
    }
})
