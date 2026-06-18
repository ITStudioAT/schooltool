import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import AdminUsers from '@/pages/admin/studentsTimetables/settings/AdminUsers.vue'

describe('Students timetable admin users settings', () => {
    it('shows the managed role next to the title', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/settings/AdminUsers.vue', 'utf8')
        const roleChipText = (AdminUsers as any).computed.roleChipText

        expect(source).toContain('prepend-icon="mdi-shield-account-outline"')
        expect(source).toContain('{{ roleChipText }}')
        expect(roleChipText.call({ roleKey: 'admins' })).toBe('studentstimetables_admin')
        expect(roleChipText.call({ roleKey: 'moderators' })).toBe('studentstimetables_moderator')
    })

    it('shows copy affordance and copied feedback for email addresses', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/settings/AdminUsers.vue', 'utf8')

        expect(source).toContain('@click.stop="copyEmail(item)"')
        expect(source).toContain("copiedEmailId === item.id ? 'mdi-check' : 'mdi-content-copy'")
        expect(source).toContain('Kopiert')
        expect(source).toContain('E-Mail-Adresse kopieren')
    })

    it('does not show the lock action in the actions panel', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/settings/AdminUsers.vue', 'utf8')

        expect(source).not.toContain('prepend-icon="mdi-lock"')
        expect(source).not.toContain('>Sperren<')
        expect(source).toContain('Entsperren')
    })

    it('keeps admin user selection single-item only', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/settings/AdminUsers.vue', 'utf8')
        const ctx = {
            selected_admin_users: [1, 2, 3],
        }

        expect(source).not.toContain('Alle auswählen')
        expect(source).toContain('Auswahl aufheben')

        ;(AdminUsers as any).watch.selected_admin_users.call(ctx, ctx.selected_admin_users)

        expect(ctx.selected_admin_users).toEqual([3])
    })

    it('copies an email and clears the copied state after feedback', async () => {
        vi.useFakeTimers()

        const methods = (AdminUsers as any).methods
        const ctx = {
            copiedEmailId: null,
            copyEmailResetTimeout: null,
            copyTextToClipboard: vi.fn().mockResolvedValue(true),
        }

        await methods.copyEmail.call(ctx, { id: 42, email: ' admin@example.test ' })

        expect(ctx.copyTextToClipboard).toHaveBeenCalledWith('admin@example.test')
        expect(ctx.copiedEmailId).toBe(42)

        vi.advanceTimersByTime(1500)

        expect(ctx.copiedEmailId).toBeNull()
        expect(ctx.copyEmailResetTimeout).toBeNull()

        vi.useRealTimers()
    })

    it('writes text through the clipboard API when available', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined)
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        })

        const copied = await (AdminUsers as any).methods.copyTextToClipboard('admin@example.test')

        expect(copied).toBe(true)
        expect(writeText).toHaveBeenCalledWith('admin@example.test')
    })
})
