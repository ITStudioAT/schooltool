import { createTestingPinia } from '@pinia/testing'
import { fireEvent, render, screen, waitFor } from '@testing-library/vue'
import { defineComponent } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import StorageAudit from '@/pages/admin/superAdmin/components/StorageAudit.vue'

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
    'v-skeleton-loader': { template: '<div><slot /></div>' },
    VSkeletonLoader: { template: '<div><slot /></div>' },
    'v-chip': { template: '<span><slot /></span>' },
    VChip: { template: '<span><slot /></span>' },
    'v-btn': { template: '<button type="button"><slot /></button>' },
    VBtn: { template: '<button type="button"><slot /></button>' },
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
    }

    beforeEach(() => {
        axiosMock.get.mockReset()
        axiosMock.post.mockReset()
        axiosMock.post.mockResolvedValue({
            data: {
                message: 'Verwaiste Dateien wurden gelöscht.',
            },
        })
        axiosMock.get.mockResolvedValue({
            data: {
                data: {
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
                                    count: 0,
                                    total_bytes: 0,
                                },
                            },
                            bucket_only_objects: [
                                {
                                    path: 'materials/schools/1/users/7/cards/orphans/active-orphan.pdf',
                                    size_bytes: 256,
                                },
                            ],
                            database_only_attachments: [],
                            has_more_bucket_only_objects: false,
                            has_more_database_only_attachments: false,
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
                                    count: 0,
                                    total_bytes: 0,
                                },
                            },
                            bucket_only_objects: [
                                {
                                    path: 'materials/schools/2/users/9/cards/orphans/other-orphan.pdf',
                                    size_bytes: 1024,
                                },
                            ],
                            database_only_attachments: [],
                            has_more_bucket_only_objects: false,
                            has_more_database_only_attachments: false,
                        },
                    ],
                },
            },
        })

        ;(globalThis as any).axios = axiosMock
    })

    afterEach(() => {
        ;(globalThis as any).axios = originalAxios
        vi.clearAllMocks()
    })

    it('shows additional storage summary values for the active school and all schools', async () => {
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
            expect(axiosMock.get).toHaveBeenCalledWith('/api/admin/materials/storage-audit', {
                params: { school_id: 1 },
            })
        })

        expect(screen.getAllByText('Zusätzliche Kennzahlen')).toHaveLength(2)
        expect(screen.getAllByText('DB gesamt')).toHaveLength(2)
        expect(screen.getAllByText('Bucket vs. DB gesamt')).toHaveLength(2)
        expect(screen.getAllByText('Bucket-Reste Anteil')).toHaveLength(2)
        expect(screen.getByText('Stand: 09.04.2026, 23:12 Uhr')).toBeInTheDocument()
        expect(screen.getByText('+2.50 KB')).toBeInTheDocument()
        expect(screen.getByText('6.3 %')).toBeInTheDocument()
        expect(screen.getByText('+2.00 KB')).toBeInTheDocument()
        expect(screen.getByText('12.5 %')).toBeInTheDocument()

        const deleteButtons = screen.getAllByRole('button', { name: 'Alle verwaisten Dateien löschen' })
        expect(deleteButtons).toHaveLength(2)

        await fireEvent.click(deleteButtons[0])

        expect(screen.getByText('Aktive Schule: Christian-Doppler-Gymnasium Salzburg')).toBeInTheDocument()
        expect(screen.getByText('1 verwaiste Datei mit insgesamt 256 B werden dauerhaft aus dem Bucket entfernt.')).toBeInTheDocument()

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
    })
})
