<template>
    <v-container fluid class="materials-page ma-0 w-100 pa-2">
        <AdminCompactSectionHero class="mb-3" eyebrow="Materialien" title="Fachkatalog" />
        <v-row class="w-100" dense>
            <v-col cols="12" xl="10" class="subjects-overview-page-col">
                <v-card class="materials-shell pa-4 pa-md-8 subjects-overview-shell" rounded="xl" elevation="0">
                    <div class="d-flex flex-wrap align-center ga-2 mb-4 subjects-overview-toolbar">
                        <v-btn
                            size="small"
                            variant="text"
                            prepend-icon="mdi-arrow-left"
                            @click="goBack">
                            Zurück
                        </v-btn>
                        <v-btn
                            size="small"
                            color="primary"
                            variant="outlined"
                            prepend-icon="mdi-refresh"
                            :loading="isLoading"
                            @click="loadOverview">
                            Aktualisieren
                        </v-btn>
                        <v-btn
                            size="small"
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-printer"
                            :disabled="isLoading || !subjects.length"
                            @click="printPage">
                            Drucken / PDF
                        </v-btn>
                        <v-spacer />
                        <v-chip size="small" variant="tonal" color="primary" prepend-icon="mdi-file-document-multiple-outline">
                            Materialien: {{ uniqueMaterialCount }}
                        </v-chip>
                    </div>

                    <div class="subjects-overview-screen-content">
                        <div ref="screenHeaderBlock">
                            <div class="text-h4 font-weight-bold mb-2">Fachkatalog Übersicht</div>
                            <div class="text-body-2 mb-4 subjects-overview-subline">
                                <div>Alle Fächer, Themen und Einheiten mit zugeordneten Materialien.</div>
                                <div>Stand: {{ generatedAtLabel }}</div>
                            </div>
                            <div v-if="printUserLabel" class="mb-1 subjects-overview-userline">
                                {{ printUserLabel }}
                            </div>
                            <div v-if="printSchoolLongName" class="mb-4 subjects-overview-schoolline">
                                {{ printSchoolLongName }}
                            </div>
                        </div>

                        <v-progress-linear
                            v-if="isLoading"
                            indeterminate
                            color="primary"
                            rounded
                            class="mb-4" />

                        <v-alert
                            v-else-if="!subjects.length"
                            type="info"
                            variant="tonal">
                            Keine Fachstruktur mit Materialien gefunden.
                        </v-alert>

                        <div v-else class="subjects-overview-print-root">
                            <section
                                v-for="subject in subjects"
                                :key="`subjects-overview-subject-${subject.id || subject.name}`"
                                ref="screenSubjectSections"
                                class="subjects-overview-subject">
                                <header class="subjects-overview-header subjects-overview-header--subject">
                                    <div class="d-flex align-center ga-2">
                                        <v-icon size="18" icon="mdi-book-education-outline" />
                                        <span class="text-subtitle-1 font-weight-bold">{{ subject.name }}</span>
                                    </div>
                                </header>

                                <ul v-if="subject.materials.length" class="subjects-overview-material-list mb-2">
                                    <li
                                        v-for="material in subject.materials"
                                        :key="`subject-material-${subject.id || subject.name}-${material.id}`"
                                        class="subjects-overview-material-item">
                                        <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" class="subjects-overview-material-icon" />
                                        <span class="subjects-overview-material-text">{{ material.title }}</span>
                                    </li>
                                </ul>

                                <section
                                    v-for="topic in subject.topics"
                                    :key="`subjects-overview-topic-${topic.id || `${subject.id || subject.name}-${topic.name}`}`"
                                    class="subjects-overview-topic">
                                    <header class="subjects-overview-header subjects-overview-header--topic">
                                        <div class="d-flex align-center ga-2">
                                            <v-icon size="16" icon="mdi-book-open-page-variant-outline" />
                                            <span class="text-body-1 font-weight-medium">{{ topic.name }}</span>
                                        </div>
                                    </header>

                                    <ul v-if="topic.materials.length" class="subjects-overview-material-list mb-2">
                                        <li
                                            v-for="material in topic.materials"
                                            :key="`topic-material-${topic.id || topic.name}-${material.id}`"
                                            class="subjects-overview-material-item">
                                            <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" class="subjects-overview-material-icon" />
                                            <span class="subjects-overview-material-text">{{ material.title }}</span>
                                        </li>
                                    </ul>

                                    <section
                                        v-for="unit in topic.units"
                                        :key="`subjects-overview-unit-${unit.id || `${topic.id || topic.name}-${unit.name}`}`"
                                        class="subjects-overview-unit">
                                        <header class="subjects-overview-header subjects-overview-header--unit">
                                            <div class="d-flex align-center ga-2">
                                                <v-icon size="14" icon="mdi-circle-medium" />
                                                <span class="text-body-1 font-weight-bold">{{ unit.name }}</span>
                                            </div>
                                        </header>

                                        <ul v-if="unit.materials.length" class="subjects-overview-material-list">
                                            <li
                                                v-for="material in unit.materials"
                                                :key="`unit-material-${unit.id || unit.name}-${material.id}`"
                                                class="subjects-overview-material-item">
                                                <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" class="subjects-overview-material-icon" />
                                                <span class="subjects-overview-material-text">{{ material.title }}</span>
                                            </li>
                                        </ul>
                                    </section>
                                </section>
                            </section>
                        </div>
                    </div>

                    <div v-if="!isLoading && effectivePrintPages.length" class="subjects-overview-print-pages" aria-hidden="true">
                        <section
                            v-for="(pageSubjects, pageIndex) in effectivePrintPages"
                            :key="`subjects-overview-print-page-${pageIndex}`"
                            class="subjects-overview-print-page">
                            <div v-if="pageIndex === 0" class="subjects-overview-print-header">
                                <div class="text-h4 font-weight-bold mb-2">Fachkatalog Übersicht</div>
                                <div class="text-body-2 mb-4 subjects-overview-subline">
                                    <div>Alle Fächer, Themen und Einheiten mit zugeordneten Materialien.</div>
                                    <div>Stand: {{ generatedAtLabel }}</div>
                                </div>
                                <div v-if="printUserLabel" class="mb-1 subjects-overview-userline">
                                    {{ printUserLabel }}
                                </div>
                                <div v-if="printSchoolLongName" class="mb-4 subjects-overview-schoolline">
                                    {{ printSchoolLongName }}
                                </div>
                            </div>

                            <section
                                v-for="subject in pageSubjects"
                                :key="`subjects-overview-print-subject-${pageIndex}-${subject.id || subject.name}`"
                                class="subjects-overview-subject">
                                <header class="subjects-overview-header subjects-overview-header--subject">
                                    <div class="d-flex align-center ga-2">
                                        <v-icon size="18" icon="mdi-book-education-outline" />
                                        <span class="text-subtitle-1 font-weight-bold">{{ subject.name }}</span>
                                    </div>
                                </header>

                                <ul v-if="subject.materials.length" class="subjects-overview-material-list mb-2">
                                    <li
                                        v-for="material in subject.materials"
                                        :key="`print-subject-material-${pageIndex}-${subject.id || subject.name}-${material.id}`"
                                        class="subjects-overview-material-item">
                                        <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" class="subjects-overview-material-icon" />
                                        <span class="subjects-overview-material-text">{{ material.title }}</span>
                                    </li>
                                </ul>

                                <section
                                    v-for="topic in subject.topics"
                                    :key="`subjects-overview-print-topic-${pageIndex}-${topic.id || `${subject.id || subject.name}-${topic.name}`}`"
                                    class="subjects-overview-topic">
                                    <header class="subjects-overview-header subjects-overview-header--topic">
                                        <div class="d-flex align-center ga-2">
                                            <v-icon size="16" icon="mdi-book-open-page-variant-outline" />
                                            <span class="text-body-1 font-weight-medium">{{ topic.name }}</span>
                                        </div>
                                    </header>

                                    <ul v-if="topic.materials.length" class="subjects-overview-material-list mb-2">
                                        <li
                                            v-for="material in topic.materials"
                                            :key="`print-topic-material-${pageIndex}-${topic.id || topic.name}-${material.id}`"
                                            class="subjects-overview-material-item">
                                            <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" class="subjects-overview-material-icon" />
                                            <span class="subjects-overview-material-text">{{ material.title }}</span>
                                        </li>
                                    </ul>

                                    <section
                                        v-for="unit in topic.units"
                                        :key="`subjects-overview-print-unit-${pageIndex}-${unit.id || `${topic.id || topic.name}-${unit.name}`}`"
                                        class="subjects-overview-unit">
                                        <header class="subjects-overview-header subjects-overview-header--unit">
                                            <div class="d-flex align-center ga-2">
                                                <v-icon size="14" icon="mdi-circle-medium" />
                                                <span class="text-body-1 font-weight-bold">{{ unit.name }}</span>
                                            </div>
                                        </header>

                                        <ul v-if="unit.materials.length" class="subjects-overview-material-list">
                                            <li
                                                v-for="material in unit.materials"
                                                :key="`print-unit-material-${pageIndex}-${unit.id || unit.name}-${material.id}`"
                                                class="subjects-overview-material-item">
                                                <v-icon size="14" :icon="material.icon || 'mdi-file-document-outline'" class="subjects-overview-material-icon" />
                                                <span class="subjects-overview-material-text">{{ material.title }}</span>
                                            </li>
                                        </ul>
                                    </section>
                                </section>
                            </section>

                            <div class="subjects-overview-print-page-footer">
                                Seite {{ pageIndex + 1 }}/{{ effectivePrintPages.length }}
                            </div>
                        </section>
                    </div>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import AdminCompactSectionHero from '@/pages/admin/components/AdminCompactSectionHero.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'

