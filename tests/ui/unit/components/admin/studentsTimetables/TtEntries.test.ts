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
    it('renders remembered offers above the TT entries card', () => {
        const source = readFileSync('resources/js/pages/admin/studentsTimetables/ttEntries/TtEntries.vue', 'utf8')
        const rememberedSectionIndex = source.indexOf('<section v-if="rememberedOffers.length"')
        const cardIndex = source.indexOf('<v-card rounded="lg" class="tt-entries-card">')

        expect(rememberedSectionIndex).toBeGreaterThan(-1)
        expect(cardIndex).toBeGreaterThan(-1)
        expect(rememberedSectionIndex).toBeLessThan(cardIndex)
        expect(source.match(/<section v-if="rememberedOffers\.length"/gu)).toHaveLength(1)
        expect(source).not.toContain('tt-entries-card__remembered-entry-list')
        expect(source).toContain('flex-wrap: wrap;')
        expect(source).toContain('rememberedOffersDetailsVisible')
        expect(source).toContain('Anzeigen')
        expect(source).toContain('/api/admin/students-timetables/tt-entry-remembered-offers')
        expect(source).toContain('Gemerkte Module')
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
                    class_name: 'D1 - 1A',
                    teacher: 'MAY',
                    rooms: ['101'],
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
                    class_name: 'D1 - 1A',
                    teacher: 'MAY',
                    rooms: ['101'],
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
                    class_name: 'D1 - 1B',
                    teacher: 'KOW',
                    rooms: ['103'],
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
                roomsLabel: '101',
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
                    class_name: 'D1 - 1A',
                    teacher: 'MAY',
                    rooms: ['101'],
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
