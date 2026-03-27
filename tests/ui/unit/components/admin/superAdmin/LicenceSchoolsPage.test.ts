import { describe, expect, it, vi } from 'vitest'
import LicenceSchools from '@/pages/admin/superAdmin/components/LicenceSchools.vue'
import { readFileSync } from 'node:fs'

describe('Super admin licence assignments', () => {
    it('sorts licences alphabetically by name', () => {
        const items = [
            { name: 'Z-Lizenz' },
            { name: 'A-Lizenz' },
            { name: 'M-Lizenz' },
        ]

        const sorted = (LicenceSchools as any).methods.sortedLicences.call({}, items)
        expect(sorted.map((item: { name: string }) => item.name)).toEqual(['A-Lizenz', 'M-Lizenz', 'Z-Lizenz'])
    })

    it('detects school licence as not needed from direct flag and model', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            toBool: methods.toBool,
        }

        const fromFlag = methods.isSchoolLicenceNotNeeded.call(ctx, { school_licence_required: false })
        const fromModel = methods.isSchoolLicenceNotNeeded.call(ctx, { licence_model: JSON.stringify({ school_licence_required: false }) })

        expect(fromFlag).toBe(true)
        expect(fromModel).toBe(true)
    })

    it('counts assigned admin and user licences from required roles', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            hasAdminLicenceRequiredRole(licence: unknown) {
                return methods.hasAdminLicenceRequiredRole.call(this, licence)
            },
            hasUserLicenceRequiredRole(licence: unknown) {
                return methods.hasUserLicenceRequiredRole.call(this, licence)
            },
            sortedLicences: methods.sortedLicences,
            toBool: methods.toBool,
        }

        const licences = [
            {
                name: 'Lehrertool',
                licence_model: {
                    affected_roles: ['teacher'],
                    user_licence_required_by_role: { teacher: true },
                },
            },
            {
                name: 'Adminportal',
                licence_model: {
                    affected_roles: ['admin'],
                    user_licence_required_by_role: { admin: true },
                },
            },
            {
                name: 'Ohne Zusatzlizenz',
                licence_model: {
                    affected_roles: ['teacher'],
                    user_licence_required_by_role: { teacher: false },
                },
            },
        ]

        expect(methods.countAssignedAdminLicences.call(ctx, licences)).toBe(1)
        expect(methods.countAssignedUserLicences.call(ctx, licences)).toBe(1)
    })

    it('shows only the assignment sections that are enabled by the licence model', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            isSchoolLicenceNotNeeded(licence: unknown) {
                return methods.isSchoolLicenceNotNeeded.call(this, licence)
            },
            sortedLicences: methods.sortedLicences,
            schoolAssignableLicences(licences: unknown) {
                return methods.schoolAssignableLicences.call(this, licences)
            },
            countAssignedAdminLicences(licences: unknown) {
                return methods.countAssignedAdminLicences.call(this, licences)
            },
            countAssignedUserLicences(licences: unknown) {
                return methods.countAssignedUserLicences.call(this, licences)
            },
            hasAdminLicenceRequiredRole(licence: unknown) {
                return methods.hasAdminLicenceRequiredRole.call(this, licence)
            },
            hasUserLicenceRequiredRole(licence: unknown) {
                return methods.hasUserLicenceRequiredRole.call(this, licence)
            },
            toBool: methods.toBool,
        }

        const licences = [
            {
                name: 'Schullizenz',
                licence_model: {
                    school_licence_enabled: true,
                    admin_licence_enabled: false,
                    user_licence_enabled: false,
                },
            },
            {
                name: 'Adminlizenz',
                licence_model: {
                    school_licence_enabled: false,
                    admin_licence_enabled: true,
                    user_licence_enabled: false,
                },
            },
            {
                name: 'Userlizenz',
                licence_model: {
                    school_licence_enabled: false,
                    admin_licence_enabled: false,
                    user_licence_enabled: true,
                },
            },
        ]

        expect(methods.schoolAssignableLicences.call(ctx, licences).map((item: { name: string }) => item.name)).toEqual(['Schullizenz'])
        expect(methods.hasSchoolLicenceAssignments.call(ctx, licences)).toBe(true)
        expect(methods.hasAdminLicenceAssignments.call(ctx, licences)).toBe(true)
        expect(methods.hasUserLicenceAssignments.call(ctx, licences)).toBe(true)
        expect(methods.hasSchoolLicenceAssignments.call(ctx, licences.slice(1))).toBe(false)
    })

    it('evaluates licence active status based on valid_until date', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            isSchoolLicenceNotNeeded: () => false,
            localDateKey: () => '2026-03-02',
        }

        const active = methods.isLicenceActive.call(ctx, { valid_until: '2026-03-03' })
        const expired = methods.isLicenceActive.call(ctx, { valid_until: '2026-03-01' })

        expect(active).toBe(true)
        expect(expired).toBe(false)
    })

    it('marks school licence model as open when a school licence is selected', () => {
        const isSchoolLicenceModelOpen = (LicenceSchools as any).computed.isSchoolLicenceModelOpen

        expect(isSchoolLicenceModelOpen.call({ selected_school_licence_id: null })).toBe(false)
        expect(isSchoolLicenceModelOpen.call({ selected_school_licence_id: 10 })).toBe(true)
    })

    it('marks user licences as open when a source licence is selected', () => {
        const isUserLicencesOpen = (LicenceSchools as any).computed.isUserLicencesOpen

        expect(isUserLicencesOpen.call({ selected_user_licences_school_licence_id: null })).toBe(false)
        expect(isUserLicencesOpen.call({ selected_user_licences_school_licence_id: 22 })).toBe(true)
    })

    it('closes selected user details dialog through computed v-model', () => {
        const dialogComputed = (LicenceSchools as any).computed.userLicenceUserDialogOpen
        const ctx = {
            selectedUserLicenceUser: { id: 22 },
            closeSelectedUserLicenceCard: vi.fn(),
        }

        expect(dialogComputed.get.call(ctx)).toBe(true)
        dialogComputed.set.call(ctx, false)
        expect(ctx.closeSelectedUserLicenceCard).toHaveBeenCalledTimes(1)
    })

    it('keeps overview interactions unlocked when only a school is selected', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            isInteractionLocked: false,
            selectedSchoolId: 7,
            action: 'school_licence_model',
            model_lock_action: 'school_licence_model',
        }

        methods.syncInteractionLockAction.call(ctx)
        expect(ctx.action).toBe('')

        ctx.isInteractionLocked = true
        methods.syncInteractionLockAction.call(ctx)
        expect(ctx.action).toBe('school_licence_model')
    })

    it('keeps school and side cards at equal xl width', () => {
        const mainColXl = (LicenceSchools as any).computed.mainColXl
        const schoolDetailXl = (LicenceSchools as any).computed.schoolDetailXl

        expect(mainColXl.call({})).toBe(11)
        expect(schoolDetailXl.call({})).toBe(6)
        expect(schoolDetailXl.call({ selectedSchoolLicence: { school_licence_id: 1 }, currentSchoolLicenceModel: { school_licence_required: true } })).toBe(6)
    })

    it('shows assigned licences in the school list as stacked rows', () => {
        const source = readFileSync('resources/js/pages/admin/superAdmin/components/LicenceSchools.vue', 'utf8')

        expect(source).toContain('class="assignment-schools-list"')
        expect(source).not.toContain('v-model:selected="selected_schools"')
        expect(source).not.toContain('@update:selected="onSelectedSchoolsUpdate"')
        expect(source).toContain('v-for="licence in sortedLicences(item.licences)"')
        expect(source).toContain('class="licence-card__roles-list assignment-school-licence-list"')
        expect(source).toContain('class="assignment-school-licence-row"')
        expect(source).toContain('class="assignment-school-licence-row__name"')
        expect(source).toContain("`licence-assignment-${item.id}-${licence.id}`")
        expect(source).not.toContain('Optionen')
        expect(source).not.toContain('assignment-school-inline-manager')
        expect(source).not.toContain('Keine E-Mail hinterlegt')
        expect(source).not.toContain('licence-card__subtitle')
        expect(source).not.toContain('class="assignment-school-licence-type-list"')
        expect(source).not.toContain('class="assignment-school-licence-type-pill"')
        expect(source).not.toContain('isSchoolLicenceRequired(licence)')
        expect(source).not.toContain('licence-card__status-icons')
        expect(source).not.toContain('Zugewiesene Schul-Lizenzen')
        expect(source).not.toContain('Zugewiesene Admin-Lizenzen')
        expect(source).not.toContain('Zugewiesene User-Lizenzen')
        expect(source).not.toContain('class="assignment-school-licence-counter-list"')
        expect(source).not.toContain('class="assignment-school-licence-counter-row"')
        expect(source).not.toContain('Gültig bis:')
        expect(source).not.toContain('<span class="licence-card__badge-label">Lizenzen</span>')
        expect(source).not.toContain('<v-col cols="12" md="8" :xl="schoolDetailXl" v-if="selectedSchool">')
    })

    it('toggles licence role assignment directly from licence-model roles', () => {
        const methods = (LicenceSchools as any).methods
        const roleEntry = {
            name: 'teacher',
            assigned: false,
            user_licence_required: true,
            valid_until: null,
            is_activated: false,
            plan_id: null,
            plans: [],
        }
        const ctx = {
            selectedUserLicenceRoleEntries: [roleEntry],
            defaultValidUntil: () => '2026-12-31',
            getLowestCostPlanId: methods.getLowestCostPlanId,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        }

        methods.toggleSelectedUserRole.call(ctx, 'teacher')
        expect(roleEntry.assigned).toBe(true)
        expect(roleEntry.valid_until).toBe('2026-12-31')

        methods.toggleSelectedUserRole.call(ctx, 'teacher')
        expect(roleEntry.assigned).toBe(false)
        expect(roleEntry.valid_until).toBe(null)
        expect(roleEntry.is_activated).toBe(false)
        expect(roleEntry.plan_id).toBe(null)
    })

    it('selects lowest-cost plan by default when enabling a role', () => {
        const methods = (LicenceSchools as any).methods
        const roleEntry = {
            name: 'teacher',
            assigned: false,
            user_licence_required: true,
            valid_until: null,
            is_activated: false,
            plan_id: null,
            plans: [
                { id: 4, price_per_year: '120,00' },
                { id: 2, price_per_year: '0,-' },
                { id: 3, price_per_year: '49.90' },
            ],
        }
        const ctx = {
            selectedUserLicenceRoleEntries: [roleEntry],
            defaultValidUntil: () => '2026-12-31',
            getLowestCostPlanId: methods.getLowestCostPlanId,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        }

        methods.toggleSelectedUserRole.call(ctx, 'teacher')

        expect(roleEntry.assigned).toBe(true)
        expect(roleEntry.plan_id).toBe(2)
    })

    it('treats roles with disabled user licence requirement as active without activation', () => {
        const methods = (LicenceSchools as any).methods
        const roleEntry = {
            name: 'teacher',
            assigned: true,
            user_licence_required: false,
            is_activated: false,
            valid_until: null,
        }
        const ctx = {
            isSchoolLicenceNotNeeded: () => false,
            isLicenceActive: () => true,
            isDateValueActive: () => true,
        }

        expect(methods.isSelectedUserRoleAssignmentActive.call(ctx, roleEntry)).toBe(true)
        expect(methods.selectedUserRoleStatusLabel.call(ctx, roleEntry)).toBe('Keine Aktivierung nötig (Userlizenz = NEIN)')
    })

    it('saves newly selected role values even if role details reload before save', async () => {
        const methods = (LicenceSchools as any).methods
        const saveSchoolLicenceUserRoles = vi.fn().mockResolvedValue(true)
        const ctx: any = {
            selected_user_licences_school_licence_id: 12,
            selectedUserLicenceUserId: 55,
            selectedUserLicenceRoleEntries: [
                {
                    name: 'teacher',
                    assigned: true,
                    valid_until: '2026-12-31',
                    is_activated: true,
                    plan_id: 3,
                },
            ],
            normalizeRoleValidUntilForApi: methods.normalizeRoleValidUntilForApi,
            buildSelectedUserRolePayload: methods.buildSelectedUserRolePayload,
            syncSelectedUserLicenceRolesToUserRoles: vi.fn().mockImplementation(async () => {
                ctx.selectedUserLicenceRoleEntries = [
                    {
                        name: 'teacher',
                        assigned: false,
                        valid_until: null,
                        is_activated: false,
                        plan_id: null,
                    },
                ]
                return true
            }),
            schoolStore: {
                saveSchoolLicenceUserRoles,
            },
            normalizeSelectedUserRoleDetails: vi.fn(),
            adminStore: null,
            school_licence_users_meta: { current_page: 1 },
            loadSchoolLicenceUsers: vi.fn().mockResolvedValue(undefined),
            closeSelectedUserLicenceCard: vi.fn(),
        }

        await methods.saveSelectedUserLicenceRoles.call(ctx)

        expect(saveSchoolLicenceUserRoles).toHaveBeenCalledTimes(1)
        const payload = saveSchoolLicenceUserRoles.mock.calls[0][2]
        expect(payload).toHaveLength(1)
        expect(payload[0]).toMatchObject({
            name: 'teacher',
            assigned: true,
            valid_until: '2026-12-31',
            is_activated: true,
            plan_id: 3,
        })
    })
})
