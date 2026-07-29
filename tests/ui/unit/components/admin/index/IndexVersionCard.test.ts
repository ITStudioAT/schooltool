import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import IndexPage from '@/pages/admin/index/Index.vue'

describe('Admin index version card', () => {
    it('renders the version card from environment versions', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('admin-dashboard-page__app-version')
        expect(source).not.toContain('<div class="admin-dashboard-page__eyebrow">Version</div>')
        expect(source).toContain('v{{ appVersion }}')
        expect(source).toContain('version_details_visible: false')
        expect(source).toContain('v-show="version_details_visible"')
        expect(source).toContain(`{{ version_details_visible ? 'Weniger anzeigen' : 'Mehr anzeigen' }}`)
        expect(source).toContain("{ key: 'laravel', label: 'Laravel', value: versions.laravel }")
        expect(source).toContain("{ key: 'composer', label: 'Composer', value: versions.composer }")
        expect(source).toContain("{ key: 'npm', label: 'npm', value: versions.npm }")
        expect(source).toContain("{ key: 'pulse', label: 'Pulse', value: packages.pulse }")
        expect(source).toContain("label: 'Laufzeit'")
        expect(source).toContain("label: 'Cache'")
        expect(source).toContain("label: 'Treiber'")
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

    it('formats safe artisan about information for the expanded card', () => {
        const aboutSections = (IndexPage as any).computed.aboutSections
        const context: Record<string, any> = {
            config: {
                environment_versions: {
                    about: {
                        environment: {
                            environment: 'production',
                            debug_mode: false,
                            url: 'schooltool.example.at',
                            maintenance_mode: false,
                            timezone: 'Europe/Vienna',
                            locale: 'de',
                        },
                        cache: {
                            config: true,
                            events: false,
                            routes: true,
                            views: true,
                        },
                        drivers: {
                            database: 'mysql',
                            queue: 'redis',
                        },
                    },
                },
            },
        }

        const sections = aboutSections.call(context)

        expect(sections.find((section: Record<string, any>) => section.key === 'environment').items)
            .toContainEqual({ key: 'debug', label: 'Debug-Modus', value: 'aus', tone: 'success' })
        expect(sections.find((section: Record<string, any>) => section.key === 'cache').items)
            .toContainEqual({ key: 'config', label: 'Konfiguration', value: 'gecached', tone: 'success' })
        expect(sections.find((section: Record<string, any>) => section.key === 'drivers').items)
            .toContainEqual({ key: 'queue', label: 'Queue', value: 'redis' })
    })
})
