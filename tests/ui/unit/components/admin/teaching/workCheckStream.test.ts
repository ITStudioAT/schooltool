import { expect, it, vi } from 'vitest'
import { createWorkCheckStream } from '@/pages/admin/teaching/overview/components/workCheckProgress'

it('shows complete progress events as their chunks arrive and returns the final result', () => {
    const progress = vi.fn()
    const stream = createWorkCheckStream(progress)
    const first = JSON.stringify({ type: 'progress', data: { active: 'folder' } }) + '\n'
    const second = JSON.stringify({ type: 'result', data: { id: 12 } }) + '\n'
    stream.options.onDownloadProgress({ event: { target: { responseText: first.slice(0, 10) } } })
    expect(progress).not.toHaveBeenCalled()
    stream.options.onDownloadProgress({ event: { target: { responseText: first + second.slice(0, 8) } } })
    expect(progress).toHaveBeenCalledExactlyOnceWith({ active: 'folder' })
    expect(stream.result({ data: first + second })).toEqual({ id: 12 })
    expect(progress).toHaveBeenCalledTimes(1)
})

it('reports a streamed error or missing final result without treating either as success', () => {
    const failed = createWorkCheckStream(vi.fn())
    const error = JSON.stringify({ type: 'error', data: { status: 409, body: { message: 'Zuordnung geändert.' } } }) + '\n'
    expect(() => failed.result({ data: error })).toThrow('Zuordnung geändert.')
    const interrupted = createWorkCheckStream(vi.fn())
    expect(() => interrupted.result({ data: JSON.stringify({ type: 'progress', data: {} }) + '\n' })).toThrow('ohne vollständiges Ergebnis')
})
