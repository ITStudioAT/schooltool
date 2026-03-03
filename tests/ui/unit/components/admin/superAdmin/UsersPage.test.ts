import { describe, expect, it } from 'vitest'
import Users from '@/pages/admin/superAdmin/components/Users.vue'

describe('Users roles list item UI helpers', () => {
    it('normalizes role names from arrays and comma-separated strings', () => {
        const methods = (Users as any).methods

        expect(methods.normalizeRoleNames(['admin', 'teacher', 'admin'])).toEqual(['admin', 'teacher'])
        expect(methods.normalizeRoleNames('admin, teacher,admin')).toEqual(['admin', 'teacher'])
        expect(methods.normalizeRoleNames(null)).toEqual([])
    })

    it('formats role labels with title case and spaces', () => {
        const methods = (Users as any).methods

        expect(methods.formatRoleLabel('teaching_admin')).toBe('Teaching Admin')
        expect(methods.formatRoleLabel('super_admin')).toBe('Super Admin')
    })

})
