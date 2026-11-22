import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { workProvisionEvidence, dispatchNotificationText, workDispatchNotification, workDispatchRecord, workDispatchReady, workProvisionRecords } from '@/helpers/workDispatch'

it('uses a confirmed Mailpit dispatch ahead of preparation while keeping real delivery evidence separate', () => {
    const log = { origin: 'dispatch_import', purpose: 'tasks', sha256: 'mailpit-proof' }
    const delivered = { origin: 'dispatch_import', purpose: 'tasks', student_id: 12, log_sha256: log.sha256,
        mode: 'mailpit', provider_id: 'mailpit-id', sent_at: '2026-10-24T00:00:00Z' }
    const work = { id: 78, status: { dispatch_logs: [log], dispatch_attempts: [delivered, { ...delivered, mode: 'prepared' }] } }
    expect(workDispatchRecord(work, 12, 'tasks')).toBe(delivered)
    expect(workDispatchNotification(work, 12, 'tasks')).toBeNull()
    expect(workDispatchReady(work, 12)).toBe(false)
    expect(dispatchNotificationText(delivered.sent_at, 'tasks', 'mailpit')).toContain('versandt · Testversand über Mailpit')
})
import WorkDispatchStatus from '@/pages/admin/teaching/overview/components/WorkDispatchStatus.vue'

