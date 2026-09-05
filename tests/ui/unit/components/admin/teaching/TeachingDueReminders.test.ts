import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import TeachingDueReminders from '@/pages/admin/teaching/overview/components/TeachingDueReminders.vue'
import { useCourseBehaviourEntryStore } from '@/stores/admin/teaching/CourseBehaviourEntryStore'

vi.mock('axios', () => ({ default: { get: vi.fn() } }))

const reminder = {
    id: 4, description: 'Unterlagen mitbringen', date: '2026-09-01', due_date: '2026-09-05', due_time: '14:30',
    course_id: 18, course_title: 'Mathematik', student_name: 'Test, Anna',
}
let wrapper: ReturnType<typeof mount> | undefined

function mockNotifications(permission = 'granted') {
    const notification = vi.fn(function () {})
    Object.assign(notification, { permission, requestPermission: vi.fn().mockResolvedValue('granted') })
    vi.stubGlobal('Notification', notification)
    return notification as typeof notification & { requestPermission: ReturnType<typeof vi.fn> }
}

describe('Due teaching reminders', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
        vi.useFakeTimers()
        vi.setSystemTime(new Date('2026-09-05T12:30:00Z'))
        vi.mocked(axios.get).mockReset().mockResolvedValue({ data: { data: [reminder] } })
    })

    afterEach(() => {
        wrapper?.unmount()
        wrapper = undefined
        vi.unstubAllGlobals()
        vi.useRealTimers()
    })

    it('shows due details, notifies once across polls, and stops polling when unmounted', async () => {
        const notification = mockNotifications()
        wrapper = mount(TeachingDueReminders)
        await flushPromises()
        expect(wrapper.text()).toContain('Test, Anna · Mathematik')
        expect(wrapper.text()).toContain('Unterlagen mitbringen')
        expect(wrapper.text()).toContain('05.09.2026 um 14:30')
        expect(notification).toHaveBeenCalledTimes(1)
        await vi.advanceTimersByTimeAsync(60000)
        expect(axios.get).toHaveBeenCalledTimes(2)
        expect(notification).toHaveBeenCalledTimes(1)
        wrapper.unmount()
        wrapper = undefined
        await vi.advanceTimersByTimeAsync(60000)
        expect(axios.get).toHaveBeenCalledTimes(2)
    })

    it('requests notification permission only after clicking activate', async () => {
        const notification = mockNotifications('default')
        wrapper = mount(TeachingDueReminders)
        await flushPromises()
        expect(notification.requestPermission).not.toHaveBeenCalled()
        expect(notification).not.toHaveBeenCalled()
        await wrapper.findAll('v-btn').find((button) => button.text().includes('aktivieren'))!.trigger('click')
        await flushPromises()
        expect(notification.requestPermission).toHaveBeenCalledTimes(1)
        expect(notification).toHaveBeenCalledTimes(1)
    })

    it.each(['denied', 'unsupported'])('keeps reminders usable when browser notifications are %s', async (permission) => {
        const notification = mockNotifications(permission)
        if (permission === 'unsupported') {
            vi.stubGlobal('Notification', undefined)
        }
        wrapper = mount(TeachingDueReminders)
        await flushPromises()
        expect(wrapper.text()).toContain(permission === 'denied' ? 'blockiert' : 'nicht verfügbar')
        expect(wrapper.text()).toContain('Unterlagen mitbringen')
        expect(notification).not.toHaveBeenCalled()
    })

    it('marks a reminder done with its original schedule and removes it only after success', async () => {
        mockNotifications('denied')
        const update = vi.spyOn(useCourseBehaviourEntryStore(), 'update').mockResolvedValueOnce(false).mockResolvedValueOnce({ data: {} })
        wrapper = mount(TeachingDueReminders)
        await flushPromises()
        await wrapper.find('v-btn').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Unterlagen mitbringen')
        await wrapper.find('v-btn').trigger('click')
        await flushPromises()
        expect(update).toHaveBeenLastCalledWith({
            id: 4, teaching_course_id: 18, kind: 'notification', description: reminder.description,
            date: '2026-09-01', due_date: '2026-09-05', due_time: '14:30', is_due: true,
            is_done: true, done_date: '2026-09-05',
        })
        expect(wrapper.text()).not.toContain('Unterlagen mitbringen')
    })

    it('recovers from a failed poll on the next interval', async () => {
        mockNotifications('denied')
        vi.mocked(axios.get).mockRejectedValueOnce(new Error('offline'))
        wrapper = mount(TeachingDueReminders)
        await flushPromises()
        expect(wrapper.text()).toContain('konnten nicht aktualisiert')
        await vi.advanceTimersByTimeAsync(60000)
        expect(wrapper.text()).toContain('Unterlagen mitbringen')
        expect(wrapper.text()).not.toContain('konnten nicht aktualisiert')
    })

    it('preserves a reminder when completing it throws and allows retrying', async () => {
        mockNotifications('denied')
        const update = vi.spyOn(useCourseBehaviourEntryStore(), 'update').mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce({ data: {} })
        wrapper = mount(TeachingDueReminders)
        await flushPromises()
        await wrapper.find('v-btn').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Unterlagen mitbringen')
        expect(wrapper.text()).toContain('nicht als erledigt gespeichert')
        await wrapper.find('v-btn').trigger('click')
        await flushPromises()
        expect(update).toHaveBeenCalledTimes(2)
        expect(wrapper.text()).not.toContain('Unterlagen mitbringen')
    })

    it('notifies again after the reminder text changes', async () => {
        const notification = mockNotifications()
        wrapper = mount(TeachingDueReminders)
        await flushPromises()
        vi.mocked(axios.get).mockResolvedValue({ data: { data: [{ ...reminder, description: 'Neue Unterlagen' }] } })
        await vi.advanceTimersByTimeAsync(60000)
        expect(notification).toHaveBeenCalledTimes(2)
    })
})