export default {
    components: { AdminCompactSectionHero },
    name: 'MaterialsSubjectsOverview',
    data() {
        return {
            materialCardStore: null,
            adminStore: null,
            isLoading: false,
            subjects: [],
            printPages: [],
            resizeTimerId: null,
            uniqueMaterialCount: 0,
            generatedAt: null,
        }
    },
    computed: {
        generatedAtLabel() {
            if (!(this.generatedAt instanceof Date)) {
                return 'Unbekannt'
            }

            const dateText = this.generatedAt.toLocaleDateString('de-AT')
            const timeText = this.generatedAt.toLocaleTimeString('de-AT', {
                hour: '2-digit',
                minute: '2-digit',
            })

            return `${dateText}, ${timeText}`
        },
        printUserLabel() {
            const user = this.adminStore?.config?.user || {}
            const short = this.normalizeText(user?.short)
            const lastName = this.normalizeText(user?.last_name)
            const firstName = this.normalizeText(user?.first_name)
            const name = [lastName, firstName].filter((value) => value !== '').join(' ')
            if (short && name) return `${short}, ${name}`
            if (name) return name
            return short
        },
        printSchoolLongName() {
            const selectedSchool = this.adminStore?.config?.selected_school || {}
            return this.normalizeText(selectedSchool?.long_name)
        },
        effectivePrintPages() {
            if (Array.isArray(this.printPages) && this.printPages.length > 0) {
                return this.printPages
            }
            if (Array.isArray(this.subjects) && this.subjects.length > 0) {
                return [this.subjects]
            }
            return []
        },
    },
    async beforeMount() {
        this.adminStore = useAdminStore()
        await this.adminStore.loadConfig()
        this.materialCardStore = useMaterialCardStore()
        await this.loadOverview()
    },
    async mounted() {
        window.addEventListener('beforeprint', this.handleBeforePrint)
        window.addEventListener('resize', this.handleWindowResize)
        await this.$nextTick()
        this.rebuildPrintPages()
    },
    beforeUnmount() {
        window.removeEventListener('beforeprint', this.handleBeforePrint)
        window.removeEventListener('resize', this.handleWindowResize)
        if (this.resizeTimerId) {
            window.clearTimeout(this.resizeTimerId)
            this.resizeTimerId = null
        }
    },
    methods: {
        normalizeText(value) {
            return String(value ?? '').trim()
        },
        normalizeFilterSelection(filters = {}) {
            const normalized = {
                search: this.normalizeText(filters?.search),
                status: this.normalizeText(filters?.status),
                subject: this.normalizeText(filters?.subject),
                topic: this.normalizeText(filters?.topic),
                unit: this.normalizeText(filters?.unit),
                type: this.normalizeText(filters?.type),
                area: this.normalizeText(filters?.area),
            }

            if (normalized.subject === '') {
                normalized.topic = ''
                normalized.unit = ''
            } else if (normalized.topic === '') {
                normalized.unit = ''
            }

            return normalized
        },
        buildRouteFilters() {
            const query = this.$route?.query || {}
            const normalized = this.normalizeFilterSelection(query)
            const result = {}
            for (const [key, value] of Object.entries(normalized)) {
                const filterKey = this.normalizeText(key)
                if (!filterKey || filterKey === 'page') continue
                if (value === '') continue
                result[filterKey] = value
            }
            return result
        },
        hasActiveFilterValues(filters = {}) {
            for (const [key, value] of Object.entries(filters || {})) {
                const filterKey = this.normalizeText(key)
                if (!filterKey || filterKey === 'page') continue
                if (this.normalizeText(value) !== '') return true
            }
            return false
        },
        rowMatchesClassificationFilters(row, filters = {}) {
            const subjectFilter = this.normalizeText(filters?.subject).toLocaleLowerCase()
            const topicFilter = this.normalizeText(filters?.topic).toLocaleLowerCase()
            const unitFilter = this.normalizeText(filters?.unit).toLocaleLowerCase()

            const subject = this.normalizeText(row?.subject).toLocaleLowerCase()
            const topic = this.normalizeText(row?.topic).toLocaleLowerCase()
            const unit = this.normalizeText(row?.unit).toLocaleLowerCase()

            if (subjectFilter !== '' && subject !== subjectFilter) return false
            if (topicFilter !== '' && topic !== topicFilter) return false
            if (unitFilter !== '' && unit !== unitFilter) return false

            return true
        },
        cmToPx(valueCm) {
            const parsed = Number(valueCm)
            if (!Number.isFinite(parsed)) return 0
            return (parsed * 96) / 2.54
        },
        getPrintableContentHeightPx() {
            const a4HeightCm = 29.7
            const topMarginCm = 2
            const bottomMarginCm = 2
            const printSafetyCm = 1.4
            return this.cmToPx(a4HeightCm - topMarginCm - bottomMarginCm - printSafetyCm)
        },
        getHeaderHeightPx() {
            const headerEl = this.$refs.screenHeaderBlock
            if (!headerEl || typeof headerEl.getBoundingClientRect !== 'function') return 0
            return Math.ceil(headerEl.getBoundingClientRect().height)
        },
        getSubjectHeightsPx() {
            const rawRefs = this.$refs.screenSubjectSections
            const subjectEls = Array.isArray(rawRefs) ? rawRefs : (rawRefs ? [rawRefs] : [])
            return subjectEls.map((element) => {
                if (!element || typeof element.getBoundingClientRect !== 'function') return 0
                return Math.ceil(element.getBoundingClientRect().height)
            })
        },
        rebuildPrintPages() {
            if (!Array.isArray(this.subjects) || this.subjects.length === 0) {
                this.printPages = []
                return
            }

            const subjectHeights = this.getSubjectHeightsPx()
            if (subjectHeights.length !== this.subjects.length) {
                this.printPages = [this.subjects]
                return
            }

            const pageHeight = this.getPrintableContentHeightPx()
            const footerHeight = this.cmToPx(0.8)
            const subjectSpacing = 14
            const firstPageHeader = this.getHeaderHeightPx()
            const minimumFreeSpace = 120

            const firstPageLimit = Math.max(minimumFreeSpace, pageHeight - firstPageHeader - footerHeight)
            const nextPageLimit = Math.max(minimumFreeSpace, pageHeight - footerHeight)

            const pages = []
            let pageSubjects = []
            let remainingHeight = firstPageLimit

            this.subjects.forEach((subject, index) => {
                const measuredHeight = Number(subjectHeights[index]) + subjectSpacing
                const requiredHeight = Number.isFinite(measuredHeight) ? measuredHeight : 220

                if (pageSubjects.length > 0 && requiredHeight > remainingHeight) {
                    pages.push(pageSubjects)
                    pageSubjects = []
                    remainingHeight = nextPageLimit
                }

                pageSubjects.push(subject)
                remainingHeight -= requiredHeight
            })

            if (pageSubjects.length > 0) {
                pages.push(pageSubjects)
            }

            this.printPages = pages.length > 0 ? pages : [this.subjects]
        },
        handleWindowResize() {
            if (this.resizeTimerId) {
                window.clearTimeout(this.resizeTimerId)
            }
            this.resizeTimerId = window.setTimeout(() => {
                this.resizeTimerId = null
                this.rebuildPrintPages()
            }, 120)
        },
        async handleBeforePrint() {
            await this.$nextTick()
            this.rebuildPrintPages()
            await this.$nextTick()
        },
        normalizeClassificationRows(rows) {
            const list = Array.isArray(rows) ? rows : []
            const result = []
            const seen = new Set()

            for (const row of list) {
                const subject = this.normalizeText(row?.subject)
                const topic = this.normalizeText(row?.topic)
                let unit = this.normalizeText(row?.unit)
                if (!subject) continue
                if (!topic) unit = ''

                const key = `${subject.toLocaleLowerCase()}|${topic.toLocaleLowerCase()}|${unit.toLocaleLowerCase()}`
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ subject, topic, unit })
            }

            return result
        },
        materialTypeIcon(typeValue) {
            const normalizedType = this.normalizeText(typeValue).toLocaleLowerCase()
            if (!normalizedType) return ''

            const options = Array.isArray(this.materialCardStore?.config?.type_values)
                ? this.materialCardStore.config.type_values
                : []

            const option = options.find((entry) =>
                this.normalizeText(entry?.value).toLocaleLowerCase() === normalizedType
            )

            return this.normalizeText(option?.icon)
        },
        materialIcon(card) {
            const configuredIcon = this.materialTypeIcon(card?.type)
            if (configuredIcon) return configuredIcon

            const attachments = Array.isArray(card?.attachments) ? card.attachments : []
            const hasFileAttachment = attachments.some((attachment) =>
                this.normalizeText(attachment?.attachment_type).toLocaleLowerCase() === 'file'
            )
            if (hasFileAttachment) return 'mdi-file-upload-outline'

            if (this.normalizeText(card?.source_url) !== '') return 'mdi-link-variant'

            const hasTextContent = this.normalizeText(card?.source_text) !== '' || this.normalizeText(card?.notes) !== ''
            if (hasTextContent) return 'mdi-note-text-outline'

            if (attachments.length > 0) return 'mdi-paperclip'
            return 'mdi-file-document-outline'
        },
        createSubjectNode(node = null) {
            const subjectName = this.normalizeText(node?.name)
            return {
                id: Number(node?.id) > 0 ? Number(node.id) : null,
                name: subjectName,
                topics: [],
                materials: [],
                material_count: 0,
                _topicByKey: new Map(),
                _materialIds: new Set(),
            }
        },
        createTopicNode(node = null) {
            const topicName = this.normalizeText(node?.name)
            return {
                id: Number(node?.id) > 0 ? Number(node.id) : null,
                name: topicName,
                units: [],
                materials: [],
                material_count: 0,
                _unitByKey: new Map(),
                _materialIds: new Set(),
            }
        },
        createUnitNode(node = null) {
            const unitName = this.normalizeText(node?.name)
            return {
                id: Number(node?.id) > 0 ? Number(node.id) : null,
                name: unitName,
                materials: [],
                material_count: 0,
                _materialIds: new Set(),
            }
        },
        ensureSubject(subjects, subjectByKey, subjectName, sourceNode = null) {
            const key = this.normalizeText(subjectName).toLocaleLowerCase()
            if (!key) return null

            let subject = subjectByKey.get(key)
            if (!subject) {
                subject = this.createSubjectNode(sourceNode || { name: subjectName })
                subjects.push(subject)
                subjectByKey.set(key, subject)
            }
            return subject
        },
        ensureTopic(subject, topicName, sourceNode = null) {
            const key = this.normalizeText(topicName).toLocaleLowerCase()
            if (!subject || !key) return null

            let topic = subject._topicByKey.get(key)
            if (!topic) {
                topic = this.createTopicNode(sourceNode || { name: topicName })
                subject.topics.push(topic)
                subject._topicByKey.set(key, topic)
            }
            return topic
        },
        ensureUnit(topic, unitName, sourceNode = null) {
            const key = this.normalizeText(unitName).toLocaleLowerCase()
            if (!topic || !key) return null

            let unit = topic._unitByKey.get(key)
            if (!unit) {
                unit = this.createUnitNode(sourceNode || { name: unitName })
                topic.units.push(unit)
                topic._unitByKey.set(key, unit)
            }
            return unit
        },
        pushMaterial(node, material) {
            if (!node || !material) return
            const id = Number(material.id)
            if (!Number.isFinite(id) || id <= 0) return
            if (node._materialIds.has(id)) return
            node._materialIds.add(id)
            node.materials.push(material)
        },
        sortMaterials(list) {
            const items = Array.isArray(list) ? list : []
            items.sort((a, b) =>
                String(a?.title || '').localeCompare(String(b?.title || ''), undefined, { sensitivity: 'base' })
            )
        },
        finalizeTree(subjects) {
            for (const subject of subjects) {
                this.sortMaterials(subject.materials)

                let topicTotal = 0
                for (const topic of subject.topics) {
                    this.sortMaterials(topic.materials)

                    let unitTotal = 0
                    for (const unit of topic.units) {
                        this.sortMaterials(unit.materials)
                        unit.material_count = unit.materials.length
                        unitTotal += unit.material_count
                        delete unit._materialIds
                    }

                    topic.material_count = topic.materials.length + unitTotal
                    topicTotal += topic.material_count
                    delete topic._unitByKey
                    delete topic._materialIds
                }

                subject.material_count = subject.materials.length + topicTotal
                delete subject._topicByKey
                delete subject._materialIds
            }

            return subjects
        },
        buildSubjectsOverview(tree, cards, filters = {}) {
            const subjects = []
            const subjectByKey = new Map()
            const treeItems = Array.isArray(tree) ? tree : []
            const cardItems = Array.isArray(cards) ? cards : []
            const hasActiveFilters = this.hasActiveFilterValues(filters)

            if (!hasActiveFilters) {
                for (const subjectNode of treeItems) {
                    const subjectName = this.normalizeText(subjectNode?.name)
                    if (!subjectName) continue
                    const subject = this.ensureSubject(subjects, subjectByKey, subjectName, subjectNode)
                    const topicNodes = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []
                    for (const topicNode of topicNodes) {
                        const topicName = this.normalizeText(topicNode?.name)
                        if (!topicName) continue
                        const topic = this.ensureTopic(subject, topicName, topicNode)
                        const unitNodes = Array.isArray(topicNode?.units) ? topicNode.units : []
                        for (const unitNode of unitNodes) {
                            const unitName = this.normalizeText(unitNode?.name)
                            if (!unitName) continue
                            this.ensureUnit(topic, unitName, unitNode)
                        }
                    }
                }
            }

            const uniqueCardIds = new Set()
            for (const card of cardItems) {
                const cardId = Number(card?.id)
                if (!Number.isFinite(cardId) || cardId <= 0) continue

                const material = {
                    id: cardId,
                    title: this.normalizeText(card?.title) || 'Ohne Titel',
                    icon: this.materialIcon(card),
                }

                let rows = this.normalizeClassificationRows(card?.classifications)
                if (hasActiveFilters) {
                    rows = rows.filter((row) => this.rowMatchesClassificationFilters(row, filters))
                }

                let cardMapped = false
                for (const row of rows) {
                    const subject = this.ensureSubject(subjects, subjectByKey, row.subject)
                    if (!subject) continue

                    if (!row.topic) {
                        this.pushMaterial(subject, material)
                        cardMapped = true
                        continue
                    }

                    const topic = this.ensureTopic(subject, row.topic)
                    if (!topic) continue

                    if (!row.unit) {
                        this.pushMaterial(topic, material)
                        cardMapped = true
                        continue
                    }

                    const unit = this.ensureUnit(topic, row.unit)
                    if (!unit) continue
                    this.pushMaterial(unit, material)
                    cardMapped = true
                }

                if (cardMapped) {
                    uniqueCardIds.add(cardId)
                }
            }

            return {
                subjects: this.finalizeTree(subjects),
                uniqueMaterialCount: uniqueCardIds.size,
            }
        },
        async loadOverview() {
            if (this.isLoading) return
            this.isLoading = true
            try {
                if (!this.materialCardStore?.config) {
                    await this.materialCardStore.loadConfig()
                } else {
                    await this.materialCardStore.loadConfig()
                }

                const routeFilters = this.buildRouteFilters()
                const cards = await this.materialCardStore.listAllCardsSnapshot(routeFilters)
                if (!Array.isArray(cards)) {
                    this.subjects = []
                    this.printPages = []
                    this.uniqueMaterialCount = 0
                    this.generatedAt = new Date()
                    return
                }

                const tree = Array.isArray(this.materialCardStore?.config?.classification_tree)
                    ? this.materialCardStore.config.classification_tree
                    : []
                const result = this.buildSubjectsOverview(tree, cards, routeFilters)
                this.subjects = result.subjects
                this.uniqueMaterialCount = result.uniqueMaterialCount
                this.generatedAt = new Date()
                await this.$nextTick()
                this.rebuildPrintPages()
            } finally {
                this.isLoading = false
            }
        },
        goBack() {
            const source = String(this.$route?.query?.source || '').trim().toLocaleLowerCase()
            if (source === 'overview') {
                this.$router.push({
                    path: '/admin/materials',
                    query: {
                        main_action: 'overview',
                    },
                })
                return
            }

            this.$router.push({
                path: '/admin/materials',
                query: {
                    main_action: 'settings',
                    settings_action: 'subjects',
                    subject_action: 'subjects_groups',
                },
            })
        },
        async printPage() {
            await this.handleBeforePrint()
            window.print()
        },
    },
}
</script>