describe('Imported work dispatch notifications', () => {
    it('shows Versandbereit on work cards and compact table statuses only for checked email preparation', async () => {
        const log = { sha256: 'f'.repeat(64), origin: 'dispatch_import', purpose: 'tasks', dispatch_ready: true }
        const prepared = { student_id: 12, origin: 'dispatch_import', purpose: 'tasks', log_sha256: log.sha256,
            mode: 'prepared', sent_at: null, prepared_at: '2026-10-15T09:00:00Z' }
        const work = { id: 78, status: { dispatch_logs: [log], dispatch_attempts: [prepared], dispatch_notifications: [] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work, aggregate: true } })
        try {
            expect(workDispatchReady(work, null, true)).toBe(true)
            expect(wrapper.text()).toContain('Versandbereit').toContain('noch nicht versendet')
            expect(wrapper.get('[aria-label="Versandbereit – Aufgabenversand noch nicht erfolgt."]').attributes('color')).toBe('info')
            await wrapper.setProps({ compact: true })
            expect(wrapper.text()).toContain('Versandbereit')
            await wrapper.setProps({ work: { ...work, status: { ...work.status, dispatch_logs: [{ ...log, dispatch_ready: false }] } } })
            expect(wrapper.text()).not.toContain('Versandbereit')
            await wrapper.setProps({ work: { ...work, status: { ...work.status, dispatch_logs: [] } } })
            expect(wrapper.text()).not.toContain('Versandbereit')
            expect(workDispatchNotification(work, 12, 'tasks')).toBeNull()
        } finally { wrapper.unmount() }
    })
    it('keeps checked email preparation ahead of a newer document snapshot and replaces readiness after scheduling or sending', () => {
        const readyLog = { sha256: 'e'.repeat(64), origin: 'dispatch_import', purpose: 'tasks', dispatch_ready: true }
        const documentLog = { ...readyLog, sha256: 'f'.repeat(64), dispatch_ready: false }
        const prepared = { student_id: 12, origin: 'dispatch_import', purpose: 'tasks', log_sha256: readyLog.sha256,
            mode: 'prepared', sent_at: null, prepared_at: '2026-10-15T09:00:00Z' }
        const document = { ...prepared, log_sha256: documentLog.sha256, prepared_at: '2026-10-15T10:00:00Z' }
        const work = { id: 78, status: { dispatch_logs: [readyLog, documentLog], dispatch_attempts: [prepared, document], dispatch_notifications: [] as any[] } }
        expect(workDispatchRecord(work, 12, 'tasks')).toBe(prepared)
        expect(workDispatchReady(work, 12)).toBe(true)
        work.status.dispatch_attempts.push({ ...prepared, mode: 'scheduled' })
        expect(workDispatchReady(work, 12)).toBe(false)
        work.status.dispatch_notifications.push({ ...prepared, sent_at: '2026-10-15T09:10:00Z' })
        expect(workDispatchRecord(work, 12, 'tasks')?.mode).toBe('live')
        expect(workDispatchReady(work, 12)).toBe(false)
    })
    it.each(['download_release', 'direct_issue'])('shows %s availability for the bound person without a task email claim', async method => {
        const log = { sha256: 'd'.repeat(64), origin: 'provision_import', purpose: 'provision' }
        const event = { student_id: 12, origin: 'provision_import', log_sha256: log.sha256, method, available_at: '2026-10-04T08:00:00Z' }
        const input = { id: 65, status: { dispatch_logs: [log], provision_events: [event,
            { ...event, student_id: 13, available_at: '2026-10-05T08:00:00Z' }] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work: input, studentId: 12, showResultTime: true } })
        try {
            expect(wrapper.text()).toContain(method === 'download_release' ? 'Zum Download bereitgestellt' : 'Direkt ausgegeben')
            expect(wrapper.text()).toContain('04.10.2026 um 10:00 Uhr')
            expect(wrapper.text()).toContain('Individueller Download oder Empfang nicht bestätigt')
            expect(wrapper.text()).not.toContain('Aufgabenversand')
            expect(workDispatchNotification(input, 12, 'tasks')).toBeNull()
            await wrapper.setProps({ aggregate: true })
            expect(wrapper.text()).toContain('05.10.2026 um 10:00 Uhr')
            expect(wrapper.text()).toContain('2 Personen mit belegter Verfügbarkeit')
            await wrapper.setProps({ aggregate: false, studentId: 999, showResultTime: false })
            expect(wrapper.find('[aria-label]').exists()).toBe(false)
            expect(workProvisionRecords({ ...input, status: { ...input.status, dispatch_logs: [] } }, 12)).toEqual([])
        } finally { wrapper.unmount() }
    })
    it('keeps server scheduling ahead of preparation snapshots regardless of file order and confirmed sends ahead of both', () => {
        const log = { sha256: 'c'.repeat(64), origin: 'dispatch_import', purpose: 'tasks' }
        const scheduled = { student_id: 12, origin: 'dispatch_import', purpose: 'tasks', log_sha256: log.sha256,
            mode: 'scheduled', sent_at: null, prepared_at: '2026-10-13T06:00:00Z', planned_at: '2026-10-13T06:40:00Z' }
        const prepared = { ...scheduled, mode: 'prepared', prepared_at: '2026-10-13T07:00:00Z' }
        const work = { id: 65, status: { dispatch_logs: [log], dispatch_notifications: [], dispatch_attempts: [scheduled, prepared] } }
        expect(workDispatchRecord(work, 12, 'tasks')).toBe(scheduled)
        const sent = { ...scheduled, sent_at: '2026-10-13T06:40:04Z' }
        work.status.dispatch_notifications = [sent] as any
        expect(workDispatchRecord(work, 12, 'tasks')?.mode).toBe('live')
    })
    it.each(['prepared', 'scheduled'])('shows %s times even after the planned date without claiming a send', mode => {
        const taskLog = { sha256: 'c'.repeat(64), origin: 'dispatch_import', purpose: 'tasks' }
        const work = { id: 65, status: { dispatch_logs: [taskLog], dispatch_notifications: [],
            dispatch_attempts: [{ student_id: 12, origin: 'dispatch_import', purpose: 'tasks', log_sha256: taskLog.sha256,
                mode, sent_at: null, planned_at: '2020-10-13T06:40:00Z' }] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work, studentId: 12, showResultTime: true } })
        try {
            expect(wrapper.text()).toContain('13.10.2020 um 08:40 Uhr')
            expect(wrapper.text()).toContain(mode === 'scheduled' ? 'serverseitig geplant' : 'vorbereitet')
            expect(wrapper.text()).toContain('Versand noch nicht bestätigt')
            expect(wrapper.text()).not.toContain('Aufgaben per E-Mail versandt')
            expect(wrapper.get('[aria-label]').attributes('color')).toBe('grey-darken-1')
            expect(workDispatchNotification(work, 12, 'tasks')).toBeNull()
        } finally { wrapper.unmount() }
    })

    it.each(['tasks', 'results'])('keeps outstanding %s recipients visible beside a confirmed aggregate send', async purpose => {
        const log = { origin: 'dispatch_import', purpose, sha256: 'd'.repeat(64) }
        const attempt = { origin: 'dispatch_import', purpose, log_sha256: log.sha256, student_id: 12,
            mode: 'scheduled', planned_at: '2026-10-13T06:40:00Z', sent_at: null }
        const work = { id: 65, status: { dispatch_logs: [log],
            dispatch_attempts: [attempt, { ...attempt, student_id: 13 }, { ...attempt, student_id: 14, mode: 'prepared' }],
            dispatch_notifications: [{ ...attempt, sent_at: '2026-10-13T06:40:04Z' }] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work, aggregate: true } })

        try {
            expect(wrapper.text()).toContain('1 Person weiterhin serverseitig geplant. Versand noch nicht bestätigt.')
            expect(wrapper.text()).toContain('1 Person weiterhin vorbereitet. Versand noch nicht bestätigt.')
            expect(wrapper.find('[icon="mdi-clock-outline"]').exists()).toBe(true)
            expect(wrapper.findAll('[color="success"]')).toHaveLength(1)

            await wrapper.setProps({ compact: true })
            expect(wrapper.get('[icon="mdi-clock-outline"]').attributes('aria-label')).toContain('1 Person weiterhin serverseitig geplant')
            await wrapper.setProps({ compact: false, work: { ...work, status: { ...work.status,
                dispatch_notifications: [12, 13, 14].map(student_id => ({ ...attempt, student_id, sent_at: '2026-10-13T06:40:04Z' })) } } })
            expect(wrapper.text()).not.toContain('weiterhin')
            expect(wrapper.find('[icon="mdi-clock-outline"]').exists()).toBe(false)
            expect(work.status.dispatch_attempts).toHaveLength(3)
        } finally { wrapper.unmount() }
    })
    const log = { sha256: 'a'.repeat(64), origin: 'dispatch_import' }
    const notification = { student_id: 12, sent_at: '2026-10-04T00:15:39Z', origin: 'dispatch_import', log_sha256: log.sha256 }
    const work = { id: 65, status: { dispatch_logs: [log], dispatch_notifications: [notification] } }

    it.each([
        ['2026-10-04T00:15:39Z', '04.10.2026 um 02:15'],
        ['2026-12-04T01:15:39Z', '04.12.2026 um 02:15'],
    ])('displays the actual provider time %s in Vienna', (time, expected) => {
        expect(dispatchNotificationText(time)).toBe(`Über die Korrektur per E-Mail verständigt am ${expected} Uhr.`)
        expect(dispatchNotificationText('invalid')).toBe('')
    })

    it('uses the registered user identity and only notifications with a saved matching protocol', async () => {
        const wrapper = mount(WorkDispatchStatus, { props: { work, studentId: '12' } })
        try {
            expect(wrapper.get('[aria-label]').attributes('aria-label')).toContain('04.10.2026 um 02:15')
            await wrapper.setProps({ studentId: 999 })
            expect(wrapper.find('[aria-label]').exists()).toBe(false)
            await wrapper.setProps({ studentId: 12, work: { ...work, status: { ...work.status, dispatch_logs: [] } } })
            expect(wrapper.find('[aria-label]').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it('shows the card symbol only after a confirmed live notification and refreshes it when work data changes', async () => {
        const empty = { ...work, status: { dispatch_logs: [log], dispatch_notifications: [] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work: empty, aggregate: true } })
        try {
            expect(wrapper.find('[aria-label]').exists()).toBe(false)
            await wrapper.setProps({ work })
            expect(wrapper.get('[aria-label="Mindestens eine Ergebnis-E-Mail versandt"]').exists()).toBe(true)
            await wrapper.setProps({ work: empty })
            expect(wrapper.find('[aria-label]').exists()).toBe(false)
        } finally { wrapper.unmount() }
    })

    it('selects the newest successful notification without changing persisted history or selecting a different person', () => {
        const older = { ...notification, sent_at: '2026-10-03T00:15:00Z' }
        const foreign = { ...notification, student_id: 13, sent_at: '2026-10-05T00:15:00Z' }
        const notices = [older, foreign, notification]
        const input = { ...work, status: { ...work.status, dispatch_notifications: notices } }
        expect(workDispatchNotification(input, 12)).toBe(notification)
        expect(notices).toEqual([older, foreign, notification])
        expect(workDispatchNotification(input, null)).toBeNull()
        expect(workDispatchNotification({ ...input, id: null }, 12)).toBeNull()
    })

    it('keeps task tests separate from result notifications and refreshes both kinds independently', async () => {
        const taskLog = { ...log, sha256: 'b'.repeat(64), purpose: 'tasks' }
        const attempt = { ...notification, purpose: 'tasks', mode: 'test', log_sha256: taskLog.sha256, sent_at: '2026-10-02T15:02:40Z' }
        const tasks = { ...work, status: { dispatch_logs: [taskLog], dispatch_notifications: [], dispatch_attempts: [attempt] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work: tasks, studentId: 12 } })
        try {
            expect(wrapper.findAll('[aria-label]')).toHaveLength(1)
            expect(wrapper.get('[aria-label]').attributes('aria-label')).toBe('Aufgabenversand: lokaler Mailpit-Test am 02.10.2026 um 17:02 Uhr.')
            expect(wrapper.get('[aria-label]').attributes('color')).toBe('grey-darken-1')
            expect(workDispatchNotification(tasks, 12)).toBeNull()
            await wrapper.setProps({ work: { ...work, status: { ...work.status, dispatch_logs: [log, taskLog], dispatch_attempts: [attempt] } } })
            expect(wrapper.findAll('[aria-label]')).toHaveLength(2)
            expect(wrapper.text()).not.toContain('Ergebnisbenachrichtigung')
            const liveTask = { ...attempt, provider_id: 'task-provider' }
            const live = { ...work, status: { ...work.status, dispatch_logs: [log, taskLog], dispatch_notifications: [notification, liveTask], dispatch_attempts: [attempt] } }
            await wrapper.setProps({ work: live, aggregate: true })
            expect(wrapper.get('[aria-label="Mindestens eine Aufgaben-E-Mail versandt"]').attributes('color')).toBe('success')
            expect(wrapper.get('[aria-label="Mindestens eine Ergebnis-E-Mail versandt"]').attributes('color')).toBe('success')
            expect(wrapper.text()).toContain('Aufgabenversand')
            expect(wrapper.text()).toContain('Ergebnisbenachrichtigung')
            expect(workDispatchRecord(live, 999, 'tasks')).toBeNull()
            expect(workDispatchRecord({ ...tasks, status: { ...tasks.status, dispatch_logs: [] } }, 12, 'tasks')).toBeNull()
        } finally { wrapper.unmount() }
    })

    it('shows the last result time on general cards and only the selected recipient time on personal cards', async () => {
        const newer = { ...notification, student_id: 13, sent_at: '2026-10-05T01:30:00Z' }
        const latest = { ...work, status: { ...work.status, dispatch_notifications: [notification, newer] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work: latest, aggregate: true } })
        try {
            expect(wrapper.text()).toContain('Ergebnisbenachrichtigung zuletzt versandt am 05.10.2026 um 03:30 Uhr.')
            await wrapper.setProps({ aggregate: false, studentId: 12, showResultTime: true })
            expect(wrapper.text()).toContain('Ergebnisbenachrichtigung versandt am 04.10.2026 um 02:15 Uhr.')
            expect(wrapper.text()).not.toContain('05.10.2026')
            await wrapper.setProps({ studentId: 13 })
            expect(wrapper.text()).toContain('05.10.2026 um 03:30 Uhr.')
            await wrapper.setProps({ work: { ...latest, status: { ...latest.status, dispatch_notifications: [] } } })
            expect(wrapper.text()).not.toContain('Ergebnisbenachrichtigung versandt')
            await wrapper.setProps({ work: latest })
            expect(wrapper.text()).toContain('05.10.2026 um 03:30 Uhr.')
        } finally { wrapper.unmount() }
    })

    it('documents task tests above result notifications on personal cards while compact icons stay adjacent', async () => {
        const taskLog = { ...log, purpose: 'tasks', sha256: 'b'.repeat(64) }
        const mixed = { ...work, status: { ...work.status, dispatch_logs: [log, taskLog], dispatch_attempts: [{ ...notification, purpose: 'tasks', mode: 'test', log_sha256: taskLog.sha256, sent_at: '2026-10-02T15:02:40Z' }] } }
        const wrapper = mount(WorkDispatchStatus, { props: { work: mixed, studentId: 12, showResultTime: true } })
        try {
            const rows = wrapper.findAll('.text-caption')
            expect(rows.map(row => row.text())).toEqual([
                'Aufgabenversand · Test am 02.10.2026 um 17:02 Uhr.',
                'Abgabe eingesammelt: Zeitpunkt unbekannt',
                'Beurteilung abgeschlossen: Zeitpunkt unbekannt',
                'Ergebnisbenachrichtigung versandt am 04.10.2026 um 02:15 Uhr.',
            ])
            await wrapper.setProps({ showResultTime: false })
            expect(wrapper.findAll('[aria-label]')).toHaveLength(2)
            expect(wrapper.findAll('.text-caption')).toHaveLength(0)
            await wrapper.setProps({ showResultTime: true, work })
            expect(wrapper.text()).toContain('Kein bestätigter Aufgabenversand.')
            expect(wrapper.text()).not.toContain('Aufgabenversand am')
        } finally { wrapper.unmount() }
    })
})


