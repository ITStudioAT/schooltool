import { beforeEach, describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import axios from 'axios'
import MaterialsOverviewView from '@/pages/admin/materials/components/views/MaterialsOverviewView.vue'

const notificationNotifyMock = vi.fn()

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: notificationNotifyMock,
    }),
}))

describe('MaterialsOverviewView', () => {
    beforeEach(() => {
        notificationNotifyMock.mockClear()
    })

    it('renders material type chips in the overview header row', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        const wrapper = shallowMount(MaterialsOverviewView, {
            data() {
                return {
                    overviewViewMode: 'subjects_contents',
                    isLoading: false,
                    isDeletingId: null,
                    isSavingEdit: false,
                    isUnlinkingId: null,
                    isUnlinkingUnitId: null,
                    isUnlinkingTopicId: null,
                    subjectsTreeWorkspaceStructureExpanded: false,
                    allListedAttachmentBytes: 1468006,
                    materialCardStore: {
                        cards: [
                            {
                                attachments: [
                                    { size_bytes: 1468006 },
                                ],
                            },
                        ],
                        config: {
                            type_values: [
                                { value: 'worksheet', label: 'Arbeitsblatt' },
                                { value: 'assignment', label: 'Auftrag' },
                            ],
                            status_values: [
                                { value: 'inbox', label: 'Inbox' },
                                { value: 'done', label: 'Erledigt' },
                            ],
                            storage_capacity_bytes: 21474836480,
                        },
                    },
                }
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    MaterialsOverviewHeader: {
                        template: '<div class="materials-overview-header-stub"><slot /></div>',
                    },
                    'v-chip': {
                        template: '<span class="v-chip"><slot /></span>',
                    },
                },
            },
        })

        try {
            const header = wrapper.get('.materials-overview-header-stub')

            expect(header.text()).toContain('Alle')
            expect(header.text()).toContain('Arbeitsblatt')
            expect(header.text()).toContain('Auftrag')
            expect(header.text()).toContain('Inbox')
            expect(header.text()).toContain('Erledigt')
            expect(header.text()).toContain('Belegter Speicher: 1.4 MB / 20 GB')
        } finally {
            beforeMountSpy.mockRestore()
        }
    })

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

    it('refreshes shared tree data after shared node changes even outside shared source mode', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            isSharedSubjectsContentsSource: false,
            loadSharedObjectsForMe,
        }

        await methods.refreshSharedStructureTree.call(vm)

        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
    })

    it('archives a shared rule and reloads shared objects', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const performSharedInboxMutation = vi.fn().mockResolvedValue({ rule_id: 55, is_archived: true })
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            isArchivingSharedRuleId: null,
            isUnarchivingSharedRuleId: null,
            performSharedInboxMutation,
            loadSharedObjectsForMe,
        }

        await methods.archiveSharedRule.call(vm, 55)

        expect(performSharedInboxMutation).toHaveBeenCalledWith({
            method: 'post',
            url: '/api/admin/materials/shares/inbox/archive',
            data: {
                rule_id: 55,
            },
            successMessage: 'Freigabe archiviert.',
            errorMessage: 'Freigabe konnte nicht archiviert werden.',
        })
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(vm.isArchivingSharedRuleId).toBeNull()
    })

    it('unarchives a shared rule and reloads shared objects', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const performSharedInboxMutation = vi.fn().mockResolvedValue({ rule_id: 56, is_archived: false })
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            isArchivingSharedRuleId: null,
            isUnarchivingSharedRuleId: null,
            performSharedInboxMutation,
            loadSharedObjectsForMe,
        }

        await methods.unarchiveSharedRule.call(vm, 56)

        expect(performSharedInboxMutation).toHaveBeenCalledWith({
            method: 'post',
            url: '/api/admin/materials/shares/inbox/unarchive',
            data: {
                rule_id: 56,
            },
            successMessage: 'Freigabe aktiviert.',
            errorMessage: 'Freigabe konnte nicht aktiviert werden.',
        })
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(vm.isUnarchivingSharedRuleId).toBeNull()
    })

    it('keeps workspace structure mode active while refreshing workspace tree', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const loadCards = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            subjectsTreeWorkspaceStructureExpanded: true,
            loadCards,
        }

        await methods.refreshWorkspaceStructureTree.call(vm)

        expect(loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
        expect(vm.subjectsTreeWorkspaceStructureExpanded).toBe(true)
    })

    it('refreshes the overview and keeps the shared tree section plus shared source active', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const loadCards = vi.fn(async function (page, options) {
            this.subjectsTreeSharedForMeExpanded = false
            this.subjectsTreeSharedForMeArchiveExpanded = false
            this.subjectsContentsSource = 'workspace'
        })
        const syncSubjectsTreeSectionToUrl = vi.fn()
        const vm = {
            ...methods,
            isSubjectsContentsOverview: true,
            forcedOverviewMode: '',
            subjectsContentsSource: 'shared',
            subjectsTreeSharedForMeExpanded: true,
            subjectsTreeSharedForMeArchiveExpanded: false,
            loadCards,
            currentSubjectsTreeSection: methods.currentSubjectsTreeSection,
            applySubjectsTreeSection: methods.applySubjectsTreeSection,
            normalizeSubjectsTreeSection: methods.normalizeSubjectsTreeSection,
            syncSubjectsTreeSectionToUrl,
            canSelectSubjectsContentsSource: methods.canSelectSubjectsContentsSource,
        }

        await methods.handleOverviewRefresh.call(vm)

        expect(loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
        expect(vm.subjectsTreeSharedForMeExpanded).toBe(true)
        expect(vm.subjectsTreeSharedForMeArchiveExpanded).toBe(false)
        expect(vm.subjectsContentsSource).toBe('shared')
        expect(syncSubjectsTreeSectionToUrl).toHaveBeenLastCalledWith('shared')
    })

    it('reads the workspace tree section from the url query', () => {
        const methods = MaterialsOverviewView?.methods || {}

        window.history.pushState({}, '', '/admin/materials?main_action=overview&overview_section=archive')

        expect(methods.readSubjectsTreeSectionFromUrl.call({
            subjectsTreeSectionQueryKey: methods.subjectsTreeSectionQueryKey,
            normalizeSubjectsTreeSection: methods.normalizeSubjectsTreeSection,
            $route: null,
        })).toBe('archive')
    })

    it('syncs the workspace tree section into the current url', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const replaceState = vi.spyOn(window.history, 'replaceState')

        window.history.pushState({}, '', '/admin/materials?main_action=overview')

        methods.syncSubjectsTreeSectionToUrl.call({
            subjectsTreeSectionQueryKey: methods.subjectsTreeSectionQueryKey,
            normalizeSubjectsTreeSection: methods.normalizeSubjectsTreeSection,
            currentSubjectsTreeSection: methods.currentSubjectsTreeSection,
            subjectsTreeSharedForMeExpanded: false,
            subjectsTreeSharedForMeArchiveExpanded: true,
        }, 'archive')

        expect(replaceState).toHaveBeenCalled()
        expect(window.location.search).toContain('main_action=overview')
        expect(window.location.search).toContain('overview_section=archive')

        replaceState.mockRestore()
    })

    it('toggles shared and archive tree sections while keeping the url in sync', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const syncSubjectsTreeSectionToUrl = vi.fn()
        const vm = {
            subjectsTreeSharedForMeExpanded: false,
            subjectsTreeSharedForMeArchiveExpanded: false,
            applySubjectsTreeSection: methods.applySubjectsTreeSection,
            normalizeSubjectsTreeSection: methods.normalizeSubjectsTreeSection,
            syncSubjectsTreeSectionToUrl,
        }

        methods.toggleSubjectsTreeSharedForMeExpanded.call(vm)
        expect(vm.subjectsTreeSharedForMeExpanded).toBe(true)
        expect(vm.subjectsTreeSharedForMeArchiveExpanded).toBe(false)
        expect(syncSubjectsTreeSectionToUrl).toHaveBeenLastCalledWith('shared')

        methods.toggleSubjectsTreeSharedForMeExpanded.call(vm)
        expect(vm.subjectsTreeSharedForMeExpanded).toBe(false)
        expect(syncSubjectsTreeSectionToUrl).toHaveBeenLastCalledWith('workspace')

        methods.toggleSubjectsTreeSharedForMeArchiveExpanded.call(vm)
        expect(vm.subjectsTreeSharedForMeExpanded).toBe(false)
        expect(vm.subjectsTreeSharedForMeArchiveExpanded).toBe(true)
        expect(syncSubjectsTreeSectionToUrl).toHaveBeenLastCalledWith('archive')
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

    it('provides readable labels for shared insert target levels', () => {
        const methods = MaterialsOverviewView?.methods || {}

        expect(methods.sharedInsertLevelLabel.call({}, 'all')).toBe('Freigabe')
        expect(methods.sharedInsertLevelLabel.call({}, 'subject')).toBe('Fach')
        expect(methods.sharedInsertLevelLabel.call({}, 'topic')).toBe('Thema')
        expect(methods.sharedInsertLevelLabel.call({}, 'unit')).toBe('Bereich')
        expect(methods.sharedInsertLevelLabel.call({}, 'material')).toBe('Material')
        expect(methods.sharedInsertLevelLabel.call({}, 'unknown')).toBe('Element')
    })

    it('keeps shared insert actions visible even before local target structures are loaded', () => {
        const methods = MaterialsOverviewView?.methods || {}

        const noWorkspaceVm = {
            ...methods,
            subjectsContentsOverviewItems: [],
        }
        expect(methods.canShowSharedInsertButton.call(noWorkspaceVm, 'subject')).toBe(true)
        expect(methods.canShowSharedInsertButton.call(noWorkspaceVm, 'topic')).toBe(true)
        expect(methods.canShowSharedInsertButton.call(noWorkspaceVm, 'unit')).toBe(true)
        expect(methods.canShowSharedInsertButton.call(noWorkspaceVm, 'material')).toBe(true)

        const subjectOnlyVm = {
            ...methods,
            subjectsContentsOverviewItems: [
                { id: 1, name: 'Mathematik', topics: [] },
            ],
        }
        expect(methods.canShowSharedInsertButton.call(subjectOnlyVm, 'topic')).toBe(true)
        expect(methods.canShowSharedInsertButton.call(subjectOnlyVm, 'unit')).toBe(true)
        expect(methods.canShowSharedInsertButton.call(subjectOnlyVm, 'material')).toBe(true)

        const topicVm = {
            ...methods,
            subjectsContentsOverviewItems: [
                { id: 1, name: 'Mathematik', topics: [{ id: 2, name: 'Algebra' }] },
            ],
        }
        expect(methods.canShowSharedInsertButton.call(topicVm, 'unit')).toBe(true)
    })

    it('opens a persistent confirm dialog when shared subject Einordnen is clicked', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            subjectsContentsOverviewItems: [],
            sharedSubjectInsertDialogOpen: false,
            sharedSubjectInsertDraft: {
                ruleId: null,
                subjectId: null,
                label: '',
            },
        }

        methods.openSharedInsertDraft.call(vm, {
            level: 'subject',
            ruleId: 21,
            targetId: 9,
            label: 'Informatik',
        })

        expect(vm.sharedSubjectInsertDialogOpen).toBe(true)
        expect(vm.sharedSubjectInsertDraft).toEqual({
            ruleId: 21,
            subjectId: 9,
            label: 'Informatik',
        })
    })

    it('opens a persistent topic insert dialog when shared topic Einordnen is clicked', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            subjectsContentsOverviewItems: [{ id: 7, name: 'Deutsch', topics: [] }],
            sharedTopicInsertDialogOpen: false,
            sharedTopicInsertDraft: {
                ruleId: null,
                topicId: null,
                label: '',
                parentLabel: '',
                targetSubjectId: null,
                nodeData: null,
            },
        }

        methods.openSharedInsertDraft.call(vm, {
            level: 'topic',
            ruleId: 31,
            targetId: 18,
            label: 'Digitale Kompetenzen',
            parentLabel: 'Medienbildung',
            nodeData: { id: 18, name: 'Digitale Kompetenzen', materials: [], units: [] },
        })

        expect(vm.sharedTopicInsertDialogOpen).toBe(true)
        expect(vm.sharedTopicInsertDraft).toEqual({
            ruleId: 31,
            topicId: 18,
            label: 'Digitale Kompetenzen',
            parentLabel: 'Medienbildung',
            targetSubjectId: null,
            nodeData: { id: 18, name: 'Digitale Kompetenzen', materials: [], units: [] },
        })
    })

    it('opens a persistent unit insert dialog when shared unit Einordnen is clicked', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            subjectsContentsOverviewItems: [
                { id: 7, name: 'Deutsch', topics: [{ id: 3, name: 'Grammatik' }] },
            ],
            sharedUnitInsertDialogOpen: false,
            sharedUnitInsertDraft: {
                ruleId: null,
                unitId: null,
                label: '',
                parentLabel: '',
                targetSubjectId: null,
                targetTopicId: null,
                nodeData: null,
            },
        }

        methods.openSharedInsertDraft.call(vm, {
            level: 'unit',
            ruleId: 32,
            targetId: 19,
            label: 'E-Mails',
            parentLabel: 'Digitale Kompetenzen',
            nodeData: { id: 19, name: 'E-Mails', materials: [] },
        })

        expect(vm.sharedUnitInsertDialogOpen).toBe(true)
        expect(vm.sharedUnitInsertDraft).toEqual({
            ruleId: 32,
            unitId: 19,
            label: 'E-Mails',
            parentLabel: 'Digitale Kompetenzen',
            targetSubjectId: null,
            targetTopicId: null,
            nodeData: { id: 19, name: 'E-Mails', materials: [] },
        })
    })

    it('opens a persistent material insert dialog when shared material Einordnen is clicked', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            subjectsContentsOverviewItems: [
                { id: 2, name: 'Informatik', topics: [] },
            ],
            sharedMaterialInsertDialogOpen: false,
            sharedMaterialInsertDraft: {
                ruleId: null,
                materialId: null,
                label: '',
                parentLabel: '',
                targetSubjectId: null,
                targetTopicId: null,
                targetUnitId: null,
                sourceTopicId: null,
                sourceUnitId: null,
            },
        }

        methods.openSharedInsertDraft.call(vm, {
            level: 'material',
            ruleId: 33,
            targetId: 91,
            label: 'Arbeitsblatt',
            parentLabel: 'Informatik / Digitale Kompetenzen / E-Mails',
            sourceTopicId: 21,
            sourceUnitId: 31,
        })

        expect(vm.sharedMaterialInsertDialogOpen).toBe(true)
        expect(vm.sharedMaterialInsertDraft).toEqual({
            ruleId: 33,
            materialId: 91,
            label: 'Arbeitsblatt',
            parentLabel: 'Informatik / Digitale Kompetenzen / E-Mails',
            targetSubjectId: null,
            targetTopicId: null,
            targetUnitId: null,
            sourceTopicId: 21,
            sourceUnitId: 31,
        })
    })

    it('renders empty shared insert target messages in red', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})
        const mountWithData = (overrides = {}) => shallowMount(MaterialsOverviewView, {
            data() {
                return {
                    sharedTopicInsertDialogOpen: false,
                    sharedUnitInsertDialogOpen: false,
                    sharedMaterialInsertDialogOpen: false,
                    sharedTopicInsertDraft: {
                        ruleId: 31,
                        topicId: 18,
                        label: 'Digitale Kompetenzen',
                        parentLabel: 'Medienbildung',
                        targetSubjectId: null,
                        nodeData: null,
                    },
                    sharedUnitInsertDraft: {
                        ruleId: 32,
                        unitId: 19,
                        label: 'E-Mails',
                        parentLabel: 'Digitale Kompetenzen',
                        targetSubjectId: null,
                        targetTopicId: null,
                        nodeData: null,
                    },
                    sharedMaterialInsertDraft: {
                        ruleId: 33,
                        materialId: 91,
                        label: 'Arbeitsblatt',
                        parentLabel: 'Informatik / Digitale Kompetenzen / E-Mails',
                        targetSubjectId: null,
                        targetTopicId: null,
                        targetUnitId: null,
                        sourceTopicId: 21,
                        sourceUnitId: 31,
                    },
                    subjectsContentsOverviewItems: [],
                    ...overrides,
                }
            },
            global: {
                stubs: {
                    MaterialsOverviewHeader: true,
                    MaterialsOverviewSortBar: true,
                    MaterialsSubjectsContentsTree: true,
                    MaterialsOverviewFilters: true,
                    MaterialsCardsList: true,
                    MaterialsCardsGrid: true,
                    MaterialsCardsAlphaList: true,
                    MaterialsDetailDialog: true,
                    MaterialsShareDialog: true,
                    MaterialsCreateInlineForm: true,
                    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    VCard: { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    VCardTitle: { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    VCardText: { template: '<div><slot /></div>' },
                    'v-card-actions': { template: '<div><slot /></div>' },
                    VCardActions: { template: '<div><slot /></div>' },
                    'v-btn': { template: '<button type="button"><slot /></button>' },
                    VBtn: { template: '<button type="button"><slot /></button>' },
                    'v-spacer': { template: '<div />' },
                    VSpacer: { template: '<div />' },
                },
            },
        })

        try {
            const topicWrapper = mountWithData({
                sharedTopicInsertDialogOpen: true,
            })
            const unitWrapper = mountWithData({
                sharedUnitInsertDialogOpen: true,
                subjectsContentsOverviewItems: [
                    {
                        id: 7,
                        name: 'Informatik',
                        topics: [
                            { id: 0, name: '' },
                        ],
                    },
                ],
                sharedUnitInsertDraft: {
                    ruleId: 32,
                    unitId: 19,
                    label: 'E-Mails',
                    parentLabel: 'Digitale Kompetenzen',
                    targetSubjectId: 7,
                    targetTopicId: null,
                    nodeData: null,
                },
            })
            const materialWrapper = mountWithData({
                sharedMaterialInsertDialogOpen: true,
                subjectsContentsOverviewItems: [
                    {
                        id: 7,
                        name: 'Informatik',
                        topics: [
                            {
                                id: 11,
                                name: 'Digitale Kompetenzen',
                                units: [],
                            },
                        ],
                    },
                ],
                sharedMaterialInsertDraft: {
                    ruleId: 33,
                    materialId: 91,
                    label: 'Arbeitsblatt',
                    parentLabel: 'Informatik / Digitale Kompetenzen / E-Mails',
                    targetSubjectId: 7,
                    targetTopicId: 11,
                    targetUnitId: null,
                    sourceTopicId: 21,
                    sourceUnitId: 31,
                },
            })

            expect(topicWrapper.get('.text-error').text()).toContain('Es sind noch keine Fächer im Workspace vorhanden.')
            expect(unitWrapper.get('.text-error').text()).toContain('Für das ausgewählte Fach sind keine Themen verfügbar.')
            expect(materialWrapper.get('.text-error').text()).toContain('Für das ausgewählte Thema sind keine Bereiche vorhanden. Das Material wird im Thema eingeordnet.')
        } finally {
            beforeMountSpy.mockRestore()
        }
    })

    it('confirms shared subject Einordnen and refreshes shared/workspace trees', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const performSharedInboxMutationWithResponse = vi.fn().mockResolvedValue({
            status: 202,
            message: 'Fach wird im Hintergrund eingeordnet.',
            data: {
                operation_id: 'subject-op-1',
                refresh_after_seconds: 3,
            },
        })
        const trackPendingSharedImport = vi.fn().mockReturnValue(true)
        const vm = {
            ...methods,
            performSharedInboxMutationWithResponse,
            trackPendingSharedImport,
            closeSharedSubjectInsertDialog: methods.closeSharedSubjectInsertDialog,
            sharedSubjectInsertDialogLoading: false,
            sharedSubjectInsertDialogOpen: true,
            sharedSubjectInsertDraft: {
                ruleId: 44,
                subjectId: 12,
                label: 'Digitale Grundlagen',
            },
        }

        await methods.confirmSharedSubjectInsert.call(vm)

        expect(performSharedInboxMutationWithResponse).toHaveBeenCalledWith(
            expect.objectContaining({
                method: 'post',
                url: '/api/admin/materials/shares/inbox/subjects/12/insert-tree',
                data: {
                    rule_id: 44,
                },
            })
        )
        expect(trackPendingSharedImport).toHaveBeenCalledWith(
            expect.objectContaining({ status: 202 }),
            expect.objectContaining({
                queuedMessage: 'Fach "Digitale Grundlagen" wird im Hintergrund eingeordnet.',
            })
        )
        expect(vm.sharedSubjectInsertDialogOpen).toBe(false)
        expect(vm.sharedSubjectInsertDraft).toEqual({
            ruleId: null,
            subjectId: null,
            label: '',
        })
    })

    it('confirms shared topic Einordnen into selected workspace subject and refreshes trees', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const performSharedInboxMutationWithResponse = vi.fn().mockResolvedValue({
            status: 202,
            message: 'Thema wird im Hintergrund eingeordnet.',
            data: {
                operation_id: 'topic-op-1',
                refresh_after_seconds: 3,
            },
        })
        const trackPendingSharedImport = vi.fn().mockReturnValue(true)
        const vm = {
            ...methods,
            performSharedInboxMutationWithResponse,
            trackPendingSharedImport,
            closeSharedTopicInsertDialog: methods.closeSharedTopicInsertDialog,
            sharedTopicInsertDialogLoading: false,
            sharedTopicInsertDialogOpen: true,
            sharedTopicInsertDraft: {
                ruleId: 55,
                topicId: 12,
                label: 'Digitale Kompetenzen',
                parentLabel: 'Medienbildung',
                targetSubjectId: 9,
                nodeData: { id: 12, name: 'Digitale Kompetenzen' },
            },
        }

        await methods.confirmSharedTopicInsert.call(vm)

        expect(performSharedInboxMutationWithResponse).toHaveBeenCalledWith(expect.objectContaining({
            method: 'post',
            url: '/api/admin/materials/shares/inbox/topics/12/insert-tree',
            data: {
                rule_id: 55,
                target_subject_id: 9,
            },
        }))
        expect(trackPendingSharedImport).toHaveBeenCalledWith(
            expect.objectContaining({ status: 202 }),
            expect.objectContaining({
                queuedMessage: 'Thema "Digitale Kompetenzen" wird im Hintergrund eingeordnet.',
            })
        )
        expect(vm.sharedTopicInsertDialogOpen).toBe(false)
    })

    it('reuses an existing target topic with the same name when shared topic Einordnen is confirmed', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const axiosPost = vi.spyOn(axios, 'post')
        axiosPost.mockImplementation(() => {
            throw new Error('axios.post should not be called')
        })

        const vm = {
            ...methods,
            subjectsContentsOverviewItems: [
                {
                    id: 9,
                    name: 'Informatik',
                    topics: [
                        { id: 120, name: 'Digitale Kompetenzen' },
                    ],
                },
            ],
        }

        const result = await methods.ensureSharedTopicInsertTargetTopic.call(vm, 9, 'Digitale Kompetenzen')

        expect(result).toBe(120)
        expect(axiosPost).not.toHaveBeenCalled()
        axiosPost.mockRestore()
    })

    it('offers shared unit insert subjects only when they already contain topics', () => {
        const computed = MaterialsOverviewView?.computed || {}
        const vm = {
            subjectsContentsOverviewItems: [
                { id: 1, name: 'Mathematik', topics: [] },
                { id: 2, name: 'Informatik', topics: [{ id: 21, name: 'Digitale Kompetenzen' }] },
            ],
            sharedUnitInsertDraft: {
                targetSubjectId: 2,
            },
        }

        expect(computed.sharedUnitInsertSubjectOptions.call(vm)).toEqual([
            { id: 2, name: 'Informatik' },
        ])
        expect(computed.sharedUnitInsertTopicOptions.call(vm)).toEqual([
            { id: 21, name: 'Digitale Kompetenzen' },
        ])
    })

    it('offers shared material targets only below workspace root', () => {
        const computed = MaterialsOverviewView?.computed || {}
        const vm = {
            subjectsContentsOverviewItems: [
                {
                    id: 2,
                    name: 'Informatik',
                    topics: [
                        {
                            id: 21,
                            name: 'Digitale Kompetenzen',
                            units: [
                                { id: 31, name: 'E-Mails' },
                            ],
                        },
                    ],
                },
            ],
            sharedMaterialInsertDraft: {
                targetSubjectId: 2,
                targetTopicId: 21,
            },
        }

        expect(computed.sharedMaterialInsertSubjectOptions.call(vm)).toEqual([
            { id: 2, name: 'Informatik' },
        ])
        expect(computed.sharedMaterialInsertTopicOptions.call(vm)).toEqual([
            { id: 21, name: 'Digitale Kompetenzen' },
        ])
        expect(computed.sharedMaterialInsertUnitOptions.call(vm)).toEqual([
            { id: 31, name: 'E-Mails' },
        ])
    })

    it('confirms shared unit Einordnen into selected workspace topic and refreshes trees', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const performSharedInboxMutationWithResponse = vi.fn().mockResolvedValue({
            status: 202,
            message: 'Bereich wird im Hintergrund eingeordnet.',
            data: {
                operation_id: 'unit-op-1',
                refresh_after_seconds: 3,
            },
        })
        const trackPendingSharedImport = vi.fn().mockReturnValue(true)
        const vm = {
            ...methods,
            performSharedInboxMutationWithResponse,
            trackPendingSharedImport,
            closeSharedUnitInsertDialog: methods.closeSharedUnitInsertDialog,
            sharedUnitInsertDialogLoading: false,
            sharedUnitInsertDialogOpen: true,
            sharedUnitInsertDraft: {
                ruleId: 56,
                unitId: 44,
                label: 'E-Mails',
                parentLabel: 'Digitale Kompetenzen',
                targetSubjectId: 9,
                targetTopicId: 120,
                nodeData: { id: 44, name: 'E-Mails' },
            },
        }

        await methods.confirmSharedUnitInsert.call(vm)

        expect(performSharedInboxMutationWithResponse).toHaveBeenCalledWith(expect.objectContaining({
            method: 'post',
            url: '/api/admin/materials/shares/inbox/units/44/insert-tree',
            data: {
                rule_id: 56,
                target_topic_id: 120,
            },
        }))
        expect(trackPendingSharedImport).toHaveBeenCalledWith(
            expect.objectContaining({ status: 202 }),
            expect.objectContaining({
                queuedMessage: 'Bereich "E-Mails" wird im Hintergrund eingeordnet.',
            })
        )
        expect(vm.sharedUnitInsertDialogOpen).toBe(false)
    })

    it('reuses an existing target unit with the same name when shared unit Einordnen is confirmed', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const ensureSharedTopicInsertTargetUnit = vi.fn()
        const vm = {
            ...methods,
            ensureSharedTopicInsertTargetUnit,
            subjectsContentsOverviewItems: [
                {
                    id: 2,
                    name: 'Informatik',
                    topics: [
                        {
                            id: 21,
                            name: 'Digitale Kompetenzen',
                            units: [
                                { id: 88, name: 'E-Mails' },
                            ],
                        },
                    ],
                },
            ],
        }

        const result = await methods.ensureSharedUnitInsertTargetUnit.call(vm, 21, 'E-Mails')

        expect(result).toBe(88)
        expect(ensureSharedTopicInsertTargetUnit).not.toHaveBeenCalled()
    })

    it('confirms shared material Einordnen into selected workspace target and refreshes trees', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const performSharedInboxMutation = vi.fn().mockResolvedValue({ id: 700 })
        const loadCards = vi.fn().mockResolvedValue(undefined)
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const closeSharedMaterialInsertDialog = vi.fn()
        const vm = {
            ...methods,
            performSharedInboxMutation,
            loadCards,
            loadSharedObjectsForMe,
            closeSharedMaterialInsertDialog,
            sharedMaterialInsertDialogLoading: false,
            sharedMaterialInsertTargetId: 31,
            sharedMaterialInsertDraft: {
                ruleId: 57,
                materialId: 905,
                label: 'Arbeitsblatt',
                parentLabel: 'Informatik / Digitale Kompetenzen / E-Mails',
                targetSubjectId: 2,
                targetTopicId: 21,
                targetUnitId: 31,
                sourceTopicId: 21,
                sourceUnitId: 44,
            },
        }

        await methods.confirmSharedMaterialInsert.call(vm)

        expect(performSharedInboxMutation).toHaveBeenCalledWith(expect.objectContaining({
            method: 'post',
            url: '/api/admin/materials/shares/inbox/material-insert',
            data: {
                rule_id: 57,
                material_id: 905,
                target_level: 'unit',
                target_id: 31,
                source_topic_id: 21,
                source_unit_id: 44,
            },
        }))
        expect(closeSharedMaterialInsertDialog).toHaveBeenCalledWith(true)
        expect(loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
    })

    it('reloads completed queued shared imports and refreshes the workspace data once', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const originalAxios = (globalThis as any).axios
        const axiosGet = vi.fn().mockResolvedValue({
            data: {
                data: {
                    status: 'completed',
                    message: 'Workspace eingeordnet.',
                    refresh_after_seconds: 3,
                },
            },
        })
        ;(globalThis as any).axios = {
            get: axiosGet,
        }
        const vm = {
            ...methods,
            pendingSharedImportOperations: {
                abc123: {
                    operationId: 'abc123',
                    refreshAfterSeconds: 3,
                },
            },
            loadCards: vi.fn().mockResolvedValue(undefined),
            loadSharedObjectsForMe: vi.fn().mockResolvedValue(undefined),
            showWorkspaceAfterSharedInsert: vi.fn(),
            clearPendingSharedImportReload: vi.fn(),
            schedulePendingSharedImportReload: vi.fn(),
        }

        try {
            await methods.reloadPendingSharedImportOperations.call(vm)

            expect(axiosGet).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/import-operations/abc123')
            expect(vm.showWorkspaceAfterSharedInsert).toHaveBeenCalledTimes(1)
            expect(vm.loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
            expect(vm.loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
            expect(notificationNotifyMock).toHaveBeenCalledWith(expect.objectContaining({
                message: 'Workspace eingeordnet.',
                type: 'success',
            }))
            expect(vm.pendingSharedImportOperations).toEqual({})
            expect(vm.clearPendingSharedImportReload).toHaveBeenCalledTimes(1)
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
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
                        rule_id: 15,
                        scope_id: 501,
                        scope_type: 'material',
                        scope_label: 'Material',
                        scope_object_label: 'Zettel',
                        scope_path_label: 'Informatik - Algebra - Einheit 1',
                        permission: 'read_write',
                        permission_label: 'LESEN/SCHREIBEN',
                        is_archived: false,
                        updated_at: '2026-03-05T10:00:00+00:00',
                    },
                    {
                        rule_id: 10,
                        scope_type: 'subject',
                        scope_label: 'Fach',
                        scope_object_label: 'Mathematik',
                        scope_path_label: 'Informatik - Alle Themen - Alle Einheiten',
                        permission: 'read_write',
                        permission_label: 'LESEN/SCHREIBEN',
                        is_archived: false,
                        updated_at: '2026-03-01T10:00:00+00:00',
                    },
                    {
                        rule_id: 9,
                        scope_type: 'subject',
                        scope_label: 'Fach',
                        scope_object_label: 'Biologie',
                        scope_path_label: 'Biologie - Alle Themen - Alle Einheiten',
                        permission: 'read_only',
                        permission_label: 'NUR LESEN',
                        is_archived: false,
                        updated_at: '2026-03-06T09:00:00+00:00',
                    },
                    {
                        rule_id: 16,
                        scope_type: 'material',
                        scope_label: 'Material',
                        scope_object_label: 'Arbeitsblatt',
                        scope_path_label: 'Biologie - Einstieg',
                        permission: 'read_only',
                        permission_label: 'NUR LESEN',
                        is_archived: false,
                        updated_at: '2026-03-02T10:00:00+00:00',
                    },
                    {
                        rule_id: 11,
                        is_archived: true,
                    },
                ],
            },
            {
                label: 'Lehrer Zwei',
                email: 'lehrer.zwei@example.test',
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
                    {
                        rule_id: 13,
                        scope_type: 'unit',
                        scope_label: 'Bereich',
                        scope_object_label: 'Bereich A',
                        scope_path_label: 'Informatik - Thema 1 - Bereich A',
                        permission: 'full_access',
                        permission_label: 'VOLLZUGRIFF',
                        is_archived: false,
                        updated_at: '2026-03-01T08:30:00+00:00',
                    },
                    {
                        rule_id: 14,
                        scope_type: 'unit',
                        scope_label: 'Bereich',
                        scope_object_label: 'Bereich B',
                        scope_path_label: 'Informatik - Thema 1 - Bereich B',
                        permission: 'full_access',
                        permission_label: 'VOLLZUGRIFF',
                        is_archived: false,
                        updated_at: '2026-03-04T08:30:00+00:00',
                    },
                ],
            },
        ])

        expect(Array.isArray(cards)).toBe(true)
        expect(cards).toHaveLength(7)
        expect(cards[0].ruleId).toBe(20)
        expect(cards[0].fromUserLabel).toBe('Lehrer Zwei')
        expect(cards[0].fromUserEmail).toBe('lehrer.zwei@example.test')
        expect(cards[0].materialsCount).toBe(3)
        expect(cards[0].scopeObjectLabel).toBe('Teamraum Mathematik')
        expect(cards[0].scopePathLabel).toBe('Teamraum Mathematik')
        expect(Array.isArray(cards[0].hierarchy)).toBe(true)
        expect(cards[0].hierarchy[0].name).toBe('Mathematik')
        expect(cards[0].hierarchy[0].materials[0].title).toBe('Kopfmaterial')
        expect(cards[0].hierarchy[0].topics[0].materials[0].title).toBe('Themamaterial')
        expect(cards[0].hierarchy[0].topics[0].units[0].name).toBe('Leere Einheit')
        expect(cards[0].hierarchy[0].topics[0].units[1].materials[0].title).toBe('Lineare Gleichungen')
        expect(cards[1].ruleId).toBe(9)
        expect(cards[1].scopePathLabel).toBe('Fach')
        expect(cards[2].ruleId).toBe(10)
        expect(cards[2].scopePathLabel).toBe('Fach')
        expect(cards[3].ruleId).toBe(13)
        expect(cards[4].ruleId).toBe(14)
        expect(cards[5].ruleId).toBe(16)
        expect(cards[6].ruleId).toBe(15)
        expect(cards[1].scopeLabel).toBe('Fach')
    })

    it('normalizes workspace tree selection payloads for created nodes', () => {
        const methods = MaterialsOverviewView?.methods || {}

        expect(methods.normalizeWorkspaceTreeSelection.call({}, {
            level: 'subject',
            nodeId: 5,
        })).toEqual({
            subjectId: 5,
            topicId: null,
            unitId: null,
        })

        expect(methods.normalizeWorkspaceTreeSelection.call({}, {
            level: 'topic',
            nodeId: 8,
            parentSubjectId: 3,
        })).toEqual({
            subjectId: 3,
            topicId: 8,
            unitId: null,
        })

        expect(methods.normalizeWorkspaceTreeSelection.call({}, {
            level: 'unit',
            nodeId: 13,
            parentSubjectId: 3,
            parentTopicId: 8,
        })).toEqual({
            subjectId: 3,
            topicId: 8,
            unitId: 13,
        })
    })

    it('keeps a temporary workspace tree selection while the workspace overview reloads', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const selectionsDuringLoad: any[] = []
        const vm = {
            subjectsTreeWorkspaceSelection: null,
            normalizeWorkspaceTreeSelection: methods.normalizeWorkspaceTreeSelection,
            refreshLastDeletedMaterialRestoreInfo: vi.fn().mockResolvedValue(undefined),
            loadCards: vi.fn(async () => {
                selectionsDuringLoad.push(vm.subjectsTreeWorkspaceSelection)
            }),
        }

        await methods.refreshWorkspaceStructureTree.call(vm, {
            level: 'unit',
            nodeId: 13,
            parentSubjectId: 3,
            parentTopicId: 8,
        })

        expect(selectionsDuringLoad).toEqual([
            {
                subjectId: 3,
                topicId: 8,
                unitId: 13,
            },
        ])
        expect(vm.subjectsTreeWorkspaceSelection).toBeNull()
        expect(vm.loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
    })

    it('normalizes archived shared objects from inbox users response when requested', () => {
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
                        permission: 'read_write',
                        permission_label: 'LESEN/SCHREIBEN',
                        is_archived: false,
                    },
                    {
                        rule_id: 11,
                        scope_type: 'topic',
                        scope_label: 'Thema',
                        scope_object_label: 'Archiviertes Thema',
                        permission: 'read_only',
                        permission_label: 'NUR LESEN',
                        is_archived: true,
                    },
                ],
            },
        ], true)

        expect(cards).toHaveLength(1)
        expect(cards[0].ruleId).toBe(11)
        expect(cards[0].scopeObjectLabel).toBe('Archiviertes Thema')
    })

    it('keeps the active shared object as first card', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            activeSharedRuleId: 15,
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
                shared_items: [
                    {
                        rule_id: 10,
                        scope_type: 'subject',
                        scope_label: 'Fach',
                        scope_object_label: 'Mathematik',
                        scope_path_label: 'Mathematik - Alle Themen - Alle Einheiten',
                        permission: 'read_write',
                        permission_label: 'LESEN/SCHREIBEN',
                    },
                    {
                        rule_id: 15,
                        scope_id: 501,
                        scope_type: 'material',
                        scope_label: 'Material',
                        scope_object_label: 'Zettel',
                        scope_path_label: 'Informatik - Algebra - Einheit 1',
                        permission: 'read_write',
                        permission_label: 'LESEN/SCHREIBEN',
                    },
                    {
                        rule_id: 20,
                        scope_type: 'all',
                        scope_label: 'Workspace',
                        scope_object_label: 'Alle Materialien',
                        permission: 'read_only',
                        permission_label: 'NUR LESEN',
                    },
                ],
            },
        ])

        expect(cards).toHaveLength(3)
        expect(cards[0].ruleId).toBe(15)
        expect(cards[0].scopeId).toBe(501)
    })

    it('preserves expanded shared tree cards across shared inbox reloads', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            isLoadingSharedObjectsForMe: false,
            sharedObjectsForMeError: '',
            sharedObjectsForMeCards: [],
            openSharedHierarchyCards: {},
            subjectsTreeExpandedSharedItems: {
                'shared-item-77': true,
                'shared-item-99': true,
            },
            materialCardStore: {
                config: {
                    workspace: {
                        name: 'Teamraum Mathematik',
                    },
                },
            },
        }

        const originalAxios = (globalThis as any).axios
        ;(globalThis as any).axios = {
            get: vi.fn().mockResolvedValue({
                data: {
                    data: [
                        {
                            label: 'Lehrer Eins',
                            shared_items: [
                                {
                                    rule_id: 77,
                                    scope_type: 'all',
                                    scope_label: 'Workspace',
                                    scope_object_label: 'Alle Materialien',
                                    permission: 'read_write',
                                    permission_label: 'LESEN/SCHREIBEN',
                                    updated_at: '2026-03-03T08:30:00+00:00',
                                    hierarchy: [],
                                },
                            ],
                        },
                    ],
                },
            }),
        }
        try {
            await methods.loadSharedObjectsForMe.call(vm)
        } finally {
            ;(globalThis as any).axios = originalAxios
        }

        expect(vm.subjectsTreeExpandedSharedItems).toEqual({
            'shared-item-77': true,
        })
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

    it('renders Einordnen buttons for all shared hierarchy levels in shared cards', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        const wrapper = shallowMount(MaterialsOverviewView, {
            data() {
                return {
                    overviewViewMode: 'subjects_contents',
                    subjectsContentsSource: 'shared',
                    isLoadingSharedObjectsForMe: false,
                    sharedObjectsForMeError: '',
                    subjectsContentsOverviewItems: [
                        {
                            id: 1,
                            name: 'Lokales Fach',
                            topics: [
                                {
                                    id: 2,
                                    name: 'Lokales Thema',
                                    units: [],
                                },
                            ],
                        },
                    ],
                    sharedObjectsForMeCards: [
                        {
                            ruleId: 21,
                            scopeType: 'all',
                            scopeLabel: 'Workspace',
                            scopeObjectLabel: 'Alle Materialien',
                            scopePathLabel: 'Teamraum Informatik',
                            permission: 'read_write',
                            permissionLabel: 'LESEN/SCHREIBEN',
                            fromUserLabel: 'Lehrer Eins',
                            fromUserEmail: 'lehrer.eins@example.test',
                            fromSchoolLabel: 'Abendgymnasium',
                            hierarchy: [
                                {
                                    id: 1,
                                    name: 'Informatik',
                                    materials: [
                                        { id: 100, title: 'Fachmaterial', status: 'inbox', statusLabel: 'Neu/Idee', attachmentsCount: 0 },
                                    ],
                                    topics: [
                                        {
                                            id: 2,
                                            name: 'Algebra',
                                            materials: [
                                                { id: 101, title: 'Themamaterial', status: 'inbox', statusLabel: 'Neu/Idee', attachmentsCount: 0 },
                                            ],
                                            units: [
                                                {
                                                    id: 3,
                                                    name: 'Einheit 1',
                                                    materials: [
                                                        { id: 102, title: 'Bereichsmaterial', status: 'inbox', statusLabel: 'Neu/Idee', attachmentsCount: 0 },
                                                    ],
                                                },
                                            ],
                                        },
                                    ],
                                },
                            ],
                            materialsCount: 3,
                        },
                    ],
                    openSharedHierarchyCards: {
                        'shared-rule-21': true,
                    },
                }
            },
            global: {
                stubs: {
                    'v-row': { template: '<div><slot /></div>' },
                    VRow: { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    VCol: { template: '<div><slot /></div>' },
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
                    'v-btn': {
                        emits: ['click'],
                        template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
                    },
                    VBtn: {
                        emits: ['click'],
                        template: '<button type="button" @click="$emit(\'click\', $event)"><slot /></button>',
                    },
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
            const insertButtons = wrapper
                .findAll('button')
                .filter((button) => String(button.text() || '').trim() === 'Einordnen')

            expect(wrapper.text()).toMatch(/Von:\s*Lehrer Eins \(lehrer\.eins@example\.test\)\s*·\s*Abendgymnasium/)
            expect(insertButtons.length).toBe(6)
        } finally {
            beforeMountSpy.mockRestore()
        }
    })

    it('keeps the subjects tree mounted while the overview reloads existing items', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        const wrapper = shallowMount(MaterialsOverviewView, {
            data() {
                return {
                    overviewViewMode: 'subjects_contents',
                    isLoadingSubjectsContentsOverview: true,
                    subjectsContentsOverviewItems: [
                        {
                            id: 1,
                            name: 'Mathematik',
                            materials: [],
                            topics: [],
                        },
                    ],
                }
            },
            global: {
                stubs: {
                    'v-row': { template: '<div><slot /></div>' },
                    VRow: { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    VCol: { template: '<div><slot /></div>' },
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
                    MaterialsSubjectsContentsTree: { template: '<div data-test="subjects-tree" />' },
                    'materials-subjects-contents-tree': { template: '<div data-test="subjects-tree" />' },
                    'v-progress-linear': { template: '<div data-test="overview-loader" />' },
                    VProgressLinear: { template: '<div data-test="overview-loader" />' },
                    'v-alert': { template: '<div data-test="overview-alert"><slot /></div>' },
                    VAlert: { template: '<div data-test="overview-alert"><slot /></div>' },
                    'v-list': { template: '<div><slot /></div>' },
                    VList: { template: '<div><slot /></div>' },
                    'v-list-item': { template: '<div><slot /></div>' },
                    VListItem: { template: '<div><slot /></div>' },
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
            expect(wrapper.find('[data-test="overview-loader"]').exists()).toBe(true)
            expect(wrapper.find('[data-test="subjects-tree"]').exists()).toBe(true)
        } finally {
            beforeMountSpy.mockRestore()
        }
    })

    it('keeps shared sections visible when workspace tree is empty', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        const wrapper = shallowMount(MaterialsOverviewView, {
            data() {
                return {
                    overviewViewMode: 'subjects_contents',
                    isLoadingSubjectsContentsOverview: false,
                    subjectsContentsOverviewItems: [],
                    sharedObjectsForMeCards: [],
                    archivedSharedObjectsForMeCards: [],
                }
            },
            global: {
                stubs: {
                    'v-row': { template: '<div><slot /></div>' },
                    VRow: { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    VCol: { template: '<div><slot /></div>' },
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
                    MaterialsSubjectsContentsTree: { template: '<div data-test="subjects-tree" />' },
                    'materials-subjects-contents-tree': { template: '<div data-test="subjects-tree" />' },
                    'v-progress-linear': { template: '<div data-test="overview-loader" />' },
                    VProgressLinear: { template: '<div data-test="overview-loader" />' },
                    'v-alert': { template: '<div data-test="overview-alert"><slot /></div>' },
                    VAlert: { template: '<div data-test="overview-alert"><slot /></div>' },
                    'v-list': { template: '<div><slot /></div>' },
                    VList: { template: '<div><slot /></div>' },
                    'v-list-item': { template: '<div><slot /></div>' },
                    VListItem: { template: '<div><slot /></div>' },
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
            expect(wrapper.find('[data-test="overview-alert"]').exists()).toBe(false)
            expect(wrapper.find('[data-test="subjects-tree"]').exists()).toBe(true)
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
            sharedObjectsForMeCards: [],
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

    it('opens shared material detail in writable dialog mode for read write permission', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const computed = MaterialsOverviewView?.computed || {}
        const fetchSharedMaterialDetail = vi.fn().mockResolvedValue({
            id: 555,
            shared_rule_id: 77,
            shared_material_id: 555,
            title: 'Geteiltes Detail',
            attachments: [],
            classifications: [],
            linked_permission: 'read_write',
            linked_permission_label: 'LESEN/SCHREIBEN',
        })
        const vm = {
            ...methods,
            readOnlyMaterialActions: false,
            detailDialogCard: null,
            detailDialogOpen: false,
            detailDialogLoading: false,
            detailDialogReadOnlyMode: false,
            detailDeleteStep: 0,
            sharedObjectsForMeCards: [
                {
                    ruleId: 77,
                    permission: 'read_write',
                    permissionLabel: 'LESEN/SCHREIBEN',
                },
            ],
            sanitizeDialogCard: methods.sanitizeDialogCard,
            fetchSharedMaterialDetail,
        }

        await methods.openSharedMaterialDetail.call(vm, 77, { id: 555, title: 'Geteiltes Detail' })

        expect(fetchSharedMaterialDetail).toHaveBeenCalledWith(77, 555)
        expect(vm.detailDialogReadOnlyMode).toBe(false)
        expect(vm.detailDialogCard.linked_permission).toBe('read_write')
        expect(vm.detailDialogCard.shared_rule_id).toBe(77)
        expect(vm.detailDialogCard.shared_material_id).toBe(555)
        expect(computed.detailDialogReadOnlyActions.call(vm)).toBe(false)
    })

    it('opens shared material attachments via attachment manager in read write mode', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const openAttachmentManager = vi.fn().mockResolvedValue(undefined)
        const fetchSharedMaterialAttachments = vi.fn().mockResolvedValue([
            { id: 9001, name: 'aufgabe.pdf', attachment_type: 'file', download_url: '/dl', preview_url: '/pv', shared_rule_id: 77, shared_material_id: 99 },
        ])
        const vm = {
            ...methods,
            sharedObjectsForMeCards: [
                {
                    ruleId: 77,
                    permission: 'read_write',
                    permissionLabel: 'LESEN/SCHREIBEN',
                },
            ],
            openAttachmentManager,
            fetchSharedMaterialAttachments,
        }

        await methods.openSharedMaterialAttachments.call(vm, 77, {
            id: 99,
            title: 'Lineare Gleichungen',
        })

        expect(openAttachmentManager).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 99,
                shared_rule_id: 77,
                shared_material_id: 99,
                linked_permission: 'read_write',
                linked_permission_label: 'LESEN/SCHREIBEN',
            }),
        )
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

    it('treats read_append links as append-only', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
        }

        const card = {
            is_linked: true,
            linked_permission: 'read_append',
        }

        expect(methods.cardAllowsFieldEditing.call(vm, card)).toBe(false)
        expect(methods.cardAllowsAttachmentAppend.call(vm, card)).toBe(true)
        expect(methods.linkedPermissionLabelForPermission.call(vm, 'read_append')).toBe('LESEN/HINZUFÜGEN')
    })

    it('preserves shared inbox context when opening the edit dialog', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            defaultStatusValue: 'inbox',
            attachmentRows: [],
            attachmentDeleteArmedIds: [],
            attachmentNameEditingIds: [],
            editDeleteStep: 0,
            editDeleteConfirmDialogOpen: false,
            editDeleteConfirmDialogLoading: false,
            editDeleteConfirmMaterialTitle: '',
            editDeleteConfirmAttachmentRows: [],
            editClassificationEditorVisible: false,
            editDialogOpen: false,
            toAttachmentRows: methods.toAttachmentRows,
        }

        methods.openEditDialog.call(vm, {
            id: 555,
            title: 'Geteiltes Detail',
            source_text: 'Text',
            attachments: [],
            classifications: [{ subject: 'Mathematik', topic: 'Algebra', unit: 'A1' }],
            is_linked: true,
            linked_permission: 'read_write',
            linked_permission_label: 'LESEN/SCHREIBEN',
            shared_rule_id: 77,
            shared_material_id: 555,
        })

        expect(vm.editDialogOpen).toBe(true)
        expect(vm.editForm.shared_rule_id).toBe(77)
        expect(vm.editForm.shared_material_id).toBe(555)
        expect(vm.editForm.linked_permission).toBe('read_write')
    })

    it('preserves shared create context when opening the create dialog from the shared tree', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            readOnlyMaterialActions: false,
            isLoading: false,
            isSavingCreate: false,
            isSavingEdit: false,
            isDeletingId: null,
            defaultStatusValue: 'inbox',
            createForm: {},
            createClassificationEditorVisible: false,
            createDialogOpen: false,
            createSharedContext: null,
        }

        methods.openCreateDialogFromTree.call(vm, {
            level: 'unit',
            subject: 'Mathematik',
            topic: 'Algebra',
            unit: 'A1',
            sharedRuleId: 77,
            sharedNodeLevel: 'unit',
            sharedNodeId: 301,
        })

        expect(vm.createDialogOpen).toBe(true)
        expect(vm.createClassificationEditorVisible).toBe(false)
        expect(vm.createForm.classifications).toEqual([{ subject: 'Mathematik', topic: 'Algebra', unit: 'A1' }])
        expect(vm.createSharedContext).toEqual({
            ruleId: 77,
            nodeId: 301,
            nodeLevel: 'unit',
        })
    })

    it('allows attachment delete actions for shared inbox materials with full access', () => {
        const computed = MaterialsOverviewView?.computed || {}
        const vm = {
            editForm: {
                is_linked: true,
                linked_permission: 'full_access',
                shared_rule_id: 77,
                shared_material_id: 555,
            },
            normalizeLinkedPermission: MaterialsOverviewView?.methods?.normalizeLinkedPermission,
        }

        vm.normalizedEditLinkedPermission = computed.normalizedEditLinkedPermission.call(vm)
        vm.isEditLinkedMaterial = computed.isEditLinkedMaterial.call(vm)
        vm.isEditSharedInboxMaterial = computed.isEditSharedInboxMaterial.call(vm)

        expect(vm.normalizedEditLinkedPermission).toBe('full_access')
        expect(vm.isEditLinkedMaterial).toBe(true)
        expect(vm.isEditSharedInboxMaterial).toBe(true)
        expect(computed.canEditLinkedDeleteAttachments.call(vm)).toBe(true)
        expect(computed.canEditLinkedDeleteMaterial.call(vm)).toBe(true)
    })

    it('routes shared full-access material deletes through inbox delete endpoint', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const sharedContext = { ruleId: 77, materialId: 555, permission: 'full_access', permissionLabel: 'VOLLZUGRIFF' }
        const deleteSharedMaterial = vi.fn().mockResolvedValue({ deleted: true })
        const closeEditDialog = vi.fn().mockResolvedValue(undefined)
        const loadCards = vi.fn().mockResolvedValue(undefined)
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const refreshLastDeletedMaterialRestoreInfo = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            isSavingEdit: false,
            isDeletingEditedMaterial: false,
            editDeleteConfirmDialogOpen: true,
            editDeleteConfirmDialogLoading: false,
            editDeleteStep: 1,
            isEditSharedInboxMaterial: true,
            canEditLinkedDeleteMaterial: true,
            editForm: {
                id: 555,
                shared_rule_id: 77,
                shared_material_id: 555,
                linked_permission: 'full_access',
            },
            sharedInboxContextForCard: vi.fn().mockReturnValue(sharedContext),
            deleteSharedMaterial,
            materialCardStore: {
                destroy: vi.fn(),
            },
            closeEditDialog,
            loadCards,
            loadSharedObjectsForMe,
            refreshLastDeletedMaterialRestoreInfo,
            isDeletingId: null,
        }

        await methods.confirmDeleteFromEdit.call(vm)

        expect(deleteSharedMaterial).toHaveBeenCalledTimes(1)
        expect(deleteSharedMaterial).toHaveBeenCalledWith(sharedContext)
        expect(vm.materialCardStore.destroy).not.toHaveBeenCalled()
        expect(closeEditDialog).toHaveBeenCalledWith(false)
        expect(loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(refreshLastDeletedMaterialRestoreInfo).toHaveBeenCalledWith({
            source: 'shared',
            ruleId: 77,
        })
    })

    it('routes shared full-access attachment deletes through inbox delete endpoint', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const sharedContext = { ruleId: 77, materialId: 555, permission: 'full_access', permissionLabel: 'VOLLZUGRIFF' }
        const deleteSharedAttachment = vi.fn().mockResolvedValue({ deleted: true })
        const vm = {
            ...methods,
            attachmentDialogCardId: 555,
            attachmentDialogCanDeleteAttachments: true,
            sharedInboxContextForAttachment: vi.fn().mockReturnValue(sharedContext),
            deleteSharedAttachment,
            materialCardStore: {
                deleteAttachment: vi.fn(),
            },
            isAttachmentDeleting: vi.fn().mockReturnValue(false),
            isAttachmentSaving: vi.fn().mockReturnValue(false),
            isAttachmentDeleteArmed: vi.fn().mockReturnValue(true),
            markAttachmentDeleting: vi.fn(),
            markAttachmentDeleteArmed: vi.fn(),
            removeAttachmentFromCard: vi.fn(),
            refreshAllListedAttachmentBytes: vi.fn(),
            attachmentRows: [
                { id: 901, name: 'aufgabe.pdf' },
                { id: 902, name: 'bild.png' },
            ],
        }

        await methods.removeAttachment.call(vm, { id: 901, shared_rule_id: 77, shared_material_id: 555 })

        expect(deleteSharedAttachment).toHaveBeenCalledTimes(1)
        expect(deleteSharedAttachment).toHaveBeenCalledWith(sharedContext, 901)
        expect(vm.materialCardStore.deleteAttachment).not.toHaveBeenCalled()
        expect(vm.attachmentRows.map((row) => row.id)).toEqual([902])
        expect(vm.removeAttachmentFromCard).toHaveBeenCalledWith(901)
        expect(vm.refreshAllListedAttachmentBytes).toHaveBeenCalledTimes(1)
    })

    it('routes shared edit saves through inbox write endpoints', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const sharedContext = { ruleId: 77, materialId: 555, permission: 'read_write', permissionLabel: 'LESEN/SCHREIBEN' }
        const updateSharedMaterial = vi.fn().mockResolvedValue({ id: 555 })
        const addSharedLinkAttachment = vi.fn().mockResolvedValue({ id: 1 })
        const addSharedImageUrlAttachment = vi.fn().mockResolvedValue({ id: 2 })
        const addSharedTempFileAttachment = vi.fn().mockResolvedValue({ id: 3 })
        const closeEditDialog = vi.fn().mockResolvedValue(undefined)
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const loadCards = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            canSaveEdit: true,
            isSavingEdit: false,
            isDeletingEditedMaterial: false,
            isEditLinkedReadOnly: false,
            defaultStatusValue: 'inbox',
            editForm: {
                id: 555,
                title: 'Bearbeitet',
                description: 'Neue Beschreibung',
                classifications: [{ subject: 'Mathematik', topic: 'Algebra', unit: 'A1' }],
                source_url: '',
                area: '',
                unit: '',
                type: '',
                status: 'done',
                notes: 'Neue Notiz',
                pendingAttachments: [
                    { attachmentType: 'link', url: 'https://example.org/q', title: 'Quelle', storeImageFile: true },
                    { tempUpload: 'temp-upload-1', title: 'Datei', fileName: 'datei.pdf' },
                ],
                shared_rule_id: 77,
                shared_material_id: 555,
                linked_permission: 'read_write',
            },
            sharedInboxContextForCard: vi.fn().mockReturnValue(sharedContext),
            normalizeClassifications: vi.fn((value) => value),
            toNullable: methods.toNullable,
            toPendingAttachments: vi.fn((value) => value),
            normalizeUrl: methods.normalizeUrl,
            defaultLinkTitle: vi.fn((value) => value),
            updateSharedMaterial,
            addSharedLinkAttachment,
            addSharedImageUrlAttachment,
            addSharedTempFileAttachment,
            addSharedFileAttachment: vi.fn(),
            materialCardStore: {
                update: vi.fn(),
                addLinkAttachment: vi.fn(),
                addImageUrlAttachment: vi.fn(),
                addTempFileAttachment: vi.fn(),
                addFileAttachment: vi.fn(),
            },
            loadSharedObjectsForMe,
            closeEditDialog,
            loadCards,
        }

        await methods.saveEdit.call(vm)

        expect(updateSharedMaterial).toHaveBeenCalledTimes(1)
        expect(addSharedLinkAttachment).toHaveBeenCalledWith(sharedContext, {
            url: 'https://example.org/q',
            name: 'Quelle',
        })
        expect(addSharedImageUrlAttachment).toHaveBeenCalledWith(sharedContext, 'https://example.org/q', 'Quelle')
        expect(addSharedTempFileAttachment).toHaveBeenCalledWith(sharedContext, 'temp-upload-1', 'Datei')
        expect(vm.materialCardStore.update).not.toHaveBeenCalled()
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(closeEditDialog).toHaveBeenCalledWith(true)
        expect(loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
    })

    it('routes shared create saves through inbox create endpoints', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const createSharedMaterial = vi.fn().mockResolvedValue({
            id: 777,
            shared_rule_id: 77,
            shared_material_id: 777,
        })
        const addSharedLinkAttachment = vi.fn().mockResolvedValue({ id: 1 })
        const addSharedImageUrlAttachment = vi.fn().mockResolvedValue({ id: 2 })
        const addSharedTempFileAttachment = vi.fn().mockResolvedValue({ id: 3 })
        const closeCreateDialog = vi.fn().mockResolvedValue(undefined)
        const loadSharedObjectsForMe = vi.fn().mockResolvedValue(undefined)
        const loadCards = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            canSaveCreate: true,
            isSavingCreate: false,
            isSavingEdit: false,
            isDeletingId: null,
            defaultStatusValue: 'inbox',
            createSharedContext: {
                ruleId: 77,
                nodeId: 301,
                nodeLevel: 'unit',
            },
            createForm: {
                title: 'Neues geteiltes Material',
                description: 'Beschreibung',
                type: '',
                status: 'done',
                classifications: [{ subject: 'Mathematik', topic: 'Algebra', unit: 'A1' }],
                pendingAttachments: [
                    { attachmentType: 'link', url: 'https://example.org/q', title: 'Quelle', storeImageFile: true },
                    { tempUpload: 'temp-upload-1', title: 'Datei', fileName: 'datei.pdf' },
                ],
            },
            normalizeClassifications: vi.fn((value) => value),
            toNullable: methods.toNullable,
            toPendingAttachments: vi.fn((value) => value),
            normalizeUrl: methods.normalizeUrl,
            defaultLinkTitle: vi.fn((value) => value),
            createSharedMaterial,
            addSharedLinkAttachment,
            addSharedImageUrlAttachment,
            addSharedTempFileAttachment,
            addSharedFileAttachment: vi.fn(),
            materialCardStore: {
                quickStore: vi.fn(),
                addLinkAttachment: vi.fn(),
                addImageUrlAttachment: vi.fn(),
                addTempFileAttachment: vi.fn(),
                addFileAttachment: vi.fn(),
            },
            closeCreateDialog,
            loadSharedObjectsForMe,
            loadCards,
        }

        await methods.saveCreate.call(vm)

        expect(createSharedMaterial).toHaveBeenCalledTimes(1)
        expect(createSharedMaterial).toHaveBeenCalledWith(
            {
                ruleId: 77,
                nodeId: 301,
                nodeLevel: 'unit',
            },
            {
                title: 'Neues geteiltes Material',
                source_text: 'Beschreibung',
                type: null,
                status: 'done',
                classifications: [{ subject: 'Mathematik', topic: 'Algebra', unit: 'A1' }],
            },
        )
        expect(addSharedLinkAttachment).toHaveBeenCalledWith({ ruleId: 77, materialId: 777 }, {
            url: 'https://example.org/q',
            name: 'Quelle',
        })
        expect(addSharedImageUrlAttachment).toHaveBeenCalledWith({ ruleId: 77, materialId: 777 }, 'https://example.org/q', 'Quelle')
        expect(addSharedTempFileAttachment).toHaveBeenCalledWith({ ruleId: 77, materialId: 777 }, 'temp-upload-1', 'Datei')
        expect(vm.materialCardStore.quickStore).not.toHaveBeenCalled()
        expect(loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(closeCreateDialog).toHaveBeenCalledTimes(1)
        expect(loadCards).toHaveBeenCalledWith(null, { forceFilterCountRefresh: true })
    })

    it('uses source material options for shared create dialogs', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const computed = MaterialsOverviewView?.computed || {}
        const sharedStatusOptions = [
            { value: 'shared_review', label: 'Freigabeprüfung', color: '#1565c0' },
        ]
        const sharedTypeOptions = [
            { value: 'Quelltyp', label: 'Quelltyp', color: '#00897b', icon: 'mdi-source-branch' },
        ]
        const vm: Record<string, any> = {
            ...methods,
            sharedObjectsForMeCards: [
                {
                    ruleId: 88,
                    materialOptions: {
                        statusOptions: sharedStatusOptions,
                        typeOptions: sharedTypeOptions,
                    },
                },
            ],
            createSharedContext: {
                ruleId: 88,
                nodeId: 301,
                nodeLevel: 'subject',
            },
            typeOptions: [{ value: 'Lokal', label: 'Lokal' }],
            statusOptions: [{ value: 'inbox', label: 'Neu/Idee' }],
            canManageTypeValues: true,
        }

        Object.defineProperty(vm, 'createSharedRuleCard', {
            get: () => computed.createSharedRuleCard.call(vm),
        })
        Object.defineProperty(vm, 'createTypeOptions', {
            get: () => computed.createTypeOptions.call(vm),
        })
        Object.defineProperty(vm, 'createStatusOptions', {
            get: () => computed.createStatusOptions.call(vm),
        })
        Object.defineProperty(vm, 'createCanManageTypeValues', {
            get: () => computed.createCanManageTypeValues.call(vm),
        })
        Object.defineProperty(vm, 'defaultStatusValue', {
            get: () => computed.defaultStatusValue.call(vm),
        })

        expect(vm.createTypeOptions).toEqual(sharedTypeOptions)
        expect(vm.createStatusOptions).toEqual(sharedStatusOptions)
        expect(vm.createCanManageTypeValues).toBe(false)
        expect(computed.createDefaultStatusValue.call(vm)).toBe('shared_review')
    })

    it('restores shared material detail after closing the shared edit dialog', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const cleanupPendingTempUploads = vi.fn().mockResolvedValue(undefined)
        const openSharedMaterialDetail = vi.fn().mockResolvedValue(undefined)
        const openDetailDialog = vi.fn().mockResolvedValue(undefined)
        const vm = {
            ...methods,
            isSavingEdit: false,
            isDeletingEditedMaterial: false,
            editForm: {
                pendingAttachments: [],
            },
            cleanupPendingTempUploads,
            closeTextAttachmentEditor: vi.fn(),
            returnToDetailOnEditCancel: true,
            detailCardForEditReturn: {
                id: 555,
                title: 'Geteiltes Detail',
                shared_rule_id: 77,
                shared_material_id: 555,
                linked_permission: 'read_write',
            },
            sanitizeDialogCard: methods.sanitizeDialogCard,
            sharedInboxContextForCard: vi.fn().mockReturnValue({ ruleId: 77, materialId: 555, permission: 'read_write', permissionLabel: 'LESEN/SCHREIBEN' }),
            openSharedMaterialDetail,
            openDetailDialog,
        }

        await methods.closeEditDialog.call(vm, true)

        expect(cleanupPendingTempUploads).toHaveBeenCalledTimes(1)
        expect(openSharedMaterialDetail).toHaveBeenCalledWith(77, expect.objectContaining({ id: 555 }))
        expect(openDetailDialog).not.toHaveBeenCalled()
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

    it('keeps only one shared tree item expanded at a time', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            subjectsTreeExpandedSharedItems: {},
        }

        methods.toggleSubjectsTreeSharedItemExpanded.call(vm, 11)
        expect(vm.subjectsTreeExpandedSharedItems).toEqual({
            'shared-item-11': true,
        })

        methods.toggleSubjectsTreeSharedItemExpanded.call(vm, 22)
        expect(vm.subjectsTreeExpandedSharedItems).toEqual({
            'shared-item-22': true,
        })

        methods.toggleSubjectsTreeSharedItemExpanded.call(vm, 22)
        expect(vm.subjectsTreeExpandedSharedItems).toEqual({})
    })

    it('moves the active shared tree card to top when expanded', () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            activeSharedRuleId: null,
            subjectsTreeExpandedSharedItems: {},
            sharedObjectsForMeCards: [
                { ruleId: 10, scopeType: 'subject', scopeObjectLabel: 'Mathematik', scopePathLabel: 'Fach' },
                { ruleId: 20, scopeType: 'all', scopeObjectLabel: 'Alle Materialien', scopePathLabel: 'Workspace A' },
                { ruleId: 30, scopeType: 'unit', scopeObjectLabel: 'Bereich A', scopePathLabel: 'Bereich A' },
            ],
        }

        methods.toggleSubjectsTreeSharedItemExpanded.call(vm, 30)

        expect(vm.subjectsTreeExpandedSharedItems).toEqual({
            'shared-item-30': true,
        })
        expect(vm.activeSharedRuleId).toBe(30)
        expect(vm.sharedObjectsForMeCards[0]?.ruleId).toBe(30)
    })

    it('loads deleted restore items for the workspace context', async () => {
        const originalAxios = (globalThis as any).axios
        const getSpy = vi.fn().mockResolvedValue({
            data: {
                data: [
                    {
                        type: 'unit',
                        type_label: 'Bereich',
                        id: 55,
                        title: 'Einheit 5',
                        path_label: 'Informatik > Digitale Kompetenzen',
                        materials_count: 0,
                        attachments_count: 0,
                        size_bytes: 2048,
                        deleted_at: '2026-04-11 12:00:00',
                    },
                ],
            },
        })
        ;(globalThis as any).axios = {
            get: getSpy,
        }
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        try {
            const wrapper = shallowMount(MaterialsOverviewView, {
                data() {
                    return {
                        deletedMaterialRestoreItems: [],
                        deletedMaterialRestoreHidden: true,
                        subjectsTreeSharedForMeExpanded: false,
                        subjectsTreeSharedForMeArchiveExpanded: false,
                        activeSharedRuleId: null,
                        sharedObjectsForMeCards: [],
                        overviewViewMode: 'list',
                    }
                },
                global: {
                    stubs: {
                        MaterialsOverviewHeader: true,
                    },
                },
            })

            await wrapper.vm.refreshLastDeletedMaterialRestoreInfo()

            expect(getSpy).toHaveBeenCalledWith('/api/admin/materials/deleted-restore-list')
            expect(wrapper.vm.deletedMaterialRestoreItems).toEqual([
                {
                    id: 55,
                    type: 'unit',
                    typeLabel: 'Bereich',
                    title: 'Einheit 5',
                    pathLabel: 'Informatik > Digitale Kompetenzen',
                    attachmentsCount: 0,
                    materialsCount: 0,
                    sizeBytes: 2048,
                    deletedAt: '2026-04-11 12:00:00',
                },
            ])
            expect(wrapper.vm.deletedMaterialRestoreHidden).toBe(true)
        } finally {
            ;(globalThis as any).axios = originalAxios
            beforeMountSpy.mockRestore()
        }
    })

    it('keeps the workspace restore context after a workspace tree refresh even when shared cards are active', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const originalAxios = (globalThis as any).axios
        const getSpy = vi.fn().mockResolvedValue({
            data: {
                data: [
                    {
                        type: 'unit',
                        type_label: 'Bereich',
                        id: 55,
                        title: 'Einheit 5',
                        path_label: 'Informatik > Digitale Kompetenzen',
                        materials_count: 0,
                        attachments_count: 0,
                        size_bytes: 2048,
                        deleted_at: '2026-04-11 12:00:00',
                    },
                ],
            },
        })
        ;(globalThis as any).axios = {
            get: getSpy,
        }

        const vm = {
            ...methods,
            deletedMaterialRestoreItems: [],
            deletedMaterialRestoreHidden: true,
            deletedMaterialRestoreContextOverride: null,
            subjectsTreeSharedForMeExpanded: false,
            subjectsTreeSharedForMeArchiveExpanded: false,
            subjectsContentsSource: 'shared',
            overviewViewMode: 'subjects_contents',
            activeSharedRuleId: 14,
            sharedObjectsForMeCards: [{ ruleId: 14 }],
            subjectsTreeWorkspaceSelection: null,
            loadCards: vi.fn().mockResolvedValue(undefined),
            normalizeWorkspaceTreeSelection: methods.normalizeWorkspaceTreeSelection,
        }

        try {
            await methods.refreshWorkspaceStructureTree.call(vm)

            expect(getSpy).toHaveBeenCalledWith('/api/admin/materials/deleted-restore-list')
            expect(vm.deletedMaterialRestoreContextOverride).toEqual({
                source: 'workspace',
                ruleId: null,
            })
            expect(vm.deletedMaterialRestoreItems[0]?.type).toBe('unit')
        } finally {
            ;(globalThis as any).axios = originalAxios
        }
    })

    it('loads deleted restore items for the active shared rule', async () => {
        const originalAxios = (globalThis as any).axios
        const getSpy = vi.fn().mockResolvedValue({
            data: {
                data: [
                    {
                        type: 'material',
                        type_label: 'Material',
                        id: 81,
                        title: 'Arbeitsblatt',
                        path_label: 'Biologie > Pflanzen > Blatt',
                        materials_count: 1,
                        attachments_count: 2,
                        size_bytes: 4096,
                        deleted_at: '2026-04-11 13:00:00',
                    },
                ],
            },
        })
        ;(globalThis as any).axios = {
            get: getSpy,
        }
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        try {
            const wrapper = shallowMount(MaterialsOverviewView, {
                data() {
                    return {
                        deletedMaterialRestoreItems: [],
                        deletedMaterialRestoreHidden: true,
                        subjectsTreeSharedForMeExpanded: true,
                        subjectsTreeSharedForMeArchiveExpanded: false,
                        activeSharedRuleId: 14,
                        sharedObjectsForMeCards: [{ ruleId: 14 }],
                        overviewViewMode: 'list',
                    }
                },
                global: {
                    stubs: {
                        MaterialsOverviewHeader: true,
                    },
                },
            })

            await wrapper.vm.refreshLastDeletedMaterialRestoreInfo()

            expect(getSpy).toHaveBeenCalledWith('/api/admin/materials/shares/inbox/deleted-restore-list', {
                params: {
                    rule_id: 14,
                },
            })
            expect(wrapper.vm.deletedMaterialRestoreItems[0]?.type).toBe('material')
            expect(wrapper.vm.deletedMaterialRestoreItems[0]?.attachmentsCount).toBe(2)
            expect(wrapper.vm.deletedMaterialRestoreItems[0]?.sizeBytes).toBe(4096)
            expect(wrapper.vm.deletedMaterialRestoreItems[0]?.pathLabel).toBe('Biologie > Pflanzen > Blatt')
        } finally {
            ;(globalThis as any).axios = originalAxios
            beforeMountSpy.mockRestore()
        }
    })

    it('renders deleted structure restore items with a visible element count', () => {
        const beforeMountSpy = vi.spyOn(MaterialsOverviewView, 'beforeMount').mockImplementation(() => {})

        try {
            const wrapper = shallowMount(MaterialsOverviewView, {
                data() {
                    return {
                        deletedMaterialRestoreItems: [
                            {
                                id: 12,
                                type: 'subject',
                                typeLabel: 'Fach',
                                title: 'Informatik',
                                pathLabel: '',
                                attachmentsCount: 0,
                                materialsCount: 31,
                                sizeBytes: 26624,
                                deletedAt: '2026-04-11 12:00:00',
                            },
                        ],
                        deletedMaterialRestoreHidden: false,
                        deletedMaterialRestoreContextOverride: {
                            source: 'workspace',
                            ruleId: null,
                        },
                        subjectsTreeSharedForMeExpanded: false,
                        subjectsTreeSharedForMeArchiveExpanded: false,
                        activeSharedRuleId: null,
                        sharedObjectsForMeCards: [],
                        overviewViewMode: 'list',
                    }
                },
                global: {
                    stubs: {
                        MaterialsOverviewHeader: true,
                    },
                },
            })

            expect(wrapper.text()).toContain('Fach: Informatik')
            expect(wrapper.text()).toContain('31 Elemente')
            expect(wrapper.text()).toContain('26 KB')
            expect(wrapper.text()).toContain('Gelöschte Elemente des Workspace (wiederherstellbar).')
        } finally {
            beforeMountSpy.mockRestore()
        }
    })

    it('refreshes the shared restore list after deleting a shared material from the edit dialog', async () => {
        const methods = MaterialsOverviewView?.methods || {}
        const vm = {
            ...methods,
            editForm: {
                id: 81,
            },
            isSavingEdit: false,
            isDeletingEditedMaterial: false,
            editDeleteConfirmDialogOpen: true,
            isDeletingId: null,
            editDeleteStep: 1,
            editDeleteConfirmDialogLoading: false,
            isEditSharedInboxMaterial: true,
            canEditLinkedDeleteMaterial: true,
            sharedInboxContextForCard: vi.fn().mockReturnValue({
                ruleId: 14,
                materialId: 81,
            }),
            deleteSharedMaterial: vi.fn().mockResolvedValue(true),
            closeEditDialog: vi.fn().mockResolvedValue(undefined),
            loadCards: vi.fn().mockResolvedValue(undefined),
            loadSharedObjectsForMe: vi.fn().mockResolvedValue(undefined),
            refreshLastDeletedMaterialRestoreInfo: vi.fn().mockResolvedValue(undefined),
            materialCardStore: {
                destroy: vi.fn(),
            },
        }

        await methods.confirmDeleteFromEdit.call(vm)

        expect(vm.deleteSharedMaterial).toHaveBeenCalledWith({
            ruleId: 14,
            materialId: 81,
        })
        expect(vm.loadSharedObjectsForMe).toHaveBeenCalledTimes(1)
        expect(vm.refreshLastDeletedMaterialRestoreInfo).toHaveBeenCalledWith({
            source: 'shared',
            ruleId: 14,
        })
    })
})
