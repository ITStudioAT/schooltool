<template>
    <div class="record-tree-node">
        <v-sheet class="record-tree-node__card" rounded="lg" border>
            <div class="record-tree-node__header">
                <div class="record-tree-node__title-wrap">
                    <div class="record-tree-node__title">{{ recordTitle }}</div>
                    <div class="record-tree-node__subtitle">Typ: {{ record.section_type }} · Ebene {{ recordLevel }}</div>
                </div>
                <v-chip size="x-small" color="primary" variant="tonal">
                    #{{ record.sort_order || '-' }}
                </v-chip>
            </div>

            <div class="record-tree-node__meta">
                <v-chip size="x-small" variant="outlined" color="primary">
                    Zeilen: {{ lineRangeLabel }}
                </v-chip>
                <v-chip v-if="pageRangeLabel" size="x-small" variant="tonal" color="success">
                    {{ pageRangeLabel }}
                </v-chip>
                <v-chip size="x-small" variant="outlined" color="primary">
                    Zeichen: {{ bodyChars }}
                </v-chip>
                <v-chip v-if="parentTitle" size="x-small" variant="outlined" color="primary">
                    Parent: {{ parentTitle }}
                </v-chip>
                <v-chip v-if="qualityScoreLabel" size="x-small" variant="outlined" color="primary">
                    Confidence: {{ qualityScoreLabel }}
                </v-chip>
            </div>

            <div class="record-tree-node__text-wrap">
                <div class="record-tree-node__label">Textinhalt</div>
                <div v-if="recordText" class="record-tree-node__text">{{ recordText }}</div>
                <div v-else class="record-tree-node__text-empty">Kein Text für diesen Datensatz gespeichert.</div>
            </div>

            <div v-if="diagnosticHints.length > 0" class="record-tree-node__diagnostics">
                <div class="record-tree-node__label">Diagnostik</div>
                <v-chip
                    v-for="(hint, index) in diagnosticHints"
                    :key="`${record.id}-hint-${index}`"
                    size="x-small"
                    color="secondary"
                    variant="tonal"
                    class="mr-1 mb-1">
                    {{ hint }}
                </v-chip>
            </div>

            <div v-if="evidenceLabels.length > 0" class="record-tree-node__evidence">
                <div class="record-tree-node__label">Evidenz</div>
                <v-chip
                    v-for="(label, index) in evidenceLabels"
                    :key="`${record.id}-evidence-${index}`"
                    size="x-small"
                    variant="outlined"
                    color="secondary"
                    class="mr-1 mb-1">
                    {{ label }}
                </v-chip>
            </div>

            <div v-if="titlePageDetails.length > 0" class="record-tree-node__details">
                <div class="record-tree-node__label">Angaben vom Titelblatt</div>
                <div class="record-tree-node__details-grid">
                    <div v-for="item in titlePageDetails" :key="`${record.id}-detail-${item.key}`" class="record-tree-node__details-row">
                        <span>{{ item.label }}</span>
                        <strong>{{ item.value }}</strong>
                    </div>
                </div>
            </div>
        </v-sheet>

        <div v-if="childRecords.length > 0" class="record-tree-node__children">
            <AbaRecordTree
                v-for="child in childRecords"
                :key="child.id"
                :record="child"
                :children-by-parent="childrenByParent"
                :records-by-id="recordsById" />
        </div>
    </div>
</template>

