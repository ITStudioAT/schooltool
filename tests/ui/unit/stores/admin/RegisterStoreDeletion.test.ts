import { afterEach, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useRegisterStore } from '@/stores/admin/RegisterStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

afterEach(() => { vi.unstubAllGlobals(); vi.restoreAllMocks() })

it.each([true, false])('sends the typed confirmation and only clears deleted selections after success (%s)', async success => {
    setActivePinia(createPinia())
    const admin = useAdminStore()
    const store = useRegisterStore()
    const notify = vi.spyOn(useNotificationStore(), 'notify').mockImplementation(() => {})
    const selected = { id: 41, name: 'Versuch' }
    admin.selected_register = selected as never
    admin.selected_active_register = selected as never
    store.selected_register = selected as never
    store.register_dates = [{ id: 1 }] as never
    const remove = success ? vi.fn().mockResolvedValue({ status: 204 }) : vi.fn().mockRejectedValue({ response: { status: 422, data: { message: 'Bestätigung fehlt' } } })
    vi.stubGlobal('axios', { delete: remove })

    expect(await store.destroy(selected, 'Versuch')).toBe(success)
    expect(remove).toHaveBeenCalledExactlyOnceWith('/api/admin/registers/41', { data: { confirmation: 'Versuch' } })
    expect(admin.selected_register).toEqual(success ? null : selected)
    expect(admin.selected_active_register).toEqual(success ? null : selected)
    expect(store.register_dates).toHaveLength(success ? 0 : 1)
    expect(admin.is_loading).toBe(0)
    expect(notify).toHaveBeenCalledOnce()
})

it('preserves selections and loaded dates for a different tool', async () => {
    setActivePinia(createPinia())
    const admin = useAdminStore()
    const store = useRegisterStore()
    vi.spyOn(useNotificationStore(), 'notify').mockImplementation(() => {})
    const other = { id: 42, name: 'Anderes Tool' }
    admin.selected_register = other as never
    admin.selected_active_register = other as never
    store.register_dates = [{ id: 1 }] as never
    vi.stubGlobal('axios', { delete: vi.fn().mockResolvedValue({ status: 204 }) })

    await store.destroy({ id: 41, name: 'Versuch' }, 'Versuch')

    expect(admin.selected_register).toEqual(other)
    expect(admin.selected_active_register).toEqual(other)
    expect(store.register_dates).toHaveLength(1)
})
