import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import TtEntries from '@/pages/admin/studentsTimetables/ttEntries/TtEntries.vue'

function buildContext(overrides: Record<string, unknown> = {}) {
    const component = TtEntries as any
    const context = {
        ...component.methods,
        rememberedOffers: [],
        rememberedOffersDetailsVisible: false,
        selectedOfferKey: '',
        schoolHours: [
            { hour: 1, from: '08:00:00', until: '08:45:00' },
            { hour: 2, from: '08:50:00', until: '09:35:00' },
            { hour: 12, from: '18:45:00', until: '19:30:00' },
            { hour: 13, from: '19:30:00', until: '20:15:00' },
        ],
        courseGroups: [],
        subjectMappings: [],
        subjectRows: [],
        ...overrides,
    } as Record<string, any>

    Object.defineProperty(context, 'courseGroupsByCourseCode', {
        get() {
            return component.computed.courseGroupsByCourseCode.call(context)
        },
    })

    Object.defineProperty(context, 'activeSubjectMappings', {
        get() {
            return component.computed.activeSubjectMappings.call(context)
        },
    })

    for (const property of ['activeSubjectRows', 'selectableSubjectRows', 'metaCourseItems']) {
        Object.defineProperty(context, property, {
            get() {
                return component.computed[property].call(context)
            },
        })
    }

    Object.defineProperty(context, 'selectedSubjectOffers', {
        get() {
            return component.computed.selectedSubjectOffers.call(context)
        },
    })

    Object.defineProperty(context, 'selectedSubjectOffer', {
        get() {
            return component.computed.selectedSubjectOffer.call(context)
        },
    })

    Object.defineProperty(context, 'selectedSubjectOfferEntries', {
        get() {
            return component.computed.selectedSubjectOfferEntries.call(context)
        },
    })

    return context
}

