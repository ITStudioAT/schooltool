<template>
    <section class="tt-entries-card__remembered" aria-live="polite">
        <div class="tt-entries-card__remembered-heading">
            <strong>Gemerkte Einträge</strong>
            <div v-if="rememberedOffers.length" class="tt-entries-card__remembered-heading-actions">
                <v-btn
                    color="primary"
                    density="compact"
                    size="small"
                    variant="tonal"
                    prepend-icon="mdi-eye-outline"
                    :aria-expanded="rememberedOffersDetailsVisible ? 'true' : 'false'"
                    @click="toggleRememberedOffersDetails">
                    {{ rememberedOffersDetailsVisible ? 'Ausblenden' : 'Anzeigen' }}
                </v-btn>
                <v-btn
                    icon="mdi-close"
                    color="secondary"
                    density="compact"
                    size="x-small"
                    title="Alle entfernen"
                    aria-label="Alle entfernen"
                    variant="text"
                    @click="clearRememberedOffers" />
            </div>
        </div>

        <div v-if="rememberedOffers.length" class="tt-entries-card__remembered-list">
            <article
                v-for="offer in rememberedOffers"
                :key="offer.key"
                class="tt-entries-card__remembered-offer">
                <div class="tt-entries-card__remembered-offer-heading">
                    <strong>{{ offer.name }}</strong>
                    <v-btn
                        icon="mdi-close"
                        color="secondary"
                        density="compact"
                        size="x-small"
                        title="Entfernen"
                        aria-label="Entfernen"
                        variant="text"
                        @click="removeRememberedOffer(offer.key)" />
                </div>
            </article>
        </div>

        <p v-else class="tt-entries-card__remembered-empty">
            Noch keine Einträge gemerkt.
        </p>
    </section>

    <v-card
        v-if="rememberedOffers.length && rememberedOffersDetailsVisible"
        rounded="lg"
        class="tt-entries-card__remembered-details">
        <v-card-title class="tt-entries-card__remembered-details-title">
            <span>Gemerkte Einträge</span>
            <v-btn
                icon="mdi-close"
                color="secondary"
                density="compact"
                size="x-small"
                title="Ausblenden"
                aria-label="Ausblenden"
                variant="text"
                @click="rememberedOffersDetailsVisible = false" />
        </v-card-title>

        <v-card-text class="tt-entries-card__remembered-detail-list">
            <article
                v-for="offer in rememberedOffers"
                :key="offer.key"
                class="tt-entries-card__remembered-detail-card">
                <strong>{{ offer.name }}</strong>

                <v-alert
                    v-if="offer.outdated === true"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="my-2"
                    data-testid="remembered-offer-outdated">
                    Dieses Angebot hat sich geändert. Bitte prüfen Sie die aktuellen Termine und merken Sie es erneut.
                </v-alert>

                <div class="tt-entries-card__remembered-detail-entries">
                    <button
                        v-for="entry in offer.entries"
                        :key="entry.key"
                        type="button"
                        class="tt-entries-card__remembered-detail-entry"
                        :class="{
                            'tt-entries-card__remembered-detail-entry--inactive': !rememberedOfferEntryActive(entry),
                            'tt-entries-card__remembered-detail-entry--overlapping':
                                rememberedOfferOverlapEntryKeys.has(rememberedOfferEntryKey(offer, entry)),
                        }"
                        :aria-pressed="rememberedOfferEntryActive(entry) ? 'true' : 'false'"
                        :title="rememberedOfferEntryActive(entry) ? 'Termin deaktivieren' : 'Termin aktivieren'"
                        @click="toggleRememberedOfferEntry(offer.key, entry.key)">
                        <span class="tt-entries-card__remembered-detail-date">{{ entry.dateLabel || '-' }}</span>
                        <span class="tt-entries-card__remembered-detail-time">{{ entry.scheduleLabel || '-' }}</span>
                    </button>
                </div>
            </article>
        </v-card-text>
    </v-card>

    <v-card rounded="lg" class="tt-entries-card">
        <v-card-title class="tt-entries-card__title">
            <span>TT-Einträge:</span>
            <span class="text-h6 font-weight-bold text-primary">
                {{ personalSchoolyearLabel }}
            </span>
            <v-chip v-if="selectedMetaCourse" size="small" color="primary" variant="tonal">
                {{ selectedMetaCourse.label }}
            </v-chip>
        </v-card-title>

        <v-card-text class="tt-entries-card__content">
            <v-progress-linear
                v-if="loading"
                indeterminate
                color="primary"
                class="tt-entries-card__loading" />

            <v-alert v-else-if="error" type="error" variant="tonal" density="compact">
                {{ error }}
            </v-alert>

            <template v-else>
                <div v-if="metaCourseItems.length" class="tt-entries-card__layout">
                    <section class="tt-entries-card__meta-list" aria-label="Meta-Kurse">
                        <template v-for="course in metaCourseItems" :key="course.key">
                            <button
                                type="button"
                                class="tt-entries-card__meta-course"
                                :class="{ 'tt-entries-card__meta-course--active': selectedMetaCourseKey === course.key }"
                                :aria-expanded="selectedMetaCourseKey === course.key ? 'true' : 'false'"
                                :aria-pressed="selectedMetaCourseKey === course.key ? 'true' : 'false'"
                                @click="selectMetaCourse(course.key)">
                                <span class="tt-entries-card__meta-code">{{ course.label }}</span>
                                <span class="tt-entries-card__meta-name">{{ course.name }}</span>
                                <span class="tt-entries-card__meta-count">{{ course.countLabel }}</span>
                            </button>

                            <v-expand-transition>
                                <div
                                    v-if="selectedMetaCourseKey === course.key"
                                    class="tt-entries-card__sub-items"
                                    role="group"
                                    :aria-label="`${course.label} Untereinträge`">
                                    <button
                                        v-for="subject in selectedMetaCourseRows"
                                        :key="subjectRowKey(subject)"
                                        type="button"
                                        class="tt-entries-card__row"
                                        :class="{
                                            'tt-entries-card__row--active': selectedSubjectRowKey === subjectRowKey(subject),
                                        }"
                                        :aria-pressed="selectedSubjectRowKey === subjectRowKey(subject) ? 'true' : 'false'"
                                        @click="selectSubjectRow(subject)">
                                        <span class="tt-entries-card__row-code">
                                            {{ subject.json_code || subject.json_subject }}
                                        </span>
                                        <span class="tt-entries-card__row-name">{{ subject.name || '-' }}</span>
                                        <span class="tt-entries-card__row-meta">
                                            {{ subjectRowMeta(subject) }}
                                        </span>
                                    </button>
                                </div>
                            </v-expand-transition>
                        </template>
                    </section>

                    <section class="tt-entries-card__details" aria-live="polite">
                        <div v-if="selectedMetaCourse" class="tt-entries-card__details-heading">
                            <strong>{{ selectedMetaCourse.label }}</strong>
                            <span>{{ selectedMetaCourse.name }}</span>
                        </div>

                        <v-alert v-if="!selectedMetaCourseRows.length" type="info" variant="tonal" density="compact">
                            Kein Meta-Kurs ausgewählt.
                        </v-alert>

                        <section v-if="selectedSubjectRow" class="tt-entries-card__offers" aria-live="polite">
                            <div class="tt-entries-card__offers-heading">
                                <strong>Angebote</strong>
                                <span>{{ selectedSubjectCourseCode }}</span>
                            </div>

                            <div v-if="selectedSubjectOffers.length" class="tt-entries-card__offer-list">
                                <button
                                    v-for="offer in selectedSubjectOffers"
                                    :key="offer.key"
                                    type="button"
                                    class="tt-entries-card__offer"
                                    :class="{ 'tt-entries-card__offer--active': selectedSubjectOffer?.key === offer.key }"
                                    :aria-pressed="selectedSubjectOffer?.key === offer.key ? 'true' : 'false'"
                                    @click="selectOffer(offer.key)">
                                    <span class="tt-entries-card__offer-name">{{ offer.name }}</span>
                                    <span class="tt-entries-card__offer-schedule">{{ offer.scheduleLabel || '-' }}</span>
                                </button>
                            </div>

                            <v-alert v-else type="info" variant="tonal" density="compact">
                                Keine Angebote gefunden.
                            </v-alert>

                            <section v-if="selectedSubjectOffer" class="tt-entries-card__entry-details" aria-live="polite">
                                <div class="tt-entries-card__entry-heading">
                                    <strong>TT-Einträge dieses Angebots</strong>
                                    <span>{{ selectedSubjectOffer.name }}</span>
                                    <v-btn
                                        :icon="rememberedOffer(selectedSubjectOffer) ? 'mdi-bookmark' : 'mdi-bookmark-outline'"
                                        :color="rememberedOffer(selectedSubjectOffer) ? 'primary' : 'secondary'"
                                        :title="rememberedOffer(selectedSubjectOffer) ? 'Angebot nicht mehr merken' : 'Angebot merken'"
                                        :aria-label="rememberedOffer(selectedSubjectOffer) ? 'Angebot nicht mehr merken' : 'Angebot merken'"
                                        density="compact"
                                        size="x-small"
                                        variant="text"
                                        @click="toggleRememberedOffer(selectedSubjectOffer)" />
                                </div>

                                <div class="tt-entries-card__entry-list">
                                    <button
                                        v-for="entry in selectedSubjectOfferEntries"
                                        :key="entry.key"
                                        type="button"
                                        class="tt-entries-card__entry"
                                        :class="{
                                            'tt-entries-card__entry--inactive': !selectedSubjectOfferEntryActive(entry),
                                        }"
                                        :aria-pressed="selectedSubjectOfferEntryActive(entry) ? 'true' : 'false'"
                                        :title="selectedSubjectOfferEntryActive(entry) ? 'Termin deaktivieren' : 'Termin aktivieren'"
                                        @click="toggleSelectedSubjectOfferEntry(entry)">
                                        <span class="tt-entries-card__entry-title">{{ entry.dateLabel || '-' }}</span>
                                        <span class="tt-entries-card__entry-meta">{{ entryMetaLabel(entry) }}</span>
                                    </button>
                                </div>
                            </section>

                        </section>
                    </section>
                </div>

                <v-alert v-else type="info" variant="tonal" density="compact">
                    Keine TT-Einträge gefunden.
                </v-alert>
            </template>
        </v-card-text>
    </v-card>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

