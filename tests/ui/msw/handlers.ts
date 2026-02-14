import { http, HttpResponse } from 'msw'

export const handlers = [
    http.get('http://localhost/api/me', () => {
        return HttpResponse.json({ id: 1, name: 'Test User' })
    }),
]