<style scoped>
.materials-page {
    --pumpkin: #fd802e;
    --charcoal: #233d4c;
    --cream: #f8efe7;
    min-height: 100%;
    position: relative;
    overflow: hidden;
    background: linear-gradient(150deg, #182a35 0%, var(--charcoal) 46%, #182a35 100%);
}

.materials-page::before,
.materials-page::after {
    content: '';
    position: absolute;
    border-radius: 999px;
    filter: blur(90px);
    opacity: 0.35;
    pointer-events: none;
}

.materials-page::before {
    width: 340px;
    height: 340px;
    top: -80px;
    right: -120px;
    background: var(--pumpkin);
}

.materials-page::after {
    width: 340px;
    height: 340px;
    bottom: -120px;
    left: -130px;
    background: #ff9f5e;
}

.subjects-overview-shell {
    position: relative;
    z-index: 1;
    color: #000;
}

.subjects-overview-page-col {
    max-width: 1280px;
    margin-left: auto;
    margin-right: auto;
}

.subjects-overview-subline {
    color: #000;
}

.subjects-overview-userline {
    color: #000;
    font-size: 1.05rem;
    font-weight: 700;
}

.subjects-overview-schoolline {
    color: #000;
    font-size: 0.95rem;
    font-weight: 400;
}

.subjects-overview-subject {
    border: 1px solid rgba(35, 61, 76, 0.18);
    border-radius: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.75);
    margin-bottom: 14px;
}

