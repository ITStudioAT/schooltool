import { beforeEach, describe, expect, it, vi } from 'vitest'
import { setupDraftScope, setupDraftKey, readSetupDraft, readActiveSetup, writeSetupDraft, removeSetupDraft } from '@/pages/admin/helpers/matura/maturaSetupDraft'

function draft() {
    return { form: { name: 'Entwurf', exam_date: '2026-10-04', waiting_places: '', rooms: [
        { name: '', student_ids: [], manualText: '', classFilter: '', search: '' },
    ] }, completed: [true, false, false], activeStep: 1, roomIndex: 0 }
}

beforeEach(() => { window.sessionStorage.clear(); window.localStorage.clear() })

describe('Matura draft storage', () => {
    it('separates user, school, year and new or edited sessions and rejects incomplete identity', () => {
        const scope = setupDraftScope(7, 8, 10)
        const key = setupDraftKey(scope)
        expect(writeSetupDraft(key, draft())).toBe(true)
        expect(writeSetupDraft(`${scope}:active`, { sessionId: null })).toBe(true)
        expect(readSetupDraft(key)).toEqual(draft())
        expect(readActiveSetup(scope)).toEqual({ sessionId: null })
        for (const otherScope of [setupDraftScope(9, 8, 10), setupDraftScope(7, 9, 10), setupDraftScope(7, 8, 11)]) {
            expect(readSetupDraft(setupDraftKey(otherScope))).toBeNull()
            expect(readActiveSetup(otherScope)).toBeNull()
        }
        expect(readSetupDraft(setupDraftKey(scope, 1))).toBeNull()
        expect(setupDraftScope(null, 8, 10)).toBeNull()
        expect(setupDraftScope(7, 8, null)).toBeNull()
    })

    it('ignores corrupted, incompatible and structurally invalid drafts', () => {
        const key = setupDraftKey(setupDraftScope(7, 8, 10))
        const records = [
            'invalid json', 'null',
            JSON.stringify({ version: 0, updatedAt: Date.now(), data: draft() }),
            JSON.stringify({ version: 1, updatedAt: 'invalid', data: draft() }),
            JSON.stringify({ version: 1, updatedAt: Date.now(), data: { ...draft(), form: { ...draft().form, rooms: [] } } }),
            JSON.stringify({ version: 1, updatedAt: Date.now(), data: { ...draft(), activeStep: 99 } }),
        ]
        for (const record of records) {
            window.sessionStorage.setItem(key, record)
            expect(readSetupDraft(key)).toBeNull()
        }
    })

    it('handles unavailable browser storage without crashing or pretending to persist', () => {
        const key = setupDraftKey(setupDraftScope(7, 8, 10))
        const write = vi.spyOn(window.sessionStorage, 'setItem').mockImplementation(() => { throw new Error('Storage unavailable') })
        const read = vi.spyOn(window.sessionStorage, 'getItem').mockImplementation(() => { throw new Error('Storage unavailable') })
        const localWrite = vi.spyOn(window.localStorage, 'setItem').mockImplementation(() => { throw new Error('Storage unavailable') })
        const localRead = vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => { throw new Error('Storage unavailable') })
        try {
            expect(writeSetupDraft(key, draft())).toBe(false)
            expect(readSetupDraft(key)).toBeNull()
        } finally { write.mockRestore(); read.mockRestore(); localWrite.mockRestore(); localRead.mockRestore() }
    })

    it('migrates an old tab draft and resumes it in a new session without an expiry deadline', () => {
        const scope = setupDraftScope(7, 8, 10)
        const key = setupDraftKey(scope)
        const activeKey = `${scope}:active`
        const oldTimestamp = Date.now() - 60 * 24 * 60 * 60 * 1000
        const legacy = JSON.stringify({ version: 1, updatedAt: oldTimestamp, data: draft() })
        window.sessionStorage.setItem(key, legacy)
        window.sessionStorage.setItem(activeKey, JSON.stringify({ version: 1, updatedAt: oldTimestamp, data: { sessionId: null } }))
        expect(readActiveSetup(scope)).toEqual({ sessionId: null })
        expect(readSetupDraft(key)).toEqual(draft())
        expect(window.localStorage.getItem(key)).toBe(legacy)
        expect(window.sessionStorage.getItem(key)).toBe(legacy)
        window.sessionStorage.clear()
        expect(readSetupDraft(key)).toEqual(draft())
        expect(readActiveSetup(scope)).toEqual({ sessionId: null })
        window.sessionStorage.setItem(key, legacy)
        removeSetupDraft(activeKey)
        removeSetupDraft(key)
        expect(window.localStorage.getItem(key)).toBeNull()
        expect(window.sessionStorage.getItem(key)).toBeNull()
        expect(readActiveSetup(scope)).toBeNull()
    })

    it('preserves the latest valid draft when local and legacy tab copies differ', () => {
        const key = setupDraftKey(setupDraftScope(7, 8, 10))
        const timestamp = Date.now()
        const localDraft = { ...draft(), form: { ...draft().form, name: 'Neuere Eingaben' } }
        window.localStorage.setItem(key, JSON.stringify({ version: 1, updatedAt: timestamp, data: localDraft }))
        window.sessionStorage.setItem(key, JSON.stringify({ version: 1, updatedAt: timestamp - 1000, data: draft() }))
        expect(readSetupDraft(key)).toEqual(localDraft)
        window.sessionStorage.setItem(key, JSON.stringify({ version: 1, updatedAt: timestamp + 1000, data: { ...draft(), activeStep: 99 } }))
        expect(readSetupDraft(key)).toEqual(localDraft)
        const newerTabDraft = { ...draft(), form: { ...draft().form, name: 'Neuester Tab-Entwurf' } }
        window.sessionStorage.setItem(key, JSON.stringify({ version: 1, updatedAt: timestamp + 2000, data: newerTabDraft }))
        expect(readSetupDraft(key)).toEqual(newerTabDraft)
        window.sessionStorage.clear()
        expect(readSetupDraft(key)).toEqual(newerTabDraft)
    })
})