const TIMETABLE_COURSE_SUBJECT_LABELS = {
    REV: { code: 'Rev', name: 'Evangelische Religion' },
    RK: { code: 'Rk', name: 'Katholische Religion' },
    RIS: { code: 'Ris', name: 'Islamische Religion' },
    ROR: { code: 'Ror', name: 'Orthodoxe Religion' },
    D: { code: 'D', name: 'Deutsch' },
    E: { code: 'E', name: 'Englisch' },
    F: { code: 'F', name: 'Französisch' },
    L: { code: 'L', name: 'Latein' },
    SPA: { code: 'SPA', name: 'Spanisch' },
    GWB: { code: 'GWB', name: 'Geographie' },
    GPB: { code: 'GPB', name: 'Geschichte' },
    BU: { code: 'BU', name: 'Biologie' },
    CH: { code: 'CH', name: 'Chemie' },
    PH: { code: 'PH', name: 'Physik' },
    PP: { code: 'PP', name: 'Psychologie und Philosophie' },
    MU: { code: 'MU', name: 'Musik' },
    KG: { code: 'KG', name: 'Kunst' },
    INF: { code: 'INF', name: 'Informatik' },
    OEKO: { code: 'ÖKO', name: 'Ökonomie' },
}

const TIMETABLE_COURSE_SUBJECT_DISPLAY_ALIASES = {
    GW: 'GWB',
    OEK: 'OEKO',
    OKO: 'OEKO',
    OKON: 'OEKO',
    S: 'SPA',
}

const REMEMBERED_TT_OFFERS_ENDPOINT = '/api/admin/students-timetables/tt-entry-remembered-offers'
const REMEMBERED_TT_OFFERS_STORAGE_KEY = 'students-timetables:tt-entries:remembered-offers'

