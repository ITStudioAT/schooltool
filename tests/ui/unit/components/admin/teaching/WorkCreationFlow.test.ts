import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from 'axios'
import { useAdminStore } from '@/stores/admin/AdminStore'
import CourseWorks from '@/pages/admin/teaching/overview/components/CourseWorks.vue'
import CourseTable from '@/pages/admin/teaching/overview/components/CourseTable.vue'

const handshake = { filename: 'handshake.json', contents: '{"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}' }
const draft = () => ({ id: null, teaching_course_id: 20, type: 'SA', title: 'Beispiel', automation_enabled: true,
    groups: [], is_group_work: false, is_random_groups: false, date_for_all_groups: '2026-10-20',
    finish_until_date: '2026-10-20', finish_until_time: null, description: '', status: {} })
const saved = { ...draft(), id: 42 }

beforeEach(() => {
    sessionStorage.clear()
    setActivePinia(createPinia())
    useAdminStore().config = { user: { id: 7 } } as never
})
afterEach(() => { sessionStorage.clear(); vi.unstubAllGlobals(); vi.restoreAllMocks() })

function worksContext(selected = handshake) {
    return {
        config: { user: { id: 7 } }, selected_course: { id: 20 }, work_form: draft(),
        hasSelectedWorkType: true, is_saving: false, is_deleting_work: false,
        handshakeState: { busy: false, error: '' }, handshakeDraft: selected, handshakePendingCreation: null,
        courseWorkStore: { store: vi.fn().mockResolvedValue({ data: saved }), update: vi.fn().mockResolvedValue({ data: saved }) },
        buildIndividualGroups: vi.fn().mockReturnValue([]), normalizeDateString: (value: unknown) => value,
        refreshWorks: vi.fn().mockResolvedValue(undefined), courseWorks: [saved], semesterCount: 1,
        filteredCourseWorks: [saved], editWork: vi.fn(), abortEdit: vi.fn(),
        $nextTick: vi.fn().mockResolvedValue(undefined),
        $refs: { moduleLink: { openLink: vi.fn() }, creationLink: { openLink: vi.fn().mockResolvedValue(undefined) } },
        resumeHandshakeLink: (CourseWorks as any).methods.resumeHandshakeLink,
    } as any
}

function tableContext(selected = handshake) {
    return {
        selected_course: { id: 20 }, workDialog: { courseDate: { date: '2026-10-20' }, open: true },
        workDialogForm: draft(), canSaveDateWork: true, workSaving: false,
        dateHandshakeState: { busy: false, error: '' }, dateHandshakeDraft: selected, dateHandshakePendingCreation: null,
        courseWorkStore: { store: vi.fn().mockResolvedValue({ data: saved }), update: vi.fn().mockResolvedValue({ data: saved }) },
        courseWorkGradeInputModeForType: vi.fn().mockReturnValue('free'),
        courseWorkGroupsForGradeInputMode: (groups: unknown[]) => groups, normalizeDateKey: (value: unknown) => value,
        applySavedCourseWork: vi.fn(), loadCourseTableData: vi.fn().mockResolvedValue(true), cancelDateWorkForm: vi.fn(), startEditingDateWork: vi.fn(),
        $nextTick: vi.fn().mockResolvedValue(undefined),
        $refs: { moduleLink: { openLink: vi.fn() }, creationLink: { openLink: vi.fn().mockResolvedValue(undefined) } },
        resumeDateHandshakeLink: (CourseTable as any).methods.resumeDateHandshakeLink,
    } as any
}