describe('TT entries overview', () => {
    it('offers imported modules without subject-plan rows and lets their lessons be remembered', () => {
        const context = buildContext({
            courseGroups: [
                {
                    key: 'gus1-12', module_code: 'GuS1', course: 'GuS', subject: 'GuS',
                    class_name: 'GuS1-2RU+3QS-PLA', display_label: 'GuS1-2RU+3QS-PLA',
                    weekday: 4, hour: 12, dates: ['2026-09-17', '2026-09-24'],
                },
                {
                    key: 'gus1-13', module_code: 'GuS1', course: 'GuS', subject: 'GuS',
                    class_name: 'GuS1-2RU+3QS-PLA', display_label: 'GuS1-2RU+3QS-PLA',
                    weekday: 4, hour: 13, dates: ['2026-09-24'],
                },
                {
                    key: 'gus2-12', module_code: 'GuS2', course: 'GuS', subject: 'GuS',
                    class_name: 'GuS2-PLA', display_label: 'GuS2-PLA',
                    weekday: 4, hour: 12, dates: ['2026-09-17'],
                },
            ],
            persistRememberedOffers: () => {},
        })

        expect(context.metaCourseItems).toHaveLength(1)
        expect(context.metaCourseItems[0].rows.map((row: any) => row.json_code.toUpperCase())).toEqual(['GUS1', 'GUS2'])
        context.selectedSubjectRow = context.metaCourseItems[0].rows[0]
        expect(context.selectedSubjectRow.hours_per_week).toBeNull()
        expect(context.selectedSubjectRow.semester).toBeNull()
        expect(context.selectedSubjectOffers).toHaveLength(1)
        expect(context.selectedSubjectOffer.entries.map((entry: any) => entry.key)).toEqual(['gus1-12', 'gus1-13'])
        expect(context.selectedSubjectOfferEntries.map((entry: any) => entry.dateValue))
            .toEqual(['2026-09-17', '2026-09-24'])

        context.toggleSelectedSubjectOfferEntry(context.selectedSubjectOfferEntries[0])

        expect(context.rememberedOffers).toHaveLength(1)
        expect(context.rememberedOffers[0].entries).toHaveLength(2)
        expect(context.subjectRows).toEqual([])
    })

    it('keeps mapped aliases and explicitly inactive subjects from being added as imported-only modules', () => {
        const mappedSubject = { id: 1, json_code: 'GW1', json_subject: 'GW', name: 'Geographie', is_active: true }
        const context = buildContext({
            subjectRows: [mappedSubject, { id: 2, json_code: 'GuS1', json_subject: 'GuS', is_active: false }],
            subjectMappings: [{ json_subject: 'GW', tt_subject: 'GWB', is_active: true }],
            courseGroups: [
                { key: 'gw1', module_code: 'GWB1', class_name: 'GWB1-1A-HUB' },
                { key: 'gus1', module_code: 'GuS1', class_name: 'GuS1-PLA' },
                { key: 'inactive', module_code: 'YOGA1', is_active: false },
                { key: 'missing-code', class_name: 'PLA' },
            ],
        })

        expect(context.selectableSubjectRows).toEqual([mappedSubject])
    })

    it('shows and loads the personal schoolyear', () => {
        const component = TtEntries as any
        const context = buildContext({
            config: {
                selected_schoolyear: {
                    concerns: '2026/27',
                },
            },
        })
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/ttEntries/TtEntries.vue', 'utf8')

        expect(component.computed.personalSchoolyearLabel.call(context)).toBe('2026/27')
        expect(source).toContain('<span>TT-Einträge:</span>')
        expect(source).toContain('class="text-h6 font-weight-bold text-primary"')
        expect(source).toContain("params: { schoolyear_scope: 'personal' }")
        expect(source).toContain("'config.selected_schoolyear.id'()")
    })

    it('always renders remembered entries above the TT entries card', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/ttEntries/TtEntries.vue', 'utf8')
        const rememberedSectionIndex = source.indexOf('<section class="tt-entries-card__remembered"')
        const cardIndex = source.indexOf('<v-card rounded="lg" class="tt-entries-card">')

        expect(rememberedSectionIndex).toBeGreaterThan(-1)
        expect(cardIndex).toBeGreaterThan(-1)
        expect(rememberedSectionIndex).toBeLessThan(cardIndex)
        expect(source).not.toContain('<section v-if="rememberedOffers.length"')
        expect(source).not.toContain('tt-entries-card__remembered-entry-list')
        expect(source).toContain('flex-wrap: wrap;')
        expect(source).toContain('rememberedOffersDetailsVisible')
        expect(source).toContain('Anzeigen')
        expect(source).toContain('/api/admin/students-timetables/tt-entry-remembered-offers')
        expect(source).toContain('Gemerkte Einträge')
        expect(source).toContain('Noch keine Einträge gemerkt.')
        expect(source).not.toContain('Gemerkte Module')
        expect(source).not.toContain('Gemerkte Angebote')
        expect(source).toContain('tt-entries-card__remembered-details')
        expect(source).toContain('tt-entries-card__remembered-detail-list')
        expect(source).toContain('align-items: flex-start;')
        expect(source).toContain('v-for="entry in offer.entries"')
        expect(source).toContain('tt-entries-card__remembered-detail-entry--overlapping')
        expect(source).toContain('tt-entries-card__remembered-detail-entry--inactive')
        expect(source).toContain('tt-entries-card__entry--inactive')
        expect(source).toContain('rememberedOfferOverlapEntryKeys.has')
        expect(source).toContain('toggleRememberedOfferEntry(offer.key, entry.key)')
        expect(source).toContain(':aria-pressed="rememberedOfferEntryActive(entry) ?')
        expect(source).toContain('selectedSubjectOfferEntryActive(entry)')
        expect(source).toContain('toggleSelectedSubjectOfferEntry(entry)')
    })

    it('shows every date and hour for the selected offer', () => {
        const component = TtEntries as any
        const context = buildContext({
            selectedSubjectRow: {
                json_code: 'D1',
                json_subject: 'D',
            },
            courseGroups: [
                {
                    key: 'd1-a-di-12',
                    semester: 1,
                    weekday: 2,
                    hour: 12,
                    course: 'D1',
                    subject: 'D',
                    display_label: 'D1 - 1A - MAY',
                    class_name: 'D1 - 1A - MAY',
                    dates: ['2026-02-17'],
                    recurrence_label: '1-wöchig',
                },
                {
                    key: 'd1-a-di-13',
                    semester: 1,
                    weekday: 2,
                    hour: 13,
                    course: 'D1',
                    subject: 'D',
                    display_label: 'D1 - 1A - MAY',
                    class_name: 'D1 - 1A - MAY',
                    dates: ['2026-02-17'],
                    recurrence_label: '1-wöchig',
                },
                {
                    key: 'd1-b',
                    semester: 1,
                    weekday: 4,
                    hour: 1,
                    course: 'D1',
                    subject: 'D',
                    display_label: 'D1 - 1B - KOW',
                    class_name: 'D1 - 1B - KOW',
                    dates: ['2026-09-10'],
                },
            ],
        })

        const offers = component.computed.selectedSubjectOffers.call(context)

        expect(offers).toHaveLength(2)
        expect(offers[0].name).toBe('D1 - 1A - MAY')
        expect(offers[0].entries.map((entry: Record<string, string>) => entry.key)).toEqual(['d1-a-di-12', 'd1-a-di-13'])
        expect(offers[0].scheduleLabel).toBe('Di 12. 18:45-19:30, Di 13. 19:30-20:15')

        context.selectedOfferKey = offers[0].key
        context.persistRememberedOffers = () => {}

        const selectedOfferEntries = component.computed.selectedSubjectOfferEntries.call(context)

        expect(selectedOfferEntries.map((entry: Record<string, string>) => ({
            dateLabel: entry.dateLabel,
            metaLabel: context.entryMetaLabel(entry),
            scheduleLabel: entry.scheduleLabel,
        }))).toEqual([
            {
                dateLabel: '17.02.2026',
                metaLabel: 'Di. 12.-13. 18:45-20:15',
                scheduleLabel: 'Di. 12.-13. 18:45-20:15',
            },
        ])

        context.toggleRememberedOffer(offers[0])

        expect(context.rememberedOffers).toEqual([{
            key: `${offers[0].key}|D1 - 1A - MAY`,
            entries: [{
                key: 'd1-a-di-12|2026-02-17|12|d1-a-di-13|2026-02-17|13',
                dateLabel: '17.02.2026',
                dateValue: '2026-02-17',
                active: true,
                scheduleLabel: 'Di. 12.-13. 18:45-20:15',
                timeFrom: '18:45',
                timeUntil: '20:15',
            }],
            name: 'D1 - 1A - MAY',
            scheduleLabel: 'Di 12. 18:45-19:30, Di 13. 19:30-20:15',
        }])
        expect(context.rememberedOffer(offers[0])).toBe(true)
        expect(context.rememberedOffersDetailsVisible).toBe(false)

        context.toggleRememberedOffersDetails()

        expect(context.rememberedOffersDetailsVisible).toBe(true)
        expect(context.selectedSubjectOfferEntryActive(selectedOfferEntries[0])).toBe(true)

        context.rememberedOffers[0].entries[0].active = false

        expect(context.selectedSubjectOfferEntryActive(selectedOfferEntries[0])).toBe(false)

        context.toggleSelectedSubjectOfferEntry(selectedOfferEntries[0])

        expect(context.rememberedOffers[0].entries[0].active).toBe(true)

        context.removeRememberedOffer(context.rememberedOffers[0].key)

        expect(context.rememberedOffers).toEqual([])
        expect(context.rememberedOffersDetailsVisible).toBe(false)

        context.selectedOfferKey = offers[1].key

        expect(component.computed.selectedSubjectOffer.call(context).name).toBe('D1 - 1B - KOW')
        expect(component.computed.selectedSubjectOfferEntries.call(context).map((entry: Record<string, string>) => entry.key))
            .toEqual(['d1-b|2026-09-10|1'])
    })

    it('stores a selected offer entry toggle when the offer is not remembered yet', () => {
        const component = TtEntries as any
        const context = buildContext({
            selectedSubjectRow: {
                json_code: 'D1',
                json_subject: 'D',
            },
            courseGroups: [
                {
                    key: 'd1-a-di-12',
                    semester: 1,
                    weekday: 2,
                    hour: 12,
                    course: 'D1',
                    subject: 'D',
                    display_label: 'D1 - 1A - MAY',
                    class_name: 'D1 - 1A - MAY',
                    dates: ['2026-02-17'],
                },
            ],
            persistRememberedOffers: () => {},
        })
        const offers = component.computed.selectedSubjectOffers.call(context)

        context.selectedOfferKey = offers[0].key

        const selectedOfferEntries = component.computed.selectedSubjectOfferEntries.call(context)

        context.toggleSelectedSubjectOfferEntry(selectedOfferEntries[0])

        expect(context.rememberedOffers).toHaveLength(1)
        expect(context.rememberedOffers[0].name).toBe('D1 - 1A - MAY')
        expect(context.rememberedOffers[0].entries[0].active).toBe(false)
        expect(context.selectedSubjectOfferEntryActive(selectedOfferEntries[0])).toBe(false)
    })

    it('marks remembered module entries with overlapping date and time ranges', () => {
        const component = TtEntries as any
        const context = buildContext({
            rememberedOffers: [
                {
                    key: 'offer-a',
                    name: 'D1 - 1A - MAY',
                    entries: [{
                        key: 'a1',
                        dateValue: '2026-02-17',
                        scheduleLabel: 'Di. 12.-13. 18:45-20:15',
                    }],
                },
                {
                    key: 'offer-b',
                    name: 'D1 - 1B - KOW',
                    entries: [{
                        key: 'b1',
                        dateValue: '2026-02-17',
                        active: false,
                        scheduleLabel: 'Di. 13. 19:30-20:15',
                        timeFrom: '19:30',
                        timeUntil: '20:15',
                    }],
                },
                {
                    key: 'offer-c',
                    name: 'D1 - 1C - NOR',
                    entries: [{
                        key: 'c1',
                        dateValue: '2026-02-18',
                        scheduleLabel: 'Mi. 13. 19:30-20:15',
                        timeFrom: '19:30',
                        timeUntil: '20:15',
                    }],
                },
            ],
        })
        const [firstOffer, secondOffer, thirdOffer] = context.rememberedOffers
        const overlappingEntryKeys = component.computed.rememberedOfferOverlapEntryKeys.call(context)

        expect(overlappingEntryKeys.has(context.rememberedOfferEntryKey(firstOffer, firstOffer.entries[0]))).toBe(false)
        expect(overlappingEntryKeys.has(context.rememberedOfferEntryKey(secondOffer, secondOffer.entries[0]))).toBe(false)
        expect(overlappingEntryKeys.has(context.rememberedOfferEntryKey(thirdOffer, thirdOffer.entries[0]))).toBe(false)

        context.persistRememberedOffers = () => {}
        context.toggleRememberedOfferEntry(secondOffer.key, secondOffer.entries[0].key)

        expect(context.rememberedOffers[1].entries[0].active).toBe(true)

        const activeOverlappingEntryKeys = component.computed.rememberedOfferOverlapEntryKeys.call(context)

        expect(activeOverlappingEntryKeys.has(context.rememberedOfferEntryKey(firstOffer, firstOffer.entries[0]))).toBe(true)
        expect(activeOverlappingEntryKeys.has(context.rememberedOfferEntryKey(secondOffer, secondOffer.entries[0]))).toBe(true)
        expect(activeOverlappingEntryKeys.has(context.rememberedOfferEntryKey(thirdOffer, thirdOffer.entries[0]))).toBe(false)
    })
})
