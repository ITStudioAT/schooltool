import { describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import LicenceSchools from '@/pages/admin/superAdmin/components/LicenceSchools.vue'
import { readFileSync } from 'node:fs'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        put: vi.fn(),
    },
}))

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
        const fromModel = methods.isSchoolLicenceNotNeeded.call(ctx, JSON.stringify({ school_licence_required: false }))

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

    it('allows inline removal only for licences without school licence and without assigned admin or user licences', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            canDeleteLicenceItem(licence: unknown) {
                return methods.canDeleteLicenceItem.call(this, licence)
            },
            isSchoolLicenceNotNeeded(licence: unknown) {
                return methods.isSchoolLicenceNotNeeded.call(this, licence)
            },
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            toBool: methods.toBool,
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
        }

        const removable = methods.canDeleteSchoolLicenceFromList.call(ctx, {
            school_licence_enabled: false,
            admin_licence_count: 0,
            user_licence_count: 0,
            licence_model: {
                school_licence_enabled: false,
            },
        })
        const blockedBySchoolLicence = methods.canDeleteSchoolLicenceFromList.call(ctx, {
            school_licence_enabled: true,
            admin_licence_count: 0,
            user_licence_count: 0,
            valid_until: '2026-07-31',
        })
        const blockedByAssignments = methods.canDeleteSchoolLicenceFromList.call(ctx, {
            school_licence_enabled: false,
            admin_licence_count: 1,
            user_licence_count: 0,
            licence_model: {
                school_licence_enabled: false,
            },
        })

        expect(removable).toBe(true)
        expect(blockedBySchoolLicence).toBe(false)
        expect(blockedByAssignments).toBe(false)
    })

    it('normalizes structured admin and user role names from school licence items', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            toBool: methods.toBool,
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
        }

        const model = methods.normalizeLicenceModel.call(ctx, {
            admin_licence_enabled: true,
            admin_role_names: ['admin', 'register_admin'],
            user_licence_enabled: true,
            user_role_names: ['teacher'],
            licence_model: {},
        })

        expect(model.admin_role_names).toEqual(['admin', 'register_admin'])
        expect(model.user_role_names).toEqual(['teacher'])
        expect(model.affected_roles).toEqual(['admin', 'register_admin', 'teacher'])
        expect(model.admin_licence_enabled).toBe(true)
        expect(model.user_licence_enabled).toBe(true)
    })

    it('derives admin filters from structured admin licence roles on school items', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            sortedRoleNames: methods.sortedRoleNames,
            toBool: methods.toBool,
        }

        const roles = methods.adminRolesFromLicenceModel.call(ctx, {
            admin_licence_enabled: true,
            admin_role_names: ['admin', 'register_admin'],
            licence_model: {},
        })

        expect(roles).toEqual(['admin', 'register_admin'])
    })

    it('derives admin filters from configured non-admin role names on school items', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            sortedRoleNames: methods.sortedRoleNames,
            toBool: methods.toBool,
        }

        const roles = methods.adminRolesFromLicenceModel.call(ctx, {
            admin_licence_enabled: true,
            admin_role_names: ['teacher'],
            licence_model: {},
        })

        expect(roles).toEqual(['teacher'])
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
        expect(source).not.toContain('@click="toggleExpiredSchools"')
        expect(source).not.toContain('>Abgelaufen</v-btn>')
        expect(source).toContain('@click.stop="openEditDateDialog(item)"')
        expect(source).toContain('v-for="licence in sortedLicences(item.licences)"')
        expect(source).toContain('class="licence-card__roles-list assignment-school-licence-list"')
        expect(source).toContain('class="assignment-school-licence-row"')
        expect(source).toContain('class="assignment-school-licence-row__name"')
        expect(source).toContain('.assignment-schools-item :deep(.licence-card__title)')
        expect(source).toContain('color: rgb(var(--v-theme-primary));')
        expect(source).toContain('class="assignment-school-licence-row__action"')
        expect(source).toContain("`licence-assignment-${item.id}-${licence.id}`")
        expect(source).toContain("openEditLicenceDialog(item, licence, 'school')")
        expect(source).toContain("openEditLicenceDialog(item, licence, 'admin')")
        expect(source).toContain("openEditLicenceDialog(item, licence, 'user')")
        expect(source).toContain('Schullizenz')
        expect(source).toContain('Admin-Lizenzen')
        expect(source).toContain('Benutzer-Lizenzen')
        expect(source).toContain('admin_licence_active_count')
        expect(source).toContain('admin_licence_expired_count')
        expect(source).toContain(`:color="(licence.admin_licence_expired_count || 0) === 0 ? 'success' : undefined"`)
        expect(source).toContain('(gültig:')
        expect(source).toContain('abgelaufen:')
        expect(source).toContain('assignment-school-licence-row__counter-expired')
        expect(source).toContain(`:class="{ 'is-highlighted': (licence.admin_licence_expired_count || 0) > 0 }"`)
        expect(source).toContain('.assignment-school-licence-row__counter-expired.is-highlighted')
        expect(source).toContain('color: rgb(var(--v-theme-error));')
        expect(source).toContain('Gesamtkosten:')
        expect(source).toContain('Gesamtspeicher:')
        expect(source).toContain('formatPrice(userLicenceListPriceTotal(item))')
        expect(source).toContain('formatStorage(userLicenceListStorageTotalGb(item))')
        expect(source).toContain('userLicenceListPriceTotal(user, licenceSource = null)')
        expect(source).toContain('userLicenceListStorageTotalGb(user, licenceSource = null)')
        expect(source).toContain('userLicenceSummaryEntries(user, licenceSource = null)')
        expect(source).toContain('userLicencePlanForRole(roleName, roleStatus, licenceSource = null)')
        expect(source).toContain('async refreshEditLicenceItem()')
        expect(source).toContain('if (this.adminStore?.loadConfig) {')
        expect(source).toContain('visibleAdminUsers()')
        expect(source).toContain('v-if="visibleAdminUsers.length && !edit_admin_add_mode"')
        expect(source).toContain('v-for="user in visibleAdminUsers"')
        expect(source).toContain('v-if="!edit_admin_user_edit_id && !edit_admin_add_mode" size="small" variant="tonal" color="success" icon="mdi-plus" @click="openAdminAddMode"')
        expect(source).toContain('editLicenceCloseLocked()')
        expect(source).toContain('editLicenceTabLocked()')
        expect(source).toContain(`:disabled="isEditLicenceTabDisabled('school')"`)
        expect(source).toContain(`:disabled="isEditLicenceTabDisabled('admin')"`)
        expect(source).toContain(`:disabled="isEditLicenceTabDisabled('user')"`)
        expect(source).toContain('isEditLicenceTabDisabled(tab)')
        expect(source).toContain('availableEditDateLicences')
        expect(source).toContain('v-model="edit_date_licence_id"')
        expect(source).toContain("edit_school_licence ? 'Lizenzdatum ändern' : 'Lizenz hinzufügen'")
        expect(source).toContain('<template v-if="edit_school_licence">')
        expect(source).toContain('v-checkbox v-model="edit_no_date" label="Kein Ablaufdatum"')
        expect(source).toContain('v-date-input v-model="edit_valid_until" label="Gültig bis" :disabled="edit_no_date"')
        expect(source).toContain('v-if="!editLicenceCloseLocked" icon="mdi-close" variant="text" rounded="lg" @click="closeEditLicenceDialog"')
        expect(source).toContain('<v-card-actions class="d-flex pa-4 justify-space-between">')
        expect(source).toContain('<v-btn v-if="!(edit_licence_tab === \'admin\' && (edit_admin_user_edit_id || edit_admin_add_mode)) && !(edit_licence_tab === \'user\' && (edit_user_licence_user_edit_id || edit_user_licence_add_mode))" color="warning" variant="text" rounded="lg" @click="closeEditLicenceDialog">Schließen</v-btn>')
        expect(source).not.toContain('Benutzerlizenz schließen')
        expect(source).toContain("admin-user-row__editing-hint")
        expect(source).toContain('<span v-if="edit_admin_user_edit_id === user.id" class="admin-user-row__editing-hint">Benutzerlizenz wird bearbeitet</span>')
        expect(source).toContain('admin-user-row__title')
        expect(source).toContain('admin-user-row__meta')
        expect(source).toContain('adminUserHasValidLicence(user)')
        expect(source).toContain('adminUserLicenceRuntimeLabel(user)')
        expect(source).toContain('<span class="admin-user-row__meta" v-if="adminUserBillingTotalForUser(user) !== null || adminUserStorageTotalGbForUser(user) !== null">')
        expect(source).toContain('Gesamt / Jahr: {{ formatPrice(adminUserBillingTotalForUser(user)) }}')
        expect(source).toContain('&nbsp;Summe Speicher: {{ formatStorage(adminUserStorageTotalGbForUser(user)) }}')
        expect(source).toContain('adminUserBillingTotalForUser(user)')
        expect(source).toContain('adminUserStorageTotalGbForUser(user)')
        expect(source).toContain('adminTeillizenzPreisLabel')
        expect(source).toContain('title="bis gestern"')
        expect(source).toContain('setValidUntilYesterday')
        expect(source).toContain('setAdminUserValidUntilYesterday(user)')
        expect(source).toContain('setUserLicenceUserValidUntilYesterday(user)')
        expect(source).toContain('overridePriceLabel(edit_admin_user_charged_price, effectiveAdminBasePrice(), adminBillingDefaultLabel())')
        expect(source).toContain("{{ overridePriceLabel(roleEntry.charged_price, selectedUserRolePlanPrice(roleEntry), 'Planpreis') }}")
        expect(source).toContain('selectedUserLicenceHasExtraStorage()')
        expect(source).toContain('selectedUserRoleExtraStorageTotal(roleEntry)')
        expect(source).toContain('selectedUserRoleStorageTotalGb(roleEntry)')
        expect(source).toContain('selectedUserRoleBillingTotal(roleEntry)')
        expect(source).toContain('v-model="roleEntry.charged_price"')
        expect(source).toContain('label="Verrechneter Preis / Jahr (€)"')
        expect(source).toContain('v-model="roleEntry.extra_storage_units"')
        expect(source).toContain('v-model="roleEntry.extra_storage_unit_price"')
        expect(source).toContain('v-model="edit_admin_user_extra_storage_units"')
        expect(source).toContain('label="Zusatz-Speicher Einheiten"')
        expect(source).toContain('v-model="edit_admin_user_charged_price_draft"')
        expect(source).toContain('v-model="edit_admin_user_extra_storage_unit_price"')
        expect(source).toContain('<div v-if="!edit_admin_user_price_editing" class="d-flex justify-end mb-2">')
        expect(source).toContain('<v-btn color="warning" variant="text" size="small" rounded="lg" prepend-icon="mdi-arrow-left" @click="closeAdminUserEdit">Zurück</v-btn>')
        expect(source).toContain('<span class="edit-licence-price-label">Preis je Einheit</span>')
        expect(source).toContain('<span class="edit-licence-price-label">Zusatz-Speicher gesamt</span>')
        expect(source).toContain('formatPrice(adminUserExtraStorageTotal())')
        expect(source).toContain('<span class="edit-licence-price-label">Summe Speicher</span>')
        expect(source).toContain('formatStorage(adminUserStorageTotalGb())')
        expect(source).toContain('<span class="edit-licence-price-label">Gesamt / Jahr</span>')
        expect(source).toContain('formatPrice(adminUserBillingTotal())')
        expect(source).toContain('adminUserExtraStorageTotal()')
        expect(source).toContain('adminUserStorageTotalGb()')
        expect(source).toContain('label="Preis je Einheit (€)"')
        expect(source).toContain(":placeholder=\"effectiveAdminExtraStorageUnitPrice() != null ? editablePriceInputValue(effectiveAdminExtraStorageUnitPrice()) : ''\"")
        expect(source).toContain(":placeholder=\"edit_licence_item ? editablePriceInputValue(edit_licence_item.school_price_per_year) : ''\"")
        expect(source).toContain(":placeholder=\"edit_licence_item ? editablePriceInputValue(edit_licence_item.school_extra_storage_step_price) : ''\"")
        expect(source).toContain('editablePriceInputValue(value)')
        expect(source).toContain('normalizedPriceInputValue(value)')
        expect(source).toContain("overrideCountLabel(edit_admin_user_extra_storage_units, edit_licence_item?.admin_extra_storage_units, 'Tarif dieser Schule')")
        expect(source).toContain("status.assigned === true")
        expect(source).toContain('assigned_only: 0')
        expect(source).toContain('this.edit_admin_add_results = this.edit_admin_add_results.filter((candidate) => Number(candidate?.id) !== Number(user?.id))')
        expect(source).toContain('<span class="edit-licence-price-label">Gesamtkosten</span>')
        expect(source).toContain('<span class="edit-licence-price-value">{{ formatPrice(billingTotal) }}</span>')
        expect(source).toContain('<span class="edit-licence-price-label">Gesamtspeicher</span>')
        expect(source).toContain('<span class="edit-licence-price-value">{{ formatStorage(schoolStorageTotalGb) }}</span>')
        expect(source).toContain("overridePriceLabel(edit_school_extra_storage_unit_price, edit_licence_item?.school_extra_storage_step_price, 'Basis-Tarif')")
        expect(source).toContain('schoolStorageTotalGb()')
        expect(source).toContain('Abrechnung dieser Schule')
        expect(source).not.toContain('saveAdminLicenceSchool')
        expect(source).not.toContain('cancelAdminBillingEdit')
        expect(source).toContain('title="Lizenz entfernen"')
        expect(source).toContain('title="Schullizenz entfernen"')
        expect(source).toContain('v-if="canDeleteEditLicenceItem()"')
        expect(source).toContain('v-if="canDeleteSchoolLicenceFromList(licence)"')
        expect(source).toContain('canDeleteEditLicenceItem()')
        expect(source).toContain('canDeleteLicenceItem(licence = this.edit_licence_item)')
        expect(source).toContain('canDeleteSchoolLicenceFromList(licence)')
        expect(source).toContain('return this.isSchoolLicenceNotNeeded(licence) && this.canDeleteLicenceItem(licence)')
        expect(source).toContain('return adminLicences + userLicences === 0')
        expect(source).toContain('prepend-icon="mdi-link-off"')
        expect(source).toContain('@click="deleteLicenceFromDialog"')
        expect(source).toContain('@click.stop="deleteLicenceFromList(item, licence)"')
        expect(source).toContain('Entfernen')
        expect(source).toContain('applyAdminUserTeillizenz(user)')
        expect(source).toContain('removeAdminUserLicence(user)')
        expect(source).toContain(":class=\"{ 'is-editing': edit_admin_user_edit_id === user.id }\"")
        expect(source).toContain(":style=\"{ visibility: edit_admin_user_edit_id ? 'hidden' : 'visible' }\"")
        expect(source).toContain('icon="mdi-check-circle"')
        expect(source).not.toContain(`v-else
                                            size="x-small"
                                            variant="tonal"
                                            color="success"
                                            icon="mdi-plus"
                                            @click="assignAdminUser(user)" />`)
        expect(source).not.toContain('v-model="edit_admin_search"')
        expect(source).not.toContain('placeholder="Suchen..."')
        expect(source).not.toContain('@click:clear="onAdminSearchClear"')
        expect(source).not.toContain('Optionen')
        expect(source).not.toContain('assignment-school-inline-manager')
        expect(source).not.toContain('Keine E-Mail hinterlegt')
        expect(source).not.toContain('licence-card__subtitle')
        expect(source).not.toContain('class="assignment-school-licence-type-list"')
        expect(source).not.toContain('class="assignment-school-licence-type-pill"')
        expect(source).not.toContain('isSchoolLicenceRequired(licence)')
        expect(source).not.toContain('licence-card__status-icons')
        expect(source).not.toContain('assignment-school-licence-row__icons')
        expect(source).not.toContain('licence-type-icon')
        expect(source).not.toContain('@click.stop="openEditLicenceDialog(item, licence)"')
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

    it('updates admin user validity for configured admin-role names', async () => {
        const methods = (LicenceSchools as any).methods
        const saveAdminUserRoles = vi.fn().mockResolvedValue(true)
        const loadAdminUsers = vi.fn().mockResolvedValue(undefined)
        const refreshEditLicenceItem = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            edit_licence_item: {
                admin_licence_enabled: true,
                admin_role_names: ['teacher'],
                licence_model: {},
            },
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            adminRolesFromLicenceModel(licence: unknown) {
                return methods.adminRolesFromLicenceModel.call(this, licence)
            },
            adminUserStatusForUser: vi.fn().mockReturnValue({ valid_until: '2027-01-31' }),
            saveAdminUserRoles,
            loadAdminUsers,
            refreshEditLicenceItem,
            sortedRoleNames: methods.sortedRoleNames,
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            toBool: methods.toBool,
        }

        await methods.setAdminUserValidUntil.call(ctx, { id: 44 }, '2026-12-31')

        expect(saveAdminUserRoles).toHaveBeenCalledTimes(1)
        const patchFn = saveAdminUserRoles.mock.calls[0][1]
        expect(patchFn({ name: 'teacher', assigned: false, valid_until: null })).toMatchObject({
            assigned: true,
            valid_until: '2026-12-31',
        })
        expect(loadAdminUsers).toHaveBeenCalledTimes(1)
        expect(refreshEditLicenceItem).toHaveBeenCalledTimes(1)
        expect(ctx.edit_admin_user_valid_until).toBe('2027-01-31')
    })

    it('updates user licence validity and refreshes the edited licence item', async () => {
        const methods = (LicenceSchools as any).methods
        const saveUserLicenceUserRoles = vi.fn().mockResolvedValue(true)
        const loadUserLicenceUsers = vi.fn().mockResolvedValue(undefined)
        const refreshEditLicenceItem = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            edit_licence_item: {
                user_licence_enabled: true,
                user_role_names: ['teacher'],
                licence_model: {},
            },
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            userRolesFromLicenceModel(licence: unknown) {
                return methods.userRolesFromLicenceModel.call(this, licence)
            },
            userLicenceUserStatusForUser: vi.fn().mockReturnValue({ valid_until: '2027-01-31' }),
            saveUserLicenceUserRoles,
            loadUserLicenceUsers,
            refreshEditLicenceItem,
            sortedRoleNames: methods.sortedRoleNames,
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            toBool: methods.toBool,
        }

        await methods.setUserLicenceUserValidUntil.call(ctx, { id: 55 }, '2026-12-31')

        expect(saveUserLicenceUserRoles).toHaveBeenCalledTimes(1)
        const patchFn = saveUserLicenceUserRoles.mock.calls[0][1]
        expect(patchFn({ name: 'teacher', assigned: false, is_activated: false, valid_until: null })).toMatchObject({
            assigned: true,
            is_activated: true,
            valid_until: '2026-12-31',
        })
        expect(loadUserLicenceUsers).toHaveBeenCalledTimes(1)
        expect(refreshEditLicenceItem).toHaveBeenCalledTimes(1)
        expect(ctx.edit_user_licence_user_valid_until).toBe('2027-01-31')
    })

    it('opens the requested licence tab when editing from assignment buttons', async () => {
        const methods = (LicenceSchools as any).methods
        const loadAdminUsers = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            edit_licence_school: null,
            edit_licence_item: null,
            edit_licence_tab: null,
            edit_school_charged_price: null,
            edit_school_extra_storage_units: null,
            edit_school_extra_storage_unit_price: null,
            edit_school_billing_editing: true,
            edit_licence_dialog: false,
            loadAdminUsers,
            editablePriceInputValue: methods.editablePriceInputValue,
        }

        await methods.openEditLicenceDialog.call(ctx, { id: 7 }, {
            id: 9,
            school_licence_enabled: true,
            admin_licence_enabled: true,
            user_licence_enabled: true,
            charged_school_price: null,
            extra_storage_units: null,
            extra_storage_unit_price: null,
        }, 'admin')

        expect(ctx.edit_licence_tab).toBe('admin')
        expect(ctx.edit_licence_dialog).toBe(true)
        expect(loadAdminUsers).toHaveBeenCalledTimes(1)
    })

    it('filters available licences for add mode by excluding already assigned licences', () => {
        const availableEditDateLicences = (LicenceSchools as any).computed.availableEditDateLicences
        const sortedLicences = (LicenceSchools as any).methods.sortedLicences

        const result = availableEditDateLicences.call({
            licences: [
                { id: 2, name: 'B-Lizenz' },
                { id: 1, name: 'A-Lizenz' },
                { id: 3, name: 'C-Lizenz' },
            ],
            edit_date_school: {
                licences: [
                    { id: 2 },
                ],
            },
            edit_school_licence: null,
            sortedLicences,
        })

        expect(result.map((item: { id: number }) => item.id)).toEqual([1, 3])
    })

    it('opens the date dialog in add mode for the selected school row', () => {
        const methods = (LicenceSchools as any).methods
        const ctx: any = {
            edit_date_school: null,
            edit_school_licence: { id: 99 },
            edit_date_licence_id: 99,
            edit_no_date: true,
            edit_valid_until: '',
            edit_date_dialog: false,
            defaultValidUntil: () => '2027-12-31',
        }

        methods.openEditDateDialog.call(ctx, { id: 7, long_name: 'School' })

        expect(ctx.edit_date_school).toEqual({ id: 7, long_name: 'School' })
        expect(ctx.edit_school_licence).toBeNull()
        expect(ctx.edit_date_licence_id).toBeNull()
        expect(ctx.edit_no_date).toBe(true)
        expect(ctx.edit_valid_until).toBe('')
        expect(ctx.edit_date_dialog).toBe(true)
    })

    it('sets the school licence valid until date to yesterday through the shortcut action', async () => {
        const methods = (LicenceSchools as any).methods
        const setLicenceValidUntil = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            yesterdayDate: vi.fn().mockReturnValue('2026-03-27'),
            setLicenceValidUntil,
        }

        await methods.setValidUntilYesterday.call(ctx)

        expect(ctx.yesterdayDate).toHaveBeenCalledTimes(1)
        expect(setLicenceValidUntil).toHaveBeenCalledWith('2026-03-27')
    })

    it('sets the admin user licence valid until date to yesterday through the shortcut action', async () => {
        const methods = (LicenceSchools as any).methods
        const setAdminUserValidUntil = vi.fn().mockResolvedValue(undefined)
        const user = { id: 44 }
        const ctx: any = {
            yesterdayDate: vi.fn().mockReturnValue('2026-03-27'),
            setAdminUserValidUntil,
        }

        await methods.setAdminUserValidUntilYesterday.call(ctx, user)

        expect(ctx.yesterdayDate).toHaveBeenCalledTimes(1)
        expect(setAdminUserValidUntil).toHaveBeenCalledWith(user, '2026-03-27')
    })

    it('sets the user licence valid until date to yesterday through the shortcut action', async () => {
        const methods = (LicenceSchools as any).methods
        const setUserLicenceUserValidUntil = vi.fn().mockResolvedValue(undefined)
        const user = { id: 55 }
        const ctx: any = {
            yesterdayDate: vi.fn().mockReturnValue('2026-03-27'),
            setUserLicenceUserValidUntil,
        }

        await methods.setUserLicenceUserValidUntilYesterday.call(ctx, user)

        expect(ctx.yesterdayDate).toHaveBeenCalledTimes(1)
        expect(setUserLicenceUserValidUntil).toHaveBeenCalledWith(user, '2026-03-27')
    })

    it('calculates the school billing total from the billed price and storage units', () => {
        const billingTotal = (LicenceSchools as any).computed.billingTotal

        expect(billingTotal.call({
            edit_school_charged_price: '50',
            edit_licence_item: { school_price_per_year: '10' },
            edit_school_extra_storage_units: '3',
            edit_school_extra_storage_unit_price: '5',
            normalizePriceToNumber: (LicenceSchools as any).methods.normalizePriceToNumber,
        })).toBe(65)

        expect(billingTotal.call({
            edit_school_charged_price: null,
            edit_licence_item: { school_price_per_year: '10' },
            edit_school_extra_storage_units: null,
            edit_school_extra_storage_unit_price: null,
            normalizePriceToNumber: (LicenceSchools as any).methods.normalizePriceToNumber,
        })).toBe(10)

        expect(billingTotal.call({
            edit_school_charged_price: '50',
            edit_licence_item: {
                school_price_per_year: '10',
                school_extra_storage_step_price: '7',
            },
            edit_school_extra_storage_units: '3',
            edit_school_extra_storage_unit_price: null,
            normalizePriceToNumber: (LicenceSchools as any).methods.normalizePriceToNumber,
        })).toBe(71)
    })

    it('calculates the school storage total from included and extra storage units', () => {
        const schoolStorageTotalGb = (LicenceSchools as any).computed.schoolStorageTotalGb

        expect(schoolStorageTotalGb.call({
            edit_licence_item: {
                school_included_storage_gb: '20',
                school_extra_storage_step_gb: '100',
            },
            edit_school_extra_storage_units: '3',
        })).toBe(320)

        expect(schoolStorageTotalGb.call({
            edit_licence_item: {
                school_included_storage_gb: '20',
                school_extra_storage_step_gb: null,
            },
            edit_school_extra_storage_units: null,
        })).toBe(20)

        expect(schoolStorageTotalGb.call({
            edit_licence_item: {
                school_included_storage_gb: null,
                school_extra_storage_step_gb: null,
            },
            edit_school_extra_storage_units: null,
        })).toBeNull()
    })

    it('calculates the admin billing total from the effective school admin tariff and storage units', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.adminBillingTotal.call({
            edit_licence_item: {
                charged_admin_price: '79.5',
                admin_price_per_year: '59',
                admin_extra_storage_units: 2,
                admin_extra_storage_unit_price: '5',
                admin_extra_storage_step_price: '7',
            },
            effectiveAdminBasePrice: methods.effectiveAdminBasePrice,
            effectiveAdminExtraStorageUnitPrice: methods.effectiveAdminExtraStorageUnitPrice,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        })).toBe(89.5)

        expect(methods.adminBillingTotal.call({
            edit_licence_item: {
                charged_admin_price: null,
                admin_price_per_year: '59',
                admin_extra_storage_units: null,
                admin_extra_storage_unit_price: null,
                admin_extra_storage_step_price: '5',
            },
            effectiveAdminBasePrice: methods.effectiveAdminBasePrice,
            effectiveAdminExtraStorageUnitPrice: methods.effectiveAdminExtraStorageUnitPrice,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        })).toBe(59)
    })

    it('calculates the admin user billing total from user storage units with school fallback', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.adminUserBillingTotal.call({
            edit_admin_user_extra_storage_units: '3',
            edit_admin_user_extra_storage_unit_price: '4',
            edit_licence_item: {
                charged_admin_price: '79.5',
                admin_price_per_year: '59',
                admin_extra_storage_units: 2,
                admin_extra_storage_unit_price: '5',
                admin_extra_storage_step_price: '7',
            },
            effectiveAdminBasePrice: methods.effectiveAdminBasePrice,
            effectiveAdminExtraStorageUnitPrice: methods.effectiveAdminExtraStorageUnitPrice,
            effectiveAdminUserExtraStorageUnits: methods.effectiveAdminUserExtraStorageUnits,
            effectiveAdminUserExtraStorageUnitPrice: methods.effectiveAdminUserExtraStorageUnitPrice,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        })).toBe(91.5)

        expect(methods.adminUserBillingTotal.call({
            edit_admin_user_extra_storage_units: null,
            edit_admin_user_extra_storage_unit_price: null,
            edit_licence_item: {
                charged_admin_price: null,
                admin_price_per_year: '59',
                admin_extra_storage_units: 2,
                admin_extra_storage_unit_price: '5',
            },
            effectiveAdminBasePrice: methods.effectiveAdminBasePrice,
            effectiveAdminExtraStorageUnitPrice: methods.effectiveAdminExtraStorageUnitPrice,
            effectiveAdminUserExtraStorageUnits: methods.effectiveAdminUserExtraStorageUnits,
            effectiveAdminUserExtraStorageUnitPrice: methods.effectiveAdminUserExtraStorageUnitPrice,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        })).toBe(69)
    })

    it('calculates the admin user overview billing total from saved user values with school fallback', () => {
        const methods = (LicenceSchools as any).methods
        const user = { id: 44 }

        expect(methods.adminUserBillingTotalForUser.call({
            edit_licence_item: {
                charged_admin_price: '59',
                admin_price_per_year: '59',
                admin_extra_storage_units: 2,
                admin_extra_storage_unit_price: '5',
                admin_extra_storage_step_price: '7',
            },
            edit_admin_users_role_statuses: {
                '44': {
                    admin: {
                        assigned: true,
                        charged_price: '12.5',
                        extra_storage_units: 3,
                        extra_storage_unit_price: '4',
                    },
                },
            },
            adminUserStatusForUser: methods.adminUserStatusForUser,
            adminUserBasePriceForUser: methods.adminUserBasePriceForUser,
            adminUserExtraStorageUnitPriceForUser: methods.adminUserExtraStorageUnitPriceForUser,
            adminUserExtraStorageUnitsForUser: methods.adminUserExtraStorageUnitsForUser,
            effectiveAdminBasePrice: methods.effectiveAdminBasePrice,
            effectiveAdminExtraStorageUnitPrice: methods.effectiveAdminExtraStorageUnitPrice,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        }, user)).toBe(24.5)
    })

    it('calculates the admin user overview storage total from saved user values with school fallback', () => {
        const methods = (LicenceSchools as any).methods
        const user = { id: 44 }

        expect(methods.adminUserStorageTotalGbForUser.call({
            edit_licence_item: {
                admin_included_storage_gb: '20',
                admin_extra_storage_units: 2,
                admin_extra_storage_step_gb: '100',
            },
            edit_admin_users_role_statuses: {
                '44': {
                    admin: {
                        assigned: true,
                        extra_storage_units: 3,
                    },
                },
            },
            adminUserStatusForUser: methods.adminUserStatusForUser,
            adminUserExtraStorageUnitsForUser: methods.adminUserExtraStorageUnitsForUser,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
        }, user)).toBe(320)
    })

    it('calculates selected user role billing and storage totals with plan and storage fallback', () => {
        const methods = (LicenceSchools as any).methods
        const roleEntry = {
            assigned: true,
            plan_id: 3,
            charged_price: null,
            extra_storage_units: 2,
            extra_storage_unit_price: null,
            plans: [{ id: 3, text: 'Pro', price_per_year: '12' }],
        }
        const ctx: any = {
            selectedUserLicencesSource: {
                user_included_storage_gb: '20',
                user_extra_storage_step_gb: '100',
                user_extra_storage_step_price: '5',
            },
            selectedUserLicenceHasExtraStorage: methods.selectedUserLicenceHasExtraStorage,
            selectedUserLicenceExtraStorageUnitPrice: methods.selectedUserLicenceExtraStorageUnitPrice,
            selectedUserRolePlan: methods.selectedUserRolePlan,
            selectedUserRolePlanPrice: methods.selectedUserRolePlanPrice,
            selectedUserRoleExtraStorageTotal: methods.selectedUserRoleExtraStorageTotal,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        }

        expect(methods.selectedUserRoleExtraStorageTotal.call(ctx, roleEntry)).toBe(10)
        expect(methods.selectedUserRoleStorageTotalGb.call(ctx, roleEntry)).toBe(220)
        expect(methods.selectedUserRoleBillingTotal.call(ctx, roleEntry)).toBe(22)
    })

    it('calculates the admin user extra storage total from user storage units with school fallback', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.adminUserExtraStorageTotal.call({
            edit_admin_user_extra_storage_units: '3',
            edit_admin_user_extra_storage_unit_price: '4',
            edit_licence_item: {
                admin_extra_storage_units: 2,
                admin_extra_storage_unit_price: '5',
                admin_extra_storage_step_gb: '100',
                admin_extra_storage_step_price: '7',
            },
            effectiveAdminExtraStorageUnitPrice: methods.effectiveAdminExtraStorageUnitPrice,
            effectiveAdminUserExtraStorageUnits: methods.effectiveAdminUserExtraStorageUnits,
            effectiveAdminUserExtraStorageUnitPrice: methods.effectiveAdminUserExtraStorageUnitPrice,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        })).toBe(12)

        expect(methods.adminUserExtraStorageTotal.call({
            edit_admin_user_extra_storage_units: null,
            edit_admin_user_extra_storage_unit_price: null,
            edit_licence_item: {
                admin_extra_storage_units: 2,
                admin_extra_storage_unit_price: '5',
                admin_extra_storage_step_gb: '100',
                admin_extra_storage_step_price: '7',
            },
            effectiveAdminExtraStorageUnitPrice: methods.effectiveAdminExtraStorageUnitPrice,
            effectiveAdminUserExtraStorageUnits: methods.effectiveAdminUserExtraStorageUnits,
            effectiveAdminUserExtraStorageUnitPrice: methods.effectiveAdminUserExtraStorageUnitPrice,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        })).toBe(10)
    })

    it('calculates the admin user total storage amount in GB from included and extra storage', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.adminUserStorageTotalGb.call({
            edit_admin_user_extra_storage_units: '3',
            edit_licence_item: {
                admin_included_storage_gb: '20',
                admin_extra_storage_units: 2,
                admin_extra_storage_step_gb: '100',
            },
            effectiveAdminUserExtraStorageUnits: methods.effectiveAdminUserExtraStorageUnits,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
        })).toBe(320)

        expect(methods.adminUserStorageTotalGb.call({
            edit_admin_user_extra_storage_units: null,
            edit_licence_item: {
                admin_included_storage_gb: '20',
                admin_extra_storage_units: 2,
                admin_extra_storage_step_gb: '100',
            },
            effectiveAdminUserExtraStorageUnits: methods.effectiveAdminUserExtraStorageUnits,
            defaultAdminUserExtraStorageUnits: methods.defaultAdminUserExtraStorageUnits,
        })).toBe(220)
    })

    it('calculates user licence list totals from assigned roles, plans, and extra storage', () => {
        const methods = (LicenceSchools as any).methods
        const ctx: any = {
            selectedUserLicencesSource: {
                user_price_per_year: '5',
                user_included_storage_gb: '20',
                user_extra_storage_step_gb: '100',
                licence_model: {
                    school_licence_required: true,
                    affected_roles: ['teacher', 'editor'],
                    user_licence_required_by_role: {
                        teacher: true,
                        editor: true,
                    },
                    user_licence_plans_by_role: {
                        teacher: [{ id: 3, text: 'Pro', price_per_year: '12' }],
                        editor: [{ id: 8, text: 'Basic', price_per_year: '7' }],
                    },
                },
            },
            school_licence_users_role_statuses: {
                '44': {
                    teacher: {
                        assigned: true,
                        plan_id: 3,
                        charged_price: null,
                        extra_storage_units: 2,
                    },
                    editor: {
                        assigned: true,
                        plan_id: 8,
                        charged_price: '9.5',
                        extra_storage_units: 1,
                    },
                },
            },
            normalizeLicenceModel: methods.normalizeLicenceModel,
            toBool: methods.toBool,
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            sortedRoleNames: methods.sortedRoleNames,
            normalizePriceToNumber: methods.normalizePriceToNumber,
            userLicenceStatusesForUser: methods.userLicenceStatusesForUser,
            userLicencePlanForRole: methods.userLicencePlanForRole,
            userLicenceSummaryEntries: methods.userLicenceSummaryEntries,
        }
        const user = {
            id: 44,
            roles: ['teacher', 'editor'],
        }

        expect(methods.userLicenceListPriceTotal.call(ctx, user)).toBe(21.5)
        expect(methods.userLicenceListStorageTotalGb.call(ctx, user)).toBe(340)
    })

    it('hides user licence list storage totals when no storage option exists', () => {
        const methods = (LicenceSchools as any).methods
        const ctx: any = {
            selectedUserLicencesSource: {
                user_price_per_year: '9',
                licence_model: {
                    school_licence_required: true,
                    affected_roles: ['teacher'],
                    user_licence_required_by_role: {
                        teacher: true,
                    },
                    user_licence_plans_by_role: {
                        teacher: [{ id: 4, text: 'Default', price_per_year: '9' }],
                    },
                },
            },
            school_licence_users_role_statuses: {
                '12': {
                    teacher: {
                        assigned: true,
                        plan_id: 4,
                        charged_price: null,
                        extra_storage_units: null,
                    },
                },
            },
            normalizeLicenceModel: methods.normalizeLicenceModel,
            toBool: methods.toBool,
            looksLikeAdminRoleName: methods.looksLikeAdminRoleName,
            sortedRoleNames: methods.sortedRoleNames,
            normalizePriceToNumber: methods.normalizePriceToNumber,
            userLicenceStatusesForUser: methods.userLicenceStatusesForUser,
            userLicencePlanForRole: methods.userLicencePlanForRole,
            userLicenceSummaryEntries: methods.userLicenceSummaryEntries,
        }

        expect(methods.userLicenceListStorageTotalGb.call(ctx, { id: 12, roles: ['teacher'] })).toBeNull()
    })

    it('disables only the other licence tabs while editing is active', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.isEditLicenceTabDisabled.call({
            editLicenceTabLocked: true,
            edit_licence_tab: 'school',
        }, 'admin')).toBe(true)

        expect(methods.isEditLicenceTabDisabled.call({
            editLicenceTabLocked: true,
            edit_licence_tab: 'school',
        }, 'school')).toBe(false)

        expect(methods.isEditLicenceTabDisabled.call({
            editLicenceTabLocked: false,
            edit_licence_tab: 'school',
        }, 'admin')).toBe(false)
    })

    it('shows only the editing admin user while a user licence is open', () => {
        const visibleAdminUsers = (LicenceSchools as any).computed.visibleAdminUsers

        const users = [
            { id: 4, first_name: 'Ada' },
            { id: 9, first_name: 'Bea' },
        ]

        expect(visibleAdminUsers.call({ edit_admin_users: users, edit_admin_user_edit_id: null })).toEqual(users)
        expect(visibleAdminUsers.call({ edit_admin_users: users, edit_admin_user_edit_id: 9 })).toEqual([
            { id: 9, first_name: 'Bea' },
        ])
    })

    it('marks admin users with an active licence as valid', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.adminUserHasValidLicence.call({
            adminUserIsAssigned: () => true,
            adminUserStatusForUser: () => ({ is_active: true }),
            isValidUntilExpired: methods.isValidUntilExpired,
        }, { id: 4 })).toBe(true)

        expect(methods.adminUserHasValidLicence.call({
            adminUserIsAssigned: () => false,
            adminUserStatusForUser: () => ({ is_active: false }),
            isValidUntilExpired: methods.isValidUntilExpired,
        }, { id: 4 })).toBe(false)

        expect(methods.adminUserHasValidLicence.call({
            adminUserIsAssigned: () => true,
            adminUserStatusForUser: () => ({ is_active: false, valid_until: null }),
            isValidUntilExpired: methods.isValidUntilExpired,
        }, { id: 4 })).toBe(true)
    })

    it('treats admin users as assigned only when the backend status marks them assigned', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.adminUserIsAssigned.call({
            edit_admin_users_role_statuses: {
                '4': {
                    teacher: { assigned: false, valid_until: null },
                },
            },
        }, { id: 4 })).toBe(false)

        expect(methods.adminUserIsAssigned.call({
            edit_admin_users_role_statuses: {
                '4': {
                    teacher: { assigned: true, valid_until: null },
                },
            },
        }, { id: 4 })).toBe(true)
    })

    it('formats the admin user licence runtime label in the overview', () => {
        const methods = (LicenceSchools as any).methods

        const activeCtx = {
            adminUserIsAssigned: () => true,
            adminUserStatusForUser: () => ({ valid_until: '2026-01-20', is_active: true }),
            formatValidUntil: methods.formatValidUntil,
            localDateKey: () => '2026-01-15',
            toDateString: methods.toDateString,
        }
        expect(methods.adminUserLicenceRuntimeLabel.call(activeCtx, { id: 4 })).toBe(
            'Benutzerlizenz läuft noch 5 Tage (20.01.2026)'
        )

        const unlimitedCtx = {
            adminUserIsAssigned: () => true,
            adminUserStatusForUser: () => ({ valid_until: null, is_active: true }),
            formatValidUntil: methods.formatValidUntil,
            localDateKey: () => '2026-01-15',
            toDateString: methods.toDateString,
        }
        expect(methods.adminUserLicenceRuntimeLabel.call(unlimitedCtx, { id: 4 })).toBe(
            'Benutzerlizenz läuft unbegrenzt'
        )

        const unlimitedAssignedInactiveCtx = {
            adminUserIsAssigned: () => true,
            adminUserStatusForUser: () => ({ valid_until: null, is_active: false }),
            formatValidUntil: methods.formatValidUntil,
            localDateKey: () => '2026-01-15',
            toDateString: methods.toDateString,
        }
        expect(methods.adminUserLicenceRuntimeLabel.call(unlimitedAssignedInactiveCtx, { id: 4 })).toBe(
            'Benutzerlizenz läuft unbegrenzt'
        )

        const expiredCtx = {
            adminUserIsAssigned: () => true,
            adminUserStatusForUser: () => ({ valid_until: '2026-01-10', is_active: false }),
            formatValidUntil: methods.formatValidUntil,
            localDateKey: () => '2026-01-15',
            toDateString: methods.toDateString,
        }
        expect(methods.adminUserLicenceRuntimeLabel.call(expiredCtx, { id: 4 })).toBe(
            'Benutzerlizenz abgelaufen am 10.01.2026'
        )
    })

    it('loads extra storage units into the focused admin user edit state', () => {
        const methods = (LicenceSchools as any).methods
        const ctx: any = {
            edit_admin_user_edit_id: null,
            edit_admin_add_mode: true,
            edit_admin_add_search: 'Ada',
            edit_admin_add_results: [{ id: 1 }],
            adminUserStatusForUser: () => ({ valid_until: '2026-12-31', charged_price: '15.00', extra_storage_units: 4, extra_storage_unit_price: '6.50' }),
            effectiveAdminBasePrice: () => 5,
            editablePriceInputValue: methods.editablePriceInputValue,
            normalizedPriceInputValue: methods.normalizedPriceInputValue,
        }

        methods.toggleAdminUserEdit.call(ctx, { id: 9 })

        expect(ctx.edit_admin_user_edit_id).toBe(9)
        expect(ctx.edit_admin_user_valid_until).toBe('2026-12-31')
        expect(ctx.edit_admin_user_charged_price).toBe('15.00')
        expect(ctx.edit_admin_user_charged_price_draft).toBe('15,00')
        expect(ctx.edit_admin_user_extra_storage_units).toBe(4)
        expect(ctx.edit_admin_user_extra_storage_unit_price).toBe('6,50')
        expect(ctx.edit_admin_add_mode).toBe(false)
        expect(ctx.edit_admin_add_search).toBe('')
        expect(ctx.edit_admin_add_results).toEqual([])
    })

    it('calculates prorated annual prices for partial licences', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            remainingDaysUntil: () => 100,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        }

        expect(methods.proratedYearPrice.call(ctx, '365', '31.12.')).toBe(100)
        expect(methods.proratedYearPrice.call(ctx, '', '31.12.')).toBeNull()
    })

    it('applies an admin partial licence until year end with prorated price', async () => {
        const methods = (LicenceSchools as any).methods
        const saveAdminUserRoles = vi.fn().mockResolvedValue(true)
        const loadAdminUsers = vi.fn().mockResolvedValue(undefined)
        const refreshEditLicenceItem = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            effectiveEndDayMonth: '31.12.',
            edit_admin_user_charged_price: null,
            edit_admin_user_extra_storage_units: '3',
            edit_admin_user_extra_storage_unit_price: '4',
            edit_licence_item: {
                admin_price_per_year: '365',
                admin_role_names: ['teacher'],
            },
            nextYearEndDate: vi.fn().mockReturnValue('2026-12-31'),
            proratedYearPrice: vi.fn().mockReturnValue(100),
            adminUserBillingTotal: vi.fn().mockReturnValue(465),
            effectiveAdminUserExtraStorageUnits: vi.fn().mockReturnValue(3),
            effectiveAdminUserExtraStorageUnitPrice: vi.fn().mockReturnValue(4),
            adminRolesFromLicenceModel: vi.fn().mockReturnValue(['teacher']),
            saveAdminUserRoles,
            loadAdminUsers,
            refreshEditLicenceItem,
            adminUserStatusForUser: vi.fn().mockReturnValue({ valid_until: '2026-12-31', charged_price: '100.00', extra_storage_units: 3, extra_storage_unit_price: '4.50' }),
            normalizedPriceInputValue: methods.normalizedPriceInputValue,
            editablePriceInputValue: methods.editablePriceInputValue,
            effectiveAdminBasePrice: vi.fn().mockReturnValue('5.00'),
        }

        await methods.applyAdminUserTeillizenz.call(ctx, { id: 44 })

        expect(ctx.adminUserBillingTotal).toHaveBeenCalledTimes(1)
        expect(ctx.proratedYearPrice).toHaveBeenCalledWith(465, '31.12.')
        expect(saveAdminUserRoles).toHaveBeenCalledTimes(1)
        const patchFn = saveAdminUserRoles.mock.calls[0][1]
        expect(patchFn({ name: 'teacher', assigned: false, valid_until: null, charged_price: null })).toMatchObject({
            assigned: true,
            valid_until: '2026-12-31',
            charged_price: 100,
            extra_storage_units: 3,
            extra_storage_unit_price: 4,
        })
        expect(loadAdminUsers).toHaveBeenCalledTimes(1)
        expect(refreshEditLicenceItem).toHaveBeenCalledTimes(1)
        expect(ctx.edit_admin_user_valid_until).toBe('2026-12-31')
        expect(ctx.edit_admin_user_charged_price).toBe('100.00')
        expect(ctx.edit_admin_user_charged_price_draft).toBe('100,00')
        expect(ctx.edit_admin_user_extra_storage_units).toBe(3)
        expect(ctx.edit_admin_user_extra_storage_unit_price).toBe('4,50')
        expect(ctx.edit_admin_user_price_editing).toBe(false)
    })

    it('removes the admin licence from the edited user', async () => {
        const methods = (LicenceSchools as any).methods
        const saveAdminUserRoles = vi.fn().mockResolvedValue(true)
        const loadAdminUsers = vi.fn().mockResolvedValue(undefined)
        const refreshEditLicenceItem = vi.fn().mockResolvedValue(undefined)
        const closeAdminUserEdit = vi.fn()
        const ctx: any = {
            edit_licence_item: {
                admin_role_names: ['teacher'],
            },
            adminRolesFromLicenceModel: vi.fn().mockReturnValue(['teacher']),
            saveAdminUserRoles,
            closeAdminUserEdit,
            loadAdminUsers,
            refreshEditLicenceItem,
        }

        await methods.removeAdminUserLicence.call(ctx, { id: 44 })

        expect(saveAdminUserRoles).toHaveBeenCalledTimes(1)
        const patchFn = saveAdminUserRoles.mock.calls[0][1]
        expect(patchFn({ name: 'teacher', assigned: true, valid_until: '2026-12-31', charged_price: 80 })).toMatchObject({
            assigned: false,
            valid_until: null,
            charged_price: null,
        })
        expect(closeAdminUserEdit).toHaveBeenCalledTimes(1)
        expect(loadAdminUsers).toHaveBeenCalledTimes(1)
        expect(refreshEditLicenceItem).toHaveBeenCalledTimes(1)
    })

    it('saves admin billing with per-user extra storage units', async () => {
        const methods = (LicenceSchools as any).methods
        const saveAdminUserRoles = vi.fn().mockResolvedValue(true)
        const loadAdminUsers = vi.fn().mockResolvedValue(undefined)
        const refreshEditLicenceItem = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            edit_admin_user_charged_price: null,
            edit_admin_user_charged_price_draft: '12,50',
            edit_admin_user_extra_storage_units: '4',
            edit_admin_user_extra_storage_unit_price: '7,25',
            edit_licence_item: {
                admin_role_names: ['teacher'],
            },
            adminRolesFromLicenceModel: vi.fn().mockReturnValue(['teacher']),
            saveAdminUserRoles,
            loadAdminUsers,
            refreshEditLicenceItem,
            adminUserStatusForUser: vi.fn().mockReturnValue({ charged_price: null, extra_storage_units: 4, extra_storage_unit_price: '7.25' }),
            effectiveAdminBasePrice: vi.fn().mockReturnValue('5.00'),
            normalizedPriceInputValue: methods.normalizedPriceInputValue,
            editablePriceInputValue: methods.editablePriceInputValue,
        }

        await methods.saveAdminUserBilling.call(ctx, { id: 44 })

        expect(saveAdminUserRoles).toHaveBeenCalledTimes(1)
        const patchFn = saveAdminUserRoles.mock.calls[0][1]
        expect(patchFn({ name: 'teacher', assigned: false, charged_price: 25, extra_storage_units: 1 })).toMatchObject({
            assigned: true,
            charged_price: '12.50',
            extra_storage_units: '4',
            extra_storage_unit_price: '7.25',
        })
        expect(loadAdminUsers).toHaveBeenCalledTimes(1)
        expect(refreshEditLicenceItem).toHaveBeenCalledTimes(1)
        expect(ctx.edit_admin_user_price_editing).toBe(false)
        expect(ctx.edit_admin_user_charged_price).toBeNull()
        expect(ctx.edit_admin_user_charged_price_draft).toBe('5,00')
        expect(ctx.edit_admin_user_extra_storage_units).toBe(4)
        expect(ctx.edit_admin_user_extra_storage_unit_price).toBe('7,25')
    })

    it('loads unassigned admin add candidates with empty search input', async () => {
        const methods = (LicenceSchools as any).methods
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: [
                    { id: 4, last_name: 'Assigned', first_name: 'Admin' },
                    { id: 9, last_name: 'Available', first_name: 'Admin' },
                ],
                role_statuses_by_user: {
                    '4': { admin: { assigned: true } },
                    '9': { admin: { assigned: false } },
                },
            },
        })
        const ctx: any = {
            edit_admin_add_search: '',
            edit_licence_item: {
                school_licence_id: 12,
                admin_role_names: ['admin'],
            },
            edit_admin_add_results: [],
            adminStore: { is_loading: 0 },
            adminRolesFromLicenceModel: vi.fn().mockReturnValue(['admin']),
        }

        await methods.searchAdminUsersToAdd.call(ctx)

        expect(axios.get).toHaveBeenCalledWith('/api/admin/school_licences/12/users', {
            params: {
                search_string: null,
                role_names: ['admin'],
                assigned_only: 0,
            },
        })
        expect(ctx.edit_admin_add_results).toEqual([
            { id: 9, last_name: 'Available', first_name: 'Admin' },
        ])
    })

    it('removes the added admin user from the current add results immediately', async () => {
        const methods = (LicenceSchools as any).methods
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                roles: [
                    { name: 'admin', assigned: false },
                ],
            },
        })
        vi.mocked(axios.put).mockResolvedValueOnce({ data: {} })

        const loadAdminUsers = vi.fn().mockResolvedValue(undefined)
        const refreshEditLicenceItem = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            edit_licence_item: {
                school_licence_id: 12,
                admin_role_names: ['admin'],
            },
            edit_admin_add_results: [
                { id: 4, last_name: 'Assigned', first_name: 'Admin' },
                { id: 9, last_name: 'Available', first_name: 'Admin' },
            ],
            adminStore: { is_loading: 0 },
            adminRolesFromLicenceModel: vi.fn().mockReturnValue(['admin']),
            loadAdminUsers,
            refreshEditLicenceItem,
        }

        await methods.assignAdminUser.call(ctx, { id: 9 })

        expect(ctx.edit_admin_add_results).toEqual([
            { id: 4, last_name: 'Assigned', first_name: 'Admin' },
        ])
        expect(loadAdminUsers).toHaveBeenCalledTimes(1)
        expect(refreshEditLicenceItem).toHaveBeenCalledTimes(1)
    })

    it('refreshes the admin config when refreshing the edited licence item', async () => {
        const methods = (LicenceSchools as any).methods
        const index = vi.fn().mockResolvedValue(undefined)
        const loadConfig = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            meta: { current_page: 3 },
            schoolStore: { index },
            schools: [
                {
                    id: 7,
                    licences: [{ id: 11, name: 'Lehrertool' }],
                },
            ],
            edit_licence_school: { id: 7 },
            edit_licence_item: { id: 11, name: 'Old' },
            adminStore: { loadConfig },
        }

        await methods.refreshEditLicenceItem.call(ctx)

        expect(index).toHaveBeenCalledWith(3)
        expect(loadConfig).toHaveBeenCalledTimes(1)
        expect(ctx.edit_licence_item).toEqual({ id: 11, name: 'Lehrertool' })
    })

    it('clears the focused admin user edit state', () => {
        const methods = (LicenceSchools as any).methods
        const ctx: any = {
            edit_admin_user_edit_id: 9,
            edit_admin_user_valid_until: '2026-12-31',
            edit_admin_user_price_editing: true,
            edit_admin_user_charged_price: 15,
            edit_admin_user_charged_price_draft: 15,
            edit_admin_user_extra_storage_units: 4,
            edit_admin_user_extra_storage_unit_price: 7,
        }

        methods.closeAdminUserEdit.call(ctx)

        expect(ctx.edit_admin_user_edit_id).toBeNull()
        expect(ctx.edit_admin_user_valid_until).toBeNull()
        expect(ctx.edit_admin_user_price_editing).toBe(false)
        expect(ctx.edit_admin_user_charged_price).toBeNull()
        expect(ctx.edit_admin_user_charged_price_draft).toBeNull()
        expect(ctx.edit_admin_user_extra_storage_units).toBeNull()
        expect(ctx.edit_admin_user_extra_storage_unit_price).toBeNull()
    })

    it('distinguishes null and zero for charged price overrides', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            formatPrice: methods.formatPrice,
            normalizePriceToNumber: methods.normalizePriceToNumber,
        }

        expect(methods.overridePriceLabel.call(ctx, null, '59')).toBe('€ 59 (Basis-Tarif)')
        expect(methods.overridePriceLabel.call(ctx, '', '59')).toBe('€ 59 (Basis-Tarif)')
        expect(methods.overridePriceLabel.call(ctx, 0, '59')).toBe('€ 0')
        expect(methods.overridePriceLabel.call(ctx, '0', '59')).toBe('€ 0')
    })

    it('formats editable price inputs with decimal commas', () => {
        const methods = (LicenceSchools as any).methods

        expect(methods.editablePriceInputValue.call({}, '5.00')).toBe('5,00')
        expect(methods.editablePriceInputValue.call({}, '7.50')).toBe('7,50')
        expect(methods.normalizedPriceInputValue.call({}, '5,00')).toBe('5.00')
        expect(methods.normalizedPriceInputValue.call({}, '7,50')).toBe('7.50')
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
                    user_licence_required: true,
                    charged_price: '19,50',
                    extra_storage_units: '2',
                    extra_storage_unit_price: '7,25',
                },
            ],
            normalizeRoleValidUntilForApi: methods.normalizeRoleValidUntilForApi,
            normalizedPriceInputValue: methods.normalizedPriceInputValue,
            buildSelectedUserRolePayload: methods.buildSelectedUserRolePayload,
            syncSelectedUserLicenceRolesToUserRoles: vi.fn().mockImplementation(async () => {
                ctx.selectedUserLicenceRoleEntries = [
                    {
                        name: 'teacher',
                        assigned: false,
                        valid_until: null,
                        is_activated: false,
                        plan_id: null,
                        user_licence_required: true,
                        charged_price: null,
                        extra_storage_units: null,
                        extra_storage_unit_price: null,
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
            charged_price: '19.50',
            extra_storage_units: '2',
            extra_storage_unit_price: '7.25',
        })
    })
})
