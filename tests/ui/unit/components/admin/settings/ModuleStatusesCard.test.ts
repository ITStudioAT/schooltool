import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import ModuleStatusesCard from '@/pages/admin/settings/components/ModuleStatusesCard.vue'

describe('ModuleStatusesCard', () => {
    it('uses vuetify switches and app-wide copy for module visibility', () => {
        const source = readFileSync('resources/js/pages/admin/settings/components/ModuleStatusesCard.vue', 'utf8')

        expect(source).toContain('<v-switch')
        expect(source).toContain('<v-select')
        expect(source).toContain('Aktive Version')
        expect(source).toContain('Version 3 – Entwicklung')
        expect(source).toContain('Angezeigt Admin')
        expect(source).toContain('Angezeigt Benutzer')
        expect(source).toContain('Benutzer Testmodus')
        expect(source).toContain('Benutzer Kommt bald')
        expect(source).toContain('App-weit gültig!')
        expect(source).toContain("density=\"compact\"")
        expect(source).toContain('module-status-card__toggle--admin')
        expect(source).toContain('module-status-card__toggle--user')
        expect(source).toContain('module-status-card__toggle--test')
        expect(source).toContain('module-status-card__toggle--soon')
        expect(source).toContain('linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(246, 250, 255, 0.92) 100%)')
        expect(source).toContain('background: rgba(255, 255, 255, 0.82);')
        expect(source).toContain('color: #10263a;')
        expect(source).not.toContain('>Speichern<')
        expect(source).toContain('module_rows')
        expect(source).not.toContain('const MODULE_ROWS = [')
    })

    it('builds visible rows from the stored module rows payload', () => {
        const methods = (ModuleStatusesCard as any).methods
        const context = {
            moduleRows: [],
        }

        methods.syncModuleRowsFromStore.call(context, {
            module_rows: [
                {
                    key: 'aba',
                    label: 'ABA',
                    meta: 'Auswerten von ABAs',
                    adminVisibleField: 'aba_visible_admin',
                    userVisibleField: 'aba_visible_user',
                    userTestModeField: 'aba_user_test_mode',
                    userComingSoonField: 'aba_user_comming_soon',
                },
            ],
        })

        expect(context.moduleRows).toEqual([
            {
                key: 'aba',
                label: 'ABA',
                meta: 'Auswerten von ABAs',
                adminVisibleField: 'aba_visible_admin',
                userVisibleField: 'aba_visible_user',
                userTestModeField: 'aba_user_test_mode',
                userComingSoonField: 'aba_user_comming_soon',
            },
        ])
    })

    it('keeps the user switches mutually exclusive', () => {
        const methods = (ModuleStatusesCard as any).methods
        const item = {
            adminVisibleField: 'register_visible_admin',
            userVisibleField: 'register_visible_user',
            userTestModeField: 'register_user_test_mode',
            userComingSoonField: 'register_user_comming_soon',
        }
        const context = {
            form: {
                register_visible_admin: true,
                register_visible_user: false,
                register_user_test_mode: false,
                register_user_comming_soon: false,
            },
            save: vi.fn(),
        }

        methods.setUserVisible.call(context, item, true)

        expect(context.form).toMatchObject({
            register_visible_user: true,
            register_user_test_mode: false,
            register_user_comming_soon: false,
        })

        methods.setUserTestMode.call(context, item, true)

        expect(context.form).toMatchObject({
            register_visible_user: false,
            register_user_test_mode: true,
            register_user_comming_soon: false,
        })

        methods.setUserComingSoon.call(context, item, true)

        expect(context.form).toMatchObject({
            register_visible_user: false,
            register_user_test_mode: false,
            register_user_comming_soon: true,
        })
    })

    it('saves immediately when a switch changes', () => {
        const methods = (ModuleStatusesCard as any).methods
        const save = vi.fn()
        const item = {
            adminVisibleField: 'register_visible_admin',
            userVisibleField: 'register_visible_user',
            userTestModeField: 'register_user_test_mode',
            userComingSoonField: 'register_user_comming_soon',
        }
        const context = {
            form: {
                register_visible_admin: false,
                register_visible_user: false,
                register_user_test_mode: false,
                register_user_comming_soon: false,
            },
            save,
        }

        methods.setAdminVisible.call(context, item, true)
        methods.setUserVisible.call(context, item, true)
        methods.setUserTestMode.call(context, item, true)
        methods.setUserComingSoon.call(context, item, true)

        expect(save).toHaveBeenCalledTimes(4)
    })

    it('saves a normalized timetable admin version immediately', () => {
        const methods = (ModuleStatusesCard as any).methods
        const save = vi.fn()
        const context = {
            form: {
                students_timetables_admin_version: 'v2',
            },
            save,
        }

        methods.setStudentsTimetablesAdminVersion.call(context, 'v3')

        expect(context.form.students_timetables_admin_version).toBe('v3')
        expect(save).toHaveBeenCalledOnce()

        methods.setStudentsTimetablesAdminVersion.call(context, undefined)

        expect(context.form.students_timetables_admin_version).toBe('v3')
        expect(save).toHaveBeenCalledTimes(2)
    })

    it('forces user visibility off when admin visibility is disabled', () => {
        const methods = (ModuleStatusesCard as any).methods
        const save = vi.fn()
        const item = {
            adminVisibleField: 'register_visible_admin',
            userVisibleField: 'register_visible_user',
            userTestModeField: 'register_user_test_mode',
            userComingSoonField: 'register_user_comming_soon',
        }
        const context = {
            form: {
                register_visible_admin: true,
                register_visible_user: true,
                register_user_test_mode: false,
                register_user_comming_soon: false,
            },
            save,
        }

        methods.setAdminVisible.call(context, item, false)
        methods.setUserVisible.call(context, item, true)

        expect(context.form).toMatchObject({
            register_visible_admin: false,
            register_visible_user: false,
        })
        expect(save).toHaveBeenCalledTimes(2)
    })
})
