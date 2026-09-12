import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, shallowMount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import { VRadioGroup, VSelect } from 'vuetify/components'
import { createTestingPinia } from '@pinia/testing'
import { reactive } from 'vue'
import DataRefresh from '@/pages/admin/studentsTimetables/timetable/DataRefresh.vue'
import Timetable from '@/pages/admin/studentsTimetables/timetable/Timetable.vue'

vi.mock('@/pages/admin/studentsTimetables/overview/Overview.vue', () => ({
    default: { name: 'Overview', template: '<div />' },
}))

describe('Students timetable timetable page', () => {

    it('automatically skips invalid entries when merging after reviewing the comparison', async () => {
        const preview = {
            id: 10, import_status: 'preview', original_filename: 'stundenplan.txt', sections: { TT: 9343 },
            tt_skipped_invalid: 72, date_plausibility: { is_plausible: true, message: 'Zeitraum passt.' },
            tt_diagnostics: { source_available: true, records: [] },
        }
        const comparison = {
            operation: 'merge', scope: null, can_confirm: true, fingerprint: 'merge-fingerprint',
            new_entries: 9271, updated_entries: 0, unchanged_entries: 0,
            removed_entries: 0, removed_appointment_count: 0, removed_appointments: [],
        }
        const post = vi.fn().mockResolvedValue({ data: {} })
        const get = vi.fn(async (url: string) => url.endsWith('/comparison')
            ? { data: { data: comparison } }
            : { data: { data: [], preview } })
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' },
            { roles: ['admin'] },
            true,
        )

        try {
            await flushPromises()
            expect(wrapper.text()).toContain('72 TT-Einträge werden automatisch übersprungen')
            expect(wrapper.text()).toContain('Für den Import und den Vergleich werden die 9271 gültigen TT-Einträge verwendet')
            expect(wrapper.find('[data-testid="timetable-valid-only"]').exists()).toBe(false)
            expect(wrapper.get('[data-testid="confirm-timetable-preview"]').attributes('disabled')).toBeDefined()
            expect(get.mock.calls.some(([url]) => url.endsWith('/comparison'))).toBe(false)
            expect(post).not.toHaveBeenCalled()
            await wrapper.get('input[type="radio"][value="merge"]').setValue(true)
            await flushPromises()
            expect(wrapper.get('[data-testid="confirm-timetable-preview"]').attributes('disabled')).toBeUndefined()
            await wrapper.get('[data-testid="confirm-timetable-preview"]').trigger('click')
            await flushPromises()
            expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/students-timetables/imports/10/confirm', {
                operation: 'merge', mode: 'partial', fingerprint: 'merge-fingerprint',
            })
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it.each([
        { operation: 'merge', ttTotal: 72, isPlausible: true },
        { operation: 'merge', ttTotal: 73, isPlausible: false },
        { operation: 'replace', ttTotal: 72, isPlausible: true },
        { operation: 'replace', ttTotal: 73, isPlausible: false },
    ])('blocks partial import when no rows are usable or dates mismatch: %j', async ({ operation, ttTotal, isPlausible }) => {
        const preview = {
            id: 10, import_status: 'preview', sections: { TT: ttTotal }, tt_skipped_invalid: 72,
            date_plausibility: { is_plausible: isPlausible },
            tt_diagnostics: { source_available: false, records: [] },
        }
        vi.stubGlobal('axios', { get: vi.fn(async (url: string) => url.endsWith('/comparison')
            ? { data: { data: { can_confirm: true, fingerprint: 'fingerprint', removed_entries: 0 } } }
            : { data: { data: [], preview } }) })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' },
            { roles: ['admin'] },
            true,
        )
        try {
            await flushPromises()
            await wrapper.get(`input[type="radio"][value="${operation}"]`).setValue(true)
            if (operation === 'replace') {
                wrapper.getComponent(VSelect).vm.$emit('update:modelValue', 'semester1')
            }
            await flushPromises()
            expect(wrapper.get('[data-testid="confirm-timetable-preview"]').attributes('disabled')).toBeDefined()
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('labels completed partial history and loads skipped-record diagnostics on demand', async () => {
        const imported = {
            id: 10, import_status: 'completed', import_mode: 'partial', original_filename: 'teilimport.txt',
            sections: { TT: 3 }, tt_imported_rows: 2, tt_skipped_invalid: 1,
            import_message: 'Teilimport abgeschlossen: 2 TT-Datensätze verarbeitet, 1 nach aktuellen Prüfregeln ausgelassen.',
        }
        const get = vi.fn(async (url: string) => {
            if (url === '/api/admin/students-timetables/imports/10') {
                return { data: { data: { ...imported, tt_diagnostics: { source_available: true, records: [{
                    line_number: 72, source_identifier: '0', errors: [{
                        column: 2, field: 'Quellkennung', value: '0', reason: 'Nach aktuellen Regeln ausgelassen.', expected: 'Wert ungleich 0.',
                    }],
                }] } } } }
            }
            return { data: { data: url === '/api/admin/students-timetables/imports' ? [imported] : [], preview: null } }
        })
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan' },
            { roles: ['admin'] },
            true,
        )
        try {
            await flushPromises()
            expect(wrapper.text()).toContain(imported.import_message)
            expect(wrapper.text()).toContain('1 nicht übernehmbare TT-Einträge wurden übersprungen')
            expect(wrapper.text()).not.toContain('kein vollständiger Import')
            expect(get.mock.calls.some(([url]) => url.endsWith('/imports/10'))).toBe(false)
            const details = wrapper.get('[data-testid="tt-diagnostics"]')
            ;(details.element as HTMLDetailsElement).open = true
            await details.trigger('toggle')
            await flushPromises()
            expect(get).toHaveBeenCalledWith('/api/admin/students-timetables/imports/10')
            expect(details.text()).toContain('Zeile 72')
            expect(details.text()).toContain('Nach aktuellen Regeln ausgelassen.')
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it.each([0, 72])('previews every removed appointment and confirms replacement while skipping %i invalid entries', async (skippedInvalid) => {
        const scope = { key: 'semester1', label: 'Wintersemester', from: '2026-09-01', until: '2027-02-14' }
        const preview = {
            id: 19, import_status: 'preview', original_filename: 'sokrates.txt', sections: { TT: 70 + skippedInvalid },
            tt_skipped_invalid: skippedInvalid, tt_courses: 2, replacement_scopes: [scope],
            date_plausibility: { is_plausible: true, message: 'Zeitraum passt.' },
        }
        const removedAppointments = Array.from({ length: 36 }, (_, index) => ({
            course: index === 35 ? 'Letzter Kurs' : 'M6 - 4R - SCHM', date: '2026-12-01', weekday: 'Di',
            starts_at: '18:45', ends_at: '20:15', entry_count: 2,
        }))
        const comparison = {
            operation: 'replace', scope, can_confirm: true, fingerprint: 'replacement-fingerprint',
            new_entries: 34, updated_entries: 0, unchanged_entries: 36,
            removed_entries: 72, removed_appointment_count: 36, removed_appointments: removedAppointments,
        }
        const post = vi.fn().mockResolvedValue({ data: {} })
        const get = vi.fn(async (url: string) => url.endsWith('/comparison')
            ? { data: { data: comparison } }
            : { data: { data: [], preview } })
        vi.stubGlobal('axios', { get, post })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' },
            { roles: ['admin'], selected_schoolyear: { id: 14, concerns: '2026/27' } },
            true,
        )

        try {
            await flushPromises()
            expect(wrapper.text()).toContain('1. Datei geprüft')
            expect(wrapper.text()).toContain('2. Übernahme wählen')
            expect(wrapper.text()).toContain('3. Änderungen prüfen')
            expect(wrapper.text()).toContain('70 gültige TT-Einträge')
            expect(wrapper.find('[data-testid="timetable-valid-only"]').exists()).toBe(false)
            if (skippedInvalid > 0) {
                expect(wrapper.text()).toContain('72 TT-Einträge werden automatisch übersprungen')
            }
            expect(wrapper.get('input[type="radio"][value="replace"]').attributes('disabled')).toBeUndefined()
            await wrapper.get('input[type="radio"][value="replace"]').setValue(true)
            expect(wrapper.get('[data-testid="confirm-timetable-preview"]').attributes('disabled')).toBeDefined()
            wrapper.getComponent(VSelect).vm.$emit('update:modelValue', 'semester1')
            await flushPromises()
            expect(get).toHaveBeenCalledWith('/api/admin/students-timetables/imports/19/comparison', {
                params: { operation: 'replace', scope: 'semester1' },
            })
            const removed = wrapper.get('[data-testid="timetable-removed-appointments"]')
            expect(removed.text()).toContain('36 Termine mit 72 TT-Einträgen würden gestrichen')
            expect(removed.text()).toContain('M6 - 4R - SCHM')
            expect(removed.text()).toContain('Di, 01.12.2026')
            expect(removed.text()).toContain('18:45–20:15')
            expect(removed.text()).not.toContain('Letzter Kurs')
            const paginationButtons = removed.get('.v-data-table-footer__pagination').findAll('button')
            await paginationButtons[paginationButtons.length - 1].trigger('click')
            await flushPromises()
            expect(removed.text()).toContain('Letzter Kurs')
            expect(post).not.toHaveBeenCalled()
            const confirm = wrapper.get('[data-testid="confirm-timetable-preview"]')
            expect(confirm.text()).toContain('Plan ersetzen – Änderungen übernehmen')
            expect(confirm.attributes('disabled')).toBeUndefined()
            await confirm.trigger('click')
            await flushPromises()
            expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/students-timetables/imports/19/confirm', {
                operation: 'replace', scope: 'semester1', fingerprint: 'replacement-fingerprint',
                mode: skippedInvalid > 0 ? 'partial' : 'strict',
            })
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('shows the replacement range, skipped entries and resulting changes in import history', async () => {
        const imported = {
            id: 19, import_status: 'completed', import_operation: 'replace', import_mode: 'partial',
            original_filename: 'sokrates.txt', tt_skipped_invalid: 72, tt_imported_rows: 70,
            replacement_from: '2026-09-01', replacement_until: '2027-02-14',
            change_summary: { removed_appointment_count: 18, removed_entries: 36, new_entries: 34, updated_entries: 2 },
        }
        vi.stubGlobal('axios', { get: vi.fn(async () => ({ data: { data: [imported] } })) })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan' },
            { roles: ['admin'] }, true,
        )

        try {
            await flushPromises()
            expect(wrapper.text()).toContain('Plan ersetzt · 01.09.2026–14.02.2027')
            expect(wrapper.text()).toContain('18 Termine gestrichen · 34 TT-Einträge neu · 2 aktualisiert')
            expect(wrapper.text()).toContain('Bereits gespeicherte Stundenpläne bitte prüfen.')
            expect(wrapper.text()).toContain('72 nicht übernehmbare TT-Einträge wurden übersprungen')
            expect(wrapper.text()).not.toContain('kein vollständiger Import')
            expect(wrapper.text()).not.toContain('Teilimport')
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('discards an outdated comparison response after switching to a different operation', async () => {
        const scope = { key: 'semester1', label: 'Wintersemester', from: '2026-09-01', until: '2027-02-14' }
        const preview = {
            id: 19, import_status: 'preview', sections: { TT: 70 }, replacement_scopes: [scope],
            date_plausibility: { is_plausible: true },
        }
        let finishReplacement: (value: unknown) => void = () => {}
        const get = vi.fn(async (url: string, options: any) => {
            if (!url.endsWith('/comparison')) return { data: { data: [], preview } }
            if (options.params.operation === 'replace') return new Promise(resolve => { finishReplacement = resolve })

            return { data: { data: { operation: 'merge', can_confirm: true, fingerprint: 'merge', removed_entries: 0 } } }
        })
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' },
            { roles: ['admin'] }, true,
        )

        try {
            await flushPromises()
            wrapper.getComponent(VRadioGroup).vm.$emit('update:modelValue', 'replace')
            await flushPromises()
            wrapper.getComponent(VSelect).vm.$emit('update:modelValue', 'semester1')
            await flushPromises()
            expect(wrapper.get('[data-testid="confirm-timetable-preview"]').attributes('disabled')).toBeDefined()
            wrapper.getComponent(VRadioGroup).vm.$emit('update:modelValue', 'merge')
            await flushPromises()
            finishReplacement({ data: { data: { operation: 'replace', can_confirm: true, fingerprint: 'old', removed_entries: 72 } } })
            await flushPromises()
            expect((wrapper.vm as any).timetableComparison.fingerprint).toBe('merge')
            expect(wrapper.text()).toContain('Es werden keine bestehenden Termine gestrichen')
            expect(wrapper.find('[data-testid="timetable-removed-appointments"]').exists()).toBe(false)
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('clears the selected import and comparison immediately when the personal schoolyear changes', async () => {
        const preview = {
            id: 19, import_status: 'preview', sections: { TT: 70 }, date_plausibility: { is_plausible: true },
        }
        const get = vi.fn(async (url: string) => url.endsWith('/comparison')
            ? { data: { data: { operation: 'merge', can_confirm: true, fingerprint: 'old-year', removed_entries: 0 } } }
            : { data: { data: [], preview } })
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' },
            { roles: ['admin'], selected_schoolyear: { id: 14, concerns: '2026/27' } }, true,
        )

        try {
            await flushPromises()
            wrapper.getComponent(VRadioGroup).vm.$emit('update:modelValue', 'merge')
            await flushPromises()
            expect(wrapper.get('[data-testid="confirm-timetable-preview"]').attributes('disabled')).toBeUndefined()
            get.mockImplementation(async () => ({ data: { data: [], preview: null } }))
            ;(wrapper.vm as any).config.selected_schoolyear = { id: 15, concerns: '2027/28' }
            expect((wrapper.vm as any).canConfirmTimetablePreview).toBe(false)
            await flushPromises()
            expect((wrapper.vm as any).timetablePreview).toBeNull()
            expect((wrapper.vm as any).timetableComparison).toBeNull()
            expect((wrapper.vm as any).timetableImportOperation).toBe('')
            expect(wrapper.find('[data-testid="confirm-timetable-preview"]').exists()).toBe(false)
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('discards pending import metadata from the previous personal schoolyear and reloads the new scope', async () => {
        const oldPreview = { id: 19, original_filename: 'altes-schuljahr.txt', sections: { TT: 70 } }
        const newPreview = { id: 20, original_filename: 'neues-schuljahr.txt', sections: { TT: 72 } }
        let finishOldMetadata: (value: unknown) => void = () => {}
        let timetableRequestCount = 0
        const get = vi.fn(async (url: string) => {
            if (url !== '/api/admin/students-timetables/imports') return { data: { data: [] } }
            timetableRequestCount++
            if (timetableRequestCount === 1) return new Promise(resolve => { finishOldMetadata = resolve })

            return { data: { data: [], preview: newPreview } }
        })
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' },
            { roles: ['admin'], selected_schoolyear: { id: 14, concerns: '2026/27' } }, true,
        )

        try {
            await flushPromises()
            ;(wrapper.vm as any).config.selected_schoolyear = { id: 15, concerns: '2027/28' }
            await flushPromises()
            finishOldMetadata({ data: { data: [], preview: oldPreview } })
            await flushPromises()
            expect(timetableRequestCount).toBe(2)
            expect(wrapper.text()).not.toContain('altes-schuljahr.txt')
            expect(wrapper.text()).toContain('neues-schuljahr.txt')
            expect((wrapper.vm as any).timetableImportOperation).toBe('')
            expect(wrapper.get('[data-testid="confirm-timetable-preview"]').attributes('disabled')).toBeDefined()
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('never reports pending or failed rows as imported', () => {
        const count = (Timetable as any).methods.importedTtCount
        expect(count({ import_status: 'pending', sections: { TT: 73 }, tt_skipped_invalid: 1 })).toBe(0)
        expect(count({ import_status: 'failed', sections: { TT: 73 }, tt_skipped_invalid: 1 })).toBe(0)
        expect(count({ import_status: 'completed', sections: { TT: 73 }, tt_imported_rows: 71, tt_skipped_invalid: 1 })).toBe(71)
        expect(count({ import_status: 'completed', sections: { TT: 73 }, tt_imported_rows: null, tt_skipped_invalid: 1 })).toBe(72)
    })
    function mountImport116Page(
        routeParams: Record<string, string> = { section: 'timetable', subsection: 'imports', detail: 'import116' },
        config: Record<string, unknown> = { roles: ['admin'] },
        renderDiagnostics = false,
    ) {
        return (renderDiagnostics ? mount : shallowMount)(Timetable, {
            global: {
                components: renderDiagnostics ? { 'v-btn': VBtn } : {},
                plugins: [createVuetify({ components: renderDiagnostics ? { VBtn } : {} }), createTestingPinia({
                    createSpy: vi.fn,
                    initialState: { AdminAdminStore: { config } },
                })],
                mocks: {
                    $route: { params: routeParams },
                    $router: { replace: vi.fn(), push: vi.fn() },
                },
                renderStubDefaultSlot: true,
                stubs: {
                    ...(renderDiagnostics ? { 'v-table': false, VTable: false, 'v-btn': false, VBtn: false } : {}),
                    FileUpload: true,
                    TimetableImportDiagnostics: false,
                    Overview: true,
                    LoadingAnimation: true,
                    DataRefresh: true,
                    'v-checkbox': !renderDiagnostics,
                    'v-divider': true,
                    'v-expansion-panel': true,
                    'v-expansion-panel-text': true,
                    'v-expansion-panel-title': true,
                    'v-expansion-panels': true,
                    'v-list-item-title': true,
                },
            },
        })
    }

    it('renders all skipped TT records and lets users reach the last page before choosing an operation', async () => {
        const records = Array.from({ length: 72 }, (_, index) => ({
            line_number: 1000 + index,
            source_identifier: '0', date: '20260914', period: '11', starts_at: '17:50', ends_at: '18:35', course: '',
            errors: [
                { column: 2, field: 'Quellkennung', value: '0', reason: 'Quellkennung 0 ist nicht importierbar.', expected: 'Kennung ungleich 0.' },
                { column: 8, field: 'Kurs-/Klassenbezeichnung', value: '', reason: 'Kurszuordnung fehlt.', expected: 'Nicht leere Bezeichnung.' },
            ],
        }))
        records[71].errors[0].value = '<img src=x onerror=alert(1)>'
        const preview = {
            id: 9, original_filename: 'stundenplan.txt', sections: { TT: 73 }, tt_skipped_invalid: 72,
            date_plausibility: { is_plausible: true, message: 'Zeitraum passt.' },
            tt_diagnostics: { source_available: true, records },
        }
        vi.stubGlobal('axios', { get: vi.fn(async () => ({ data: { data: [], preview } })), post: vi.fn() })
        const wrapper = mountImport116Page(
            { section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' },
            { roles: ['admin'] },
            true,
        )

        try {
            await flushPromises()
            const details = wrapper.get('[data-testid="tt-diagnostics"]')
            expect(details.text()).toContain('Betroffene TT-Datensätze und Prüfgründe (72)')
            expect(details.text()).toContain('einschließlich Leerzeilen')
            await details.get('summary').trigger('click')
            expect(details.text()).toContain('Zeile 1000')
            expect(details.text()).toContain('Feld 2: Quellkennung')
            expect(details.text()).toContain('Feld 8: Kurs-/Klassenbezeichnung')
            expect(details.text()).toContain('Wert: (leer)')
            expect(details.text()).toContain('Erwartet: Nicht leere Bezeichnung.')
            expect(details.text()).not.toContain('Zeile 1071')

            const pagination = details.get('.v-data-table-footer__pagination')
            const buttons = pagination.findAll('button')
            await buttons[buttons.length - 1].trigger('click')
            await flushPromises()
            expect(details.text()).toContain('Zeile 1071')
            expect(details.text()).toContain('<img src=x onerror=alert(1)>')
            expect(details.find('img').exists()).toBe(false)
            expect(details.text()).not.toContain('Zeile 1000')
            const importButton = wrapper.get('[data-testid="confirm-timetable-preview"]')
            expect(importButton.attributes('disabled')).toBeDefined()
            expect(axios.post).not.toHaveBeenCalled()
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('explains unavailable archived TT diagnostics and shows the skipped count', async () => {
        const preview = {
            id: 9, tt_skipped_invalid: 72, sections: { TT: 73 },
            tt_diagnostics: { source_available: false, records: [] },
        }
        vi.stubGlobal('axios', { get: vi.fn(async () => ({ data: { data: [], preview } })) })
        const wrapper = mountImport116Page({ section: 'timetable', subsection: 'imports', detail: 'stundenplan', action: 'import' })

        try {
            await flushPromises()
            expect(wrapper.get('[data-testid="tt-diagnostics"]').text()).toContain('Quelldatei ist nicht verfügbar')
            expect(wrapper.text()).toContain('72 TT-Einträge werden automatisch übersprungen')
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('shows every affected student row when opening import 116 details on the timetable page', async () => {
        const warnings = [652, 653].map(row =>
            `Excel-Zeile ${row}: Muster Anna (Klasse 2U, Schülerkennzahl STU-002): Schulstufe 10_1 nicht zulässig.`,
        )
        const run = {
            id: 24,
            status: 'completed',
            counts: { inserted: 0, unchanged: 1, warning_rows: 2 },
            report_summary_preview: { warnings: [warnings[0]] },
        }
        const get = vi.fn(async (url: string) => {
            if (url === '/api/admin/students-timetables/import116/runs/24') {
                return { data: { run: { ...run, report_summary: { warnings } }, changes: {} } }
            }
            if (url === '/api/admin/students-timetables/import116/runs') {
                return { data: { data: [run] } }
            }
            return { data: { data: [] } }
        })
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page()

        try {
            await flushPromises()
            expect(get.mock.calls.filter(([url]) => url.endsWith('/import116/runs'))).toHaveLength(1)

            expect(wrapper.get('[data-testid="import116-run-warnings"]').text()).toContain('2 Excel-Zeilen')
            expect(wrapper.find('[data-testid="import116-warning-details"]').exists()).toBe(false)

            await wrapper.findAll('v-btn').find(button => button.text() === 'Details anzeigen')!.trigger('click')
            await flushPromises()

            const details = wrapper.get('[data-testid="import116-warning-details"]')
            expect(details.text()).toContain('Betroffene Studierende')
            expect(details.text()).toContain('Muster Anna')
            expect(details.text()).toContain('Klasse 2U')
            expect(details.text()).toContain('Schülerkennzahl STU-002')
            for (const warning of warnings) {
                expect(details.text()).toContain(warning)
            }
            expect(get).toHaveBeenCalledWith('/api/admin/students-timetables/import116/runs/24')

            await wrapper.findAll('v-btn').find(button => button.text() === 'Details ausblenden')!.trigger('click')
            expect(wrapper.find('[data-testid="import116-warning-details"]').exists()).toBe(false)

            await wrapper.findAll('v-btn').find(button => button.text() === 'Aktualisieren')!.trigger('click')
            await flushPromises()
            await wrapper.findAll('v-btn').find(button => button.text() === 'Details anzeigen')!.trigger('click')
            await flushPromises()

            expect(wrapper.get('[data-testid="import116-warning-details"]').text()).toContain(warnings[1])
            expect(get.mock.calls.filter(([url]) => url.endsWith('/runs/24'))).toHaveLength(1)
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('opens legacy import 116 details without warning metadata', async () => {
        const run = { id: 23, status: 'completed', counts: { inserted: 1 } }
        const get = vi.fn(async (url: string) => {
            if (url === '/api/admin/students-timetables/import116/runs/23') {
                return { data: { run, changes: {} } }
            }
            return { data: { data: url.endsWith('/import116/runs') ? [run] : [] } }
        })
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page()

        try {
            await flushPromises()
            await wrapper.findAll('v-btn').find(button => button.text() === 'Details anzeigen')!.trigger('click')
            await flushPromises()

            expect(get).toHaveBeenCalledWith('/api/admin/students-timetables/import116/runs/23')
            expect(wrapper.find('[data-testid="import116-run-warnings"]').exists()).toBe(false)
            expect(wrapper.find('[data-testid="import116-warning-details"]').exists()).toBe(false)
            expect(wrapper.text()).toContain('Eingefügt (0)')
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('loads import 116 history on navigation, re-entry and schoolyear changes', async () => {
        const routeParams = reactive({ section: 'timetable', subsection: 'overview', detail: '' })
        const get = vi.fn(async (_url: string) => ({ data: { data: [] } }))
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page(routeParams, { roles: ['admin'], selected_schoolyear: { id: 25 } })
        const historyRequests = () => get.mock.calls.filter(([url]) => url === '/api/admin/students-timetables/import116/runs')

        try {
            await flushPromises()
            expect(historyRequests()).toHaveLength(0)

            routeParams.subsection = 'imports'
            routeParams.detail = 'import116'
            await flushPromises()
            expect(historyRequests()).toHaveLength(1)

            routeParams.detail = 'anrechnungen'
            await flushPromises()
            expect(historyRequests()).toHaveLength(1)
            routeParams.detail = 'import116'
            await flushPromises()
            expect(historyRequests()).toHaveLength(2)

            ;(wrapper.vm as any).config.selected_schoolyear.id = 26
            await flushPromises()
            expect(historyRequests()).toHaveLength(3)
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('loads import 116 history when role configuration arrives after mounting', async () => {
        const get = vi.fn(async (_url: string) => ({ data: { data: [] } }))
        vi.stubGlobal('axios', { get })
        const wrapper = mountImport116Page(undefined, {})

        try {
            await flushPromises()
            expect(get).not.toHaveBeenCalledWith('/api/admin/students-timetables/import116/runs')
            ;(wrapper.vm as any).config = { roles: ['admin'] }
            await flushPromises()
            expect(get.mock.calls.filter(([url]) => url === '/api/admin/students-timetables/import116/runs')).toHaveLength(1)
        } finally {
            wrapper.unmount()
            vi.unstubAllGlobals()
        }
    })

    it('shows the timetable overview on the timetable overview subpage', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue',
            'utf8',
        )

        expect(componentSource).toContain(
            "const Overview = defineAsyncComponent(() => import('../overview/Overview.vue'))",
        )
        expect(componentSource).toContain('<Overview v-if="subAction === \'overview\'" />')
        expect(componentSource).not.toContain("import RobotTimetable from '../robot/RobotTimetable.vue'")
        expect(componentSource).not.toContain('<RobotTimetable v-else-if="subAction === \'robot\'" />')
        expect(componentSource).not.toContain("{ key: 'overview', label: 'Übersicht' }")
        expect(componentSource).not.toContain("{ key: 'robot', label: 'Wizzard' }")
        expect(componentSource).not.toContain("{ key: 'imports', label: 'Importe' }")
        expect(componentSource).toContain("const allowed = this.canManageTimetableImports")
        expect(componentSource).toContain('configuredRolesLoaded()')
        expect(componentSource).toContain('syncRouteStateFromParams()')
        expect(componentSource).toContain("['overview', 'imports']")
        expect(componentSource).toContain("['overview']")
        expect(componentSource).toContain('redirectUnauthorizedImportRoute()')
        expect(componentSource).toContain('redirectLegacyOverviewRoute()')
        expect(componentSource).toContain('redirectLegacyRobotRoute()')
        expect(componentSource).toContain('mounted() {\n        this.syncRouteStateFromParams()')
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/timetable/overview' })")
        expect(componentSource).not.toContain("this.$router.replace({ path: '/admin/students-timetables' })")
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/timetable/overview/automatic' })")
        expect(componentSource).not.toContain('v-if="showTimetableSubnav"')
        expect(componentSource).not.toContain('showTimetableSubnav()')
        expect(componentSource).not.toContain('subnavItems()')
        expect(componentSource).not.toContain('handleSubnavigation(key)')
        expect(componentSource).not.toContain('Hier entsteht das Stundenplan Center.')
    })

    it('writes the default timetable overview step into the URL', () => {
        const methods = (Timetable as any).methods
        const replace = vi.fn()
        const ctx: any = {
            $route: {
                params: {
                    section: 'timetable',
                },
            },
            $router: {
                replace,
            },
            subAction: 'imports',
            importPage: 'stundenplan',
            importSubPage: 'import',
        }

        expect(methods.redirectLegacyOverviewRoute.call(ctx)).toBe(true)
        expect(ctx.subAction).toBe('overview')
        expect(ctx.importPage).toBe('')
        expect(ctx.importSubPage).toBe('')
        expect(replace).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable/overview' })
    })

    it('keeps the imports route active while role config is still loading', () => {
        const methods = (Timetable as any).methods
        const replace = vi.fn()
        const ctx: any = {
            $route: {
                params: {
                    subsection: 'imports',
                },
            },
            $router: {
                replace,
            },
            canManageTimetableImports: false,
            configuredRolesLoaded: false,
            subAction: 'imports',
            importPage: '',
            importSubPage: '',
        }

        methods.redirectUnauthorizedImportRoute.call(ctx)

        expect(ctx.subAction).toBe('imports')
        expect(replace).not.toHaveBeenCalled()
    })

    it('restores the direct imports route after admin roles load', () => {
        const methods = (Timetable as any).methods
        const roleWatcher = (Timetable as any).watch.configuredRoleNames
        const ctx: any = {
            ...methods,
            $route: {
                params: {
                    section: 'timetable',
                    subsection: 'imports',
                    detail: 'stundenplan',
                    action: 'import',
                },
            },
            $router: {
                replace: vi.fn(),
            },
            canManageTimetableImports: true,
            configuredRolesLoaded: true,
            subAction: 'overview',
            importPage: '',
            importSubPage: '',
            loadImportButtonInfo: vi.fn(),
        }

        roleWatcher.call(ctx)

        expect(ctx.subAction).toBe('imports')
        expect(ctx.importPage).toBe('stundenplan')
        expect(ctx.importSubPage).toBe('import')
        expect(ctx.loadImportButtonInfo).toHaveBeenCalledOnce()
        expect(ctx.$router.replace).not.toHaveBeenCalled()
    })

    it('restores the direct imports route when the component mounts with roles already loaded', () => {
        const methods = (Timetable as any).methods
        const ctx: any = {
            ...methods,
            $route: {
                params: {
                    section: 'timetable',
                    subsection: 'imports',
                },
            },
            $router: {
                replace: vi.fn(),
            },
            canManageTimetableImports: true,
            configuredRolesLoaded: true,
            subAction: 'overview',
            importPage: '',
            importSubPage: '',
            loadImportButtonInfo: vi.fn(),
        }

        methods.syncRouteStateFromParams.call(ctx)

        expect(ctx.subAction).toBe('imports')
        expect(ctx.importPage).toBe('')
        expect(ctx.importSubPage).toBe('')
        expect(ctx.loadImportButtonInfo).toHaveBeenCalledOnce()
        expect(ctx.$router.replace).not.toHaveBeenCalled()
    })

    it('shows import buttons that open import subpages', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue',
            'utf8',
        )
        const routeSource = readFileSync('resources/routes/admin.js', 'utf8')
        const fileUploadSource = readFileSync('resources/js/pages/components/FileUpload.vue', 'utf8')

        expect(componentSource).toContain('v-if="subAction === \'imports\' && !activeImportPage"')
        expect(componentSource).toContain('Importe')
        expect(componentSource).toContain('<span>Importe:</span>')
        expect(componentSource).toContain('{{ personalImportSchoolyearLabel }}')
        expect(componentSource).toContain('class="text-h6 font-weight-bold text-primary"')
        expect(componentSource).toContain('importButtons()')
        expect(componentSource).toContain("label: 'Stundenplan'")
        expect(componentSource).toContain("label: 'Anrechnungen'")
        expect(componentSource).toContain("label: 'Sokrates 116'")
        expect(componentSource).toContain("key: 'datenaktualisierung'")
        expect(componentSource).toContain("label: 'Datenaktualisierung'")
        expect(componentSource).toContain("meta: 'Studienauswahl und Noten aktualisieren'")
        expect(componentSource).toContain("'datenaktualisierung'")
        expect(componentSource).toContain('<DataRefresh')
        expect(componentSource).toContain(':disabled="button.disabled === true"')
        expect(componentSource).not.toContain("key: 'faecher'")
        expect(componentSource).toContain(':prepend-icon="button.icon"')
        expect(componentSource).toContain('/api/admin/students-timetables/imports')
        expect(componentSource).not.toContain('subjectImportIndexRoute')
        expect(componentSource).toContain('Letzter Import:')
        expect(componentSource).toContain('@click="openImportPage(button.key)"')
        expect(componentSource).toContain('openImportPage(key)')
        expect(componentSource).toContain('if (!importButton || importButton.disabled === true) return')
        expect(componentSource).toContain('/admin/students-timetables/timetable/imports/${this.importPage}')
        expect(componentSource).toContain('activeImportButton')
        expect(componentSource).toContain('st-import-page-title')
        expect(componentSource).toContain('class="st-import-back-button"')
        expect(componentSource).toContain('margin-left: auto')
        expect(componentSource.indexOf('class="st-import-back-button"'))
            .toBeLessThan(componentSource.indexOf('@click="closeImportPage"'))
        expect(componentSource).toContain('st-import-file-info-card')
        expect(componentSource).toContain('Benötigte Importdatei')
        expect(componentSource.indexOf('<span class="st-import-page-title__label">{{ activeImportButton.label }}</span>'))
            .toBeLessThan(componentSource.indexOf('<span class="font-weight-bold">Benötigte Importdatei</span>'))
        expect(componentSource).toContain('st-import-file-info-card__button')
        expect(componentSource).toContain('@click="closeImportPage"')
        expect(componentSource).toContain('to="/admin/students-timetables/timetable/imports/stundenplan/import"')
        expect(componentSource.indexOf('class="st-import-file-info-card__button"'))
            .toBeLessThan(componentSource.indexOf('to="/admin/students-timetables/timetable/imports/stundenplan/import"'))
        expect(componentSource).toContain('activeImportSubPage === \'import\'')
        expect(componentSource).toContain('TXT-Datei importieren')
        expect(componentSource).not.toContain('JSON-Datei importieren')
        expect(componentSource).toContain('activeImportUploadTitle')
        expect(componentSource).toContain('activeImportUploadPath')
        expect(componentSource).toContain('activeImportAllowedFileTypes')
        expect(componentSource).toContain('activeImportUploadVisible')
        expect(componentSource).toContain('Schuljahr ändern')
        expect(componentSource).toContain('@click.prevent="openSchoolyearEdit"')
        expect(componentSource).toContain('v-model="schoolyearDialog"')
        expect(componentSource).toContain('saveSchoolyear')
        expect(componentSource).toContain('useSchoolyearStore')
        expect(componentSource).toContain('useValidationRulesSetup')
        expect(componentSource).toContain('/api/admin/students-timetables/upload')
        expect(componentSource).not.toContain('subjectUploadRoute')
        expect(componentSource).not.toContain("['application/json']")
        expect(componentSource).toContain("['text/plain']")
        expect(componentSource).toContain('activeImportUploadSuccessLabel')
        expect(componentSource).toContain('refreshFilePond')
        expect(componentSource).toContain('onImportUploadStart')
        expect(componentSource).toContain('onUploadFinished')
        expect(componentSource).toContain('onUploadError')
        expect(componentSource).toContain('TXT-Datei (.txt)')
        expect(componentSource).toContain('Untis-Export')
        expect(componentSource).toContain('Tabstopps / tabulatorgetrennt')
        expect(componentSource).toContain('TT-Einträge mit Stundenplan-Zeilen')
        expect(componentSource).toContain('Folgende Einträge dürfen in der Untis-Datei enthalten sein:')
        expect(componentSource).toContain('mdi-information-outline')
        expect(componentSource).not.toContain('<span>Info:</span>')
        expect(componentSource).toContain('allowedUntisEntryTypes')
        expect(componentSource).toContain('st-import-file-info-note')
        expect(componentSource).toContain('st-import-history-card')
        expect(componentSource).toContain('Importverlauf')
        expect(componentSource).toContain('downloadSource as downloadTimetableImportSource')
        expect(componentSource).toContain('confirm as confirmTimetableImport')
        expect(componentSource).toContain('destroy as destroyTimetableImport')
        expect(componentSource).toContain("import { downloadSource as downloadRecognitionImportSource } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/RecognitionCsvUploadController'")
        expect(componentSource).toContain("import { downloadSource as downloadImport116Source } from '@/actions/App/Http/Controllers/Admin/Teaching/Import116Controller'")
        expect(componentSource).toContain(':href="timetableSourceDownloadUrl(importItem)"')
        expect(componentSource).toContain(':href="recognitionSourceDownloadUrl(importItem)"')
        expect(componentSource).toContain(':href="import116SourceDownloadUrl(run)"')
        expect(componentSource).toContain('mdi-download-outline')
        expect(componentSource).toContain('Quelldatei nicht mehr verfügbar')
        expect(componentSource.indexOf('@click.stop="openDeleteDialog(importItem)"'))
            .toBeLessThan(componentSource.indexOf(':href="timetableSourceDownloadUrl(importItem)"'))
        expect(componentSource.indexOf('@click.stop="openRecognitionDeleteDialog(importItem)"'))
            .toBeLessThan(componentSource.indexOf(':href="recognitionSourceDownloadUrl(importItem)"'))
        expect(componentSource.indexOf('@click.stop="import116OpenDeleteDialog(run)"'))
            .toBeLessThan(componentSource.indexOf(':href="import116SourceDownloadUrl(run)"'))
        expect(componentSource).toContain("const shouldLoadFullTimetableImports = this.activeImportPage === 'stundenplan'")
        expect(componentSource).toContain("const shouldLoadFullRecognitionImports = this.activeImportPage === 'anrechnungen'")
        expect(componentSource).toContain('per_page: shouldLoadFullTimetableImports ? 100 : 1')
        expect(componentSource).toContain('summary: shouldLoadFullTimetableImports ? 0 : 1')
        expect(componentSource).toContain('summary: shouldLoadFullRecognitionImports ? 0 : 1')
        expect(componentSource).toContain('openDeleteDialog(importItem)')
        expect(componentSource).toContain('deleteImport()')
        expect(componentSource).toContain('Import löschen')
        expect(componentSource).toContain('sectionLabel(code)')
        expect(componentSource).toContain('statusText(importItem)')
        expect(componentSource).toContain('importProgress(importItem)')
        expect(componentSource).toContain('updatePolling()')
        expect(componentSource).toContain('st-main-dataset-summary')
        expect(componentSource).toContain('redirectRemovedSubjectImportRoute()')
        expect(componentSource).not.toContain('subjectImportPrompt')
        expect(componentSource).not.toContain('activeSubjectImport')
        expect(componentSource).not.toContain('subjectDataset')
        expect(componentSource).not.toContain('student_timetable_subject_rows')
        expect(componentSource).not.toContain('/subjects-overview-json')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen'")
        expect(componentSource).toContain('CSV-Datei (.csv)')
        expect(componentSource).toContain('Sokrates Bund')
        expect(componentSource).toContain('Studierende, Fächer, Noten')
        expect(componentSource).toContain('to="/admin/students-timetables/timetable/imports/anrechnungen/import"')
        expect(componentSource).toContain('CSV-Datei importieren')
        expect(componentSource).toContain('Anrechnungen · Sokrates Bund')
        expect(componentSource).toContain('/api/admin/students-timetables/recognitions-csv')
        expect(componentSource).toContain('recognitionImports')
        expect(componentSource).toContain('recognitionsResponse.data?.data || []')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen' && activeRecognitionDataset")
        expect(componentSource).toContain('student_timetable_recognition_rows')
        expect(componentSource).toContain('recognitionDatasetSummaryItems')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen' && activeRecognitionSubjectGradeCounts.length")
        expect(componentSource).toContain('activeRecognitionSubjectGradeCounts')
        expect(componentSource).toContain('activeRecognitionGradeCounts')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen' && activeRecognitionTeacherCodes.length")
        expect(componentSource).toContain('activeRecognitionTeacherCodes')
        expect(componentSource).toContain('recognitionTeacherTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionsResponse.data?.active_dataset || null')
        expect(componentSource).toContain(`if (this.activeImportPage === 'anrechnungen') {
                this.uploadedFilename = file?.name || 'gespeichert'
                this.refreshFilePond++
                this.loadImportButtonInfo()
                this.closeImportUploadPage()`)
        expect(componentSource).toContain('recognitionStatusText(importItem)')
        expect(componentSource).toContain('recognitionStatusColor(importItem)')
        expect(componentSource).toContain('recognitionImportIsProcessing(importItem)')
        expect(componentSource).toContain('v-if="recognitionImports.length" variant="accordion" multiple')
        expect(componentSource).toContain('<div class="st-import-history-meta-section">Gesamt</div>')
        expect(componentSource).toContain('Gesamtzeilen')
        expect(componentSource).toContain('Importierte Zeilen')
        expect(componentSource).toContain('Importierte Studierende')
        expect(componentSource).not.toContain('Studierende ohne Noten')
        expect(componentSource).toContain('Übersprungene Zeilen')
        expect(componentSource).toContain('st-import-history-meta-section')
        expect(componentSource).not.toContain('st-import-history-subject-panels')
        expect(componentSource).not.toContain('st-import-history-subject-title')
        expect(componentSource).not.toContain('st-import-history-teacher-panels')
        expect(componentSource).not.toContain('<v-expansion-panels flat variant="accordion" class="st-import-history-teacher-panels">')
        expect(componentSource).not.toContain('Fächer relativ')
        expect(componentSource).toContain('Importierte Noten')
        expect(componentSource).toContain('Noten N')
        expect(componentSource).toContain('Noten B')
        expect(componentSource).toContain('Noten 1-4')
        expect(componentSource).toContain('Noten 5')
        expect(componentSource).toContain('Sonstige Noten')
        expect(componentSource).not.toContain('Anzahl {{ importItem.imported_subjects_count || 0 }}')
        expect(componentSource).not.toContain('<v-expansion-panels flat variant="accordion" class="st-import-history-subject-panels">')
        expect(componentSource).toContain('<v-expansion-panel-title>')
        expect(componentSource).toContain('<v-expansion-panel-text>')
        expect(componentSource).not.toContain('v-if="importItem.subject_grade_counts?.length"')
        expect(componentSource).toContain('class="st-import-history-subject-table"')
        expect(componentSource).toContain('Fach')
        expect(componentSource).toContain('<th class="text-right">1-4</th>')
        expect(componentSource).toContain('<th class="text-right">5</th>')
        expect(componentSource).toContain('<th class="text-right">N</th>')
        expect(componentSource).toContain('<v-icon icon="mdi-sigma" size="14" title="Summe" />')
        expect(componentSource).toContain('<th class="text-right">A</th>')
        expect(componentSource).toContain('<th class="text-right">B</th>')
        expect(componentSource).not.toContain('<template v-for="subjectItem in importItem.subject_grade_counts" :key="subjectItem.subject">')
        expect(componentSource).toContain('v-for="subjectItem in activeRecognitionSubjectGradeCounts"')
        expect(componentSource).toContain('{{ subjectItem.subject }}')
        expect(componentSource).toContain('{{ subjectItem.one_to_four_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.b_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.five_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.n_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.other_count || 0 }}')
        expect(componentSource).toContain('{{ recognitionSubjectCountedTotal(subjectItem) }}')
        expect(componentSource).toContain('st-import-history-subject-total-cell')
        expect(componentSource).toContain(':deep(th:not(:last-child))')
        expect(componentSource).toContain('border-right: 1px solid rgba(25, 118, 210, 0.16);')
        expect(componentSource).toContain('st-import-history-subject-percent-row')
        expect(componentSource).toContain('recognitionSubjectPercentage(subjectItem.one_to_four_count, recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionSubjectCountedTotal(subjectItem), recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).toContain('st-import-history-subject-sum-row')
        expect(componentSource).toContain('st-import-history-subject-sum-percent-row')
        expect(componentSource).not.toContain('recognitionGradeCountedTotal(importItem.grade_counts)')
        expect(componentSource).toContain('recognitionGradeCountedTotal(activeRecognitionGradeCounts)')
        expect(componentSource).toContain('recognitionSubjectPercentage(activeRecognitionGradeCounts?.one_to_four, recognitionGradeCountedTotal(activeRecognitionGradeCounts))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionGradeCountedTotal(activeRecognitionGradeCounts), recognitionGradeCountedTotal(activeRecognitionGradeCounts))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(subjectItem.other_count, recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(subjectItem.b_count, recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(importItem.grade_counts?.other, recognitionGradeCountedTotal(importItem.grade_counts))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(importItem.grade_counts?.b, recognitionGradeCountedTotal(importItem.grade_counts))')
        expect(componentSource).toContain('Summe')
        expect(componentSource).not.toContain('Meiste N')
        expect(componentSource).not.toContain('Meiste B')
        expect(componentSource).not.toContain('Meiste 1-4')
        expect(componentSource).not.toContain('Meiste 5')
        expect(componentSource).toContain('importItem.total_rows || 0')
        expect(componentSource).toContain('importItem.imported_rows || 0')
        expect(componentSource).toContain('importItem.imported_students_count || 0')
        expect(componentSource).not.toContain('importItem.students_without_grades_count || 0')
        expect(componentSource).toContain('importItem.skipped_rows || 0')
        expect(componentSource).toContain('importItem.grade_counts?.total || 0')
        expect(componentSource).toContain('importItem.grade_counts?.n || 0')
        expect(componentSource).toContain('importItem.grade_counts?.b || 0')
        expect(componentSource).toContain('importItem.grade_counts?.one_to_four || 0')
        expect(componentSource).toContain('importItem.grade_counts?.five || 0')
        expect(componentSource).toContain('importItem.grade_counts?.other || 0')
        expect(componentSource).not.toContain('importItem.imported_subjects_count || 0')
        expect(componentSource).not.toContain('Anzahl {{ importItem.imported_teachers_count || 0 }}')
        expect(componentSource).not.toContain('v-if="importItem.teacher_codes?.length"')
        expect(componentSource).toContain('class="st-import-history-teacher-table"')
        expect(componentSource).toContain('Alle Lehrer')
        expect(componentSource).toContain('<th class="text-right">1-4</th>')
        expect(componentSource).not.toContain('<template v-for="teacherItem in importItem.teacher_codes" :key="teacherItem.code">')
        expect(componentSource).toContain('v-for="teacherItem in activeRecognitionTeacherCodes"')
        expect(componentSource).toContain(':key="teacherItem.code"')
        expect(componentSource).toContain('{{ teacherItem.code }}')
        expect(componentSource).toContain('{{ teacherItem.one_to_four_count || 0 }}')
        expect(componentSource).toContain('{{ teacherItem.five_count || 0 }}')
        expect(componentSource).toContain('{{ teacherItem.n_count || 0 }}')
        expect(componentSource).toContain('{{ recognitionTeacherCountedTotal(teacherItem) }}')
        expect(componentSource).toContain('st-import-history-teacher-percent-row')
        expect(componentSource).toContain('st-import-history-teacher-subjects-cell')
        expect(componentSource).toContain('{{ recognitionTeacherSubjectsLabel(teacherItem) }}')
        expect(componentSource).toContain('color: rgba(0, 0, 0, 0.5);')
        expect(componentSource).toContain('font-size: 0.68rem;')
        expect(componentSource).toContain('padding: 0 4px !important;')
        expect(componentSource).toContain('padding: 0 2px !important;')
        expect(componentSource).toContain('recognitionSubjectPercentage(teacherItem.one_to_four_count, recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).toContain('recognitionSubjectPercentage(teacherItem.five_count, recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).toContain('recognitionSubjectPercentage(teacherItem.n_count, recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionTeacherCountedTotal(teacherItem), recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).toContain('{{ teacherItem.other_count || 0 }}')
        expect(componentSource).toContain('{{ teacherItem.b_count || 0 }}')
        expect(componentSource).toContain('st-import-history-teacher-divider')
        expect(componentSource).toContain('st-import-history-teacher-total-cell')
        expect(componentSource).toContain('st-import-history-teacher-sum-row')
        expect(componentSource).not.toContain('recognitionTeacherOneToFourTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherFiveTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherNTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherOtherTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherBTotal(importItem.teacher_codes)')
        expect(componentSource).toContain('recognitionTeacherOneToFourTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherFiveTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherNTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherOtherTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherBTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('st-import-history-teacher-sum-percent-row')
        expect(componentSource).toContain('recognitionSubjectPercentage(recognitionTeacherOneToFourTotal(activeRecognitionTeacherCodes), recognitionTeacherTotal(activeRecognitionTeacherCodes))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionTeacherTotal(importItem.teacher_codes), recognitionTeacherTotal(importItem.teacher_codes))')
        expect(componentSource).not.toContain('importItem.subject_grade_counts')
        expect(componentSource).toContain('st-import-history-label-col')
        expect(componentSource).toContain('st-import-history-count-col')
        expect(componentSource).toContain('width: 150px;')
        expect(componentSource).toContain('min-width: 150px;')
        expect(componentSource).toContain('max-width: 150px;')
        expect(componentSource).toContain('width: 48px;')
        expect(componentSource).toContain('width: 100%;')
        expect(componentSource).not.toContain('max-width: 430px;')
        expect(componentSource).toContain('recognitionOtherGradesLabel(importItem.grade_counts?.other_details)')
        expect(componentSource).toContain(
            'v-if="recognitionOtherGradesLabel(importItem.grade_counts?.other_details) !== \'-\'"',
        )
        expect(componentSource).toContain('({{ recognitionOtherGradesLabel(importItem.grade_counts?.other_details) }})')
        expect(componentSource).toContain('st-import-history-meta-item small')
        expect(componentSource).toContain('st-import-history-inline-detail')
        expect(componentSource).toContain('.st-import-history-meta-item .st-import-history-inline-detail')
        expect(componentSource).toContain('display: inline;')
        expect(componentSource).not.toContain('recognitionSubjectLeaderLabel(')
        expect(componentSource).not.toContain('recognitionSubjectRelativeLeaderLabel(')
        expect(componentSource).toContain('importItem.grade_counts?.one_to_four')
        expect(componentSource).toContain('importItem.grade_counts?.b')
        expect(componentSource).toContain('importItem.grade_counts?.five')
        expect(componentSource).toContain('importItem.grade_counts?.n')
        expect(componentSource.indexOf('<div class="st-import-history-meta-section">Gesamt</div>'))
            .toBeLessThan(componentSource.indexOf('Gesamtzeilen'))
        expect(componentSource.indexOf('importItem.skipped_rows || 0'))
            .toBeLessThan(componentSource.indexOf('importItem.imported_students_count || 0'))
        expect(componentSource.indexOf('importItem.imported_students_count || 0'))
            .toBeLessThan(componentSource.indexOf('Importierte Noten'))
        expect(componentSource.indexOf('Importierte Noten'))
            .toBeLessThan(componentSource.indexOf('Noten 1-4'))
        expect(componentSource.indexOf('Noten 1-4'))
            .toBeLessThan(componentSource.indexOf('Noten B'))
        expect(componentSource.indexOf('Noten B'))
            .toBeLessThan(componentSource.indexOf('Noten 5'))
        expect(componentSource.indexOf('Noten 5'))
            .toBeLessThan(componentSource.indexOf('Noten N'))
        expect(componentSource.indexOf('Noten N'))
            .toBeLessThan(componentSource.indexOf('Sonstige Noten'))
        const aggregateSubjectRowsIndex = componentSource.indexOf('v-for="subjectItem in activeRecognitionSubjectGradeCounts"')
        const aggregateSubjectPercentIndex = componentSource.indexOf('st-import-history-subject-percent-row', aggregateSubjectRowsIndex)
        const aggregateSubjectSumIndex = componentSource.indexOf('st-import-history-subject-sum-row', aggregateSubjectPercentIndex)
        const aggregateSubjectSumPercentIndex = componentSource.indexOf('st-import-history-subject-sum-percent-row', aggregateSubjectSumIndex)

        expect(aggregateSubjectRowsIndex).toBeLessThan(aggregateSubjectPercentIndex)
        expect(aggregateSubjectPercentIndex).toBeLessThan(aggregateSubjectSumIndex)
        expect(aggregateSubjectSumIndex).toBeLessThan(aggregateSubjectSumPercentIndex)
        const subjectSumHeaderIndex = componentSource.indexOf('<v-icon icon="mdi-sigma" size="14" title="Summe" />')
        const subjectOtherHeaderIndex = componentSource.indexOf('<th class="text-right">A</th>', subjectSumHeaderIndex)
        const subjectBHeaderIndex = componentSource.indexOf('<th class="text-right">B</th>', subjectOtherHeaderIndex)

        expect(subjectSumHeaderIndex).toBeLessThan(subjectOtherHeaderIndex)
        expect(subjectOtherHeaderIndex).toBeLessThan(subjectBHeaderIndex)
        expect(componentSource).toContain('Abgeschlossen')
        expect(componentSource).toContain('Fehlgeschlagen')
        expect(componentSource).toContain('Wartet')
        expect(componentSource).toContain('Läuft')
        expect(componentSource).toContain('Import wird verarbeitet.')
        expect(componentSource).toContain('recognitionImports.some(importItem => this.recognitionImportIsProcessing(importItem))')
        expect(componentSource).toContain('Anrechnungs-Import löschen')
        expect(componentSource).toContain('Danach kann diese Datei erneut importiert werden.')
        expect(componentSource).toContain('openRecognitionDeleteDialog(importItem)')
        expect(componentSource).toContain('deleteRecognitionImport()')
        expect(componentSource).toContain('/api/admin/students-timetables/recognitions-csv/${this.recognitionDeleteTargetImport.id}')
        expect(componentSource).toContain('recognitionImportDetail')
        expect(componentSource).toContain('importItem?.imported_rows')
        expect(componentSource).toContain("if (this.activeImportPage === 'anrechnungen') return true")
        expect(componentSource).toContain("if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei'")
        expect(componentSource).toContain("this.uploadError = serverMessage || 'Die CSV-Datei konnte nicht gespeichert werden.'")
        expect(componentSource).toContain('Vor der Übernahme wird geprüft, ob die Datei gültige Schülerdaten enthält.')
        expect(componentSource).toContain('Voraussetzung vor dem Import')
        expect(componentSource).toContain('Pflichtspalten und mindestens ein vollständiger Studierendendatensatz')
        expect(componentSource).toContain("'Voraussetzung fehlt'")
        expect(componentSource).toContain('Vor dem Löschen prüft das System alle verbleibenden Quelldateien.')
        expect(componentSource).toContain('1. Datei geprüft')
        expect(componentSource).toContain('Der aktive Stundenplan wurde noch nicht verändert.')
        expect(componentSource).toContain('gültige TT-Einträge')
        expect(componentSource).toContain('<strong>Datumsprüfung:</strong>')
        expect(componentSource).toContain('timetablePreview.date_plausibility.message')
        expect(componentSource).toContain(":type=\"timetablePreviewDateIsPlausible ? 'success' : 'error'\"")
        expect(componentSource).toContain(':disabled="!canConfirmTimetablePreview"')
        expect(componentSource).toContain('Nicht übernehmbare Einträge werden übersprungen')
        expect(componentSource).toContain('Plan ersetzen – Änderungen übernehmen')
        expect(componentSource).toContain('Datei löschen')
        expect(componentSource).toContain('confirmTimetablePreview()')
        expect(componentSource).toContain('deleteTimetablePreview()')
        expect(componentSource).toContain('confirmTimetableImport.url(this.timetablePreview.id)')
        expect(componentSource).toContain('destroyTimetableImport.url(this.timetablePreview.id)')
        expect(componentSource).toContain('this.timetablePreview = timetableResponse.data?.preview || null')
        expect(componentSource.match(/Schuljahr:\s*\{\{ personalImportSchoolyearLabel \}\}/g)?.length).toBeGreaterThanOrEqual(2)
        expect(componentSource).toContain('@click.stop="import116OpenDeleteDialog(run)"')
        expect(componentSource).toContain('Die aktiven Schülerdaten bleiben unverändert.')
        expect(fileUploadSource).toContain('onerror: onServerError')
        expect(fileUploadSource).toContain("this.$emit('error', message)")
        expect(componentSource).toContain(':allowMultiple="activeImportUploadAllowsMultiple"')
        expect(componentSource).toContain("return this.activeImportPage === 'anrechnungen'")
        expect(componentSource).not.toContain('Anrechnungen-Konfiguration')
        expect(componentSource).not.toContain('JSON mit Schülern, Fächern und Anrechnungen')
        expect(componentSource).not.toContain('Anrechnungen, Fachzuordnungen und Gültigkeiten')
        expect(componentSource).toContain('Noch keine Anrechnungs-Datei importiert.')
        expect(componentSource).toContain('Hauptdatenbestand')
        expect(componentSource).toContain('student_timetable_entries')
        expect(componentSource).toContain('Zeitraum')
        expect(componentSource).toContain('Stundenplan-Einträge')
        expect(componentSource).toContain('Verschiedene Kurse')
        expect(componentSource).toContain('Zuletzt geändert')
        expect(componentSource).toContain('timetableResponse.data?.main_dataset')
        expect(componentSource).toContain('st-course-summary')
        expect(componentSource).toContain('<v-expansion-panels variant="accordion">')
        expect(componentSource).toContain('activeDatasetCourses')
        expect(componentSource).toContain('Alle Kurse')
        expect(componentSource).toContain('Wochenstd.')
        expect(componentSource).toContain('datasetCourseWeeklyHoursLabel(courseItem)')
        expect(componentSource).toContain('datasetCourseDateRangeLabel(courseItem)')
        expect(componentSource).toContain('st-single-date-summary')
        expect(componentSource).toContain('activeDatasetSingleDateCourses')
        expect(componentSource).toContain('activeDatasetSingleDateAppointmentsCount')
        expect(componentSource).toContain('Einzeltermine')
        expect(componentSource).toContain('singleDateAppointmentTimeLabel(appointment)')
        expect(componentSource).toContain('formatDateWithWeekdayLabel(appointment.date)')
        expect(componentSource).toContain('label="Aktiv"')
        expect(componentSource).toContain('setAllSingleDateAppointmentsActive')
        expect(componentSource).toContain('setCourseSingleDateAppointmentsActive(courseItem, $event)')
        expect(componentSource).toContain('setSingleDateAppointmentActive(courseItem, appointment, $event)')
        expect(componentSource).toContain('/api/admin/students-timetables/imports/single-date-appointments')
        expect(componentSource).toContain('singleDateActivationPayload()')
        expect(componentSource).toContain('singleDateActivationSaveInProgress')
        expect(componentSource).toContain('Änderungen werden im Hintergrund gespeichert.')
        expect(componentSource).toContain('queueSingleDateAppointmentActivationSave()')
        expect(routeSource).toContain('/admin/students-timetables/:section?/:subsection?/:detail?/:action?')
    })

    it('shows progress and a completion summary on the data refresh page', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetable/DataRefresh.vue',
            'utf8',
        )

        expect(componentSource).toContain('Datenaktualisierung')
        expect(componentSource).toContain('{{ schoolyearLabel }}')
        expect(componentSource).toContain('Aktualisierung aller Studienauswahl bei den Studierenden')
        expect(componentSource).toContain('Aktualisierung aller Noten bei den Studierenden')
        expect(componentSource).toContain('Datenaktualisierung starten')
        expect(componentSource).toContain('<v-progress-linear')
        expect(componentSource).toContain('Datenaktualisierung abgeschlossen')
        expect(componentSource).toContain('Studierende verarbeitet')
        expect(componentSource).toContain('Studienauswahl aktualisiert')
        expect(componentSource).toContain('Noten aktualisiert')
        expect(componentSource).toContain('loadStudentDataRefresh.url()')
        expect(componentSource).toContain('startStudentDataRefresh.url()')
        expect(componentSource).toContain('this.loadRefresh({ silent: true })')
    })

    it('polls an active data refresh and stops after completion', async () => {
        const computed = (DataRefresh as any).computed
        const methods = (DataRefresh as any).methods
        const runningRefresh = {
            status: 'running',
            total_students: 20,
            processed_students: 10,
            progress_percent: 50,
        }
        const completedRefresh = {
            ...runningRefresh,
            status: 'completed',
            processed_students: 20,
            progress_percent: 100,
        }
        const get = vi.fn()
            .mockResolvedValueOnce({ data: { data: runningRefresh } })
            .mockResolvedValueOnce({ data: { data: completedRefresh } })
        const ctx: any = {
            dataRefresh: null,
            loading: false,
            loadError: '',
            schedulePolling: vi.fn(),
            clearPolling: vi.fn(),
        }
        Object.defineProperty(ctx, 'refreshIsActive', {
            get: () => computed.refreshIsActive.call(ctx),
        })
        vi.stubGlobal('axios', { get })

        try {
            await methods.loadRefresh.call(ctx)

            expect(ctx.dataRefresh).toEqual(runningRefresh)
            expect(ctx.schedulePolling).toHaveBeenCalledOnce()
            expect(ctx.loading).toBe(false)

            await methods.loadRefresh.call(ctx, { silent: true })

            expect(ctx.dataRefresh).toEqual(completedRefresh)
            expect(ctx.clearPolling).toHaveBeenCalledOnce()
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('shows the personal target schoolyear for all import types', () => {
        const computed = (Timetable as any).computed

        expect(computed.personalImportSchoolyearLabel.call({
            config: {
                schoolwide_active_schoolyear: { id: 12, concerns: '2026/27' },
                selected_schoolyear: { id: 11, concerns: '2025/26' },
            },
        })).toBe('2025/26')
    })

    it('allows confirming only date-plausible timetable previews', () => {
        const computed = (Timetable as any).computed

        expect(computed.timetablePreviewDateIsPlausible.call({
            timetablePreview: { date_plausibility: { is_plausible: true } },
        })).toBe(true)
        expect(computed.timetablePreviewDateIsPlausible.call({
            timetablePreview: { date_plausibility: { is_plausible: false } },
        })).toBe(false)
        expect(computed.timetablePreviewDateIsPlausible.call({ timetablePreview: null })).toBe(false)
    })

    it('shows a failed Sokrates import as an error without updating the last import time', async () => {
        const methods = (Timetable as any).methods
        const loadRuns = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            import116Importing: true,
            import116LastImportAt: null,
            import116RunActionMessage: 'Alter Erfolg',
            import116RunActionError: '',
            import116LoadRuns: loadRuns,
        }

        await methods.handleImport116Finished.call(ctx, {
            detail: {
                status: 422,
                message: 'Die Datei enthält keine gültigen Schülerdaten.',
                data: { created: 0, updated: 0, deleted: 0 },
            },
        })

        expect(ctx.import116Importing).toBe(false)
        expect(ctx.import116LastImportAt).toBeNull()
        expect(ctx.import116RunActionMessage).toBe('')
        expect(ctx.import116RunActionError).toBe('Die Datei enthält keine gültigen Schülerdaten.')
        expect(loadRuns).toHaveBeenCalledOnce()
    })

    it('keeps the timetable delete dialog open when the server rejects rebuilding', async () => {
        const methods = (Timetable as any).methods
        const globalScope = globalThis as any
        const originalAxios = globalScope.axios
        globalScope.axios = {
            delete: vi.fn().mockRejectedValue({
                response: { data: { message: 'Die verbleibende Quelldatei fehlt. Es wurde nichts gelöscht.' } },
            }),
        }
        const ctx: any = {
            deleteTargetImport: { id: 17 },
            deleteDialog: true,
            deleting: false,
            deleteError: '',
            loadImportButtonInfo: vi.fn(),
        }

        try {
            await methods.deleteImport.call(ctx)
        } finally {
            globalScope.axios = originalAxios
        }

        expect(ctx.deleteDialog).toBe(true)
        expect(ctx.deleteTargetImport).toEqual({ id: 17 })
        expect(ctx.deleteError).toBe('Die verbleibende Quelldatei fehlt. Es wurde nichts gelöscht.')
        expect(ctx.deleting).toBe(false)
        expect(ctx.loadImportButtonInfo).not.toHaveBeenCalled()
    })

    it('confirms and deletes timetable previews through the scoped import actions', async () => {
        const methods = (Timetable as any).methods
        const globalScope = globalThis as any
        const originalAxios = globalScope.axios
        const post = vi.fn().mockResolvedValue({})
        const remove = vi.fn().mockResolvedValue({})
        globalScope.axios = { post, delete: remove }

        try {
            const confirmContext: any = {
                timetablePreview: { id: 41 },
                canConfirmTimetablePreview: true,
                timetableImportOperation: 'merge',
                timetableComparison: { fingerprint: 'confirmed-comparison' },
                timetablePreviewHasSemanticErrors: false,
                confirmingPreview: false,
                previewActionError: 'alt',
                uploadedFilename: 'preview.txt',
                loadImportButtonInfo: vi.fn().mockResolvedValue(undefined),
                schedulePolling: vi.fn(),
                closeImportUploadPage: vi.fn(),
            }

            await methods.confirmTimetablePreview.call(confirmContext)

            expect(post).toHaveBeenCalledWith('/api/admin/students-timetables/imports/41/confirm', {
                operation: 'merge', fingerprint: 'confirmed-comparison', mode: 'strict',
            })
            expect(confirmContext.timetablePreview).toBeNull()
            expect(confirmContext.loadImportButtonInfo).toHaveBeenCalledOnce()
            expect(confirmContext.schedulePolling).toHaveBeenCalledOnce()
            expect(confirmContext.closeImportUploadPage).toHaveBeenCalledOnce()
            expect(confirmContext.confirmingPreview).toBe(false)

            const deleteContext: any = {
                timetablePreview: { id: 42 },
                deletingPreview: false,
                previewActionError: 'alt',
                uploadedFilename: 'preview.txt',
                refreshFilePond: 0,
                loadImportButtonInfo: vi.fn().mockResolvedValue(undefined),
            }

            await methods.deleteTimetablePreview.call(deleteContext)

            expect(remove).toHaveBeenCalledWith('/api/admin/students-timetables/imports/42')
            expect(deleteContext.timetablePreview).toBeNull()
            expect(deleteContext.refreshFilePond).toBe(1)
            expect(deleteContext.loadImportButtonInfo).toHaveBeenCalledOnce()
            expect(deleteContext.deletingPreview).toBe(false)
        } finally {
            globalScope.axios = originalAxios
        }
    })

    it('formats import button metadata', () => {
        const methods = (Timetable as any).methods

        expect(methods.importedTtCount({
            sections: { TT: 9 },
            tt_skipped_invalid: 2,
        })).toBe(7)

        expect(methods.formatFileSize(1536)).toBe('1.5 KB')

        expect(methods.dateRangeLabel({
            tt_first_date: '2026-02-16',
            tt_last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.sectionLabel('TT')).toBe('Stundenplan-Einträge')
        expect((Timetable as any).computed.allowedUntisEntryTypes()).toBe('VV, SU, TE, RM, KL, GR, LS, TT')
        expect(methods.statusText({ import_status: 'completed' })).toBe('Abgeschlossen')
        expect(methods.importProgress({ progress_current: 25, progress_total: 100 })).toBe(25)
        expect(methods.recognitionOtherGradesLabel([{ note: 'A', count: 2 }, { note: 'X', count: 1 }]))
            .toBe('A: 2 · X: 1')
        expect(methods.recognitionOtherGradesLabel([])).toBe('-')
        expect(methods.recognitionSubjectPercentage(2, 8)).toBe('25.0%')
        expect(methods.recognitionSubjectPercentage(0, 0)).toBe('-')
        expect(methods.recognitionSubjectCountedTotal({
            one_to_four_count: 2,
            five_count: 1,
            n_count: 3,
            other_count: 4,
            b_count: 5,
        })).toBe(6)
        expect(methods.recognitionGradeCountedTotal({
            one_to_four: 20,
            five: 4,
            n: 6,
            other: 8,
            b: 10,
        })).toBe(30)
        expect(methods.recognitionTeacherOneToFourTotal([
            { one_to_four_count: 2 },
            { one_to_four_count: 3 },
            {},
        ])).toBe(5)
        expect(methods.recognitionTeacherOneToFourTotal(null)).toBe(0)
        expect(methods.recognitionTeacherFiveTotal([
            { five_count: 1 },
            { five_count: 4 },
            {},
        ])).toBe(5)
        expect(methods.recognitionTeacherFiveTotal(null)).toBe(0)
        expect(methods.recognitionTeacherNTotal([
            { n_count: 2 },
            { n_count: 4 },
            {},
        ])).toBe(6)
        expect(methods.recognitionTeacherNTotal(null)).toBe(0)
        expect(methods.recognitionTeacherCountedTotal({
            one_to_four_count: 2,
            five_count: 1,
            n_count: 3,
        })).toBe(6)
        expect(methods.recognitionTeacherTotal([
            { one_to_four_count: 2, five_count: 1, n_count: 3 },
            { one_to_four_count: 4, five_count: 0, n_count: 2 },
            {},
        ])).toBe(12)
        expect(methods.recognitionTeacherTotal(null)).toBe(0)
        expect(methods.recognitionTeacherOtherTotal([
            { other_count: 2 },
            { other_count: 3 },
            {},
        ])).toBe(5)
        expect(methods.recognitionTeacherOtherTotal(null)).toBe(0)
        expect(methods.recognitionTeacherBTotal([
            { b_count: 2 },
            { b_count: 4 },
            {},
        ])).toBe(6)
        expect(methods.recognitionTeacherBTotal(null)).toBe(0)
        expect(methods.recognitionTeacherSubjectsLabel({
            subjects: ['Deutsch', 'Mathematik'],
        })).toBe('Deutsch, Mathematik')
        expect(methods.recognitionTeacherSubjectsLabel({})).toBe('')

        expect(methods.datasetDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.datasetCourseDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.datasetCourseWeeklyHoursLabel({ weekly_hours: 4 })).toBe('4')
        expect(methods.datasetCourseWeeklyHoursLabel({})).toBe('-')

        expect(methods.formatDateWithWeekdayLabel('2026-02-17')).toBe('Di, 17.02.2026')
        expect(methods.singleDateAppointmentTimeLabel({
            period: '14',
            starts_at: '20:25',
            ends_at: '21:10',
        })).toBe('14. Std. 20:25-21:10')

        const appointments = [
            {
                date: '2026-02-17',
                period: '14',
                starts_at: '20:25',
                ends_at: '21:10',
                subject: 'LPT',
                course: 'LPT',
                entry_ids: [1],
            },
            {
                date: '2026-02-18',
                period: '10',
                starts_at: '17:05',
                ends_at: '17:50',
                subject: 'LPT',
                course: 'LPT',
                entry_ids: [2],
            },
        ]
        const courseItem = { name: 'LPT-ALT', appointments }
        const firstKey = methods.singleDateAppointmentKey(courseItem, appointments[0])
        const payloadContext = {
            activeDatasetSingleDateCourses: [courseItem],
            activeSingleDateAppointmentKeySet: new Set([firstKey]),
            singleDateAppointmentKey: methods.singleDateAppointmentKey,
        }

        expect(methods.singleDateActivationPayload.call(payloadContext)).toEqual([
            { entry_ids: [1], active: true },
            { entry_ids: [2], active: false },
        ])
    })
})
