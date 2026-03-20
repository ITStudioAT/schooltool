import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import Groups from '@/pages/admin/groups/Groups.vue'

describe('Groups page header', () => {
    it('builds header chips from school and groups count', () => {
        const ctx = {
            selectedSchoolLabel: 'Christian-Doppler-Gymnasium Salzburg',
            groups: [{ id: 1 }, { id: 2 }, { id: 3 }],
        }

        const chips = (Groups as any).computed.headerChips.call(ctx)

        expect(chips).toEqual([
            { key: 'school', text: 'Christian-Doppler-Gymnasium Salzburg', icon: 'mdi-domain' },
            { key: 'groups', text: '3 Gruppen', icon: 'mdi-account-group-outline' },
        ])
    })

    it('returns reusable active-section metadata for centralized hero', () => {
        const section = (Groups as any).computed.headerActiveSection.call({})

        expect(section).toEqual({
            icon: 'mdi-account-group-outline',
            label: 'Schulgruppen, Materialien, Eigene',
            note: 'Löschen nur ohne Mitglieder möglich.',
        })
    })

    it('opens members dialog in read-only mode when clicking a group row', () => {
        const methods = (Groups as any).methods
        const openAssignUsersDialog = vi.fn()
        const ctx = {
            selectedGroupIdsByType: { school: null, materials: null, own: null },
            openAssignUsersDialog,
        }

        methods.onGroupRowClick.call(ctx, 'school', { id: 42, type: 'school' })

        expect(ctx.selectedGroupIdsByType.school).toBe(42)
        expect(openAssignUsersDialog).toHaveBeenCalledTimes(1)
        expect(openAssignUsersDialog).toHaveBeenCalledWith({ id: 42, type: 'school' }, { readOnly: true })
    })

    it('opens the editable members dialog for manually created own groups', () => {
        const methods = (Groups as any).methods
        const openAssignUsersDialog = vi.fn()
        const ctx = {
            selectedGroupIdsByType: { school: null, materials: null, own: null },
            openAssignUsersDialog,
            showInlineMemberManagementButton: methods.showInlineMemberManagementButton,
            isAutomaticOwnCourseGroup: methods.isAutomaticOwnCourseGroup,
        }

        methods.openManageMembersDialog.call(ctx, 'own', { id: 7, type: 'own', can_manage_members: true })

        expect(ctx.selectedGroupIdsByType.own).toBe(7)
        expect(openAssignUsersDialog).toHaveBeenCalledTimes(1)
        expect(openAssignUsersDialog).toHaveBeenCalledWith({ id: 7, type: 'own', can_manage_members: true })
    })

    it('shows the inline member management button for all editable groups only', () => {
        const methods = (Groups as any).methods
        const ctx = {}

        expect(methods.showInlineMemberManagementButton.call(ctx, { id: 1, type: 'own', can_manage_members: true })).toBe(true)
        expect(methods.showInlineMemberManagementButton.call(ctx, { id: 2, type: 'own', can_manage_members: false })).toBe(false)
        expect(methods.showInlineMemberManagementButton.call(ctx, { id: 3, type: 'own', teaching_course_id: 11, can_manage_members: false })).toBe(false)
        expect(methods.showInlineMemberManagementButton.call(ctx, { id: 4, type: 'school', can_manage_members: true })).toBe(true)
        expect(methods.showInlineMemberManagementButton.call(ctx, { id: 5, type: 'materials', can_manage_members: true })).toBe(true)
    })

    it('shows the auto-managed notice only for read-only system-managed groups', () => {
        const methods = (Groups as any).methods

        expect(methods.showReadOnlyAutoManagedNotice.call({
            assignUsersDialog: {
                readOnly: true,
                group: { id: 1, can_manage_members: false },
            },
        })).toBe(true)

        expect(methods.showReadOnlyAutoManagedNotice.call({
            assignUsersDialog: {
                readOnly: true,
                group: { id: 2, can_manage_members: true },
            },
        })).toBe(false)

        expect(methods.showReadOnlyAutoManagedNotice.call({
            assignUsersDialog: {
                readOnly: false,
                group: { id: 3, can_manage_members: false },
            },
        })).toBe(false)
    })

    it('keeps members dialog read-only and loads source-members while skipping assignable-groups', () => {
        const methods = (Groups as any).methods
        const loadAssignedMembers = vi.fn()
        const loadAssignableGroups = vi.fn()
        const loadSourceMembers = vi.fn()
        const showSourceMembersPanel = vi.fn(() => true)
        const notifyError = vi.fn()
        const ctx: any = {
            assignUsersDialog: {
                group: null,
                readOnly: false,
                membersExpanded: false,
                membersPage: 9,
                members: [],
                membersLoading: false,
                selectedMemberIds: [],
                removingUserId: null,
                bulkRemoving: false,
                extraPanel: null,
                userSearchString: 'x',
                userSearchResults: [{ id: 1 }],
                userSearchHasRun: true,
                teacherSearchString: 'x',
                teacherSearchResults: [{ id: 2 }],
                teacherSearchHasRun: true,
                teacherSearchLoading: false,
                selectedTeacherIds: [2],
                teacherShowAll: true,
                myCoursesLoading: false,
                myCoursesLoaded: true,
                myCourses: [{ id: 3 }],
                studentSearchString: 'x',
                studentSearchResults: [{ id: 4 }],
                studentSearchHasRun: true,
                studentSearchLoading: false,
                studentClassesVisible: true,
                studentClassesLoading: false,
                studentClasses: [{ key: '2B' }],
                sourceGroups: [{ id: 5 }],
                sourceGroupCategory: 'classes',
                bulkSourceGroupId: 5,
                sourceGroupId: 5,
                selectedSourceGroupMembers: [{ id: 11 }],
                selectedSourceGroupSourceMembers: [{ id: 12 }],
                selectedSourceGroupMembersLoading: true,
                sourceMembers: [{ id: 9 }],
                sourceMembersLoading: false,
                readOnlyPage: 4,
                readOnlyPanel: 'registered',
                open: false,
            },
            notifyError,
            loadAssignedMembers,
            loadAssignableGroups,
            loadSourceMembers,
            showSourceMembersPanel,
        }

        methods.openAssignUsersDialog.call(ctx, { id: 7, type: 'school', can_manage_members: false }, { readOnly: true })

        expect(ctx.assignUsersDialog.open).toBe(true)
        expect(ctx.assignUsersDialog.readOnly).toBe(true)
        expect(ctx.assignUsersDialog.membersPage).toBe(1)
        expect(ctx.assignUsersDialog.readOnlyPage).toBe(1)
        expect(ctx.assignUsersDialog.sourceGroupCollection).toBeNull()
        expect(ctx.assignUsersDialog.readOnlyPanel).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupCategory).toBeNull()
        expect(ctx.assignUsersDialog.bulkSourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.selectedSourceGroupMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupSourceMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupMembersLoading).toBe(false)
        expect(showSourceMembersPanel).toHaveBeenCalledTimes(1)
        expect(loadAssignedMembers).toHaveBeenCalledTimes(1)
        expect(loadSourceMembers).toHaveBeenCalledTimes(1)
        expect(loadAssignableGroups).not.toHaveBeenCalled()
        expect(notifyError).not.toHaveBeenCalled()
    })

    it('keeps the assigned-members expandable collapsed by default in edit mode', () => {
        const methods = (Groups as any).methods
        const loadAssignedMembers = vi.fn()
        const loadAssignableGroups = vi.fn()
        const ctx: any = {
            assignUsersDialog: {
                group: null,
                readOnly: true,
                membersExpanded: true,
                membersPage: 3,
                members: [{ id: 1 }],
                membersLoading: true,
                selectedMemberIds: [1],
                removingUserId: 1,
                bulkRemoving: true,
                extraPanel: 'group',
                userSearchString: 'abc',
                userSearchResults: [{ id: 1 }],
                userSearchHasRun: true,
                teacherSearchString: 'x',
                teacherSearchResults: [{ id: 2 }],
                teacherSearchHasRun: true,
                teacherSearchLoading: true,
                selectedTeacherIds: [2],
                teacherShowAll: true,
                myCoursesLoading: true,
                myCoursesLoaded: true,
                myCourses: [{ id: 3 }],
                studentSearchString: 'x',
                studentSearchResults: [{ id: 4 }],
                studentSearchHasRun: true,
                studentSearchLoading: true,
                studentClassesVisible: true,
                studentClassesLoading: true,
                studentClasses: [{ key: '2B' }],
                sourceGroups: [{ id: 5 }],
                sourceGroupCategory: 'teachers',
                bulkSourceGroupId: 5,
                sourceGroupId: 5,
                selectedSourceGroupMembers: [{ id: 11 }],
                selectedSourceGroupSourceMembers: [{ id: 12 }],
                selectedSourceGroupMembersLoading: true,
                sourceMembers: [{ id: 9 }],
                sourceMembersLoading: true,
                readOnlyPage: 7,
                readOnlyPanel: 'registered',
                open: false,
            },
            notifyError: vi.fn(),
            loadAssignedMembers,
            loadAssignableGroups,
            loadSourceMembers: vi.fn(),
            showSourceMembersPanel: vi.fn(() => false),
        }

        methods.openAssignUsersDialog.call(ctx, { id: 8, type: 'own', can_manage_members: true })

        expect(ctx.assignUsersDialog.open).toBe(true)
        expect(ctx.assignUsersDialog.readOnly).toBe(false)
        expect(ctx.assignUsersDialog.membersExpanded).toBe(false)
        expect(ctx.assignUsersDialog.membersPage).toBe(1)
        expect(ctx.assignUsersDialog.readOnlyPage).toBe(1)
        expect(ctx.assignUsersDialog.sourceGroupCollection).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupCategory).toBeNull()
        expect(ctx.assignUsersDialog.bulkSourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.selectedSourceGroupMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupSourceMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupMembersLoading).toBe(false)
        expect(loadAssignedMembers).toHaveBeenCalledTimes(1)
        expect(loadAssignableGroups).toHaveBeenCalledTimes(1)
    })

    it('builds school source category buttons from available school source groups only', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                sourceGroups: [
                    { id: 1, type: 'school', name: '1A', is_system_default: true },
                    { id: 2, type: 'school', name: 'Lehrer', is_system_default: true },
                    { id: 3, type: 'school', name: '1A Eltern', is_system_default: true, is_parent_group: true },
                    { id: 4, type: 'school', name: 'AG Robotik', is_system_default: false },
                    { id: 5, type: 'school', name: 'Alle Schulmitglieder', is_system_default: true, is_all_school_members_group: true },
                    { id: 6, type: 'own', name: 'Eigene Gruppe' },
                ],
            },
            schoolSourceGroupsPanels: methods.schoolSourceGroupsPanels,
            isAutomaticSchoolGroup: methods.isAutomaticSchoolGroup,
            isTeacherSchoolGroup: methods.isTeacherSchoolGroup,
            isParentSchoolGroup: methods.isParentSchoolGroup,
            isAllSchoolMembersGroup: methods.isAllSchoolMembersGroup,
        }

        const buttons = methods.schoolSourceGroupCategoryButtons.call(ctx)

        expect(buttons.map((button: any) => button.key)).toEqual(['classes', 'teachers', 'parents', 'other-school-groups'])
        expect(buttons.map((button: any) => button.title)).toEqual(['Klassen', 'Lehrer', 'Eltern', 'Weitere Gruppen'])
    })

    it('keeps the four school source categories visible even before concrete groups are chosen', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                sourceGroups: [],
            },
            schoolSourceGroupsPanels: methods.schoolSourceGroupsPanels,
            isAutomaticSchoolGroup: methods.isAutomaticSchoolGroup,
            isTeacherSchoolGroup: methods.isTeacherSchoolGroup,
            isParentSchoolGroup: methods.isParentSchoolGroup,
            isAllSchoolMembersGroup: methods.isAllSchoolMembersGroup,
        }

        const buttons = methods.schoolSourceGroupCategoryButtons.call(ctx)

        expect(buttons.map((button: any) => button.title)).toEqual(['Klassen', 'Lehrer', 'Eltern', 'Weitere Gruppen'])
    })

    it('builds own source category buttons from available own source groups only', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                sourceGroups: [
                    { id: 1, type: 'own', name: 'Mathematik (1A)', teaching_course_id: 10, teaching_course_group_type: 'students' },
                    { id: 2, type: 'own', name: 'Mathematik (1A) Eltern', teaching_course_id: 10, teaching_course_group_type: 'parents', is_parent_group: true },
                    { id: 3, type: 'own', name: 'Freie Gruppe', teaching_course_id: null },
                    { id: 4, type: 'school', name: '1A', is_system_default: true },
                ],
            },
            ownSourceGroupsPanels: methods.ownSourceGroupsPanels,
            isAutomaticOwnCourseGroup: methods.isAutomaticOwnCourseGroup,
            isAutomaticOwnCourseParentGroup: methods.isAutomaticOwnCourseParentGroup,
        }

        const buttons = methods.ownSourceGroupCategoryButtons.call(ctx)

        expect(buttons.map((button: any) => button.key)).toEqual(['course-groups', 'course-parent-groups', 'own-groups'])
        expect(buttons.map((button: any) => button.title)).toEqual(['Kursgruppen', 'Eltern Kursgruppen', 'Eigene Gruppen'])
    })

    it('builds materials source category buttons from available materials source groups only', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                sourceGroups: [
                    { id: 1, type: 'materials', name: 'Materialteam' },
                    { id: 2, type: 'materials', name: 'Redaktion' },
                    { id: 3, type: 'school', name: '1A', is_system_default: true },
                ],
            },
            materialsSourceGroupsPanels: methods.materialsSourceGroupsPanels,
        }

        const buttons = methods.materialsSourceGroupCategoryButtons.call(ctx)

        expect(buttons.map((button: any) => button.key)).toEqual(['materials-groups'])
        expect(buttons.map((button: any) => button.title)).toEqual(['Materialgruppen'])
    })

    it('switches own takeover between school and own source collections', () => {
        const methods = (Groups as any).methods
        const ctx: any = {
            assignUsersDialog: {
                sourceGroupCollection: null,
                sourceGroupCategory: 'classes',
                bulkSourceGroupId: 3,
                sourceGroupId: 9,
                selectedSourceGroupMembers: [{ id: 1 }],
                selectedSourceGroupSourceMembers: [{ id: 2 }],
                selectedSourceGroupMembersLoading: true,
            },
            clearSelectedSourceGroup: methods.clearSelectedSourceGroup,
            defaultSourceGroupCategoryForCollection: methods.defaultSourceGroupCategoryForCollection,
        }

        methods.selectSourceGroupCollection.call(ctx, 'school')

        expect(ctx.assignUsersDialog.sourceGroupCollection).toBe('school')
        expect(ctx.assignUsersDialog.sourceGroupCategory).toBeNull()
        expect(ctx.assignUsersDialog.bulkSourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.selectedSourceGroupMembers).toEqual([])

        methods.selectSourceGroupCollection.call(ctx, 'school')

        expect(ctx.assignUsersDialog.sourceGroupCollection).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupCategory).toBeNull()
    })

    it('offers school, materials, and own takeover sources for own groups in that order', () => {
        const methods = (Groups as any).methods

        expect(methods.ownSourceGroupCollectionButtons.call({})).toEqual([
            { key: 'school', title: 'Schulgruppen', icon: 'mdi-domain' },
            { key: 'materials', title: 'Materialgruppen', icon: 'mdi-folder-multiple-outline' },
            { key: 'own', title: 'Eigene Gruppen', icon: 'mdi-account-group-outline' },
        ])
    })

    it('auto-selects the materials source category when choosing Materialgruppen', () => {
        const methods = (Groups as any).methods
        const ctx: any = {
            assignUsersDialog: {
                group: { id: 8, type: 'own' },
                sourceGroupCollection: null,
                sourceGroupCategory: null,
                bulkSourceGroupId: 3,
                sourceGroupId: 9,
                selectedSourceGroupMembers: [{ id: 1 }],
                selectedSourceGroupSourceMembers: [{ id: 2 }],
                selectedSourceGroupMembersLoading: true,
            },
            clearSelectedSourceGroup: methods.clearSelectedSourceGroup,
            defaultSourceGroupCategoryForCollection: methods.defaultSourceGroupCategoryForCollection,
        }

        methods.selectSourceGroupCollection.call(ctx, 'materials')

        expect(ctx.assignUsersDialog.sourceGroupCollection).toBe('materials')
        expect(ctx.assignUsersDialog.sourceGroupCategory).toBe('materials-groups')
        expect(ctx.assignUsersDialog.bulkSourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.selectedSourceGroupMembers).toEqual([])
    })

    it('returns the active source category buttons for own groups based on the selected collection', () => {
        const methods = (Groups as any).methods
        const ctx: any = {
            assignUsersDialog: {
                group: { id: 8, type: 'own' },
                sourceGroupCollection: 'own',
            },
            schoolSourceGroupCategoryButtons: vi.fn(() => [{ key: 'classes', title: 'Klassen' }]),
            materialsSourceGroupCategoryButtons: vi.fn(() => [{ key: 'materials-groups', title: 'Materialgruppen' }]),
            ownSourceGroupCategoryButtons: vi.fn(() => [{ key: 'own-groups', title: 'Eigene Gruppen' }]),
        }

        expect(methods.activeSourceGroupCategoryButtons.call(ctx)).toEqual([{ key: 'own-groups', title: 'Eigene Gruppen' }])

        ctx.assignUsersDialog.sourceGroupCollection = 'materials'

        expect(methods.activeSourceGroupCategoryButtons.call(ctx)).toEqual([{ key: 'materials-groups', title: 'Materialgruppen' }])

        ctx.assignUsersDialog.sourceGroupCollection = 'school'

        expect(methods.activeSourceGroupCategoryButtons.call(ctx)).toEqual([{ key: 'classes', title: 'Klassen' }])
    })

    it('returns the active source panel for own groups when Materialgruppen is selected', () => {
        const methods = (Groups as any).methods
        const ctx: any = {
            assignUsersDialog: {
                group: { id: 8, type: 'own' },
                sourceGroupCollection: 'materials',
            },
            activeSchoolSourceGroupsPanel: vi.fn(() => ({ key: 'classes', groups: [{ id: 1 }] })),
            activeMaterialsSourceGroupsPanel: vi.fn(() => ({ key: 'materials-groups', groups: [{ id: 2 }] })),
            activeOwnSourceGroupsPanel: vi.fn(() => ({ key: 'own-groups', groups: [{ id: 3 }] })),
        }

        expect(methods.activeSourceGroupsPanel.call(ctx)).toEqual({ key: 'materials-groups', groups: [{ id: 2 }] })
    })

    it('returns the active source category buttons for materials groups based on the selected collection', () => {
        const methods = (Groups as any).methods
        const ctx: any = {
            assignUsersDialog: {
                group: { id: 8, type: 'materials' },
                sourceGroupCollection: 'materials',
            },
            schoolSourceGroupCategoryButtons: vi.fn(() => [{ key: 'classes', title: 'Klassen' }]),
            materialsSourceGroupCategoryButtons: vi.fn(() => [{ key: 'materials-groups', title: 'Materialgruppen' }]),
        }

        expect(methods.activeSourceGroupCategoryButtons.call(ctx)).toEqual([{ key: 'materials-groups', title: 'Materialgruppen' }])

        ctx.assignUsersDialog.sourceGroupCollection = 'school'

        expect(methods.activeSourceGroupCategoryButtons.call(ctx)).toEqual([{ key: 'classes', title: 'Klassen' }])
    })

    it('returns the active materials source panel and falls back to the first available category', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                sourceGroupCategory: 'missing',
            },
            materialsSourceGroupCategoryButtons: vi.fn(() => [
                { key: 'materials-groups', title: 'Materialgruppen', groups: [{ id: 1 }] },
            ]),
        }

        expect(methods.activeMaterialsSourceGroupsPanel.call(ctx)).toEqual({ key: 'materials-groups', title: 'Materialgruppen', groups: [{ id: 1 }] })
    })

    it('hides the extra category buttons when only one source category exists', () => {
        const methods = (Groups as any).methods

        expect(methods.shouldShowSourceCategoryButtons.call({
            activeSourceGroupCategoryButtons: () => [{ key: 'materials-groups', title: 'Materialgruppen' }],
        })).toBe(false)

        expect(methods.shouldShowSourceCategoryButtons.call({
            activeSourceGroupCategoryButtons: () => [
                { key: 'classes', title: 'Klassen' },
                { key: 'teachers', title: 'Lehrer' },
            ],
        })).toBe(true)
    })

    it('returns the active own source panel and falls back to the first available own category', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                sourceGroupCategory: 'missing',
            },
            ownSourceGroupCategoryButtons: vi.fn(() => [
                { key: 'course-groups', title: 'Kursgruppen', groups: [{ id: 1 }] },
                { key: 'own-groups', title: 'Eigene Gruppen', groups: [{ id: 2 }] },
            ]),
        }

        expect(methods.activeOwnSourceGroupsPanel.call(ctx)).toEqual({ key: 'course-groups', title: 'Kursgruppen', groups: [{ id: 1 }] })

        ctx.assignUsersDialog.sourceGroupCategory = 'own-groups'

        expect(methods.activeOwnSourceGroupsPanel.call(ctx)).toEqual({ key: 'own-groups', title: 'Eigene Gruppen', groups: [{ id: 2 }] })
    })

    it('returns the active school source panel and falls back to the first available category', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                sourceGroupCategory: 'missing',
            },
            schoolSourceGroupCategoryButtons: vi.fn(() => [
                { key: 'classes', title: 'Klassen', groups: [{ id: 1 }] },
                { key: 'teachers', title: 'Lehrer', groups: [{ id: 2 }] },
            ]),
        }

        expect(methods.activeSchoolSourceGroupsPanel.call(ctx)).toEqual({ key: 'classes', title: 'Klassen', groups: [{ id: 1 }] })

        ctx.assignUsersDialog.sourceGroupCategory = 'teachers'

        expect(methods.activeSchoolSourceGroupsPanel.call(ctx)).toEqual({ key: 'teachers', title: 'Lehrer', groups: [{ id: 2 }] })
    })

    it('selects a school source category and clears stale source group selections', () => {
        const methods = (Groups as any).methods
        const ctx: any = {
            assignUsersDialog: {
                sourceGroupCategory: null,
                sourceGroupId: 99,
            },
            activeSourceGroupsPanel: vi.fn(() => ({
                key: 'classes',
                groups: [{ id: 1 }, { id: 2 }],
            })),
            clearSelectedSourceGroup: methods.clearSelectedSourceGroup,
        }

        methods.selectSchoolSourceGroupCategory.call(ctx, 'classes')

        expect(ctx.assignUsersDialog.sourceGroupCategory).toBe('classes')
        expect(ctx.assignUsersDialog.sourceGroupId).toBeNull()
    })

    it('toggles an already selected school source category off again', () => {
        const methods = (Groups as any).methods
        const ctx: any = {
            assignUsersDialog: {
                sourceGroupCategory: 'teachers',
                bulkSourceGroupId: 3,
                sourceGroupId: 9,
                selectedSourceGroupMembers: [{ id: 1 }],
                selectedSourceGroupSourceMembers: [{ id: 2 }],
                selectedSourceGroupMembersLoading: true,
            },
            activeSourceGroupsPanel: vi.fn(() => ({
                key: 'teachers',
                groups: [{ id: 2 }, { id: 3 }],
            })),
            clearSelectedSourceGroup: methods.clearSelectedSourceGroup,
        }

        methods.selectSchoolSourceGroupCategory.call(ctx, 'teachers')

        expect(ctx.assignUsersDialog.sourceGroupCategory).toBeNull()
        expect(ctx.assignUsersDialog.bulkSourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.sourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.selectedSourceGroupMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupSourceMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupMembersLoading).toBe(false)
    })

    it('loads members when selecting a source group for auswählen and toggles it off on second click', async () => {
        const methods = (Groups as any).methods
        const loadSelectedSourceGroupMembers = vi.fn()
        const ctx: any = {
            assignUsersDialog: {
                sourceGroupId: null,
                selectedSourceGroupMembers: [],
                selectedSourceGroupSourceMembers: [],
                selectedSourceGroupMembersLoading: false,
            },
            loadSelectedSourceGroupMembers,
            clearSelectedSourceGroup: methods.clearSelectedSourceGroup,
        }

        await methods.toggleSelectedSourceGroup.call(ctx, { id: 12, name: '1A' })

        expect(ctx.assignUsersDialog.sourceGroupId).toBe(12)
        expect(loadSelectedSourceGroupMembers).toHaveBeenCalledTimes(1)
        expect(loadSelectedSourceGroupMembers).toHaveBeenCalledWith({ id: 12, name: '1A' })

        await methods.toggleSelectedSourceGroup.call(ctx, { id: 12, name: '1A' })

        expect(ctx.assignUsersDialog.sourceGroupId).toBeNull()
        expect(ctx.assignUsersDialog.selectedSourceGroupMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupSourceMembers).toEqual([])
        expect(ctx.assignUsersDialog.selectedSourceGroupMembersLoading).toBe(false)
    })

    it('merges selected source-group members into one combined list', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                selectedSourceGroupMembers: [
                    { id: 7, user_id: 7, name: 'Registered User', email: 'registered@test.local' },
                ],
                selectedSourceGroupSourceMembers: [
                    { id: 101, user_id: 7, import116_id: 101, name: 'Registered User', email: 'registered@test.local', already_member: true, has_user_account: true },
                    { id: 102, user_id: null, import116_id: 102, name: 'Import Only', email: 'import@test.local', has_user_account: false },
                ],
            },
            readOnlyMemberKey: methods.readOnlyMemberKey,
            memberHasUserAccount: methods.memberHasUserAccount,
            mergeReadOnlyMember: methods.mergeReadOnlyMember,
            sortReadOnlyMembers: methods.sortReadOnlyMembers,
        }

        const rows = methods.selectedSourceGroupCombinedMembers.call(ctx)

        expect(rows).toEqual([
            { id: 102, user_id: null, import116_id: 102, name: 'Import Only', email: 'import@test.local', has_user_account: false, is_registered: false },
            { id: 101, user_id: 7, import116_id: 101, name: 'Registered User', email: 'registered@test.local', already_member: true, has_user_account: true, is_registered: true },
        ])
    })

    it('treats a selected source category as disabling the user search area', () => {
        const methods = (Groups as any).methods

        expect(methods.isSchoolSourceCategorySelected.call({
            assignUsersDialog: {
                group: { id: 1, type: 'school' },
                sourceGroupCategory: 'classes',
            },
        })).toBe(true)

        expect(methods.isSchoolSourceCategorySelected.call({
            assignUsersDialog: {
                group: { id: 2, type: 'school' },
                sourceGroupCategory: null,
            },
        })).toBe(false)

        expect(methods.isSchoolSourceCategorySelected.call({
            assignUsersDialog: {
                group: { id: 3, type: 'own' },
                sourceGroupCategory: 'own-groups',
            },
        })).toBe(true)
    })

    it('shows the source-members panel for automatic own course groups', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                group: { id: 10, type: 'own', teaching_course_id: 77 },
            },
            isAutomaticOwnCourseGroup: methods.isAutomaticOwnCourseGroup,
        }

        expect(methods.showSourceMembersPanel.call(ctx)).toBe(true)
    })

    it('shows the source-members panel for teacher school groups', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                group: { id: 11, type: 'school', name: 'Lehrer' },
            },
            isAutomaticOwnCourseGroup: methods.isAutomaticOwnCourseGroup,
        }

        expect(methods.showSourceMembersPanel.call(ctx)).toBe(true)
    })

    it('merges registered and all members into one read-only list and marks registered entries', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                group: { id: 11, type: 'school', name: '1A' },
                members: [
                    { id: 7, user_id: 7, name: 'Registered User', email: 'registered@test.local' },
                ],
                sourceMembers: [
                    { id: 101, user_id: 7, import116_id: 101, name: 'Registered User', email: 'registered@test.local', already_member: true, has_user_account: true },
                    { id: 102, user_id: null, import116_id: 102, name: 'Import Only', email: 'import@test.local', has_user_account: false },
                ],
            },
            showSourceMembersPanel: vi.fn(() => true),
            readOnlyMemberKey: methods.readOnlyMemberKey,
            memberHasUserAccount: methods.memberHasUserAccount,
            mergeReadOnlyMember: methods.mergeReadOnlyMember,
            sortReadOnlyMembers: methods.sortReadOnlyMembers,
        }

        const rows = methods.readOnlyCombinedMembers.call(ctx)

        expect(rows).toEqual([
            { id: 102, user_id: null, import116_id: 102, name: 'Import Only', email: 'import@test.local', has_user_account: false, is_registered: false },
            { id: 101, user_id: 7, import116_id: 101, name: 'Registered User', email: 'registered@test.local', already_member: true, has_user_account: true, is_registered: true },
        ])
    })

    it('does not mark parent contacts without user account as registered', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                group: { id: 12, type: 'school', name: '1A Eltern' },
                members: [
                    { id: 'import116.parent_contact:1:abc', name: 'Parent Contact', email: 'parent@test.local', has_user_account: false },
                ],
                sourceMembers: [
                    { id: 'import116.parent_contact:1:abc', name: 'Parent Contact', email: 'parent@test.local', has_user_account: false },
                ],
            },
            showSourceMembersPanel: vi.fn(() => true),
            readOnlyMemberKey: methods.readOnlyMemberKey,
            memberHasUserAccount: methods.memberHasUserAccount,
            mergeReadOnlyMember: methods.mergeReadOnlyMember,
            sortReadOnlyMembers: methods.sortReadOnlyMembers,
        }

        const rows = methods.readOnlyCombinedMembers.call(ctx)

        expect(rows).toEqual([
            { id: 'import116.parent_contact:1:abc', name: 'Parent Contact', email: 'parent@test.local', has_user_account: false, is_registered: false },
        ])
    })

    it('sorts read-only members by last_name and first_name', () => {
        const methods = (Groups as any).methods

        const rows = methods.sortReadOnlyMembers.call({}, [
            { id: 3, name: 'Zeta User', last_name: 'Zeta', first_name: 'Adam' },
            { id: 1, name: 'Alpha Berta', last_name: 'Alpha', first_name: 'Berta' },
            { id: 2, name: 'Alpha Anton', last_name: 'Alpha', first_name: 'Anton' },
        ])

        expect(rows.map((row: any) => row.id)).toEqual([2, 1, 3])
    })

    it('builds provider-based assignment payloads and selected member batches', () => {
        const methods = (Groups as any).methods
        const ctx = {
            memberAssignmentPayload: methods.memberAssignmentPayload,
            memberSelectionValue: methods.memberSelectionValue,
        }

        expect(methods.memberAssignmentPayload.call(ctx, {
            member_provider: 'import116.student',
            member_ref: 'import116.student:11',
        })).toMatchObject({
            member_provider: 'import116.student',
            member_ref: 'import116.student:11',
        })

        expect(methods.memberSelectionValue.call(ctx, {
            member_provider: 'teacher_list.teacher',
            member_ref: 'teacher_list.teacher:9',
        })).toBe('teacher_list.teacher:9')

        expect(methods.buildSelectedAssignableMemberPayloads.call(ctx, [
            { member_provider: 'import116.student', member_ref: 'import116.student:11' },
            { member_provider: 'teacher_list.teacher', member_ref: 'teacher_list.teacher:9' },
            { member_provider: 'user', member_ref: 'user:5' },
        ], [
            'teacher_list.teacher:9',
            'user:5',
        ])).toEqual([
            expect.objectContaining({ member_provider: 'teacher_list.teacher', member_ref: 'teacher_list.teacher:9' }),
            expect.objectContaining({ member_provider: 'user', member_ref: 'user:5' }),
        ])
    })

    it('schedules a reload while group sync is in progress', () => {
        vi.useFakeTimers()

        try {
            const methods = (Groups as any).methods
            const loadGroups = vi.fn()
            const ctx: any = {
                syncStatus: {
                    in_progress: true,
                    refresh_after_seconds: 8,
                },
                syncReloadTimer: null,
                loadGroups,
                clearSyncStatusReload: methods.clearSyncStatusReload,
            }

            methods.scheduleSyncStatusReload.call(ctx)

            expect(ctx.syncReloadTimer).not.toBeNull()

            vi.advanceTimersByTime(8000)

            expect(loadGroups).toHaveBeenCalledTimes(1)
            expect(ctx.syncReloadTimer).toBeNull()
        } finally {
            vi.useRealTimers()
        }
    })

    it('paginates dialog member lists in blocks of 100 entries', () => {
        const methods = (Groups as any).methods
        const members = Array.from({ length: 105 }, (_, index) => ({
            id: index + 1,
            name: `User ${index + 1}`,
            email: `user${index + 1}@test.local`,
        }))
        const ctx = {
            assignUsersDialog: {
                members,
                sourceMembers: [],
                membersPage: 2,
                readOnlyPage: 2,
            },
            showSourceMembersPanel: vi.fn(() => false),
            readOnlyCombinedMembers: methods.readOnlyCombinedMembers,
            readOnlyMemberKey: methods.readOnlyMemberKey,
            memberHasUserAccount: methods.memberHasUserAccount,
            mergeReadOnlyMember: methods.mergeReadOnlyMember,
            sortReadOnlyMembers: (rows: any[]) => rows,
            dialogPaginationSize: methods.dialogPaginationSize,
            shouldPaginateDialogList: methods.shouldPaginateDialogList,
            dialogPaginationPageCount: methods.dialogPaginationPageCount,
            normalizedDialogPage: methods.normalizedDialogPage,
            dialogPaginationSlice: methods.dialogPaginationSlice,
            paginatedAssignedMembers: methods.paginatedAssignedMembers,
            paginatedReadOnlyCombinedMembers: methods.paginatedReadOnlyCombinedMembers,
        }

        const assignedRows = methods.paginatedAssignedMembers.call(ctx)
        const readOnlyRows = methods.paginatedReadOnlyCombinedMembers.call(ctx)

        expect(assignedRows).toHaveLength(5)
        expect(assignedRows[0].id).toBe(101)
        expect(assignedRows[4].id).toBe(105)
        expect(readOnlyRows).toHaveLength(5)
        expect(readOnlyRows[0]).toMatchObject({ id: 101, is_registered: false })
        expect(methods.dialogPaginationSummary.call(ctx, 105, 2)).toBe('101-105 von 105')
    })

    it('clamps dialog pagination to the last available page after list changes', () => {
        const methods = (Groups as any).methods
        const ctx = {
            assignUsersDialog: {
                members: Array.from({ length: 150 }, (_, index) => ({ id: index + 1, name: `User ${index + 1}` })),
                sourceMembers: [],
                membersPage: 4,
                readOnlyPage: 3,
            },
            showSourceMembersPanel: vi.fn(() => false),
            readOnlyCombinedMembers: methods.readOnlyCombinedMembers,
            readOnlyMemberKey: methods.readOnlyMemberKey,
            memberHasUserAccount: methods.memberHasUserAccount,
            mergeReadOnlyMember: methods.mergeReadOnlyMember,
            sortReadOnlyMembers: (rows: any[]) => rows,
            dialogPaginationSize: methods.dialogPaginationSize,
            dialogPaginationPageCount: methods.dialogPaginationPageCount,
            normalizedDialogPage: methods.normalizedDialogPage,
        }

        methods.syncDialogPagination.call(ctx)

        expect(ctx.assignUsersDialog.membersPage).toBe(2)
        expect(ctx.assignUsersDialog.readOnlyPage).toBe(2)
    })

    it('splits school groups into Klassen, Lehrer, Eltern, Gesamtgruppen, and Weitere Gruppen panels', () => {
        const methods = (Groups as any).methods
        const ctx = {
            groups: [
                { id: 1, type: 'school', name: '1A', is_system_default: true },
                { id: 2, type: 'school', name: 'Lehrer', is_system_default: true },
                { id: 3, type: 'school', name: '1A Eltern', is_parent_group: true, is_system_default: true },
                { id: 4, type: 'school', name: '6A', is_system_default: true },
                { id: 5, type: 'school', name: 'Alle Schulmitglieder', is_system_default: true, is_all_school_members_group: true },
                { id: 6, type: 'school', name: 'AG Robotik', is_system_default: false },
                { id: 7, type: 'materials', name: 'Materialteam' },
            ],
            groupsByType(type: string) {
                return this.groups.filter((group: any) => group.type === type)
            },
            isTeacherSchoolGroup: methods.isTeacherSchoolGroup,
            isParentSchoolGroup: methods.isParentSchoolGroup,
            isAllSchoolMembersGroup: methods.isAllSchoolMembersGroup,
            isAutomaticSchoolGroup: methods.isAutomaticSchoolGroup,
        }

        const panels = methods.schoolSectionPanels.call(ctx)

        expect(panels).toEqual([
            {
                key: 'classes',
                title: 'Klassen',
                metaLabel: '(automatisch erstellt)',
                icon: 'mdi-google-classroom',
                groups: [
                    { id: 1, type: 'school', name: '1A', is_system_default: true },
                    { id: 4, type: 'school', name: '6A', is_system_default: true },
                ],
            },
            {
                key: 'teachers',
                title: 'Lehrer',
                metaLabel: '(automatisch erstellt)',
                icon: 'mdi-account-tie',
                groups: [
                    { id: 2, type: 'school', name: 'Lehrer', is_system_default: true },
                ],
            },
            {
                key: 'parents',
                title: 'Eltern',
                metaLabel: '(automatisch erstellt)',
                icon: 'mdi-account-multiple-outline',
                groups: [
                    { id: 3, type: 'school', name: '1A Eltern', is_parent_group: true, is_system_default: true },
                ],
            },
            {
                key: 'school-members',
                title: 'Gesamtgruppen',
                metaLabel: '(automatisch erstellt)',
                icon: 'mdi-account-school-outline',
                groups: [
                    { id: 5, type: 'school', name: 'Alle Schulmitglieder', is_system_default: true, is_all_school_members_group: true },
                ],
            },
            {
                key: 'other-school-groups',
                title: 'Weitere Gruppen',
                metaLabel: null,
                icon: 'mdi-shape-outline',
                groups: [
                    { id: 6, type: 'school', name: 'AG Robotik', is_system_default: false },
                ],
            },
        ])
    })

    it('keeps school expandable panels closed by default', () => {
        const data = (Groups as any).data()

        expect(data.schoolSectionPanelsOpen).toEqual([])
    })

    it('keeps own expandable panels closed by default', () => {
        const data = (Groups as any).data()

        expect(data.ownSectionPanelsOpen).toEqual([])
    })

    it('splits own groups into Kursgruppen, Eltern Kursgruppen, and Eigene Gruppen panels', () => {
        const methods = (Groups as any).methods
        const ctx = {
            groups: [
                { id: 1, type: 'own', name: 'Mathematik (1A)', teaching_course_id: 10, teaching_course_group_type: 'students' },
                { id: 2, type: 'own', name: 'Freie Gruppe', teaching_course_id: null },
                { id: 3, type: 'own', name: 'Mathematik (1A) Eltern', teaching_course_id: 10, teaching_course_group_type: 'parents', is_parent_group: true },
                { id: 4, type: 'own', name: 'Deutsch (2B)', teaching_course_id: 11, teaching_course_group_type: 'students' },
                { id: 5, type: 'materials', name: 'Materialteam' },
            ],
            groupsByType(type: string) {
                return this.groups.filter((group: any) => group.type === type)
            },
            isAutomaticOwnCourseGroup: methods.isAutomaticOwnCourseGroup,
            isAutomaticOwnCourseParentGroup: methods.isAutomaticOwnCourseParentGroup,
        }

        const panels = methods.ownSectionPanels.call(ctx)

        expect(panels).toEqual([
            {
                key: 'course-groups',
                title: 'Kursgruppen',
                metaLabel: '(automatisch erstellt)',
                icon: 'mdi-google-classroom',
                groups: [
                    { id: 1, type: 'own', name: 'Mathematik (1A)', teaching_course_id: 10, teaching_course_group_type: 'students' },
                    { id: 4, type: 'own', name: 'Deutsch (2B)', teaching_course_id: 11, teaching_course_group_type: 'students' },
                ],
            },
            {
                key: 'course-parent-groups',
                title: 'Eltern Kursgruppen',
                metaLabel: '(automatisch erstellt)',
                icon: 'mdi-account-multiple-outline',
                groups: [
                    { id: 3, type: 'own', name: 'Mathematik (1A) Eltern', teaching_course_id: 10, teaching_course_group_type: 'parents', is_parent_group: true },
                ],
            },
            {
                key: 'own-groups',
                title: 'Eigene Gruppen',
                metaLabel: null,
                icon: 'mdi-account-group-outline',
                groups: [
                    { id: 2, type: 'own', name: 'Freie Gruppe', teaching_course_id: null },
                ],
            },
        ])
    })

    it('keeps the group counter chip in a right-aligned meta area', () => {
        const source = readFileSync('resources/js/pages/admin/groups/Groups.vue', 'utf8')

        expect(source).toContain('class="groups-row-meta"')
        expect(source).toContain('class="groups-row-counter-chip"')
        expect(source).toContain('v-model="schoolSectionPanelsOpen"')
        expect(source).toContain('v-model="ownSectionPanelsOpen"')
        expect(source).toContain('showSourceUsersCounter(group)')
        expect(source).toContain('schoolSectionPanels()')
        expect(source).toContain('ownSectionPanels()')
        expect(source).toContain('isParentSchoolGroup(group)')
        expect(source).toContain('isAllSchoolMembersGroup(group)')
        expect(source).toContain('isAutomaticSchoolGroup(group)')
        expect(source).toContain('class="groups-panel-title-wrap"')
        expect(source).toContain('class="groups-panel-title-meta"')
        expect(source).toContain("title: 'Klassen'")
        expect(source).toContain("title: 'Lehrer'")
        expect(source).toContain("title: 'Eltern'")
        expect(source).toContain("title: 'Gesamtgruppen'")
        expect(source).toContain("title: 'Weitere Gruppen'")
        expect(source).toContain("title: 'Kursgruppen'")
        expect(source).toContain("title: 'Eltern Kursgruppen'")
        expect(source).toContain("metaLabel: '(automatisch erstellt)'")
        expect(source).toContain('readOnlyCombinedMembers()')
        expect(source).toContain('paginatedReadOnlyCombinedMembers()')
        expect(source).toContain('paginatedAssignedMembers()')
        expect(source).toContain('readOnlyCombinedMembersLoading()')
        expect(source).toContain("return 'Lade Mitglieder ...'")
        expect(source).toContain("return 'Keine Schulmitglieder gefunden.'")
        expect(source).toContain('dialogPaginationSize()')
        expect(source).toContain('shouldPaginateDialogList(readOnlyCombinedMembers().length)')
        expect(source).toContain('shouldPaginateDialogList(assignUsersDialog.members.length)')
        expect(source).toContain('dialogPaginationSummary(')
        expect(source).toContain('v-model="assignUsersDialog.readOnlyPage"')
        expect(source).toContain('v-model="assignUsersDialog.membersPage"')
        expect(source).toContain('class="groups-list-pagination"')
        expect(source).toContain('Synchronisierung läuft')
        expect(source).toContain('syncStatus.in_progress')
        expect(source).toContain('scheduleSyncStatusReload()')
        expect(source).toContain('clearSyncStatusReload()')
        expect(source).toContain('memberAssignmentPayload(member)')
        expect(source).toContain('memberSelectionValue(memberOrValue)')
        expect(source).toContain('buildSelectedAssignableMemberPayloads(rows = [], selectedValues = [])')
        expect(source).toContain('members: normalizedMembers')
        expect(source).toContain('member_ids: memberIds')
        expect(source).toContain('member_provider')
        expect(source).toContain('member_ref')
        expect(source).toContain('member.member_type_label')
        expect(source).toContain('user.member_type_label')
        expect(source).toContain('member.is_registered')
        expect(source).toContain('mdi-check-circle')
        expect(source).toContain('title="Registriert"')
        expect(source).toContain('.groups-panel-title-meta {')
        expect(source).toContain('isDeleteDisabled(group)')
        expect(source).toContain('deleteButtonTitle(group)')
        expect(source).toContain('showInlineMemberManagementButton(group)')
        expect(source).toContain('showReadOnlyAutoManagedNotice()')
        expect(source).toContain('openManageMembersDialog(section.type, group)')
        expect(source).toContain('mdi-account-edit-outline')
        expect(source).toContain('membersExpanded: false')
        expect(source).toContain('toggleAssignedMembersExpanded()')
        expect(source).toContain('collapseTakeoverSection()')
        expect(source).toContain('isTakeoverSectionDisabled()')
        expect(source).toContain('schoolSourceGroupCategoryButtons()')
        expect(source).toContain('ownSourceGroupCategoryButtons()')
        expect(source).toContain('ownSourceGroupCollectionButtons()')
        expect(source).toContain('materialsSourceGroupCategoryButtons()')
        expect(source).toContain('materialsSourceGroupCollectionButtons()')
        expect(source).toContain('activeMaterialsSourceGroupsPanel()')
        expect(source).toContain('activeSourceGroupCategoryButtons()')
        expect(source).toContain('shouldShowSourceCategoryButtons()')
        expect(source).toContain('activeSourceGroupsPanel()')
        expect(source).toContain('selectSourceGroupCollection(collection.key)')
        expect(source).toContain('defaultSourceGroupCategoryForCollection(collectionKey)')
        expect(source).toContain('selectSourceGroupCategory(panel.key)')
        expect(source).toContain('source-collection-')
        expect(source).toContain('source-category-')
        expect(source).toContain('sourceGroupCollection: null')
        expect(source).toContain('sourceGroupCategory: null')
        expect(source).toContain("assignUsersDialog.sourceGroupCategory === panel.key ? 'primary' : 'grey-lighten-1'")
        expect(source).toContain("2. Von Gruppe übernehmen")
        expect(source).toContain("v-else-if=\"!assignUsersDialog.readOnly && assignUsersDialog.group?.type === 'own'\"")
        expect(source).toContain("v-else-if=\"!assignUsersDialog.readOnly && assignUsersDialog.group?.type === 'materials'\"")
        expect(source).toContain('Schulgruppen')
        expect(source).toContain('Eigene Gruppen')
        expect(source).toContain('Materialgruppen')
        expect(source).toContain("title: 'Kursgruppen'")
        expect(source).toContain("title: 'Eltern Kursgruppen'")
        expect(source).toContain("title: 'Materialgruppen'")
        expect(source).toContain(":class=\"{ 'groups-assign-section--disabled': isSchoolSourceCategorySelected() }\"")
        expect(source).toContain(":disabled=\"isSchoolSourceCategorySelected()\"")
        expect(source).toContain(":class=\"{ 'groups-assign-section--disabled': isTakeoverSectionDisabled() }\"")
        expect(source).toContain('isSchoolSourceCategorySelected()')
        expect(source).toContain('school-source-group-')
        expect(source).toContain('Keine Gruppen in dieser Kategorie.')
        expect(source).toContain('Alle übernehmen')
        expect(source).toContain('toggleSelectedSourceGroup(sourceGroup)')
        expect(source).toContain('selectedSourceGroup()')
        expect(source).toContain('selectedSourceGroupCombinedMembers()')
        expect(source).toContain('selectedSourceGroupMembersLoading')
        expect(source).toContain('loadSelectedSourceGroupMembers(sourceGroup)')
        expect(source).toContain('clearSelectedSourceGroup()')
        expect(source).toContain('selectedSourceMemberAssignedRecord(member)')
        expect(source).toContain('Hinzufügen')
        expect(source).toContain('Entfernen')
        expect(source).toContain('display_name: member.display_name ?? member.name ?? null')
        expect(source).toContain('showSourceUsersCounter(sourceGroup)')
        expect(source).toContain('Number(sourceGroup.source_users_count || 0) }}/{{ Number(sourceGroup.members_count || 0)')
        expect(source).toContain('activeSchoolSourceGroupsPanel() && !selectedSourceGroup()')
        expect(source).toContain('Andere Gruppe wählen')
        expect(source).toContain('selectSchoolSourceGroupCategory(panel.key)')
        expect(source).toContain("title: 'Klassen'")
        expect(source).toContain("title: 'Lehrer'")
        expect(source).toContain("title: 'Eltern'")
        expect(source).toContain("title: 'Weitere Gruppen'")
        expect(source).not.toContain('Keine Schulgruppen verfügbar.')
        expect(source).not.toContain('>Weiter<')
        expect(source).not.toContain('>Zurück<')
        expect(source).not.toContain('3. Von Lehrer:innen übernehmen')
        expect(source).not.toContain('4. Von Schüler:innen übernehmen')
        expect(source).not.toContain('openAssignUsersDialog(selectedGroupByType(section.type))')
        expect(source).not.toContain('mdi-account-plus-outline')
        expect(source).toContain('.groups-row-meta {')
        expect(source).toContain('.groups-list-pagination {')
        expect(source).toContain('justify-content: flex-end;')
        expect(source).toContain('margin-left: auto;')
    })
})