export default {
    data() {
        return {
            error: '',
            loading: false,
            rememberedOffersDetailsVisible: false,
            rememberedOffers: [],
            selectedMetaCourseKey: '',
            selectedSubjectRowKey: '',
            selectedOfferKey: '',
            schoolHours: [],
            courseGroups: [],
            subjectMappings: [],
            subjectRows: [],
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        personalSchoolyearLabel() {
            return this.config?.selected_schoolyear?.concerns
                || this.config?.selected_schoolyear?.name
                || 'nicht festgelegt'
        },
        activeSubjectRows() {
            return (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .filter((subject) => subject?.is_active !== false)
        },
        activeSubjectMappings() {
            return (Array.isArray(this.subjectMappings) ? this.subjectMappings : [])
                .filter((mapping) => mapping?.is_active !== false)
                .filter((mapping) => this.normalizedCourseCode(mapping?.json_subject) && mapping?.tt_subject)
        },
        selectableSubjectRows() {
            const knownCourseAliases = (Array.isArray(this.subjectRows) ? this.subjectRows : [])
                .flatMap(subject => this.courseCodeAliases({ code: this.selectedCourseCodeForSubject(subject) }))
            const importedSubjects = new Map()
            const courseGroups = Array.isArray(this.courseGroups) ? this.courseGroups : []

            courseGroups.forEach(courseGroup => {
                if (courseGroup?.is_active === false || this.courseGroupMatchesCourseAliases(courseGroup, knownCourseAliases)) {
                    return
                }

                const code = this.courseDisplayLabel(courseGroup?.module_code)
                const key = this.normalizedCourseCode(code)
                if (!key || importedSubjects.has(key)) {
                    return
                }

                importedSubjects.set(key, {
                    id: `imported:${key}`,
                    json_code: code,
                    json_subject: this.courseCodeWithoutModule(code),
                    name: code,
                    semester: null,
                    hours_per_week: null,
                    is_active: true,
                })
            })

            return [...this.activeSubjectRows, ...importedSubjects.values()]
        },
        metaCourseItems() {
            const coursesByKey = new Map()

            this.selectableSubjectRows.forEach((subject) => {
                const key = this.metaCourseKey(subject)
                if (!key) {
                    return
                }

                const item = coursesByKey.get(key) || {
                    key,
                    label: this.metaCourseLabel(key),
                    name: '',
                    rows: [],
                }

                item.rows.push(subject)
                item.name ||= this.metaCourseName(subject, key)
                coursesByKey.set(key, item)
            })

            return [...coursesByKey.values()]
                .map((course) => ({
                    ...course,
                    countLabel: `${course.rows.length} ${course.rows.length === 1 ? 'Eintrag' : 'Einträge'}`,
                }))
                .sort((firstCourse, secondCourse) => this.compareText(firstCourse.label, secondCourse.label))
        },
        selectedMetaCourse() {
            return this.metaCourseItems.find((course) => course.key === this.selectedMetaCourseKey)
                || this.metaCourseItems[0]
                || null
        },
        selectedMetaCourseRows() {
            return this.sortedSubjectRows(this.selectedMetaCourse?.rows || [])
        },
        selectedSubjectRow() {
            return this.selectedMetaCourseRows.find((subject) => this.subjectRowKey(subject) === this.selectedSubjectRowKey)
                || this.selectedMetaCourseRows[0]
                || null
        },
        selectedSubjectCourseCode() {
            return this.selectedCourseCodeForSubject(this.selectedSubjectRow)
        },
        selectedSubjectOffers() {
            if (!this.selectedSubjectRow) {
                return []
            }

            return this.offeredCourseItemsForSubject(this.selectedSubjectRow)
        },
        selectedSubjectOffer() {
            return this.selectedSubjectOffers.find((offer) => offer.key === this.selectedOfferKey)
                || this.selectedSubjectOffers[0]
                || null
        },
        selectedSubjectOfferEntries() {
            return this.concreteOfferEntryItems(this.selectedSubjectOffer?.entries || [])
        },
        rememberedOfferOverlapEntryKeys() {
            const comparableEntries = this.rememberedOfferComparableEntries()
            const overlappingEntryKeys = new Set()

            comparableEntries.forEach((entry, entryIndex) => {
                comparableEntries.slice(entryIndex + 1).forEach((candidateEntry) => {
                    if (!this.rememberedOfferEntriesOverlap(entry, candidateEntry)) {
                        return
                    }

                    overlappingEntryKeys.add(entry.key)
                    overlappingEntryKeys.add(candidateEntry.key)
                })
            })

            return overlappingEntryKeys
        },
        courseGroupsByCourseCode() {
            const courseGroupsByCourseCode = new Map()
            const courseGroups = Array.isArray(this.courseGroups) ? this.courseGroups : []

            courseGroups.forEach((courseGroup) => {
                this.courseGroupCodes(courseGroup).forEach((courseCode) => {
                    if (!courseGroupsByCourseCode.has(courseCode)) {
                        courseGroupsByCourseCode.set(courseCode, [])
                    }

                    courseGroupsByCourseCode.get(courseCode).push(courseGroup)
                })
            })

            return courseGroupsByCourseCode
        },
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadRememberedOffers()
            this.loadSubjectRows()
        },
        metaCourseItems() {
            this.ensureSelectedMetaCourse()
        },
        selectedMetaCourseRows() {
            this.ensureSelectedSubjectRow()
        },
        selectedSubjectOffers() {
            this.ensureSelectedOffer()
        },
    },
    mounted() {
        this.loadRememberedOffers()
        this.loadSubjectRows()
    },
    methods: {
        async loadRememberedOffers() {
            try {
                const response = await axios.get(REMEMBERED_TT_OFFERS_ENDPOINT)
                const storedOffers = this.normalizedRememberedOffers(response.data?.data?.offers || [])

                if (storedOffers.length) {
                    this.rememberedOffers = storedOffers
                    this.clearLegacyRememberedOffers()

                    return
                }

                const legacyOffers = this.legacyRememberedOffers()
                this.rememberedOffers = legacyOffers

                if (legacyOffers.length) {
                    await this.persistRememberedOffers()
                    this.clearLegacyRememberedOffers()
                }
            } catch {
                this.rememberedOffers = this.legacyRememberedOffers()
            }
        },
        async persistRememberedOffers() {
            try {
                const response = await axios.put(REMEMBERED_TT_OFFERS_ENDPOINT, {
                    offers: this.rememberedOffers,
                })

                this.rememberedOffers = this.normalizedRememberedOffers(response.data?.data?.offers || this.rememberedOffers)
            } catch {
                // Keep the in-memory state visible; the next user action will retry persistence.
            }
        },
        legacyRememberedOffers() {
            try {
                const offers = JSON.parse(localStorage.getItem(REMEMBERED_TT_OFFERS_STORAGE_KEY) || '[]')

                return this.normalizedRememberedOffers(offers)
            } catch {
                return []
            }
        },
        clearLegacyRememberedOffers() {
            try {
                localStorage.removeItem(REMEMBERED_TT_OFFERS_STORAGE_KEY)
            } catch {
                // Ignore storage access failures.
            }
        },
        normalizedRememberedOffers(offers) {
            return this.sortedRememberedOffers(Array.isArray(offers) ? offers : [])
                .filter((offer) => offer?.key && offer?.name && Array.isArray(offer?.entries) && offer.entries.length)
                .map((offer) => ({
                    ...offer,
                    entries: offer.entries.map((entry) => ({
                        ...entry,
                        active: entry?.active !== false,
                    })),
                }))
        },
        rememberedOffer(offer) {
            const key = this.rememberedOfferKey(offer)

            return this.rememberedOffers.some((rememberedOffer) => rememberedOffer.key === key)
        },
        async toggleRememberedOffer(offer) {
            const key = this.rememberedOfferKey(offer)
            if (this.rememberedOffers.some((rememberedOffer) => rememberedOffer.key === key)) {
                await this.removeRememberedOffer(key)

                return
            }

            this.rememberedOffers = this.sortedRememberedOffers([
                ...this.rememberedOffers,
                this.rememberedOfferPayload(offer),
            ])
            await this.persistRememberedOffers()
        },
        async removeRememberedOffer(key) {
            this.rememberedOffers = this.rememberedOffers.filter((offer) => offer.key !== key)
            if (!this.rememberedOffers.length) {
                this.rememberedOffersDetailsVisible = false
            }
            await this.persistRememberedOffers()
        },
        async clearRememberedOffers() {
            this.rememberedOffers = []
            this.rememberedOffersDetailsVisible = false
            await this.persistRememberedOffers()
        },
        toggleRememberedOffersDetails() {
            this.rememberedOffersDetailsVisible = !this.rememberedOffersDetailsVisible
        },
        rememberedOfferPayload(offer) {
            return {
                key: this.rememberedOfferKey(offer),
                entries: this.selectedSubjectOfferEntries.map((entry) => ({
                    key: entry?.key || '',
                    dateLabel: entry?.dateLabel || '',
                    dateValue: entry?.dateValue || '',
                    active: true,
                    scheduleLabel: entry?.scheduleLabel || '',
                    timeFrom: entry?.timeFrom || '',
                    timeUntil: entry?.timeUntil || '',
                })),
                name: offer?.name || '',
                scheduleLabel: offer?.scheduleLabel || '',
            }
        },
        rememberedOfferKey(offer) {
            return [
                offer?.key || '',
                offer?.name || '',
            ].join('|')
        },
        sortedRememberedOffers(offers) {
            return [...(Array.isArray(offers) ? offers : [])].sort((firstOffer, secondOffer) =>
                this.compareText(firstOffer?.name, secondOffer?.name))
        },
        rememberedOfferEntryActive(entry) {
            return entry?.active !== false
        },
        selectedSubjectOfferEntryActive(entry) {
            const rememberedOffer = this.rememberedOffers.find((offer) =>
                offer?.key === this.rememberedOfferKey(this.selectedSubjectOffer))
            const rememberedEntry = (Array.isArray(rememberedOffer?.entries) ? rememberedOffer.entries : [])
                .find((rememberedEntry) => rememberedEntry?.key === entry?.key)

            return this.rememberedOfferEntryActive(rememberedEntry)
        },
        async toggleSelectedSubjectOfferEntry(entry) {
            if (!this.selectedSubjectOffer) {
                return
            }

            const offerKey = this.rememberedOfferKey(this.selectedSubjectOffer)
            if (this.rememberedOffers.some((offer) => offer?.key === offerKey)) {
                await this.toggleRememberedOfferEntry(offerKey, entry?.key || '')

                return
            }

            const rememberedOffer = this.rememberedOfferPayload(this.selectedSubjectOffer)

            this.rememberedOffers = this.sortedRememberedOffers([
                ...this.rememberedOffers,
                {
                    ...rememberedOffer,
                    entries: rememberedOffer.entries.map((rememberedEntry) => ({
                        ...rememberedEntry,
                        active: rememberedEntry?.key === entry?.key
                            ? false
                            : this.rememberedOfferEntryActive(rememberedEntry),
                    })),
                },
            ])
            await this.persistRememberedOffers()
        },
        async toggleRememberedOfferEntry(offerKey, entryKey) {
            this.rememberedOffers = this.rememberedOffers.map((offer) => {
                if (offer?.key !== offerKey) {
                    return offer
                }

                return {
                    ...offer,
                    entries: (Array.isArray(offer?.entries) ? offer.entries : []).map((entry) => {
                        if (entry?.key !== entryKey) {
                            return entry
                        }

                        return {
                            ...entry,
                            active: !this.rememberedOfferEntryActive(entry),
                        }
                    }),
                }
            })
            await this.persistRememberedOffers()
        },
        rememberedOfferComparableEntries() {
            return (Array.isArray(this.rememberedOffers) ? this.rememberedOffers : [])
                .flatMap((offer) => (Array.isArray(offer?.entries) ? offer.entries : [])
                    .filter((entry) => this.rememberedOfferEntryActive(entry))
                    .map((entry) => this.rememberedOfferComparableEntry(offer, entry))
                    .filter(Boolean))
        },
        rememberedOfferComparableEntry(offer, entry) {
            const timeRange = this.rememberedOfferEntryTimeRange(entry)

            if (!entry?.dateValue || !timeRange) {
                return null
            }

            return {
                key: this.rememberedOfferEntryKey(offer, entry),
                dateValue: entry.dateValue,
                endMinute: timeRange.endMinute,
                offerKey: offer?.key || '',
                startMinute: timeRange.startMinute,
            }
        },
        rememberedOfferEntryKey(offer, entry) {
            return [
                offer?.key || '',
                entry?.key || '',
            ].join('|')
        },
        rememberedOfferEntriesOverlap(firstEntry, secondEntry) {
            return firstEntry.offerKey !== secondEntry.offerKey
                && firstEntry.dateValue === secondEntry.dateValue
                && firstEntry.startMinute < secondEntry.endMinute
                && secondEntry.startMinute < firstEntry.endMinute
        },
        rememberedOfferEntryTimeRange(entry) {
            const startMinute = this.timeLabelMinutes(entry?.timeFrom)
            const endMinute = this.timeLabelMinutes(entry?.timeUntil)

            if (startMinute !== null && endMinute !== null && startMinute < endMinute) {
                return { endMinute, startMinute }
            }

            const scheduleMatch = String(entry?.scheduleLabel || '').match(/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/u)

            if (!scheduleMatch) {
                return null
            }

            const parsedStartMinute = this.timeLabelMinutes(scheduleMatch[1])
            const parsedEndMinute = this.timeLabelMinutes(scheduleMatch[2])

            if (parsedStartMinute === null || parsedEndMinute === null || parsedStartMinute >= parsedEndMinute) {
                return null
            }

            return {
                endMinute: parsedEndMinute,
                startMinute: parsedStartMinute,
            }
        },
        async loadSubjectRows() {
            this.loading = true
            this.error = ''

            try {
                const [settingsResponse, courseGroupsResponse, schoolHoursResponse] = await Promise.all([
                    axios.get('/api/admin/students-timetables/subjects-overview-settings', {
                        params: { schoolyear_scope: 'personal' },
                    }),
                    axios.get('/api/admin/students-timetables/course-groups'),
                    axios.get('/api/admin/students-timetables/school-hours'),
                ])

                this.subjectRows = settingsResponse.data?.data?.subjects || []
                this.subjectMappings = settingsResponse.data?.data?.mappings || []
                this.courseGroups = courseGroupsResponse.data?.data || []
                this.schoolHours = schoolHoursResponse.data?.data || []
                this.ensureSelectedMetaCourse()
                this.ensureSelectedSubjectRow()
                this.ensureSelectedOffer()
            } catch {
                this.courseGroups = []
                this.schoolHours = []
                this.subjectMappings = []
                this.subjectRows = []
                this.error = 'Die TT-Einträge konnten nicht geladen werden.'
            } finally {
                this.loading = false
            }
        },
        ensureSelectedMetaCourse() {
            if (!this.metaCourseItems.length) {
                this.selectedMetaCourseKey = ''

                return
            }

            if (this.metaCourseItems.some((course) => course.key === this.selectedMetaCourseKey)) {
                return
            }

            this.selectedMetaCourseKey = this.metaCourseItems[0].key
        },
        selectMetaCourse(key) {
            this.selectedMetaCourseKey = key
            this.$nextTick(() => {
                this.ensureSelectedSubjectRow()
                this.ensureSelectedOffer()
            })
        },
        ensureSelectedSubjectRow() {
            if (!this.selectedMetaCourseRows.length) {
                this.selectedSubjectRowKey = ''

                return
            }

            if (this.selectedMetaCourseRows.some((subject) => this.subjectRowKey(subject) === this.selectedSubjectRowKey)) {
                return
            }

            this.selectedSubjectRowKey = this.subjectRowKey(this.selectedMetaCourseRows[0])
        },
        selectSubjectRow(subject) {
            this.selectedSubjectRowKey = this.subjectRowKey(subject)
            this.$nextTick(() => this.ensureSelectedOffer())
        },
        ensureSelectedOffer() {
            if (!this.selectedSubjectOffers.length) {
                this.selectedOfferKey = ''

                return
            }

            if (this.selectedSubjectOffers.some((offer) => offer.key === this.selectedOfferKey)) {
                return
            }

            this.selectedOfferKey = this.selectedSubjectOffers[0].key
        },
        selectOffer(key) {
            this.selectedOfferKey = key
        },
        metaCourseKey(subject) {
            return this.canonicalCourseKey(
                this.ttSubjectForSubject(subject)
                || subject?.json_subject
                || this.courseCodeWithoutModule(subject?.json_code)
                || subject?.json_code
                || subject?.name,
            )
        },
        metaCourseLabel(key) {
            return this.courseDisplayLabel(key)
        },
        metaCourseName(subject, key) {
            const name = String(subject?.name || '').trim()
            const label = this.metaCourseLabel(key)
            const canonicalSubjectName = this.canonicalCourseSubjectName(key)
            if (!name || name === label) {
                return canonicalSubjectName || label
            }

            return canonicalSubjectName || name.replace(/\s+\d+$/u, '')
        },
        ttSubjectForSubject(subject) {
            const subjectAliases = this.subjectJsonAliases(subject)
            const mapping = this.activeSubjectMappings.find((subjectMapping) =>
                subjectAliases.includes(this.normalizedCourseCode(subjectMapping.json_subject)),
            )

            return mapping?.tt_subject || ''
        },
        subjectJsonAliases(subject) {
            return [
                subject?.json_subject,
                this.courseCodeWithoutModule(subject?.json_code),
            ]
                .map((value) => this.normalizedCourseCode(value))
                .filter(Boolean)
                .filter((value, index, values) => values.indexOf(value) === index)
        },
        subjectRowKey(subject) {
            return [
                subject?.id || '',
                subject?.semester || '',
                subject?.branch || 'common',
                subject?.json_code || '',
                subject?.json_subject || '',
            ].join('|')
        },
        subjectRowMeta(subject) {
            return [
                this.semesterLabel(subject?.semester),
                this.hoursLabel(subject?.hours_per_week),
            ].filter(Boolean).join(' · ')
        },
        semesterLabel(semester) {
            const normalizedSemester = Number(semester || 0)
            return Number.isFinite(normalizedSemester) && normalizedSemester > 0 ? `${normalizedSemester}. Sem.` : ''
        },
        hoursLabel(hours) {
            const normalizedHours = Number(hours || 0)
            if (!Number.isFinite(normalizedHours) || normalizedHours <= 0) {
                return ''
            }

            return `${this.formatNumber(normalizedHours)} Std.`
        },
        selectedCourseCodeForSubject(subject) {
            if (!subject) {
                return ''
            }

            const moduleNumber = this.courseCodeModuleParts(subject?.json_code).module
            const mappedSubject = this.ttSubjectForSubject(subject)

            if (mappedSubject && moduleNumber) {
                return this.courseDisplayLabel(`${mappedSubject}${moduleNumber}`)
            }

            return this.courseDisplayLabel(subject?.json_code || subject?.json_subject || subject?.name)
        },
        offeredCourseItemsForSubject(subject) {
            const courseCode = this.selectedCourseCodeForSubject(subject)
            const courseAliases = this.courseCodeAliases({ code: courseCode })
            const courseGroups = this.courseGroupsForCourseAliases(courseAliases)

            return this.uniqueOfferedCourseItems(courseGroups
                .filter((courseGroup) => this.courseGroupMatchesCourseAliases(courseGroup, courseAliases))
                .map((courseGroup) => this.offeredCourseGroupItem(courseGroup))
                .sort((firstCourse, secondCourse) => this.compareOfferedCourseItems(firstCourse, secondCourse)))
        },
        courseGroupsForCourseAliases(courseAliases) {
            if (!Array.isArray(courseAliases) || !courseAliases.length) {
                return []
            }

            const courseGroups = new Set()

            courseAliases.forEach((courseAlias) => {
                const matchingCourseGroups = this.courseGroupsByCourseCode.get(courseAlias) || []

                matchingCourseGroups.forEach((courseGroup) => {
                    courseGroups.add(courseGroup)
                })
            })

            return Array.from(courseGroups)
        },
        uniqueOfferedCourseItems(courseItems) {
            const courseItemsByIdentity = new Map()
            const offeredCourseItems = Array.isArray(courseItems) ? courseItems : []

            offeredCourseItems.forEach((courseItem) => {
                const identityKey = [
                    courseItem?.semester || '',
                    this.normalizedCourseCode(courseItem?.code),
                    this.normalizedCourseCode(courseItem?.name),
                ].join('|')
                const existingCourseItem = courseItemsByIdentity.get(identityKey)

                if (!existingCourseItem) {
                    courseItemsByIdentity.set(identityKey, { ...courseItem })

                    return
                }

                courseItemsByIdentity.set(identityKey, {
                    ...existingCourseItem,
                    entries: this.mergedEntryItems(existingCourseItem.entries, courseItem.entries),
                    scheduleSlots: this.mergedScheduleSlots(existingCourseItem.scheduleSlots, courseItem.scheduleSlots),
                })
            })

            return [...courseItemsByIdentity.values()].map((courseItem) => {
                const scheduleSlots = this.mergedScheduleSlots(courseItem.scheduleSlots, [])

                return {
                    ...courseItem,
                    key: [
                        courseItem?.key || '',
                        this.compactScheduleSlotsLabel(scheduleSlots),
                    ].join('|'),
                    scheduleLabel: this.compactScheduleSlotsLabel(scheduleSlots),
                    entries: this.sortedEntryItems(courseItem.entries),
                    scheduleSlots,
                }
            })
        },
        courseGroupMatchesCourseAliases(courseGroup, courseAliases) {
            const courseGroupCodes = this.courseGroupCodes(courseGroup)
            if (!courseAliases.some((courseAlias) => courseGroupCodes.includes(courseAlias))) {
                return false
            }

            return !this.courseGroupHasConflictingModuleCode(this.courseGroupLeadingCodes(courseGroup), courseAliases)
        },
        courseGroupHasConflictingModuleCode(leadingCourseCodes, courseAliases) {
            const aliasesWithModule = courseAliases
                .map((courseAlias) => this.courseCodeModuleParts(courseAlias))
                .filter((parts) => parts.module)

            if (!aliasesWithModule.length) {
                return false
            }

            return (Array.isArray(leadingCourseCodes) ? leadingCourseCodes : [])
                .map((courseCode) => this.courseCodeModuleParts(courseCode))
                .filter((parts) => parts.module)
                .some((parts) => {
                    const aliasesWithSameBase = aliasesWithModule.filter((aliasParts) =>
                        this.courseBaseAliases(aliasParts.base).includes(parts.base)
                            || this.courseBaseAliases(parts.base).includes(aliasParts.base))

                    return aliasesWithSameBase.length
                        && !aliasesWithSameBase.some((aliasParts) => aliasParts.module === parts.module)
                })
        },
        courseGroupCodes(courseGroup) {
            return this.uniqueValues([
                ...this.courseGroupLeadingCodes(courseGroup),
                ...[
                    courseGroup?.course,
                    courseGroup?.subject,
                    courseGroup?.module_code,
                    courseGroup?.title,
                    courseGroup?.display_label,
                ].flatMap((value) => this.courseCodeTokensFromValue(value)),
            ]
                .map((value) => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        courseGroupLeadingCodes(courseGroup) {
            return this.uniqueValues([
                courseGroup?.class_name,
                courseGroup?.display_label,
                courseGroup?.title,
            ]
                .flatMap((value) => this.leadingCourseCodesFromValue(value))
                .map((value) => this.normalizedCourseCode(value))
                .filter(Boolean))
        },
        leadingCourseCodesFromValue(value) {
            const firstSegment = String(value || '')
                .split(/\s+-\s+|[-\s]/u)[0]
                ?.trim() || ''

            return this.courseCodeTokensFromValue(firstSegment)
        },
        courseCodeTokensFromValue(value) {
            return String(value || '')
                .split(/[,\s/]+/u)
                .map((part) => part.trim())
                .filter(Boolean)
                .flatMap((part) => this.courseCodeAliasParts(part))
        },
        offeredCourseGroupItem(courseGroup) {
            const code = this.courseDisplayLabel(
                this.courseGroupLeadingCodes(courseGroup)[0]
                || courseGroup?.course
                || courseGroup?.module_code
                || courseGroup?.subject
                || courseGroup?.title,
            )
            const scheduleSlots = this.courseGroupScheduleSlots(courseGroup)

            return {
                key: courseGroup?.key || [
                    courseGroup?.semester,
                    courseGroup?.weekday,
                    courseGroup?.hour,
                    courseGroup?.starts_at,
                    courseGroup?.ends_at,
                    courseGroup?.module_code || courseGroup?.course || courseGroup?.subject,
                    courseGroup?.class_name,
                ].join('|'),
                code,
                name: this.offeredCourseGroupLabel(courseGroup, code),
                entries: [this.ttEntryItemForCourseGroup(courseGroup, scheduleSlots)],
                scheduleLabel: this.compactScheduleSlotsLabel(scheduleSlots),
                scheduleSlots,
                semester: Number(courseGroup?.semester || 0) || null,
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour: Number(courseGroup?.hour || 0) || null,
            }
        },
        offeredCourseGroupLabel(courseGroup, code) {
            const group = this.cleanedOfferedCourseGroupSegment(
                courseGroup?.class_name || courseGroup?.display_label,
                code,
            )
            const fallbackLabel = this.courseDisplayLabel(courseGroup?.display_label || courseGroup?.title || courseGroup?.course || courseGroup?.subject)

            return this.uniqueValues([
                code,
                group,
            ].filter(Boolean)).join(' - ') || fallbackLabel || 'Ohne Bezeichnung'
        },
        ttEntryItemForCourseGroup(courseGroup, scheduleSlots = null) {
            const slots = Array.isArray(scheduleSlots) ? scheduleSlots : this.courseGroupScheduleSlots(courseGroup)
            const firstSlot = slots[0] || {}

            return {
                key: courseGroup?.key || [
                    courseGroup?.semester,
                    courseGroup?.weekday,
                    courseGroup?.hour,
                    courseGroup?.starts_at,
                    courseGroup?.ends_at,
                    courseGroup?.module_code || courseGroup?.course || courseGroup?.subject,
                    courseGroup?.class_name,
                ].join('|'),
                displayLabel: courseGroup?.display_label
                    || courseGroup?.title
                    || this.offeredCourseGroupLabel(courseGroup, this.courseDisplayLabel(courseGroup?.course || courseGroup?.subject)),
                scheduleLabel: this.compactScheduleSlotsLabel(slots),
                dates: (Array.isArray(courseGroup?.dates) ? courseGroup.dates : [])
                    .filter(Boolean)
                    .sort(),
                firstDate: courseGroup?.first_date || null,
                lastDate: courseGroup?.last_date || null,
                recurrenceLabel: courseGroup?.recurrence_label || '',
                semester: Number(courseGroup?.semester || 0) || null,
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour: Number(courseGroup?.hour || 0) || null,
                endHour: Number(courseGroup?.hour || 0) || null,
                timeFrom: firstSlot.from || '',
                timeUntil: firstSlot.until || '',
            }
        },
        mergedEntryItems(firstEntries, secondEntries) {
            const entryItemsByKey = new Map()

            ;[
                ...(Array.isArray(firstEntries) ? firstEntries : []),
                ...(Array.isArray(secondEntries) ? secondEntries : []),
            ].forEach((entry) => {
                if (!entry?.key) {
                    return
                }

                entryItemsByKey.set(entry.key, entry)
            })

            return this.sortedEntryItems([...entryItemsByKey.values()])
        },
        concreteOfferEntryItems(entries) {
            const entryItems = (Array.isArray(entries) ? entries : [])
                .flatMap((entry) => this.concreteEntryItemsForEntry(entry))
                .sort((firstEntry, secondEntry) =>
                    this.compareText(firstEntry?.dateValue, secondEntry?.dateValue)
                    || Number(firstEntry?.weekday || 0) - Number(secondEntry?.weekday || 0)
                    || Number(firstEntry?.hour || 0) - Number(secondEntry?.hour || 0)
                    || this.compareText(firstEntry?.displayLabel, secondEntry?.displayLabel))

            return this.compactConsecutiveEntryItems(entryItems)
        },
        concreteEntryItemsForEntry(entry) {
            const dateValues = this.entryDateValues(entry)

            if (!dateValues.length) {
                return [{
                    ...entry,
                    key: `${entry?.key || 'entry'}|without-date|${entry?.hour || ''}`,
                    dateLabel: '',
                    dateValue: '',
                }]
            }

            return dateValues.map((dateValue) => ({
                ...entry,
                key: `${entry?.key || 'entry'}|${dateValue}|${entry?.hour || ''}`,
                dateLabel: this.formatDateValue(dateValue),
                dateValue,
            }))
        },
        entryDateValues(entry) {
            const dates = (Array.isArray(entry?.dates) ? entry.dates : [])
                .filter(Boolean)
                .sort()

            if (dates.length) {
                return this.uniqueValues(dates)
            }

            return this.uniqueValues([
                entry?.firstDate,
                entry?.lastDate,
            ].filter(Boolean))
        },
        compactConsecutiveEntryItems(entries) {
            return (Array.isArray(entries) ? entries : []).reduce((compactedEntries, entry) => {
                const previousEntry = compactedEntries[compactedEntries.length - 1]

                if (!this.entriesCanCompact(previousEntry, entry)) {
                    compactedEntries.push(entry)

                    return compactedEntries
                }

                compactedEntries[compactedEntries.length - 1] = this.compactedEntryItem(previousEntry, entry)

                return compactedEntries
            }, [])
        },
        entriesCanCompact(firstEntry, secondEntry) {
            if (!firstEntry || !secondEntry) {
                return false
            }

            return firstEntry.dateValue === secondEntry.dateValue
                && Number(firstEntry.weekday || 0) === Number(secondEntry.weekday || 0)
                && Number(firstEntry.endHour || firstEntry.hour || 0) + 1 === Number(secondEntry.hour || 0)
                && firstEntry.timeUntil
                && secondEntry.timeFrom
                && firstEntry.timeUntil === secondEntry.timeFrom
                && (firstEntry.displayLabel || '') === (secondEntry.displayLabel || '')
                && (firstEntry.recurrenceLabel || '') === (secondEntry.recurrenceLabel || '')
        },
        compactedEntryItem(firstEntry, secondEntry) {
            const hour = Number(firstEntry.hour || 0)
            const endHour = Number(secondEntry.endHour || secondEntry.hour || 0)
            const scheduleLabel = this.compactEntryScheduleLabel({
                weekday: firstEntry.weekday,
                hour,
                endHour,
                timeFrom: firstEntry.timeFrom,
                timeUntil: secondEntry.timeUntil,
            })

            return {
                ...firstEntry,
                key: `${firstEntry.key}|${secondEntry.key}`,
                endHour,
                recurrenceLabel: '',
                scheduleLabel,
                timeUntil: secondEntry.timeUntil,
            }
        },
        compactEntryScheduleLabel(entry) {
            const weekdayLabel = this.courseGroupWeekdayLabel(entry?.weekday)
            const hour = Number(entry?.hour || 0)
            const endHour = Number(entry?.endHour || entry?.hour || 0)
            const hourLabel = endHour > hour ? `${hour}.-${endHour}.` : `${hour}.`
            const timeRangeLabel = [entry?.timeFrom, entry?.timeUntil].filter(Boolean).join('-')

            return [
                weekdayLabel ? `${weekdayLabel}.` : '',
                hourLabel,
                timeRangeLabel,
            ].filter(Boolean).join(' ')
        },
        sortedEntryItems(entries) {
            return [...(Array.isArray(entries) ? entries : [])].sort((firstEntry, secondEntry) =>
                Number(firstEntry?.semester || 0) - Number(secondEntry?.semester || 0)
                || Number(firstEntry?.weekday || 0) - Number(secondEntry?.weekday || 0)
                || Number(firstEntry?.hour || 0) - Number(secondEntry?.hour || 0)
                || this.compareText(firstEntry?.displayLabel, secondEntry?.displayLabel))
        },
        entryMetaLabel(entry) {
            return [
                entry?.scheduleLabel,
                entry?.recurrenceLabel,
            ].filter(Boolean).join(' - ')
        },
        courseGroupDateLabel(courseGroup) {
            const dates = (Array.isArray(courseGroup?.dates) ? courseGroup.dates : [])
                .filter(Boolean)
                .sort()
            if (dates.length > 1) {
                return `${dates.length} Termine: ${this.formatDateValue(dates[0])} - ${this.formatDateValue(dates[dates.length - 1])}`
            }

            if (dates.length === 1) {
                return this.formatDateValue(dates[0])
            }

            return [courseGroup?.first_date, courseGroup?.last_date]
                .filter(Boolean)
                .map((date) => this.formatDateValue(date))
                .join(' - ')
        },
        cleanedOfferedCourseTeacherSegment(value, code) {
            let segment = String(value || '').trim()

            segment = this.stripLabelSegment(segment, code)
            segment = this.stripLabelSegment(segment, String(code || '').replace(/\s+/gu, ''))

            return segment.trim()
        },
        cleanedOfferedCourseGroupSegment(value, code) {
            let segment = String(value || '').trim()

            segment = this.stripLabelSegment(segment, code)
            segment = this.stripLabelSegment(segment, String(code || '').replace(/\s+/gu, ''))

            return segment
                .replace(/\s*-\s*/gu, ' - ')
                .replace(/^(?:-|\s)+|(?:-|\s)+$/gu, '')
                .trim()
        },
        stripLabelSegment(value, segment) {
            const labelSegment = String(segment || '').trim()
            if (!value || !labelSegment) {
                return value
            }

            const escapedSegment = labelSegment.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&')

            return value
                .replace(new RegExp(`^\\s*${escapedSegment}\\s*-?\\s*`, 'iu'), '')
                .replace(new RegExp(`\\s*-?\\s*${escapedSegment}\\s*$`, 'iu'), '')
        },
        courseGroupScheduleSlots(courseGroup) {
            const hour = Number(courseGroup?.hour)
            if (!Number.isFinite(hour) || hour <= 0) {
                return []
            }

            const timeRange = this.courseGroupTimeRange(courseGroup)

            return [{
                weekday: Number(courseGroup?.weekday || 0) || null,
                hour,
                from: timeRange.from,
                until: timeRange.until,
            }]
        },
        courseGroupTimeRange(courseGroup) {
            const schoolHour = (Array.isArray(this.schoolHours) ? this.schoolHours : [])
                .find((configuredSchoolHour) => Number(configuredSchoolHour?.hour) === Number(courseGroup?.hour))
            const from = this.formatTimeValue(schoolHour?.from)
            const until = this.formatTimeValue(schoolHour?.until)

            return { from, until }
        },
        mergedScheduleSlots(firstSlots, secondSlots) {
            const scheduleSlots = [
                ...(Array.isArray(firstSlots) ? firstSlots : []),
                ...(Array.isArray(secondSlots) ? secondSlots : []),
            ]

            return Object.values(scheduleSlots.reduce((slots, slot) => {
                const key = [
                    slot?.weekday || '',
                    slot?.hour || '',
                    slot?.from || '',
                    slot?.until || '',
                ].join('|')

                slots[key] ??= slot

                return slots
            }, {})).sort((firstSlot, secondSlot) =>
                Number(firstSlot?.weekday || 0) - Number(secondSlot?.weekday || 0)
                || Number(firstSlot?.hour || 0) - Number(secondSlot?.hour || 0))
        },
        compactScheduleSlotsLabel(scheduleSlots) {
            const slots = (Array.isArray(scheduleSlots) ? scheduleSlots : [])
                .filter((slot) => Number.isFinite(Number(slot?.hour)) && Number(slot?.hour) > 0)
                .sort((firstSlot, secondSlot) =>
                    Number(firstSlot?.weekday || 0) - Number(secondSlot?.weekday || 0)
                    || Number(firstSlot?.hour || 0) - Number(secondSlot?.hour || 0))

            return slots.map((slot) => this.scheduleSlotLabel(slot)).filter(Boolean).join(', ')
        },
        scheduleSlotLabel(slot) {
            const weekdayLabel = this.courseGroupWeekdayLabel(slot?.weekday)
            const hourLabel = `${Number(slot?.hour)}.`
            const timeRangeLabel = [slot?.from, slot?.until].filter(Boolean).join('-')

            return [weekdayLabel, hourLabel, timeRangeLabel].filter(Boolean).join(' ')
        },
        courseGroupWeekdayLabel(weekday) {
            return [
                { shortTitle: 'Mo', value: 1 },
                { shortTitle: 'Di', value: 2 },
                { shortTitle: 'Mi', value: 3 },
                { shortTitle: 'Do', value: 4 },
                { shortTitle: 'Fr', value: 5 },
                { shortTitle: 'Sa', value: 6 },
            ]
                .find((option) => Number(option.value) === Number(weekday))?.shortTitle || ''
        },
        compareOfferedCourseItems(firstCourse, secondCourse) {
            const firstSemester = Number(firstCourse?.semester || 0)
            const secondSemester = Number(secondCourse?.semester || 0)
            if (firstSemester !== secondSemester) {
                return firstSemester - secondSemester
            }

            const firstSlot = Number(firstCourse?.weekday || 0) * 100 + Number(firstCourse?.hour || 0)
            const secondSlot = Number(secondCourse?.weekday || 0) * 100 + Number(secondCourse?.hour || 0)
            if (firstSlot !== secondSlot) {
                return firstSlot - secondSlot
            }

            return this.compareText(firstCourse?.name || firstCourse?.code, secondCourse?.name || secondCourse?.code)
        },
        sortedSubjectRows(subjects) {
            return [...(Array.isArray(subjects) ? subjects : [])].sort((firstSubject, secondSubject) => {
                const semesterComparison = Number(firstSubject?.semester || 0) - Number(secondSubject?.semester || 0)
                if (semesterComparison !== 0) {
                    return semesterComparison
                }

                return this.compareText(
                    firstSubject?.json_code || firstSubject?.name,
                    secondSubject?.json_code || secondSubject?.name,
                )
            })
        },
        courseCodeWithoutModule(code) {
            return this.courseCodeModuleParts(code).base
        },
        courseCodeAliases(course) {
            const courseCodeValue = String(course?.code || course?.subject || course?.label || course?.name || '').trim()

            return this.courseCodeAliasParts(courseCodeValue)
                .map((courseCode) => this.normalizedCourseCode(courseCode))
                .flatMap((courseCode) => {
                    const { base, module } = this.courseCodeModuleParts(courseCode)

                    return this.courseBaseAliases(base).map((baseAlias) => `${baseAlias}${module}`)
                })
                .filter(Boolean)
                .filter((courseCode, index, courseCodes) => courseCodes.indexOf(courseCode) === index)
        },
        courseBaseAliases(base) {
            const normalizedBase = this.normalizedCourseCode(base)
            const mappedAliases = {
                ET: ['ETH'],
                ETH: ['ET'],
                GPB: ['GS'],
                GS: ['GPB'],
                GW: ['GWB'],
                GWB: ['GW'],
                LPT: ['LET'],
                LET: ['LPT'],
                ME: ['MU'],
                MU: ['ME'],
                OEKO: ['OEK', 'OKO', 'OKON'],
                OEK: ['OEKO', 'OKO', 'OKON'],
                OKO: ['OEKO', 'OEK', 'OKON'],
                OKON: ['OEKO', 'OEK', 'OKO'],
                R: ['RK', 'REV', 'RIS', 'ROR'],
                REV: ['R', 'EV', 'EVANG'],
                RIS: ['R', 'ISLAM'],
                RK: ['R', 'RKATH'],
                RKATH: ['R', 'RK'],
                ROR: ['R', 'ORTH'],
                S: ['SPA'],
                SPA: ['S'],
            }

            return this.uniqueValues([normalizedBase, ...(mappedAliases[normalizedBase] || [])].filter(Boolean))
        },
        courseCodeAliasParts(value) {
            return String(value || '')
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
        },
        courseCodeModuleParts(value) {
            const normalizedValue = this.normalizedCourseCode(value)
            const match = normalizedValue.match(/^([A-Z]+)([0-9]*)$/u)
                || normalizedValue.match(/^([A-Z]+)([0-9]+)[A-Z]+$/u)

            if (!match) {
                return {
                    base: normalizedValue,
                    module: '',
                }
            }

            return {
                base: match[1],
                module: match[2] || '',
            }
        },
        courseDisplayLabel(code) {
            const label = String(code || '').trim()
            if (!label) {
                return ''
            }
            if (label.includes('/')) {
                return label
                    .split('/')
                    .map((part) => this.courseDisplayLabel(part))
                    .filter(Boolean)
                    .join(' / ')
            }

            return this.canonicalCourseDisplayLabel(label) || this.alternativeDisplay(label)
        },
        canonicalCourseDisplayLabel(value) {
            const label = String(value || '').trim()
            const match = label.match(/^([A-Za-zÄÖÜäöüß]+)\s*([0-9]*)/u)

            if (!match) {
                return ''
            }

            const [, subjectCode, moduleCode] = match
            const subjectLabel = this.canonicalCourseSubjectLabel(subjectCode)

            if (!subjectLabel) {
                return ''
            }

            const rest = label.slice(match[0].length)

            return `${subjectLabel.code}${moduleCode || ''}${rest || ''}`
        },
        canonicalCourseSubjectLabel(value) {
            const normalizedSubjectCode = this.canonicalCourseKey(value)

            return TIMETABLE_COURSE_SUBJECT_LABELS[normalizedSubjectCode] || null
        },
        canonicalCourseSubjectName(value) {
            return this.canonicalCourseSubjectLabel(value)?.name || ''
        },
        canonicalCourseKey(value) {
            const rawValue = String(value || '').trim()
            if (rawValue.includes('/')) {
                return rawValue
                    .split('/')
                    .map((part) => this.canonicalCourseKey(part))
                    .filter(Boolean)
                    .join('/')
            }

            const normalizedValue = this.normalizedCourseCode(rawValue)

            return TIMETABLE_COURSE_SUBJECT_DISPLAY_ALIASES[normalizedValue] || normalizedValue
        },
        alternativeDisplay(value) {
            const displayValue = String(value || '').trim()
            if (!displayValue) {
                return '-'
            }

            return displayValue
                .split('/')
                .map((part) => part.trim())
                .filter(Boolean)
                .join(' / ')
        },
        normalizedCourseCode(value) {
            return String(value || '')
                .trim()
                .toLocaleUpperCase('de-AT')
                .replace(/Ä/gu, 'AE')
                .replace(/Ö/gu, 'OE')
                .replace(/Ü/gu, 'UE')
                .replace(/ß/gu, 'SS')
                .replace(/[^A-Z0-9]/gu, '')
        },
        compareText(firstValue, secondValue) {
            return String(firstValue || '').localeCompare(String(secondValue || ''), 'de-AT', {
                numeric: true,
                sensitivity: 'base',
            })
        },
        uniqueValues(values) {
            return (Array.isArray(values) ? values : [])
                .filter((value, index, allValues) => allValues.indexOf(value) === index)
        },
        mergedLabelList(firstLabel, secondLabel) {
            return this.uniqueValues([
                ...String(firstLabel || '').split(','),
                ...String(secondLabel || '').split(','),
            ]
                .map((label) => label.trim())
                .filter(Boolean))
                .join(', ')
        },
        formatTimeValue(value) {
            const rawValue = String(value || '').trim()

            if (!rawValue) {
                return ''
            }

            const match = rawValue.match(/^(\d{1,2}):(\d{2})/u)

            if (match) {
                return `${match[1].padStart(2, '0')}:${match[2]}`
            }

            return rawValue
        },
        timeLabelMinutes(value) {
            const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})/u)

            if (!match) {
                return null
            }

            const hours = Number(match[1])
            const minutes = Number(match[2])

            if (!Number.isFinite(hours) || !Number.isFinite(minutes) || hours < 0 || minutes < 0 || minutes > 59) {
                return null
            }

            return hours * 60 + minutes
        },
        formatNumber(value) {
            return new Intl.NumberFormat('de-AT', {
                maximumFractionDigits: 2,
                minimumFractionDigits: 0,
            }).format(value)
        },
        formatDateValue(value) {
            const rawValue = String(value || '')
            const dateParts = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})/u)
            const date = dateParts
                ? new Date(Number(dateParts[1]), Number(dateParts[2]) - 1, Number(dateParts[3]))
                : new Date(rawValue)
            if (Number.isNaN(date.getTime())) {
                return rawValue
            }

            return new Intl.DateTimeFormat('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            }).format(date)
        },
    },
}
</script>

