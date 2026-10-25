import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { clearWorkDropboxStates, loadWorkDropbox, loadWorkExercise, workDropboxStates, workExerciseCheck } from '@/helpers/workDropbox'

const key = '7:2:65'
const work = { id: 65, teaching_course_id: 2, date_for_all_groups: '2026-10-23', groups: [] }
const found = { connected: true, link_version: 'version-a', folder: null,
    module_link: { module_id: 'module-a', folder: { id: 'id:module-a', name: 'Modul' } },
    exercise: { status: 'found', folder: { id: 'id:exercise', name: 'LF', path: '/Materialien/Modul/LF' } } }

beforeEach(() => { clearWorkDropboxStates() })
afterEach(() => { clearWorkDropboxStates(); vi.unstubAllGlobals() })

it('shares one first check between overview, folder status and details', async () => {
    let finish: (value: unknown) => void = () => {}
    const get = vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve }))
    vi.stubGlobal('axios', { get })

    const overview = loadWorkExercise(key, work, work.date_for_all_groups)
    const folder = loadWorkDropbox(key, work.id)
    const detail = loadWorkExercise(key, work, work.date_for_all_groups)
    expect(get).toHaveBeenCalledTimes(1)
    finish({ data: found })
    expect(await Promise.all([overview, folder, detail])).toEqual([found, found, found])
    expect(workExerciseCheck(key, work, work.date_for_all_groups)).toMatchObject({ status: 'found', folder: found.exercise.folder })
    await loadWorkExercise(key, work, work.date_for_all_groups)
    expect(get).toHaveBeenCalledTimes(1)
})

it('restores the timestamped last check after reload and contacts Dropbox only on explicit refresh', async () => {
    const get = vi.fn().mockResolvedValue({ data: found })
    const post = vi.fn().mockResolvedValue({ data: { ...found, exercise: { status: 'missing', folder: null } } })
    vi.stubGlobal('axios', { get, post })
    await loadWorkExercise(key, work, work.date_for_all_groups)
    const checkedAt = workExerciseCheck(key, work, work.date_for_all_groups)?.checkedAt
    clearWorkDropboxStates(false)

    expect(await loadWorkExercise(key, work, work.date_for_all_groups)).toEqual(found)
    expect(workExerciseCheck(key, work, work.date_for_all_groups)?.checkedAt).toBe(checkedAt)
    expect(get).toHaveBeenCalledTimes(1)
    await loadWorkExercise(key, work, work.date_for_all_groups, true)
    expect(workExerciseCheck(key, work, work.date_for_all_groups)?.status).toBe('missing')
    expect(get).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/65/dropbox/refresh', {
        date: '2026-10-23', finish_until_date: null, finish_until_time: null, expected: { date: null, time: null },
    }, { timeout: 120000 })
})

it('keeps the server timestamp when reading a previously verified status', async () => {
    const checkedAt = '2026-10-22T18:00:00+02:00'
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: { ...found, exercise: { ...found.exercise, checked_at: checkedAt } } }) })
    await loadWorkExercise(key, work, work.date_for_all_groups)
    expect(workExerciseCheck(key, work, work.date_for_all_groups)?.checkedAt).toBe(checkedAt)
})

it('keeps user, course, date, schedule and changed folder bindings separate', async () => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: found }) })
    await loadWorkExercise(key, work, work.date_for_all_groups)

    expect(workExerciseCheck('8:2:65', work, work.date_for_all_groups)).toBeNull()
    expect(workExerciseCheck('7:3:65', { ...work, teaching_course_id: 3 }, work.date_for_all_groups)).toBeNull()
    expect(workExerciseCheck(key, work, '2026-10-24')).toBeNull()
    expect(workExerciseCheck(key, { ...work, groups: [{ date: '2026-10-24' }] }, work.date_for_all_groups)).toBeNull()
    workDropboxStates.set(key, { ...found, link_version: 'version-b' })
    expect(workExerciseCheck(key, work, work.date_for_all_groups)).toBeNull()
})

it('does not let a late response restore another module or a cleared connection', async () => {
    let finish: (value: unknown) => void = () => {}
    vi.stubGlobal('axios', { get: vi.fn().mockImplementation(() => new Promise(resolve => { finish = resolve })) })
    const loading = loadWorkExercise(key, work, work.date_for_all_groups)
    workDropboxStates.set(key, { ...found, link_version: 'version-b' })
    finish({ data: found })
    await expect(loading).rejects.toThrow('Verbindung')
    expect(workDropboxStates.get(key)?.link_version).toBe('version-b')
    expect(workExerciseCheck(key, work, work.date_for_all_groups)).toBeNull()

    const afterSwitch = loadWorkExercise(key, work, work.date_for_all_groups)
    clearWorkDropboxStates()
    finish({ data: found })
    await expect(afterSwitch).rejects.toThrow('Verbindung')
    expect(workExerciseCheck(key, work, work.date_for_all_groups)).toBeNull()
    expect(workDropboxStates.has(key)).toBe(false)
})

it('keeps a failed check unknown and reuses that result until explicitly retried', async () => {
    const get = vi.fn().mockRejectedValue(new Error('Offline'))
    const post = vi.fn().mockResolvedValue({ data: found })
    vi.stubGlobal('axios', { get, post })
    await expect(loadWorkExercise(key, work, work.date_for_all_groups)).rejects.toThrow('Offline')
    expect(workExerciseCheck(key, work, work.date_for_all_groups)?.status).toBe('unknown')
    await loadWorkExercise(key, work, work.date_for_all_groups)
    expect(get).toHaveBeenCalledTimes(1)
    await loadWorkExercise(key, work, work.date_for_all_groups, true)
    expect(workExerciseCheck(key, work, work.date_for_all_groups)?.status).toBe('found')
})
