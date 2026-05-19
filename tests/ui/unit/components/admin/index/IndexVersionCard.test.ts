import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import IndexPage from '@/pages/admin/index/Index.vue'

describe('Admin index version card', () => {
    it('renders the version card from environment versions', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('admin-dashboard-page__app-version')
        expect(source).toContain('v{{ appVersion }}')
        expect(source).toContain("{ key: 'laravel', label: 'Laravel', value: versions.laravel }")
        expect(source).toContain("{ key: 'composer', label: 'Composer', value: versions.composer }")
        expect(source).toContain("{ key: 'npm', label: 'npm', value: versions.npm }")
    })

    it('falls back to config version and unavailable version labels', () => {
        const appVersion = (IndexPage as any).computed.appVersion
        const versionItems = (IndexPage as any).computed.versionItems
        const context: Record<string, any> = {
            config: {
                version: '3.20.19',
                environment_versions: {
                    laravel: '13.9.0',
                    php: '8.3.0',
                },
            },
        }

        expect(appVersion.call(context)).toBe('3.20.19')
        expect(versionItems.call(context)).toContainEqual({ key: 'laravel', label: 'Laravel', value: '13.9.0' })
        expect(versionItems.call(context)).toContainEqual({ key: 'composer', label: 'Composer', value: 'nicht verfügbar' })
    })
})
