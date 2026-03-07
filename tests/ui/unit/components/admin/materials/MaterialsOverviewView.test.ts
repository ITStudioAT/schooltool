import { describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import MaterialsOverviewView from '@/pages/admin/materials/components/views/MaterialsOverviewView.vue'

describe('MaterialsOverviewView', () => {
    it('keeps linked topic metadata from classification tree in subjects overview', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            typeOptions: [],
            statusOptions: [],
            defaultStatusValue: 'inbox',
            classificationTree: [
                {
                    id: 10,
                    name: 'Deutsch',
                    topics: [
                        {
                            id: 20,
                            name: 'Literatur',
                            is_linked: true,
                            linked_permission: 'read_only',
                            linked_permission_label: 'NUR LESEN',
                            units: [],
                        },
                    ],
                },
            ],
        }

        const items = methods.buildSubjectsContentsOverviewItems.call(vm, [], {})
        expect(items).toHaveLength(1)

        const topic = items[0]?.topics?.[0]
        expect(topic).toBeTruthy()
        expect(topic.isLinked).toBe(true)
        expect(topic.linkedPermission).toBe('read_only')
        expect(topic.linkedPermissionLabel).toBe('NUR LESEN')
    })

    it('keeps duplicate unit names separated by unit_id in subjects overview', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            typeOptions: [],
            statusOptions: [],
            defaultStatusValue: 'inbox',
            classificationTree: [
                {
                    id: 10,
                    name: 'Deutsch',
                    topics: [
                        {
                            id: 20,
                            name: 'Literatur',
                            units: [
                                { id: 100, name: 'Informatik', is_linked: true, linked_permission: 'read_only', linked_permission_label: 'NUR LESEN' },
                                { id: 101, name: 'Informatik', is_linked: true, linked_permission: 'read_only', linked_permission_label: 'NUR LESEN' },
                            ],
                        },
                    ],
                },
            ],
        }

        const cards = [
            {
                id: 501,
                title: 'Material A',
                type: '',
                status: 'inbox',
                attachments: [],
                is_linked: true,
                linked_permission: 'read_only',
                linked_permission_label: 'NUR LESEN',
                classifications: [
                    {
                        id: 9001,
                        subject_id: 10,
                        topic_id: 20,
                        unit_id: 100,
                        subject: 'Deutsch',
                        topic: 'Literatur',
                        unit: 'Informatik',
                    },
                ],
            },
            {
                id: 502,
                title: 'Material B',
                type: '',
                status: 'inbox',
                attachments: [],
                is_linked: true,
                linked_permission: 'read_only',
                linked_permission_label: 'NUR LESEN',
                classifications: [
                    {
                        id: 9002,
                        subject_id: 10,
                        topic_id: 20,
                        unit_id: 101,
                        subject: 'Deutsch',
                        topic: 'Literatur',
                        unit: 'Informatik',
                    },
                ],
            },
        ]

        const items = methods.buildSubjectsContentsOverviewItems.call(vm, cards, {})
        expect(Array.isArray(items)).toBe(true)
        expect(items).toHaveLength(1)

        const topic = items[0]?.topics?.[0]
        expect(topic).toBeTruthy()
        expect(Array.isArray(topic.units)).toBe(true)
        expect(topic.units).toHaveLength(2)

        const unitsById = new Map(topic.units.map((unit) => [Number(unit?.id || 0), unit]))
        const firstUnit = unitsById.get(100)
        const secondUnit = unitsById.get(101)

        expect(firstUnit).toBeTruthy()
        expect(secondUnit).toBeTruthy()
        expect(firstUnit.materials).toHaveLength(1)
        expect(secondUnit.materials).toHaveLength(1)
        expect(Number(firstUnit.materials[0]?.id || 0)).toBe(501)
        expect(Number(secondUnit.materials[0]?.id || 0)).toBe(502)
    })

    it('hides source toggle and forces workspace source when overview mode is forced', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const computed = MaterialsOverviewView?.computed || {}
        const vm = {
            ...methods,
            forcedOverviewMode: 'subjects_contents',
            overviewViewMode: 'subjects_contents',
            isSubjectsContentsOverview: true,
            subjectsContentsSource: 'shared',
        }

        expect(computed.showSubjectsContentsSourceToggle.call(vm)).toBe(false)
        expect(computed.isWorkspaceSubjectsContentsSource.call(vm)).toBe(true)
    })

    it('switches subjects source and loads shared/workspace data', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const loadSubjectsContentsOverview = vi.fn()
        const loadSharedObjectsForMe = vi.fn()
        const vm = {
            ...methods,
            forcedOverviewMode: '',
            subjectsContentsSource: 'workspace',
            isSubjectsContentsOverview: true,
            loadSubjectsContentsOverview,
            loadSharedObjectsForMe,
        }

        methods.setSubjectsContentsSource.call(vm, 'shared')
        expect(vm.subjectsContentsSource).toBe('shared')
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(loadSubjectsContentsOverview).not.toHaveBeenCalled()

        methods.setSubjectsContentsSource.call(vm, 'workspace')
        expect(vm.subjectsContentsSource).toBe('workspace')
        expect(loadSubjectsContentsOverview).toHaveBeenCalledWith({ force: true })
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(2)
    })

    it('resets subjects source to workspace when switching to subjects overview mode', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const loadSubjectsContentsOverview = vi.fn()
        const loadSharedObjectsForMe = vi.fn()
        const vm = {
            ...methods,
            forcedOverviewMode: '',
            overviewViewMode: 'list',
            subjectsContentsSource: 'shared',
            loadSubjectsContentsOverview,
            loadSharedObjectsForMe,
        }

        methods.setOverviewMode.call(vm, 'subjects_contents')

        expect(vm.overviewViewMode).toBe('subjects_contents')
        expect(vm.subjectsContentsSource).toBe('workspace')
        expect(loadSubjectsContentsOverview).toHaveBeenCalledWith({ force: true })
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
    })

    it('loads shared objects together with subjects contents on initial card load', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const index = vi.fn().mockResolvedValue(true)
        const loadSubjectsContentsOverview = vi.fn().mockResolvedValue(undefined)
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const refreshFilterCountCards = vi.fn()
        const refreshAllListedAttachmentBytes = vi.fn()
        const vm = {
            ...methods,
            isLoading: false,
            currentPage: 1,
            isSubjectsContentsOverview: true,
            materialCardStore: {
                index,
                meta: {
                    current_page: 1,
                    last_page: 1,
                },
            },
            refreshFilterCountCards,
            refreshAllListedAttachmentBytes,
            loadSubjectsContentsOverview,
            loadSharedObjectsForMe,
        }

        await methods.loadCards.call(vm)

        expect(index).toHaveBeenCalledWith(1)
        expect(loadSubjectsContentsOverview).toHaveBeenCalledWith({ force: true })
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
    })

    it('openShareDialog opens persistent dummy dialog when share actions are disabled', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const loadShareAssignments = vi.fn()
        const vm = {
            ...methods,
            enableShareButtons: false,
            shareDialogOpen: false,
            shareDummyDialogOpen: false,
            shareAssignments: [{ id: 1 }],
            shareAssignmentsError: 'x',
            shareTarget: {
                level: '',
                id: null,
                label: '',
                parentLabel: '',
            },
            loadShareAssignments,
        }

        methods.openShareDialog.call(vm, {
            level: 'subject',
            id: 7,
            label: 'Mathematik',
        })

        expect(vm.shareDummyDialogOpen).toBe(true)
        expect(vm.shareDialogOpen).toBe(false)
        expect(vm.shareTarget.level).toBe('subject')
        expect(vm.shareTarget.id).toBe(7)
        expect(vm.shareTarget.label).toBe('Mathematik')
        expect(loadShareAssignments).not.toHaveBeenCalled()
    })

    it('normalizes and sorts shared objects from inbox users response', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            materialCardStore: {
                config: {
                    workspace: {
                        name: 'Teamraum Mathematik',
                    },
                },
            },
        }
        const cards = methods.normalizeSharedObjectsForMeResponse.call(vm, [
            {
                label: 'Lehrer Eins',
                school_label: 'CDGym',
                shared_items: [
                    {
                        rule_id: 10,
                        scope_type: 'subject',
                        scope_label: 'Fach',
                        scope_object_label: 'Mathematik',
                        scope_path_label: 'Mathematik',
                        permission: 'read_write',
                        permission_label: 'LESEN/SCHREIBEN',
                        is_archived: false,
                        updated_at: '2026-03-01T10:00:00+00:00',
                    },
                    {
                        rule_id: 11,
                        is_archived: true,
                    },
                ],
            },
            {
                label: 'Lehrer Zwei',
                school_label: 'Abendgymnasium',
                shared_items: [
                    {
                        rule_id: 20,
                        scope_type: 'all',
                        scope_label: 'Workspace',
                        scope_object_label: 'Alle Materialien',
                        permission: 'read_only',
                        permission_label: 'NUR LESEN',
                        is_archived: false,
                        updated_at: '2026-03-03T08:30:00+00:00',
                        hierarchy: [
                            {
                                id: 1,
                                name: 'Mathematik',
                                materials: [
                                    {
                                        id: 97,
                                        title: 'Kopfmaterial',
                                        status: 'done',
                                    },
                                ],
                                topics: [
                                    {
                                        id: 2,
                                        name: 'Algebra',
                                        materials: [
                                            {
                                                id: 98,
                                                title: 'Themamaterial',
                                                status: 'in_progress',
                                            },
                                        ],
                                        units: [
                                            {
                                                id: 4,
                                                name: 'Leere Einheit',
                                                materials: [],
                                            },
                                            {
                                                id: 3,
                                                name: 'Einheit 1',
                                                materials: [
                                                    {
                                                        id: 99,
                                                        title: 'Lineare Gleichungen',
                                                        status: 'done',
                                                    },
                                                ],
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        ])

        expect(Array.isArray(cards)).toBe(true)
        expect(cards).toHaveLength(2)
        expect(cards[0].ruleId).toBe(20)
        expect(cards[0].fromUserLabel).toBe('Lehrer Zwei')
        expect(cards[0].materialsCount).toBe(3)
        expect(cards[0].scopePathLabel).toBe('Teamraum Mathematik')
        expect(Array.isArray(cards[0].hierarchy)).toBe(true)
        expect(cards[0].hierarchy[0].name).toBe('Mathematik')
        expect(cards[0].hierarchy[0].materials[0].title).toBe('Kopfmaterial')
        expect(cards[0].hierarchy[0].topics[0].materials[0].title).toBe('Themamaterial')
        expect(cards[0].hierarchy[0].topics[0].units[0].name).toBe('Leere Einheit')
        expect(cards[0].hierarchy[0].topics[0].units[1].materials[0].title).toBe('Lineare Gleichungen')
        expect(cards[1].ruleId).toBe(10)
        expect(cards[1].scopeLabel).toBe('Fach')
    })

    it('renders shared cards with tighter grid breakpoints', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        const wrapper = shallowMount(MaterialsOverviewView, {
            data() {
                return {
                    overviewViewMode: 'subjects_contents',
                    subjectsContentsSource: 'shared',
                    isLoadingSharedObjectsForMe: false,
                    sharedObjectsForMeError: '',
                    sharedObjectsForMeCards: [
                        {
                            ruleId: 20,
                            scopeType: 'all',
                            scopeLabel: 'Workspace',
                            scopeObjectLabel: 'Alle Materialien',
                            scopePathLabel: 'Teamraum Mathematik',
                            permission: 'read_only',
                            permissionLabel: 'NUR LESEN',
                            fromUserLabel: 'Lehrer Zwei',
                            fromSchoolLabel: 'Abendgymnasium',
                            sharedAt: '2026-03-03T08:30:00+00:00',
                            hierarchy: [],
                            materialsCount: 0,
                        },
                    ],
                    openSharedHierarchyCards: {},
                }
            },
            global: {
                stubs: {
                    'v-row': { template: '<div><slot /></div>' },
                    VRow: { template: '<div><slot /></div>' },
                    'v-col': {
                        props: ['cols', 'sm', 'md', 'xl'],
                        template: '<div data-test="shared-col" :data-cols="cols" :data-sm="sm" :data-md="md" :data-xl="xl"><slot /></div>',
                    },
                    VCol: {
                        props: ['cols', 'sm', 'md', 'xl'],
                        template: '<div data-test="shared-col" :data-cols="cols" :data-sm="sm" :data-md="md" :data-xl="xl"><slot /></div>',
                    },
                    'v-card': { template: '<div><slot /></div>' },
                    VCard: { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    VCardText: { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    VCardTitle: { template: '<div><slot /></div>' },
                    'v-card-actions': { template: '<div><slot /></div>' },
                    VCardActions: { template: '<div><slot /></div>' },
                    'v-chip': { template: '<span><slot /></span>' },
                    VChip: { template: '<span><slot /></span>' },
                    'v-icon': { template: '<i><slot /></i>' },
                    VIcon: { template: '<i><slot /></i>' },
                    'v-btn': { template: '<button type="button"><slot /></button>' },
                    VBtn: { template: '<button type="button"><slot /></button>' },
                    'v-alert': { template: '<div><slot /></div>' },
                    VAlert: { template: '<div><slot /></div>' },
                    'v-list': { template: '<div><slot /></div>' },
                    VList: { template: '<div><slot /></div>' },
                    'v-list-item': { template: '<div><slot /></div>' },
                    VListItem: { template: '<div><slot /></div>' },
                    'v-progress-linear': { template: '<div />' },
                    VProgressLinear: { template: '<div />' },
                    'v-skeleton-loader': { template: '<div />' },
                    VSkeletonLoader: { template: '<div />' },
                    'v-text-field': { template: '<input />' },
                    VTextField: { template: '<input />' },
                    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                    'v-tooltip': { template: '<div><slot name="activator" :props="{}" /><slot /></div>' },
                    VTooltip: { template: '<div><slot name="activator" :props="{}" /><slot /></div>' },
                    'v-expand-transition': { template: '<div><slot /></div>' },
                    VExpandTransition: { template: '<div><slot /></div>' },
                    'v-spacer': { template: '<div />' },
                    VSpacer: { template: '<div />' },
                },
            },
        })

        try {
            const column = wrapper.get('[data-test="shared-col"]')

            expect(column.attributes('data-cols')).toBe('12')
            expect(column.attributes('data-sm')).toBe('6')
            expect(column.attributes('data-md')).toBe('4')
            expect(column.attributes('data-xl')).toBe('3')
            expect(wrapper.html()).toContain('Teamraum Mathematik')
        } finally {
            beforeMountSpy.mockRestore()
        }
    })

    it('keeps shared cards side by side even when a hierarchy card is open', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        const wrapper = shallowMount(MaterialsOverviewView, {
            data() {
                return {
                    overviewViewMode: 'subjects_contents',
                    subjectsContentsSource: 'shared',
                    isLoadingSharedObjectsForMe: false,
                    sharedObjectsForMeError: '',
                    sharedObjectsForMeCards: [
                        {
                            ruleId: 20,
                            scopeType: 'all',
                            scopeLabel: 'Workspace',
                            scopeObjectLabel: 'Alle Materialien',
                            scopePathLabel: 'Teamraum Mathematik',
                            permission: 'read_only',
                            permissionLabel: 'NUR LESEN',
                            fromUserLabel: 'Lehrer Zwei',
                            fromSchoolLabel: 'Abendgymnasium',
                            sharedAt: '2026-03-03T08:30:00+00:00',
                            hierarchy: [
                                {
                                    id: 1,
                                    name: 'Mathematik',
                                    topics: [
                                        {
                                            id: 2,
                                            name: 'Algebra',
                                            units: [
                                                {
                                                    id: 3,
                                                    name: 'Einheit 1',
                                                    materials: [
                                                        {
                                                            id: 99,
                                                            title: 'Lineare Gleichungen',
                                                            statusLabel: 'Erledigt',
                                                            status: 'done',
                                                            attachmentsCount: 0,
                                                        },
                                                    ],
                                                },
                                            ],
                                        },
                                    ],
                                },
                            ],
                            materialsCount: 1,
                        },
                    ],
                    openSharedHierarchyCards: {
                        'shared-rule-20': true,
                    },
                }
            },
            global: {
                stubs: {
                    'v-row': { template: '<div><slot /></div>' },
                    VRow: { template: '<div><slot /></div>' },
                    'v-col': {
                        props: ['cols', 'sm', 'md', 'xl'],
                        template: '<div data-test="shared-col" :data-cols="cols" :data-sm="sm" :data-md="md" :data-xl="xl"><slot /></div>',
                    },
                    VCol: {
                        props: ['cols', 'sm', 'md', 'xl'],
                        template: '<div data-test="shared-col" :data-cols="cols" :data-sm="sm" :data-md="md" :data-xl="xl"><slot /></div>',
                    },
                    'v-card': { template: '<div><slot /></div>' },
                    VCard: { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    VCardText: { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    VCardTitle: { template: '<div><slot /></div>' },
                    'v-card-actions': { template: '<div><slot /></div>' },
                    VCardActions: { template: '<div><slot /></div>' },
                    'v-chip': { template: '<span><slot /></span>' },
                    VChip: { template: '<span><slot /></span>' },
                    'v-icon': { template: '<i><slot /></i>' },
                    VIcon: { template: '<i><slot /></i>' },
                    'v-btn': { template: '<button type="button"><slot /></button>' },
                    VBtn: { template: '<button type="button"><slot /></button>' },
                    'v-alert': { template: '<div><slot /></div>' },
                    VAlert: { template: '<div><slot /></div>' },
                    'v-list': { template: '<div><slot /></div>' },
                    VList: { template: '<div><slot /></div>' },
                    'v-list-item': { template: '<div><slot /></div>' },
                    VListItem: { template: '<div><slot /></div>' },
                    'v-progress-linear': { template: '<div />' },
                    VProgressLinear: { template: '<div />' },
                    'v-skeleton-loader': { template: '<div />' },
                    VSkeletonLoader: { template: '<div />' },
                    'v-text-field': { template: '<input />' },
                    VTextField: { template: '<input />' },
                    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                    'v-tooltip': { template: '<div><slot name="activator" :props="{}" /><slot /></div>' },
                    VTooltip: { template: '<div><slot name="activator" :props="{}" /><slot /></div>' },
                    'v-expand-transition': { template: '<div><slot /></div>' },
                    VExpandTransition: { template: '<div><slot /></div>' },
                    'v-spacer': { template: '<div />' },
                    VSpacer: { template: '<div />' },
                },
            },
        })

        try {
            const column = wrapper.get('[data-test="shared-col"]')

            expect(column.attributes('data-cols')).toBe('12')
            expect(column.attributes('data-sm')).toBe('6')
            expect(column.attributes('data-md')).toBe('4')
            expect(column.attributes('data-xl')).toBe('3')
            expect(wrapper.html()).toContain('Lineare Gleichungen')
        } finally {
            beforeMountSpy.mockRestore()
        }
    })

    it('normalizes shared hierarchy materials with optional attachments', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = { ...methods }

        const normalized = methods.normalizeSharedHierarchyMaterial.call(vm, {
            id: 77,
            title: 'Arbeitsblatt',
            attachments_count: 2,
            attachments: [
                { id: 701, name: 'blatt.pdf', attachment_type: 'file' },
                { id: 702, name: 'lösung.pdf', attachment_type: 'file' },
            ],
        })

        expect(normalized.id).toBe(77)
        expect(normalized.title).toBe('Arbeitsblatt')
        expect(normalized.attachmentsCount).toBe(2)
        expect(Array.isArray(normalized.attachments)).toBe(true)
        expect(normalized.attachments).toHaveLength(2)
        expect(normalized.attachments[0].id).toBe(701)
    })

    it('opens shared material attachments via attachment manager in read-only mode', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const openAttachmentManager = vi.fn().mockResolvedValue(undefined)
        const fetchSharedMaterialAttachments = vi.fn().mockResolvedValue([
            { id: 9001, name: 'aufgabe.pdf', attachment_type: 'file', download_url: '/dl', preview_url: '/pv', shared_rule_id: 77, shared_material_id: 99 },
        ])
        const vm = {
            ...methods,
            openAttachmentManager,
            fetchSharedMaterialAttachments,
        }

        await methods.openSharedMaterialAttachments.call(vm, 77, {
            id: 99,
            title: 'Lineare Gleichungen',
        })

        expect(fetchSharedMaterialAttachments).toHaveBeenCalledTimes(1)
        expect(fetchSharedMaterialAttachments).toHaveBeenCalledWith(77, 99)
        expect(openAttachmentManager).toHaveBeenCalledTimes(1)
        expect(openAttachmentManager).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 99,
                title: 'Lineare Gleichungen',
                attachments: expect.arrayContaining([expect.objectContaining({ id: 9001 })]),
                is_linked: true,
                linked_permission: 'read_only',
                linked_permission_label: 'NUR LESEN',
            }),
        )
    })

    it('routes shared tree material clicks into the read-only shared detail handler', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const openSharedMaterialDetail = vi.fn()
        const vm = {
            ...methods,
            openSharedMaterialDetail,
        }

        await methods.openSharedMaterialFromTree.call(vm, {
            ruleId: 77,
            material: {
                id: 99,
                title: 'Lineare Gleichungen',
            },
        })

        expect(openSharedMaterialDetail).toHaveBeenCalledTimes(1)
        expect(openSharedMaterialDetail).toHaveBeenCalledWith(77, {
            id: 99,
            title: 'Lineare Gleichungen',
        })
    })

    it('routes shared tree attachment clicks into the read-only shared attachment handler', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const openSharedMaterialAttachments = vi.fn()
        const vm = {
            ...methods,
            openSharedMaterialAttachments,
        }

        await methods.openSharedMaterialAttachmentsFromTree.call(vm, {
            ruleId: 77,
            material: {
                id: 99,
                title: 'Lineare Gleichungen',
            },
        })

        expect(openSharedMaterialAttachments).toHaveBeenCalledTimes(1)
        expect(openSharedMaterialAttachments).toHaveBeenCalledWith(77, {
            id: 99,
            title: 'Lineare Gleichungen',
        })
    })

    it('refreshes shared download url when attachment url is stale', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const fetchSharedMaterialAttachments = vi.fn().mockResolvedValue([
            {
                id: 5001,
                name: 'blatt.pdf',
                download_url: '/api/admin/materials/attachments/5001/download?rule_id=9&material_id=77',
                preview_url: '/api/admin/materials/attachments/5001/preview?rule_id=9&material_id=77',
                download_docx_url: '',
            },
        ])
        const vm = {
            ...methods,
            fetchSharedMaterialAttachments,
        }

        const attachment = {
            id: 5001,
            name: 'blatt.pdf',
            shared_rule_id: 9,
            shared_material_id: 77,
            download_url: '',
            preview_url: '',
            download_docx_url: '',
        }

        const refreshedUrl = await methods.refreshSharedAttachmentDownloadUrl.call(vm, attachment)
        expect(fetchSharedMaterialAttachments).toHaveBeenCalledTimes(1)
        expect(fetchSharedMaterialAttachments).toHaveBeenCalledWith(9, 77)
        expect(refreshedUrl).toContain('/attachments/5001/download')
        expect(String(attachment.download_url)).toContain('/attachments/5001/download')
        expect(String(attachment.preview_url)).toContain('/attachments/5001/preview')
    })

    it('keeps file attachments visible when only preview url is available', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const rows = methods.fileAttachments.call({}, {
            attachments: [
                { id: 1, attachment_type: 'file', preview_url: '/preview/1', download_url: '' },
                { id: 2, attachment_type: 'file', preview_url: '', download_url: '/download/2' },
                { id: 3, attachment_type: 'link', preview_url: '/preview/3', download_url: '' },
            ],
        })

        expect(Array.isArray(rows)).toBe(true)
        expect(rows).toHaveLength(2)
        expect(rows.map((row) => Number(row?.id || 0))).toEqual([1, 2])
    })

    it('opens shared material detail in read-only dialog mode', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const fetchSharedMaterialDetail = vi.fn().mockResolvedValue({
            id: 555,
            title: 'Geteiltes Detail',
            attachments: [],
            classifications: [],
            linked_permission: 'read_only',
        })
        const vm = {
            ...methods,
            detailDialogCard: null,
            detailDialogOpen: false,
            detailDialogLoading: false,
            detailDialogReadOnlyMode: false,
            detailDeleteStep: 0,
            sanitizeDialogCard: methods.sanitizeDialogCard,
            fetchSharedMaterialDetail,
        }

        await methods.openSharedMaterialDetail.call(vm, 77, { id: 555, title: 'Geteiltes Detail' })

        expect(fetchSharedMaterialDetail).toHaveBeenCalledTimes(1)
        expect(fetchSharedMaterialDetail).toHaveBeenCalledWith(77, 555)
        expect(vm.detailDialogOpen).toBe(true)
        expect(vm.detailDialogLoading).toBe(false)
        expect(vm.detailDialogReadOnlyMode).toBe(true)
        expect(vm.detailDialogCard).toBeTruthy()
        expect(vm.detailDialogCard.id).toBe(555)
        expect(vm.detailDialogCard.title).toBe('Geteiltes Detail')
    })

    it('computes detail dialog read-only actions from override flag', () => {
        const computed = MaterialsOverviewView?.computed || {}
        const vm = {
            readOnlyMaterialActions: false,
            detailDialogReadOnlyMode: true,
        }

        expect(computed.detailDialogReadOnlyActions.call(vm)).toBe(true)
    })

    it('falls back to attachment dialog context when card is not in overview cards', () => {
        const computed = MaterialsOverviewView?.computed || {}
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            cards: [],
            attachmentDialogCardId: 1234,
            attachmentDialogCardContext: {
                id: 1234,
                is_linked: true,
                linked_permission: 'read_only',
            },
        }

        const dialogCard = computed.attachmentDialogCard.call(vm)
        expect(dialogCard).toBeTruthy()
        expect(dialogCard.id).toBe(1234)
        expect(methods.cardAllowsFieldEditing.call(vm, dialogCard)).toBe(false)
    })

    it('toggles shared hierarchy cards by rule id', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            openSharedHierarchyCards: {},
        }

        expect(methods.isSharedHierarchyOpen.call(vm, 55)).toBe(false)

        methods.toggleSharedHierarchy.call(vm, 55)
        expect(methods.isSharedHierarchyOpen.call(vm, 55)).toBe(true)

        methods.toggleSharedHierarchy.call(vm, 55)
        expect(methods.isSharedHierarchyOpen.call(vm, 55)).toBe(false)
    })
})