describe('provision evidence for the selected work or group', () => {
    const date = '2026-11-20'
    const otherDate = '2026-11-21'
    const time = date + 'T09:00:00Z'
    const recipient = { student_id: 12, state: 'accepted', accepted_at: time }
    const groups = [{ date, student_ids: [12, 14] }, { date: otherDate, student_ids: [13, 14] }]
    const work = { id: 65, groups }
    const labels = (status: object, selectedDate = date) => workProvisionEvidence({ ...work, status }, selectedDate).map(entry => entry.label)

    it('distinguishes a chosen portal, a prepared portal and a released portal with its status', () => {
        expect(labels({ provision_method: { channel: 'portal' } })).toEqual(['Schülerbereich gewählt'])
        expect(labels({ student_distribution: { prepared_at: time, student_ids: [12] } })).toEqual(['Schülerbereich vorbereitet'])
        const release = { published_at: time, withdrawn_at: time, student_ids: [12] }
        expect(workProvisionEvidence({ ...work, status: { student_distribution: release } }, date)[0])
            .toMatchObject({ label: 'Im Schülerbereich bereitgestellt', state: 'Freigabe pausiert' })
        expect(workProvisionEvidence({ ...work, status: { student_distribution: { ...release, ended_at: time } } }, date)[0])
            .toMatchObject({ label: 'Im Schülerbereich bereitgestellt', state: 'Freigabe beendet' })
        expect(labels({ student_distribution: { ...release, student_ids: [13] } })).toEqual(['Bereitstellungsweg offen'])
    })

    it('keeps provider acceptance separate from preparations, tests and another group', () => {
        const batches = {
            all: { state: 'partial', transport: 'postmark', recipients: [recipient, { student_id: 13, state: 'queued' }] },
            [otherDate]: { state: 'sent', transport: 'postmark', recipients: [{ ...recipient, student_id: 14 }] },
        }
        const status = { assignment_mail: { batches } }
        const rows = workProvisionEvidence({ ...work, status }, date)
        expect(rows).toHaveLength(1)
        expect(rows[0]).toMatchObject({ label: 'Per E-Mail versandt',
            detail: 'Postmark hat 1 Nachricht angenommen. Zustellung nicht bestätigt.' })
        expect(labels({ assignment_mail: { batches: { [date]: { transport: 'postmark', state: 'draft', recipients: [{ student_id: 12, state: 'draft' }] } } } }))
            .toEqual(['E-Mail vorbereitet'])
        expect(labels({ assignment_mail: { batches: { [date]: { transport: 'postmark', state: 'queued', recipients: [{ student_id: 12, state: 'queued' }] } } } }))
            .toEqual(['E-Mail-Versand geplant'])
        expect(labels({ assignment_mail: { batches: { [date]: { transport: 'postmark', state: 'sent', recipients: [] } } } }))
            .toEqual(['E-Mail-Versand nicht bestätigt'])
        expect(labels({ assignment_mail: { batches: { [date]: { transport: 'mailpit', state: 'sent', recipients: [recipient] } } } }))
            .toEqual(['Lokaler Mailpit-Test'])
    })

    it('uses only unambiguous historical receipts from the selected group and keeps direct issue distinct', () => {
        const log = { origin: 'dispatch_import', purpose: 'tasks', sha256: 'd'.repeat(64) }
        const teacherLog = { ...log, sha256: 'e'.repeat(64), recipient_scope: 'teacher' }
        const provision = { origin: 'provision_import', purpose: 'provision', sha256: 'f'.repeat(64) }
        const notification = { ...log, sent_at: time, log_sha256: log.sha256 }
        const status = { dispatch_logs: [log, teacherLog, provision],
            dispatch_notifications: [{ ...notification, student_id: 13 }, { ...notification, student_id: 14 },
                { ...notification, student_id: 12, log_sha256: teacherLog.sha256 }],
            provision_events: [{ student_id: 12, available_at: time, log_sha256: provision.sha256,
                origin: 'provision_import', method: 'direct_issue' }],
        }
        expect(labels(status)).toEqual(['Direkt ausgegeben'])
        expect(labels({ ...status, dispatch_notifications: [...status.dispatch_notifications, { ...notification, student_id: 12 }] }))
            .toEqual(['Per E-Mail versandt', 'Direkt ausgegeben'])
    })

    it('only uses an aggregated legacy folder receipt when the work has one unambiguous group date', () => {
        const dispatch = { transport: 'office_outlook', status: 'partial', confirmed_count: 1, sent_at: time }
        expect(workProvisionEvidence(work, date, dispatch).map(entry => entry.label)).toEqual(['Bereitstellungsweg offen'])
        const single = { ...work, groups: [{ date, student_ids: [12] }] }
        expect(workProvisionEvidence(single, date, dispatch)[0]).toMatchObject({ label: 'Per E-Mail versandt', detail: 'Versandbeleg im Prüfungsordner' })
        expect(workProvisionEvidence(single, date, { ...dispatch, transport: 'mailpit' })[0].label).toBe('Lokaler Mailpit-Test')
    })
})
