import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import StorageAudit from '@/pages/admin/superAdmin/components/StorageAudit.vue'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const vuetifyStubs = {
    'v-card': { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    'v-sheet': { template: '<div><slot /></div>' },
    VSheet: { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    VRow: { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    VCol: { template: '<div><slot /></div>' },
    'v-alert': { template: '<div><slot /></div>' },
    VAlert: { template: '<div><slot /></div>' },
    'v-progress-linear': { template: '<div data-testid="storage-audit-progress"><slot /></div>' },
    VProgressLinear: { template: '<div data-testid="storage-audit-progress"><slot /></div>' },
    'v-skeleton-loader': { template: '<div><slot /></div>' },
    VSkeletonLoader: { template: '<div><slot /></div>' },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-btn': { props: ['disabled'], template: '<button type="button" :disabled="disabled"><slot /></button>' },
    VBtn: { props: ['disabled'], template: '<button type="button" :disabled="disabled"><slot /></button>' },
    'v-list': { template: '<div><slot /></div>' },
    VList: { template: '<div><slot /></div>' },
    'v-list-item': { template: '<div><slot name="title" /><slot name="subtitle" /><slot /></div>' },
    VListItem: { template: '<div><slot name="title" /><slot name="subtitle" /><slot /></div>' },
    'v-icon': { template: '<i><slot /></i>' },
    VIcon: { template: '<i><slot /></i>' },
    'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
    VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
}

describe('StorageAudit', () => {
    const originalAxios = (globalThis as any).axios
    const axiosMock = {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    }

    const reportsPayload = {
        generated_at: '2026-04-09T23:12:10+02:00',
        reports: [
            {
                scope_key: 'active_school',
                scope_label: 'Aktive Schule',
                school: {
                    id: 1,
                    long_name: 'Christian-Doppler-Gymnasium Salzburg',
                    short_name: 'CDGym',
                },
                bucket_prefix: 'materials/schools/1',
                bucket: {
                    object_count: 3,
                    total_bytes: 4096,
                },
                cloud_sync_source: {
                    count: 2,
                    total_bytes: 1536,
                },
                database: {
                    live: {
                        count: 1,
                        total_bytes: 1024,
                    },
                    trashed: {
                        count: 1,
                        total_bytes: 512,
                    },
                    all: {
                        count: 2,
                        total_bytes: 1536,
                    },
                },
                differences: {
                    bucket_only: {
                        count: 1,
                        total_bytes: 256,
                    },
                    database_only: {
                        count: 1,
                        total_bytes: 512,
                    },
                    local_missing: {
                        count: 1,
                        total_bytes: 1024,
                    },
                },
                bucket_only_objects: [
                    {
                        path: 'materials/schools/1/users/7/cards/orphans/active-orphan.pdf',
                        size_bytes: 256,
                    },
                ],
                database_only_attachments: [
                    {
                        id: 301,
                        material_card_id: 101,
                        material_card_title: 'Irgendwas',
                        subject_name: 'Informatik',
                        topic_name: 'Netzwerke',
                        unit_name: 'Sicherheit',
                        file_path: 'materials/schools/1/users/7/cards/101/missing.docx',
                        size_bytes: 512,
                        deleted_at: null,
                    },
                ],
                local_missing_files: [
                    {
                        path: 'materials/schools/1/users/7/cards/101/live.pdf',
                        material_card_title: 'Aktive Datei',
                        size_bytes: 1024,
                        reference_count: 2,
                        trashed_reference_count: 1,
                    },
                ],
                cloud_sync_files: [
                    {
                        path: 'materials/schools/1/users/7/cards/101/live.pdf',
                        material_card_title: 'Aktive Datei',
                        size_bytes: 1024,
                        reference_count: 2,
                        trashed_reference_count: 1,
                    },
                    {
                        path: 'materials/schools/1/users/7/cards/102/restore.pdf',
                        material_card_title: 'Gelöschte Datei',
                        size_bytes: 512,
                        reference_count: 1,
                        trashed_reference_count: 1,
                    },
                ],
                has_more_bucket_only_objects: false,
                has_more_database_only_attachments: false,
                has_more_local_missing_files: false,
                has_more_cloud_sync_files: false,
            },
            {
                scope_key: 'all_schools',
                scope_label: 'Alle Schulen',
                school: null,
                bucket_prefix: 'materials',
                bucket: {
                    object_count: 5,
                    total_bytes: 8192,
                },
                cloud_sync_source: {
                    count: 3,
                    total_bytes: 6144,
                },
                database: {
                    live: {
                        count: 2,
                        total_bytes: 4096,
                    },
                    trashed: {
                        count: 1,
                        total_bytes: 2048,
                    },
                    all: {
                        count: 3,
                        total_bytes: 6144,
                    },
                },
                differences: {
                    bucket_only: {
                        count: 1,
                        total_bytes: 1024,
                    },
                    database_only: {
                        count: 1,
                        total_bytes: 512,
                    },
                    local_missing: {
                        count: 1,
                        total_bytes: 2048,
                    },
                },
                bucket_only_objects: [
                    {
                        path: 'materials/schools/2/users/9/cards/orphans/other-orphan.pdf',
                        size_bytes: 1024,
                    },
                ],
                database_only_attachments: [
                    {
                        id: 301,
                        material_card_id: 101,
                        material_card_title: 'Irgendwas',
                        subject_name: 'Informatik',
                        topic_name: 'Netzwerke',
                        unit_name: 'Sicherheit',
                        file_path: 'materials/schools/1/users/7/cards/101/missing.docx',
                        size_bytes: 512,
                        deleted_at: null,
                    },
                ],
                local_missing_files: [
                    {
                        path: 'materials/schools/2/users/9/cards/202/other.pdf',
                        material_card_title: 'Andere Datei',
                        size_bytes: 2048,
                        reference_count: 1,
                        trashed_reference_count: 0,
                    },
                ],
                cloud_sync_files: [
                    {
                        path: 'materials/schools/2/users/9/cards/202/other.pdf',
                        material_card_title: 'Andere Datei',
                        size_bytes: 2048,
                        reference_count: 1,
                        trashed_reference_count: 0,
                    },
                ],
                has_more_bucket_only_objects: false,
                has_more_database_only_attachments: false,
                has_more_local_missing_files: false,
                has_more_cloud_sync_files: false,
            },
        ],
    }

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.delete.mockReset()
        axiosMock.post.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/storage-audit/start') {
                return Promise.resolve({
                    data: {
                        message: 'Speicherprüfung wurde gestartet.',
                        data: {
                            operation_id: 'operation-1',
                            status: 'queued',
                            progress: 0,
                            completed_steps: 0,
                            total_steps: 0,
                            refresh_after_seconds: 1,
                            message: 'Speicherprüfung wird gestartet.',
                        },
                    },
                })
            }

            if (url === '/api/admin/materials/storage-audit/purge') {
                return Promise.resolve({
                    data: {
                        message: 'Verwaiste Dateien wurden gelöscht.',
                    },
                })
            }

            if (url === '/api/admin/materials/storage-audit/sync-local') {
                return Promise.resolve({
                    data: {
                        message: 'Der Download der Materialdateien wurde im Hintergrund gestartet.',
                        data: {
                            operation_id: 'sync-operation-1',
                            status: 'queued',
                            progress: 0,
                            completed_steps: 0,
                            total_steps: 0,
                            refresh_after_seconds: 1,
                            message: 'Materialdateien werden zum Download vorbereitet.',
                        },
                    },
                })
            }

            return Promise.reject(new Error(`Unexpected POST ${url}`))
        })
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/storage-audit/operations/operation-1') {
                return Promise.resolve({
                    data: {
                        data: {
                            operation_id: 'operation-1',
                            status: 'completed',
                            progress: 100,
                            completed_steps: 10,
                            total_steps: 10,
                            refresh_after_seconds: 1,
                            message: 'Speicherprüfung abgeschlossen.',
                            result: reportsPayload,
                        },
                    },
                })
            }

            if (url === '/api/admin/materials/storage-audit/sync-operations/sync-operation-1') {
                return Promise.resolve({
                    data: {
                        data: {
                            operation_id: 'sync-operation-1',
                            status: 'completed',
                            progress: 100,
                            completed_steps: 2,
                            total_steps: 2,
                            refresh_after_seconds: 1,
                            message: 'Der Download der Materialdateien ist abgeschlossen.',
                            result: {
                                synced_count: 1,
                                already_local_count: 1,
                            },
                        },
                    },
                })
            }

            return Promise.reject(new Error(`Unexpected GET ${url}`))
        })
        axiosMock.delete.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/storage-audit/database-only-attachments/301') {
                return Promise.resolve({
                    data: {
                        message: 'Der defekte Anhang wurde entfernt.',
                    },
                })
            }

            return Promise.reject(new Error(`Unexpected DELETE ${url}`))
        })

        ;(globalThis as any).axios = axiosMock
    })

    afterEach(() => {
        ;(globalThis as any).axios = originalAxios
        vi.useRealTimers()
        vi.clearAllMocks()
    })

    it('shows a linear progress indicator while the audit is loading', async () => {
        axiosMock.get.mockResolvedValueOnce({
            data: {
                data: {
                    operation_id: 'operation-1',
                    status: 'running',
                    progress: 42,
                    completed_steps: 4,
                    total_steps: 10,
                    refresh_after_seconds: 1,
                    message: 'Alle Schulen: Bucket-Dateien werden geprüft.',
                },
            },
        })

        render(StorageAudit, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    selected_school: {
                                        id: 1,
                                        long_name: 'Christian-Doppler-Gymnasium Salzburg',
                                    },
                                },
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                },
            },
        })

        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/storage-audit/start', {
                school_id: 1,
            })
        })

        await waitFor(() => {
            expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/storage-audit/operations/operation-1')
        })

        expect(screen.getByTestId('storage-audit-progress')).toBeInTheDocument()

        await waitFor(() => {
            expect(screen.getByText((content) => content.includes('Speicherprüfung läuft • 42 %'))).toBeInTheDocument()
        })

        expect(screen.getByText((content) => content.includes('Alle Schulen: Bucket-Dateien werden geprüft.'))).toBeInTheDocument()
    })

    it('shows additional storage summary values for the active school and all schools', async () => {
        vi.useFakeTimers()

        render(StorageAudit, {
            global: {
                plugins: [
                    createTestingPinia({
                        stubActions: true,
                        initialState: {
                            AdminAdminStore: {
                                config: {
                                    is_auth: true,
                                    selected_school: {
                                        id: 1,
                                        long_name: 'Christian-Doppler-Gymnasium Salzburg',
                                    },
                                },
                            },
                        },
                    }),
                ],
                stubs: {
                    ...vuetifyStubs,
                },
            },
        })

        const notificationStore = useNotificationStore()

        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/storage-audit/start', {
                school_id: 1,
            })
        })

        await waitFor(() => {
            expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/storage-audit/operations/operation-1')
        })

        await waitFor(() => {
            expect(screen.getAllByText('Was ist hier zu tun?')).toHaveLength(2)
        })

        expect(screen.getAllByText('Lokal fehlt noch')).toHaveLength(2)
        expect(screen.getAllByText('Fehlt auch online')).toHaveLength(4)
        expect(screen.getAllByText('Nur online ohne Materialeintrag')).toHaveLength(2)
        expect(screen.getByText('Belegt 4.00 KB')).toBeInTheDocument()
        expect(screen.getByText('Belegt 8.00 KB')).toBeInTheDocument()
        expect(screen.getAllByText('Alle Materialdateien dieser Schule lokal laden')).toHaveLength(1)
        expect(screen.getAllByRole('button', { name: 'Alle Dateien lokal laden' })).toHaveLength(1)
        expect(screen.getByText('Stand: 09.04.2026, 23:12 Uhr')).toBeInTheDocument()
        expect(screen.getByText('Geteilte oder verknüpfte Materialien sowie gelöschte, aber wiederherstellbare Materialien sind berücksichtigt. Beim Laden zählt jede Datei nur einmal, auch wenn sie in mehreren Karten vorkommt.')).toBeInTheDocument()
        expect(screen.getAllByText('Informatik - Netzwerke - Sicherheit - Irgendwas • Schule 1')).toHaveLength(2)
        expect(screen.queryByText('materials/schools/1/users/7/cards/101/missing.docx')).not.toBeInTheDocument()
        expect(screen.queryByText((content) => content.includes('2 Verweise'))).not.toBeInTheDocument()
        expect(screen.queryByText((content) => content.includes('1 im Papierkorb'))).not.toBeInTheDocument()
        expect(screen.getAllByRole('button', { name: 'Defekten Anhang löschen' })).toHaveLength(2)

        const deleteButtons = screen.getAllByRole('button', { name: 'Nur-online-Dateien löschen' })
        expect(deleteButtons).toHaveLength(2)

        await fireEvent.click(deleteButtons[0])

        expect(screen.getByText('Aktive Schule: Christian-Doppler-Gymnasium Salzburg')).toBeInTheDocument()
        expect(screen.getByText('1 Datei ohne Materialeintrag mit insgesamt 256 B werden dauerhaft aus dem Bucket entfernt.')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Jetzt löschen' }))

        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/storage-audit/purge', {
                scope_key: 'active_school',
                school_id: 1,
            })
        })

        await waitFor(() => {
            expect(screen.getByText(/Verwaiste Dateien wurden gelöscht/)).toBeInTheDocument()
        })

        let syncStatusPollCount = 0
        axiosMock.get.mockImplementation((url: string) => {
            if (url === '/api/admin/materials/storage-audit/operations/operation-1') {
                return Promise.resolve({
                    data: {
                        data: {
                            operation_id: 'operation-1',
                            status: 'completed',
                            progress: 100,
                            completed_steps: 10,
                            total_steps: 10,
                            refresh_after_seconds: 1,
                            message: 'Speicherprüfung abgeschlossen.',
                            result: reportsPayload,
                        },
                    },
                })
            }

            if (url === '/api/admin/materials/storage-audit/sync-operations/sync-operation-1') {
                syncStatusPollCount += 1

                if (syncStatusPollCount === 1) {
                    return Promise.resolve({
                        data: {
                            data: {
                                operation_id: 'sync-operation-1',
                                status: 'running',
                                progress: 50,
                                completed_steps: 1,
                                total_steps: 2,
                                refresh_after_seconds: 1,
                                message: 'Datei 1 von 2 verarbeitet.',
                            },
                        },
                    })
                }

                return Promise.resolve({
                    data: {
                        data: {
                            operation_id: 'sync-operation-1',
                            status: 'completed',
                            progress: 100,
                            completed_steps: 2,
                            total_steps: 2,
                            refresh_after_seconds: 1,
                            message: 'Der Download der Materialdateien ist abgeschlossen.',
                            result: {
                                synced_count: 1,
                                already_local_count: 1,
                            },
                        },
                    },
                })
            }

            return Promise.reject(new Error(`Unexpected GET ${url}`))
        })

        await fireEvent.click(screen.getByRole('button', { name: 'Alle Dateien lokal laden' }))

        await waitFor(() => {
            expect(axiosMock.post).toHaveBeenCalledWith('/api/admin/materials/storage-audit/sync-local', {
                scope_key: 'active_school',
                school_id: 1,
            })
        })

        expect(screen.getByRole('button', { name: 'Alle Dateien lokal laden' })).toBeDisabled()

        await waitFor(() => {
            expect(screen.getByText('Der Download der Materialdateien wurde im Hintergrund gestartet.')).toBeInTheDocument()
        })

        expect(screen.getByText((content) => content.includes('50 %'))).toBeInTheDocument()
        expect(screen.getByText((content) => content.includes('Datei 1 von 2 verarbeitet.'))).toBeInTheDocument()

        await vi.advanceTimersByTimeAsync(1000)

        await waitFor(() => {
            expect(notificationStore.notify).toHaveBeenCalledWith({
                message: 'Der Download der Materialdateien ist abgeschlossen.',
                type: 'success',
            })
        })

        await waitFor(() => {
            expect(screen.queryByText('Der Download der Materialdateien wurde im Hintergrund gestartet.')).not.toBeInTheDocument()
        })

        const brokenAttachmentDeleteButtons = screen.getAllByRole('button', { name: 'Defekten Anhang löschen' })

        await fireEvent.click(brokenAttachmentDeleteButtons[0])

        expect(screen.getByText('Der Eintrag "Informatik - Netzwerke - Sicherheit - Irgendwas • Schule 1" wird aus der Materialkarte entfernt.')).toBeInTheDocument()

        await fireEvent.click(screen.getByRole('button', { name: 'Anhang löschen' }))

        await waitFor(() => {
            expect(axiosMock.delete).toHaveBeenCalledWith(
                '/api/admin/materials/storage-audit/database-only-attachments/301',
                {
                    data: {
                        scope_key: 'active_school',
                        school_id: 1,
                    },
                },
            )
        })

        await waitFor(() => {
            expect(screen.getByText('Der defekte Anhang wurde entfernt.')).toBeInTheDocument()
        })
    })
})
