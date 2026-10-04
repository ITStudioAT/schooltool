import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { dispatchNotificationText, workDispatchNotification, workDispatchRecord } from '@/helpers/workDispatch'
import WorkDispatchStatus from '@/pages/admin/teaching/overview/components/WorkDispatchStatus.vue'

describe('Imported work dispatch notifications', () => {
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
            expect(rows.map(row => row.text())).toEqual(['Aufgabenversand · Test am 02.10.2026 um 17:02 Uhr.', 'Ergebnisbenachrichtigung versandt am 04.10.2026 um 02:15 Uhr.'])
            await wrapper.setProps({ showResultTime: false })
            expect(wrapper.findAll('[aria-label]')).toHaveLength(2)
            expect(wrapper.findAll('.text-caption')).toHaveLength(0)
            await wrapper.setProps({ showResultTime: true, work })
            expect(wrapper.text()).toContain('Kein bestätigter Aufgabenversand.')
            expect(wrapper.text()).not.toContain('Aufgabenversand am')
        } finally { wrapper.unmount() }
    })
})