<script>
export default {
    name: 'AbaRecordTree',

    props: {
        record: {
            type: Object,
            required: true,
        },
        childrenByParent: {
            type: Object,
            required: true,
        },
        recordsById: {
            type: Object,
            required: true,
        },
    },

    computed: {
        recordTitle() {
            const title = String(this.record?.section_title || '').trim()
            return title || 'Ohne Titel'
        },
        recordLevel() {
            const level = Number(this.record?.hierarchy_level || 0)
            return Number.isFinite(level) && level > 0 ? level : 1
        },
        recordText() {
            return String(this.record?.extracted_text || '').trim()
        },
        bodyChars() {
            return this.recordText.length
        },
        lineRangeLabel() {
            const startLine = Number(this.record?.start_line || 0)
            const endLine = Number(this.record?.end_line || 0)
            if (startLine > 0 && endLine >= startLine) {
                return `${startLine}–${endLine}`
            }
            if (startLine > 0) {
                return `${startLine}`
            }
            return '-'
        },
        pageRangeLabel() {
            const startPage = Number(this.record?.start_page || 0)
            const endPage = Number(this.record?.end_page || 0)
            if (startPage > 0 && endPage > startPage) {
                return `Seiten ${startPage}–${endPage}`
            }
            if (startPage > 0) {
                return `Seite ${startPage}`
            }
            return null
        },
        qualityScoreLabel() {
            const metadata = this.safeMetadata
            const value = metadata.quality_score ?? metadata.confidence ?? null
            if (value === null || value === undefined) {
                return null
            }

            const score = Number(value)
            if (!Number.isFinite(score)) {
                return null
            }

            const normalized = score > 1 ? score / 100 : score
            return `${Math.max(0, Math.min(1, normalized)).toFixed(2)}`
        },
        safeMetadata() {
            return this.record?.metadata && typeof this.record.metadata === 'object' ? this.record.metadata : {}
        },
        parentTitle() {
            const parentId = Number(this.record?.parent_result_id || 0)
            if (!parentId) {
                return null
            }

            const parent = this.recordsById[parentId]
            if (!parent) {
                return `#${parentId}`
            }

            return String(parent.section_title || '').trim() || `#${parentId}`
        },
        diagnosticHints() {
            const metadata = this.safeMetadata
            const hints = []

            if (metadata.selection_reason) {
                hints.push(`reason: ${metadata.selection_reason}`)
            }
            if (metadata.accepted_via_hierarchy === true) {
                hints.push('accepted_via_hierarchy')
            }
            if (metadata.rejected_as_toc_duplicate === true) {
                hints.push('toc_duplicate_flag')
            }
            if (metadata.heading_source) {
                hints.push(`heading_source: ${metadata.heading_source}`)
            }

            return hints
        },
        evidenceLabels() {
            const metadata = this.safeMetadata
            const labels = []
            const sourceBlockIds = Array.isArray(metadata.source_block_ids)
                ? metadata.source_block_ids.filter((value) => String(value || '').trim() !== '')
                : []

            if (sourceBlockIds.length > 0) {
                labels.push(`source_block_ids: ${sourceBlockIds.slice(0, 6).join(', ')}`)
                if (sourceBlockIds.length > 6) {
                    labels.push(`+${sourceBlockIds.length - 6} weitere`) 
                }
            }

            return labels
        },
        titlePageDetails() {
            if (String(this.record?.section_type || '') !== 'title_page') {
                return []
            }

            const metadata = this.safeMetadata
            const details = metadata.title_page_details && typeof metadata.title_page_details === 'object'
                ? metadata.title_page_details
                : {}

            const resolveValue = (...values) => {
                for (const value of values) {
                    const normalized = String(value || '').trim()
                    if (normalized !== '') {
                        return normalized
                    }
                }

                return null
            }

            const items = [
                { key: 'title', label: 'Titel', value: resolveValue(details.title, metadata.title_page_title) },
                { key: 'submitter', label: 'Einreicher:in (Verfasst von)', value: resolveValue(details.submitter, metadata.title_page_submitter) },
                { key: 'advisor', label: 'Betreuer:in', value: resolveValue(details.advisor, metadata.title_page_advisor) },
                { key: 'class', label: 'Klasse', value: resolveValue(details.class, metadata.title_page_class) },
                { key: 'year', label: 'Jahr', value: resolveValue(details.year, metadata.title_page_year) || '--' },
            ]

            return items.filter((item) => item.key === 'year' || item.value !== null)
        },
        childRecords() {
            const id = Number(this.record?.id || 0)
            if (!id) {
                return []
            }

            return Array.isArray(this.childrenByParent[id]) ? this.childrenByParent[id] : []
        },
    },
}
</script>

<style scoped>
.record-tree-node {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.record-tree-node__children {
    border-left: 2px solid rgba(59, 130, 246, 0.22);
    margin-left: 12px;
    padding-left: 12px;
    display: grid;
    gap: 10px;
}

.record-tree-node__card {
    padding: 10px;
    border-color: rgba(15, 23, 42, 0.12) !important;
    background: rgba(255, 255, 255, 0.88);
}

.record-tree-node__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.record-tree-node__title {
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.28;
    color: #0f172a;
}

.record-tree-node__subtitle {
    margin-top: 2px;
    font-size: 0.77rem;
    color: rgba(30, 41, 59, 0.78);
}

.record-tree-node__meta {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.record-tree-node__text-wrap,
.record-tree-node__diagnostics,
.record-tree-node__evidence,
.record-tree-node__details {
    margin-top: 10px;
}

.record-tree-node__details-grid {
    display: grid;
    gap: 6px;
}

.record-tree-node__details-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    font-size: 0.8rem;
    color: rgba(30, 41, 59, 0.9);
}

.record-tree-node__details-row strong {
    color: #0f172a;
    text-align: right;
}

.record-tree-node__label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: rgba(30, 41, 59, 0.74);
    margin-bottom: 4px;
}

.record-tree-node__text {
    white-space: pre-wrap;
    font-size: 0.84rem;
    line-height: 1.45;
    color: #0f172a;
}

.record-tree-node__text-empty {
    font-size: 0.82rem;
    color: rgba(30, 41, 59, 0.74);
    font-style: italic;
}
</style>
