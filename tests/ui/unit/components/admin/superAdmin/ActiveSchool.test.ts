import { describe, expect, it, vi } from 'vitest'
import ActiveSchool from '@/pages/admin/superAdmin/components/ActiveSchool.vue'

describe('active school account navigation', () => {
    it.each([
        [['super_admin'], '/admin/super-admin?panel=school'],
        [['teacher'], '/admin'],
    ])('discards the old document and respects target roles %s', async (roles, target) => {
        const assign = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
        const context = {
            schoolStore: { switchSchool: vi.fn().mockResolvedValue(true), loadSchoolInfos: vi.fn() },
            adminStore: { loadConfig: vi.fn().mockResolvedValue({ roles }) },
            $route: { fullPath: '/admin/super-admin?panel=school' },
            switch_email: 'operator@example.test',
        }
        try {
            await (ActiveSchool as any).methods.doSwitch.call(context, 5)
            expect(context.adminStore.loadConfig).toHaveBeenCalledWith({ navigationOnly: true })
            expect(context.schoolStore.loadSchoolInfos).not.toHaveBeenCalled()
            expect(assign).toHaveBeenCalledWith(target)
        } finally {
            assign.mockRestore()
        }
    })

    it('keeps the current document when the switch is refused', async () => {
        const assign = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
        const context = { schoolStore: { switchSchool: vi.fn().mockResolvedValue(false) },
            adminStore: { loadConfig: vi.fn() }, switch_email: 'operator@example.test' }
        try {
            await (ActiveSchool as any).methods.doSwitch.call(context, 5)
            expect(context.adminStore.loadConfig).not.toHaveBeenCalled()
            expect(assign).not.toHaveBeenCalled()
        } finally {
            assign.mockRestore()
        }
    })
})
