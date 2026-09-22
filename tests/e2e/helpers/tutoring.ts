import { expect, type APIRequestContext } from '@playwright/test'

export async function expectRemovedTutoringEndpoint(
    request: APIRequestContext,
    path: string,
    method: 'GET' | 'POST' = 'GET',
): Promise<void> {
    const response = await request.fetch(path, {
        method,
        headers: { Accept: 'application/json' },
        maxRedirects: 0,
    })

    expect(response.status(), path).toBe(404)
}