<style scoped>
.tt-entries-card {
    border: 1px solid rgba(37, 99, 235, 0.16);
    background: rgba(255, 255, 255, 0.94);
}

.tt-entries-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 0;
    padding: 12px 14px 4px;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 800;
}

.tt-entries-card__content {
    display: grid;
    gap: 12px;
    padding: 10px 14px 14px !important;
}

.tt-entries-card__layout {
    display: grid;
    grid-template-columns: minmax(220px, 0.82fr) minmax(0, 1.18fr);
    gap: 12px;
}

.tt-entries-card__meta-list,
.tt-entries-card__sub-items {
    display: grid;
    align-content: start;
    gap: 6px;
}

.tt-entries-card__sub-items {
    padding-left: 24px;
}

.tt-entries-card__meta-course {
    display: grid;
    grid-template-columns: minmax(52px, auto) minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
    width: 100%;
    border: 1px solid rgba(37, 99, 235, 0.16);
    border-radius: 8px;
    padding: 7px 9px;
    background: rgba(248, 250, 252, 0.96);
    color: #1e293b;
    cursor: pointer;
    font: inherit;
    letter-spacing: 0;
    text-align: left;
}

.tt-entries-card__meta-course:hover,
.tt-entries-card__meta-course:focus-visible,
.tt-entries-card__meta-course--active {
    border-color: rgba(37, 99, 235, 0.38);
    background: rgba(219, 234, 254, 0.92);
    outline: none;
}