.subjects-overview-topic {
    border-left: 2px solid rgba(35, 61, 76, 0.2);
    margin-left: 18px;
    padding-left: 12px;
    margin-top: 10px;
}

.subjects-overview-unit {
    border-left: 1px dashed rgba(35, 61, 76, 0.28);
    margin-left: 16px;
    padding-left: 10px;
    margin-top: 8px;
}

.subjects-overview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.subjects-overview-header--topic {
    min-height: 28px;
}

.subjects-overview-header--unit {
    min-height: 24px;
}

.subjects-overview-material-list {
    margin: 8px 0 0 0;
    padding-left: 24px;
    list-style: none;
    color: #000;
}

.subjects-overview-material-item {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 2px 0;
}

.subjects-overview-material-icon {
    color: #000;
}

.subjects-overview-material-text {
    font-size: 0.875rem;
    line-height: 1.35;
}

.subjects-overview-print-pages {
    display: none;
}

.subjects-overview-print-page {
    display: block;
}

.subjects-overview-print-page-footer {
    display: none;
}

@media print {
    .materials-page {
        background: #fff !important;
        padding: 0 !important;
        min-height: 0 !important;
        height: auto !important;
        overflow: visible !important;
    }

    .materials-page::before,
    .materials-page::after,
    .subjects-overview-toolbar,
    .subjects-overview-screen-content {
        display: none !important;
    }

    .subjects-overview-shell {
        border: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
        padding: 0 !important;
    }

    .subjects-overview-subject {
        break-inside: avoid;
        page-break-inside: avoid;
        background: #fff !important;
    }

    .subjects-overview-print-pages {
        display: block;
        margin: 0 !important;
        padding: 0 !important;
    }

    .subjects-overview-print-page {
        position: relative;
        box-sizing: border-box;
        height: calc(29.7cm - 4cm - 1.4cm);
        padding-bottom: 1.2cm;
        overflow: hidden;
        margin: 0 !important;
    }

    .subjects-overview-print-page:not(:last-child) {
        break-after: page;
    }

    .subjects-overview-print-page-footer {
        display: block;
        position: absolute;
        right: 0;
        left: 0;
        bottom: 0.5cm;
        text-align: right;
        font-size: 10pt;
        color: #000;
    }

}
</style>

<style>
@page {
    size: A4 portrait;
    margin: 2cm 2cm 2cm 4cm;
}

@media print {
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        min-height: 0 !important;
        height: auto !important;
        overflow: visible !important;
    }

    .v-application,
    .v-application__wrap,
    .v-layout,
    .v-main,
    .v-main__scroller {
        margin: 0 !important;
        padding: 0 !important;
        min-height: 0 !important;
        height: auto !important;
        overflow: visible !important;
    }

    .v-navigation-drawer,
    .v-app-bar,
    footer {
        display: none !important;
    }

    .v-main {
        padding-top: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin: 0 !important;
    }

    .v-main__scroller {
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin: 0 !important;
    }

    .v-container,
    .v-container--fluid,
    .v-row,
    .v-col {
        margin: 0 !important;
        padding: 0 !important;
        max-width: none !important;
        width: 100% !important;
    }

    .materials-page .subjects-overview-page-col {
        margin-left: 0 !important;
        margin-right: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        flex: 0 0 100% !important;
    }

    .materials-page .v-row {
        justify-content: flex-start !important;
        align-items: flex-start !important;
    }

    .subjects-overview-shell {
        margin-left: 0 !important;
        margin-right: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        padding: 0 !important;
    }
}
</style>
