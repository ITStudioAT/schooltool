<template>
    <v-container fluid class="materials-page ma-0 w-100 pa-2">
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

                    <div class="text-h4 font-weight-bold mb-2">Fachkatalog Übersicht</div>
                    <div class="text-body-2 mb-4 subjects-overview-subline">
                        Alle Fächer, Themen und Einheiten mit zugeordneten Materialien.
                        Stand: {{ generatedAtLabel }}
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
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'

export default {
    name: 'MaterialsSubjectsOverview',
    data() {
        return {
            materialCardStore: null,
            isLoading: false,
            subjects: [],
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
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        await this.loadOverview()
    },
    methods: {
        normalizeText(value) {
            return String(value ?? '').trim()
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
        buildSubjectsOverview(tree, cards) {
            const subjects = []
            const subjectByKey = new Map()
            const treeItems = Array.isArray(tree) ? tree : []
            const cardItems = Array.isArray(cards) ? cards : []

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

            const uniqueCardIds = new Set()
            for (const card of cardItems) {
                const cardId = Number(card?.id)
                if (!Number.isFinite(cardId) || cardId <= 0) continue
                uniqueCardIds.add(cardId)

                const material = {
                    id: cardId,
                    title: this.normalizeText(card?.title) || 'Ohne Titel',
                    icon: this.materialIcon(card),
                }

                const rows = this.normalizeClassificationRows(card?.classifications)
                for (const row of rows) {
                    const subject = this.ensureSubject(subjects, subjectByKey, row.subject)
                    if (!subject) continue

                    if (!row.topic) {
                        this.pushMaterial(subject, material)
                        continue
                    }

                    const topic = this.ensureTopic(subject, row.topic)
                    if (!topic) continue

                    if (!row.unit) {
                        this.pushMaterial(topic, material)
                        continue
                    }

                    const unit = this.ensureUnit(topic, row.unit)
                    if (!unit) continue
                    this.pushMaterial(unit, material)
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

                const cards = await this.materialCardStore.listAllCardsSnapshot({})
                if (!Array.isArray(cards)) {
                    this.subjects = []
                    this.uniqueMaterialCount = 0
                    this.generatedAt = new Date()
                    return
                }

                const tree = Array.isArray(this.materialCardStore?.config?.classification_tree)
                    ? this.materialCardStore.config.classification_tree
                    : []
                const result = this.buildSubjectsOverview(tree, cards)
                this.subjects = result.subjects
                this.uniqueMaterialCount = result.uniqueMaterialCount
                this.generatedAt = new Date()
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
        printPage() {
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

@media print {
    .materials-page {
        background: #fff !important;
        padding: 0 !important;
    }

    .materials-page::before,
    .materials-page::after,
    .subjects-overview-toolbar {
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

}
</style>

<style>
@page {
    margin: 2cm 2cm 2cm 4cm;
}

@media print {
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
    }

    .v-application,
    .v-application__wrap,
    .v-layout {
        margin: 0 !important;
        padding: 0 !important;
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
