import { describe, expect, it } from 'vitest'
import { materialsV2Api } from '@/domains/materialsV2/api'

describe('Materials V2 Wayfinder API', () => {
    it('resolves controller routes without hard-coded domain URLs', () => {
        expect(materialsV2Api.config()).toBe('/api/admin/materials-v2/config')
        expect(materialsV2Api.items()).toBe('/api/admin/materials-v2/items')
        expect(materialsV2Api.storeItem()).toBe('/api/admin/materials-v2/items')
        expect(materialsV2Api.updateItem({ id: 17 })).toBe('/api/admin/materials-v2/items/17')
        expect(materialsV2Api.destroyAttachment({ id: 23 })).toBe('/api/admin/materials-v2/attachments/23')
        expect(materialsV2Api.linkPreview()).toBe('/api/admin/materials-v2/link-preview')
    })
})
