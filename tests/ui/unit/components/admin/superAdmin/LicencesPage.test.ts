import { describe, expect, it } from 'vitest'
import Licences from '@/pages/admin/superAdmin/components/Licences.vue'
import { readFileSync } from 'node:fs'

describe('Super admin licences overview', () => {
    it('opens create/edit licence dialog based on action', () => {
        expect((Licences as any).computed.licenceDialogOpen.get.call({ action: 'create_licence' })).toBe(true)
        expect((Licences as any).computed.licenceDialogOpen.get.call({ action: 'edit_licence' })).toBe(true)
        expect((Licences as any).computed.licenceDialogOpen.get.call({ action: '' })).toBe(false)

        const ctx = { action: 'create_licence' }
        ;(Licences as any).computed.licenceDialogOpen.set.call(ctx, false)
        expect(ctx.action).toBe('')
    })

    it('computes licence dialog title from action', () => {
        expect((Licences as any).computed.licenceDialogTitle.call({ action: 'create_licence' })).toBe('Neue Lizenz')
        expect((Licences as any).computed.licenceDialogTitle.call({ action: 'edit_licence' })).toBe('Lizenz ändern')
        expect((Licences as any).computed.licenceDialogTitle.call({ action: '' })).toBe('Lizenz')
    })

    it('opens licence model dialog based on action', () => {
        expect((Licences as any).computed.licenceModelDialogOpen.get.call({ action: 'licence_model' })).toBe(true)
        expect((Licences as any).computed.licenceModelDialogOpen.get.call({ action: '' })).toBe(false)

        const ctx = { action: 'licence_model' }
        ;(Licences as any).computed.licenceModelDialogOpen.set.call(ctx, false)
        expect(ctx.action).toBe('')
        expect((Licences as any).computed.licenceModelDialogMaxWidth.call({})).toBe(1100)
    })

    it('opens delete dialog based on action', () => {
        expect((Licences as any).computed.licenceDeleteDialogOpen.get.call({ action: 'delete_licence' })).toBe(true)
        expect((Licences as any).computed.licenceDeleteDialogOpen.get.call({ action: '' })).toBe(false)

        const ctx = { action: 'delete_licence' }
        ;(Licences as any).computed.licenceDeleteDialogOpen.set.call(ctx, false)
        expect(ctx.action).toBe('')
    })

    it('computes total count from meta with fallback to local list length', () => {
        const withMeta = {
            meta: { total: 12 },
            licences: [{ id: 1 }, { id: 2 }],
        }
        const withoutMeta = {
            meta: {},
            licences: [{ id: 1 }, { id: 2 }, { id: 3 }],
        }

        expect((Licences as any).computed.totalLicencesCount.call(withMeta)).toBe(12)
        expect((Licences as any).computed.totalLicencesCount.call(withoutMeta)).toBe(3)
    })

    it('builds safe pagination meta defaults', () => {
        const ctx = {
            meta: {
                from: 2,
                to: 5,
                total: 9,
                current_page: 2,
                last_page: 4,
            },
            licences: [{ id: 1 }],
        }

        expect((Licences as any).computed.safeMeta.call(ctx)).toEqual({
            from: 2,
            to: 5,
            total: 9,
            current_page: 2,
            last_page: 4,
        })

        expect(
            (Licences as any).computed.safeMeta.call({
                meta: {},
                licences: [{ id: 10 }, { id: 20 }],
            })
        ).toEqual({
            from: 0,
            to: 0,
            total: 2,
            current_page: 1,
            last_page: 1,
        })
    })

    it('supports single-selection helper actions', () => {
        const ctx = {
            licences: [{ id: 11 }, { id: 12 }, { id: 13 }],
            selected_licences: [],
        }

        ;(Licences as any).methods.selectFirstLicence.call(ctx)
        expect(ctx.selected_licences).toEqual([11])
        expect((Licences as any).methods.isSelectedLicence.call(ctx, 11)).toBe(true)

        ;(Licences as any).methods.unselectAll.call(ctx)
        expect(ctx.selected_licences).toEqual([])
        expect((Licences as any).methods.isSelectedLicence.call(ctx, 12)).toBe(false)
    })

    it('keeps only the last selected licence when multiple values are emitted', () => {
        const ctx = {
            selected_licences: [],
        }

        ;(Licences as any).methods.onSelectedLicencesUpdate.call(ctx, [11, 12, 13])
        expect(ctx.selected_licences).toEqual([13])

        ;(Licences as any).methods.onSelectedLicencesUpdate.call(ctx, [12])
        expect(ctx.selected_licences).toEqual([12])

        ;(Licences as any).methods.onSelectedLicencesUpdate.call(ctx, null)
        expect(ctx.selected_licences).toEqual([])
    })

    it('normalizes the structured licence editor model with role arrays and prices', () => {
        const methods = (Licences as any).methods
        const ctx = {
            ...methods,
        }

        const normalized = methods.normalizeLicenceModel.call(ctx, {
            school_licence_enabled: true,
            school_price_per_year: '199',
            admin_licence_enabled: true,
            admin_price_per_year: '59',
            admin_role_names: ['admin', 'register_admin', 'admin'],
            user_licence_enabled: true,
            user_price_per_year: '29',
            user_role_names: ['teacher'],
        })

        expect(normalized).toEqual({
            school_licence_enabled: true,
            school_price_per_year: '199',
            admin_licence_enabled: true,
            admin_price_per_year: '59',
            admin_role_names: ['admin', 'register_admin'],
            user_licence_enabled: true,
            user_price_per_year: '29',
            user_role_names: ['teacher'],
        })
    })

    it('converts boolean-like values while normalizing the licence model', () => {
        const methods = (Licences as any).methods
        const ctx = {
            ...methods,
        }

        expect(methods.toBool.call(ctx, 'ja', false)).toBe(true)
        expect(methods.toBool.call(ctx, 'nein', true)).toBe(false)
        expect(methods.toBool.call(ctx, 1, false)).toBe(true)

        const normalized = methods.normalizeLicenceModel.call(ctx, {
            school_licence_enabled: 'nein',
            admin_licence_enabled: 'ja',
            user_licence_enabled: 0,
            admin_role_names: ['admin'],
        })

        expect(normalized.school_licence_enabled).toBe(false)
        expect(normalized.admin_licence_enabled).toBe(true)
        expect(normalized.user_licence_enabled).toBe(false)
    })

    it('validates cost fields as positive integers or empty', () => {
        const methods = (Licences as any).methods
        const rule = methods.positiveIntegerOrNull.call({})

        expect(rule('')).toBe(true)
        expect(rule('199')).toBe(true)
        expect(rule('0')).toBe('Es muss sich um eine positive ganze Zahl handeln oder leer sein.')
        expect(rule('29.5')).toBe('Es muss sich um eine positive ganze Zahl handeln oder leer sein.')
        expect(rule('-3')).toBe('Es muss sich um eine positive ganze Zahl handeln oder leer sein.')
    })

    it('validates start and end day-month values in TT.MM. format', () => {
        const methods = (Licences as any).methods
        const ctx = {
            ...methods,
        }
        const validDayMonth = methods.validDayMonth.call(ctx)
        const endAfterStart = methods.endDayMonthAfterStart.call(ctx, () => '01.09.')

        expect(validDayMonth('01.09.')).toBe(true)
        expect(validDayMonth('31.02.')).toBe('Das Datum muss im Format TT.MM. angegeben werden.')
        expect(methods.normalizeDayMonthDisplayValue.call(ctx, '1.9.')).toBe('1.9.')
        expect(methods.normalizeDayMonthDisplayValue.call(ctx, '01.09')).toBe('01.09.')
        expect(endAfterStart('31.07.')).toBe(true)
        expect(endAfterStart('01.09.')).toBe('Das End-Datum muss nach dem Start-Datum liegen.')
    })

    it('stores wildcard user role selection with the Alle action', () => {
        const methods = (Licences as any).methods
        const ctx = {
            ...methods,
            currentLicenceModel: {
                user_role_names: ['teacher'],
            },
            availableRoles: [
                { id: 1, name: 'teacher' },
                { id: 2, name: 'student' },
                { id: 3, name: 'teacher' },
                { id: 4, name: ' ' },
            ],
        }

        methods.selectAllUserRoles.call(ctx)

        expect(ctx.currentLicenceModel.user_role_names).toEqual(['*'])
        expect(methods.usesAllUserRoles.call(ctx)).toBe(true)
        expect(methods.displayRoleName.call(ctx, '*')).toBe('Alle')
        expect(methods.formatSelectedRoleNames.call(ctx, ['*'])).toBe('Alle')
        expect(methods.isUserRoleSelected.call(ctx, 'teacher')).toBe(true)
        expect(methods.isUserRoleSelected.call(ctx, 'student')).toBe(true)
    })

    it('formats overview prices for the licence cards', () => {
        const methods = (Licences as any).methods

        expect(methods.overviewPriceLabel.call({}, '199')).toBe('EUR 199 / Jahr')
        expect(methods.overviewPriceLabel.call({}, '59', 'month')).toBe('EUR 59 / Monat')
        expect(methods.overviewPriceLabel.call({}, '')).toBe('')
        expect(methods.overviewPriceLabel.call({}, null)).toBe('')
    })

    it('keeps admin and user overview prices independent from the school licence state', () => {
        const source = readFileSync('resources/js/pages/admin/superAdmin/components/Licences.vue', 'utf8')

        expect(source).toContain('v-if="licenceModelFor(item).school_licence_enabled" class="licence-card__badge-meta"')
        expect(source).toContain('v-if="licenceModelFor(item).admin_licence_enabled" class="licence-card__badge-meta"')
        expect(source).toContain('v-if="licenceModelFor(item).user_licence_enabled" class="licence-card__badge-meta"')
        expect(source).toContain("overviewPriceLabel(licenceModelFor(item).admin_price_per_year, 'month')")
        expect(source).toContain("overviewPriceLabel(licenceModelFor(item).user_price_per_year, 'month')")
    })

    it('keeps admin and user role selection visible independent of the JA/NEIN toggle', () => {
        const source = readFileSync('resources/js/pages/admin/superAdmin/components/Licences.vue', 'utf8')
        const roleSectionAfterToggleBlocks = source.match(/<\/template>\r?\n\r?\n\s+<div class="text-subtitle-2 mt-4 mb-3">Zuordnung von Rollen<\/div>/g) ?? []

        expect(roleSectionAfterToggleBlocks).toHaveLength(2)
        expect(source).toContain('@click="toggleAdminRole(role.name)"')
        expect(source).toContain('@click="toggleUserRole(role.name)"')
        expect(source).toContain('v-if="licenceModelFor(item).school_licence_enabled" class="licence-card__badge-meta"')
        expect(source).toContain('v-if="licenceModelFor(item).admin_licence_enabled" class="licence-card__badge-meta"')
        expect(source).toContain('v-if="licenceModelFor(item).user_licence_enabled" class="licence-card__badge-meta"')
        expect(source).toContain('<span class="licence-card__badge-label">User-Lizenz</span>')
        expect(source).toContain("overviewPriceLabel(licenceModelFor(item).school_price_per_year, 'year')")
        expect(source).toContain("overviewPriceLabel(licenceModelFor(item).admin_price_per_year, 'month')")
        expect(source).toContain("overviewPriceLabel(licenceModelFor(item).user_price_per_year, 'month')")
        expect(source).toContain('label="Kosten pro Monat"')
        expect(source).toContain('label="Start-Datum"')
        expect(source).toContain('label="End-Datum"')
        expect(source).toContain("placeholder=\"TT.MM.\"")
        expect(source).toContain('@click="selectAllUserRoles"')
        expect(source).toContain(":variant=\"usesAllUserRoles() ? 'flat' : 'tonal'\"")
        expect(source).toContain('Alle')
        expect(source).toContain('@update:selected="onSelectedLicencesUpdate"')
        expect(source).toContain('@click="selectFirstLicence"')
    })
})