describe.each([
    { name: 'Arbeitenübersicht', make: worksContext, save: (ctx: any) => (CourseWorks as any).methods.saveWork.call(ctx),
        form: 'work_form' },
    { name: 'Kursspalte', make: tableContext, save: (ctx: any) => (CourseTable as any).methods.saveDateWork.call(ctx),
        form: 'workDialogForm' },
])('$name: Erstellung mit Handshake', ({ make, save, form }) => {
    it('creates once and opens the inline link check with the selected file', async () => {
        const post = vi.spyOn(axios, 'post').mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', axios)
        const ctx = make()
        await save(ctx)
        expect(post).toHaveBeenCalledOnce()
        expect(post.mock.calls[0][1]).toMatchObject({ title: 'Beispiel', teaching_course_id: 20,
            creation_request_id: expect.any(String) })
        expect(ctx.courseWorkStore.store).not.toHaveBeenCalled()
        expect(ctx.$refs.moduleLink.openLink).not.toHaveBeenCalled()
        expect(ctx.$refs.creationLink.openLink).toHaveBeenCalledExactlyOnceWith(saved, handshake, true)
        expect(ctx[form].id).toBe(42)
    })

    it('does not create a second work when the button is clicked twice while saving', async () => {
        let finish!: (value: unknown) => void
        const post = vi.spyOn(axios, 'post').mockImplementation(() => new Promise(resolve => { finish = resolve }))
        vi.stubGlobal('axios', axios)
        const ctx = make()
        const first = save(ctx)
        const second = save(ctx)
        expect(post).toHaveBeenCalledOnce()
        finish({ data: { data: saved } })
        await Promise.all([first, second])
        expect(post).toHaveBeenCalledOnce()
        expect(ctx.$refs.creationLink.openLink).toHaveBeenCalledExactlyOnceWith(saved, handshake, true)
        expect(ctx[form].id).toBe(42)
    })

    it('retries an uncertain creation with exactly the original request and ID', async () => {
        const post = vi.spyOn(axios, 'post').mockRejectedValueOnce(new Error('Antwort verloren')).mockResolvedValueOnce({ data: { data: saved } })
        vi.stubGlobal('axios', axios)
        const ctx = make()
        await save(ctx)
        expect(ctx.$refs.creationLink.openLink).not.toHaveBeenCalled()
        ctx[form].title = 'Nach dem Fehler verändert'
        await save(ctx)
        expect(post).toHaveBeenCalledTimes(2)
        expect(post.mock.calls[1][1]).toEqual(post.mock.calls[0][1])
        expect(post.mock.calls[1][1].creation_request_id).toBe(post.mock.calls[0][1].creation_request_id)
        expect(ctx.$refs.creationLink.openLink).toHaveBeenCalledExactlyOnceWith(saved, handshake, true)
    })

    it('retains the created work and marks the link incomplete if refreshing fails', async () => {
        vi.spyOn(axios, 'post').mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', axios)
        const ctx = make()
        if (form === 'work_form') ctx.refreshWorks.mockRejectedValueOnce(new Error('Aktualisierung fehlgeschlagen'))
        else ctx.loadCourseTableData.mockRejectedValueOnce(new Error('Aktualisierung fehlgeschlagen'))
        await expect(save(ctx)).rejects.toThrow('Aktualisierung fehlgeschlagen')
        expect(ctx[form].id).toBe(42)
        expect(form === 'work_form' ? ctx.handshakeAwaitingLink : ctx.dateHandshakeAwaitingLink).toBe(true)
        expect(form === 'work_form' ? ctx.handshakeNotice : ctx.dateHandshakeNotice).toContain('noch nicht abgeschlossen')
    })

    it('reports a saved link accurately while a folder retry is pending and after postponing it', () => {
        const ctx = make()
        ctx[form].id = saved.id
        const component = form === 'work_form' ? CourseWorks : CourseTable
        const savedMethod = form === 'work_form' ? 'handshakeLinkSaved' : 'dateHandshakeLinkSaved'
        const deferMethod = form === 'work_form' ? 'deferHandshakeLink' : 'deferDateHandshakeLink'
        const notice = form === 'work_form' ? 'handshakeNotice' : 'dateHandshakeNotice'
        ;(component as any).methods[savedMethod].call(ctx, saved)
        expect(ctx[notice]).toContain('Lernmodul ist verknüpft')
        expect(ctx[notice]).toContain('Prüfungsordner ist noch nicht bestätigt')
        ;(component as any).methods[deferMethod].call(ctx, true)
        expect(ctx[notice]).toContain('Lernmodul ist verknüpft')
        expect(ctx[notice]).not.toContain('noch nicht verknüpft')
    })

    it('uses the ordinary store path when no file was selected', async () => {
        const post = vi.spyOn(axios, 'post')
        vi.stubGlobal('axios', axios)
        const ctx = make(null as any)
        await save(ctx)
        expect(ctx.courseWorkStore.store).toHaveBeenCalledOnce()
        expect(ctx.courseWorkStore.store.mock.calls[0][0]).not.toHaveProperty('creation_request_id')
        expect(post).not.toHaveBeenCalled()
        expect(ctx.$refs.creationLink.openLink).not.toHaveBeenCalled()
    })

    it.each([
        { label: 'ohne Leistungsfeststellung', status: {} },
        { label: 'mit Leistungsfeststellung', status: { folder_imported_at: '2026-10-20T10:00:00Z' } },
    ])('does not send an existing title with ordinary changes: $label', async ({ status }) => {
        const ctx = make(null as any)
        ctx[form].id = 42
        ctx[form].title = 'Nicht über das allgemeine Speichern ändern'
        ctx[form].status = status
        await save(ctx)
        expect(ctx.courseWorkStore.update).toHaveBeenCalledOnce()
        expect(ctx.courseWorkStore.update.mock.calls[0][0]).not.toHaveProperty('title')
    })
})