.tt-entries-card__meta-course--active {
    box-shadow: inset 3px 0 0 #2563eb;
}

.tt-entries-card__meta-code,
.tt-entries-card__row-code {
    color: #0f172a;
    font-weight: 900;
}

.tt-entries-card__meta-name,
.tt-entries-card__row-name {
    min-width: 0;
    overflow: hidden;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__meta-count,
.tt-entries-card__row-meta {
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
}

.tt-entries-card__details {
    display: grid;
    align-content: start;
    gap: 8px;
    min-width: 0;
}

.tt-entries-card__details-heading {
    display: flex;
    align-items: baseline;
    gap: 8px;
    min-width: 0;
    color: #0f172a;
}

.tt-entries-card__details-heading span {
    min-width: 0;
    overflow: hidden;
    color: #475569;
    font-size: 0.82rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__row {
    display: grid;
    grid-template-columns: minmax(62px, auto) minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    width: 100%;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 8px;
    padding: 7px 9px;
    background: rgba(248, 250, 252, 0.72);
    color: inherit;
    cursor: pointer;
    font: inherit;
    letter-spacing: 0;
    text-align: left;
}

.tt-entries-card__row:hover,
.tt-entries-card__row:focus-visible,
.tt-entries-card__row--active {
    border-color: rgba(37, 99, 235, 0.34);
    background: rgba(239, 246, 255, 0.92);
    outline: none;
}

.tt-entries-card__offers {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.tt-entries-card__offers-heading {
    display: flex;
    align-items: baseline;
    gap: 8px;
    color: #0f172a;
}

.tt-entries-card__offers-heading span {
    color: #475569;
    font-size: 0.82rem;
    font-weight: 800;
}

.tt-entries-card__offer-list {
    display: grid;
    gap: 6px;
}

.tt-entries-card__offer {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 8px;
    width: 100%;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 8px;
    padding: 7px 9px;
    background: rgba(255, 255, 255, 0.74);
    color: inherit;
    cursor: pointer;
    font: inherit;
    letter-spacing: 0;
    text-align: left;
}

.tt-entries-card__offer:hover,
.tt-entries-card__offer:focus-visible,
.tt-entries-card__offer--active {
    border-color: rgba(37, 99, 235, 0.34);
    background: rgba(239, 246, 255, 0.92);
    outline: none;
}

.tt-entries-card__offer--active {
    box-shadow: inset 3px 0 0 #2563eb;
}

.tt-entries-card__offer-name {
    min-width: 0;
    overflow: hidden;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__offer-schedule {
    color: #475569;
    font-size: 0.72rem;
    font-weight: 800;
}

.tt-entries-card__entry-details {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.tt-entries-card__entry-heading {
    display: flex;
    align-items: baseline;
    gap: 8px;
    color: #0f172a;
}

.tt-entries-card__entry-heading span {
    min-width: 0;
    overflow: hidden;
    color: #475569;
    font-size: 0.82rem;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__entry-list {
    display: grid;
    gap: 6px;
}

.tt-entries-card__entry {
    display: grid;
    grid-template-columns: minmax(96px, auto) minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 8px;
    padding: 7px 9px;
    background: rgba(248, 250, 252, 0.74);
    color: inherit;
    cursor: pointer;
    font: inherit;
    text-align: left;
}

.tt-entries-card__entry--inactive {
    opacity: 0.52;
    text-decoration: line-through;
}

.tt-entries-card__entry:focus-visible {
    outline: 2px solid rgba(37, 99, 235, 0.5);
    outline-offset: 2px;
}

.tt-entries-card__entry-title {
    min-width: 0;
    overflow: hidden;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__entry-meta {
    color: #475569;
    font-size: 0.72rem;
    font-weight: 800;
}

.tt-entries-card__remembered {
    display: grid;
    gap: 8px;
    min-width: 0;
    margin-bottom: 8px;
    border: 1px solid rgba(37, 99, 235, 0.16);
    border-radius: 8px;
    padding: 9px;
    background: rgba(255, 255, 255, 0.78);
}

.tt-entries-card__remembered-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: #0f172a;
}

.tt-entries-card__remembered-heading-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
}

.tt-entries-card__remembered-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.tt-entries-card__remembered-empty {
    margin: 0;
    color: #64748b;
    font-size: 0.82rem;
    font-weight: 700;
}

.tt-entries-card__remembered-offer {
    display: inline-flex;
    max-width: 220px;
    min-width: 0;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 8px;
    padding: 3px 5px 3px 8px;
    background: rgba(248, 250, 252, 0.74);
}

.tt-entries-card__remembered-offer-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-width: 0;
    color: #0f172a;
    font-size: 0.82rem;
}

.tt-entries-card__remembered-offer-heading strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__remembered-details {
    margin-bottom: 8px;
    border: 1px solid rgba(37, 99, 235, 0.16);
    background: rgba(255, 255, 255, 0.84);
}

.tt-entries-card__remembered-details-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 0;
    padding: 8px 10px 4px;
    color: #0f172a;
    font-size: 0.9rem;
    font-weight: 900;
}

.tt-entries-card__remembered-detail-list {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 10px 10px;
}

.tt-entries-card__remembered-detail-card {
    display: grid;
    flex: 1 1 220px;
    gap: 7px;
    max-width: 340px;
    min-width: 0;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 8px;
    padding: 8px;
    background: rgba(248, 250, 252, 0.78);
}

.tt-entries-card__remembered-detail-card strong {
    min-width: 0;
    overflow: hidden;
    color: #0f172a;
    font-size: 0.82rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__remembered-detail-entries {
    display: grid;
    gap: 5px;
}

.tt-entries-card__remembered-detail-entry {
    display: grid;
    grid-template-columns: minmax(72px, auto) minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    border: 0;
    padding: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font: inherit;
    text-align: left;
}

.tt-entries-card__remembered-detail-entry--overlapping {
    border: 1px solid rgba(220, 38, 38, 0.28);
    border-radius: 6px;
    padding: 3px 5px;
    background: rgba(254, 226, 226, 0.78);
}

.tt-entries-card__remembered-detail-entry--overlapping .tt-entries-card__remembered-detail-date,
.tt-entries-card__remembered-detail-entry--overlapping .tt-entries-card__remembered-detail-time {
    color: #b91c1c;
}

.tt-entries-card__remembered-detail-entry--inactive {
    opacity: 0.52;
    text-decoration: line-through;
}

.tt-entries-card__remembered-detail-entry--inactive.tt-entries-card__remembered-detail-entry--overlapping {
    border-color: transparent;
    background: transparent;
}

.tt-entries-card__remembered-detail-entry:focus-visible {
    outline: 2px solid rgba(37, 99, 235, 0.5);
    outline-offset: 2px;
}

.tt-entries-card__remembered-detail-date,
.tt-entries-card__remembered-detail-time {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tt-entries-card__remembered-detail-date {
    color: #0f172a;
    font-size: 0.78rem;
    font-weight: 900;
}

.tt-entries-card__remembered-detail-time {
    color: #475569;
    font-size: 0.72rem;
    font-weight: 800;
}

@media (max-width: 820px) {
    .tt-entries-card__layout {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 520px) {
    .tt-entries-card__meta-course,
    .tt-entries-card__row,
    .tt-entries-card__offer,
    .tt-entries-card__entry,
    .tt-entries-card__remembered-detail-entry {
        grid-template-columns: minmax(0, 1fr);
        gap: 3px;
    }
}
</style>
