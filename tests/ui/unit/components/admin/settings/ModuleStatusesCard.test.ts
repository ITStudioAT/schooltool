import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import ModuleStatusesCard from '@/pages/admin/settings/components/ModuleStatusesCard.vue'

describe('ModuleStatusesCard', () => {
    it('uses vuetify switches and app-wide copy for module visibility', () => {
        const source = readFileSync('resources/js/pages/admin/settings/components/ModuleStatusesCard.vue', 'utf8')

        expect(source).toContain('<v-switch')
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
        expect(source).not.toContain('>Speichern<')
    })

    it('keeps the user switches mutually exclusive', () => {
        const methods = (ModuleStatusesCard as any).methods
        const item = {
            userVisibleField: 'register_visible_user',
            userTestModeField: 'register_user_test_mode',
            userComingSoonField: 'register_user_comming_soon',
        }
        const context = {
            form: {
                register_visible_user: false,
                register_user_test_mode: false,
                register_user_comming_soon: false,
            },
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
