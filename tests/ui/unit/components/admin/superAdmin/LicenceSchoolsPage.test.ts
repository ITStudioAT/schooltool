import { describe, expect, it, vi } from 'vitest'
import LicenceSchools from '@/pages/admin/superAdmin/components/LicenceSchools.vue'

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

    it('shows overview card only when no school is selected', () => {
        const showOverviewCard = (LicenceSchools as any).computed.showOverviewCard

        expect(showOverviewCard.call({ selectedSchool: null })).toBe(true)
        expect(showOverviewCard.call({ selectedSchool: { id: 1 } })).toBe(false)
    })

    it('locks action when a school is selected and unlocks when cleared', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            isInteractionLocked: false,
            selectedSchoolId: 7,
            action: '',
            model_lock_action: 'school_licence_model',
            selection_lock_action: 'school_licence_selection',
        }

        methods.syncInteractionLockAction.call(ctx)
        expect(ctx.action).toBe('school_licence_selection')

        ctx.selectedSchoolId = null
        methods.syncInteractionLockAction.call(ctx)
        expect(ctx.action).toBe('')
    })

    it('keeps school and side cards at equal xl width', () => {
        const schoolDetailXl = (LicenceSchools as any).computed.schoolDetailXl

        expect(schoolDetailXl.call({})).toBe(6)
        expect(schoolDetailXl.call({ selectedSchoolLicence: { school_licence_id: 1 }, currentSchoolLicenceModel: { school_licence_required: true } })).toBe(6)
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
