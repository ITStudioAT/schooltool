import { describe, expect, it } from 'vitest'
import { teachersListApi } from '@/domains/teachersList/api'

describe('Teachers List Wayfinder API', () => {
    it('resolves the complete domain without hard-coded URLs', () => {
        expect(teachersListApi.index()).toBe('/api/admin/teachers_list')
        expect(teachersListApi.store()).toBe('/api/admin/teachers_list')
        expect(teachersListApi.update(17)).toBe('/api/admin/teachers_list/17')
        expect(teachersListApi.deleteTeachers()).toBe('/api/admin/teachers_list/delete_teachers')
        expect(teachersListApi.importStatus()).toBe('/api/admin/teachers_list_import_status')
        expect(teachersListApi.upload()).toBe('/api/admin/teachers_list_upload')
    })
})
