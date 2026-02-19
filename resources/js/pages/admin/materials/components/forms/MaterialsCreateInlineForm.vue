<template>
    <v-card class="create-form-card pa-5 pa-md-6" rounded="xl" elevation="0">
        <div class="d-flex align-start justify-space-between flex-wrap ga-2 mb-2">
            <div class="text-h6 font-weight-bold">{{ formTitle }}</div>

            <div class="d-flex align-center flex-wrap ga-2">
                <v-menu location="bottom end">
                    <template #activator="{ props: statusMenuActivatorProps }">
                        <v-chip
                            v-bind="statusMenuActivatorProps"
                            size="small"
                            variant="flat"
                            :color="currentStatusColor"
                            append-icon="mdi-chevron-down"
                            class="status-chip">
                            {{ currentStatusLabel }}
                        </v-chip>
                    </template>

                    <v-list density="comfortable" style="min-width: 210px;">
                        <v-list-subheader>Status ändern</v-list-subheader>
                        <v-list-item
                            v-for="option in normalizedStatusOptions"
                            :key="`status-option-${option.value}`"
                            :active="option.value === normalizedStatusValue"
                            @click="selectStatus(option.value)">
                            <v-list-item-title>{{ option.label }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>

                <v-menu location="bottom end">
                    <template #activator="{ props: typeMenuActivatorProps }">
                        <v-chip
                            v-bind="typeMenuActivatorProps"
                            size="small"
                            :variant="normalizedMaterialTypeValue ? 'flat' : 'tonal'"
                            :color="currentMaterialTypeColor"
                            append-icon="mdi-chevron-down"
                            class="type-chip">
                            {{ currentMaterialTypeLabel }}
                        </v-chip>
                    </template>

                    <v-list density="comfortable" style="min-width: 230px;">
                        <v-list-subheader>Typ ändern</v-list-subheader>
                        <v-list-item
                            :active="normalizedMaterialTypeValue === ''"
                            @click="selectMaterialType('')">
                            <v-list-item-title>Kein Typ</v-list-item-title>
                        </v-list-item>
                        <v-list-item
                            v-for="option in normalizedTypeOptions"
                            :key="`type-option-${option.id || option.value}`"
                            :active="option.value === normalizedMaterialTypeValue"
                            @click="selectMaterialType(option.value)">
                            <v-list-item-title>{{ option.label }}</v-list-item-title>
                        </v-list-item>

                        <template v-if="canManageTypes">
                            <v-divider class="my-1" />
                            <v-list-item prepend-icon="mdi-cog-outline" @click="openTypeManager">
                                <v-list-item-title>Typen verwalten</v-list-item-title>
                            </v-list-item>
                        </template>
                    </v-list>
                </v-menu>
            </div>
        </div>

        <div class="text-body-2 form-subline mb-4">{{ formSubline }}</div>

        <v-text-field
            ref="titleField"
            :model-value="title"
            label="Titel *"
            placeholder="z. B. Bruchrechnen Arbeitsblatt"
            variant="outlined"
            density="comfortable"
            clearable
            required
            :autofocus="autofocusTitle"
            hide-details="auto"
            @update:modelValue="$emit('update:title', $event)" />

        <v-textarea
            :model-value="description"
            label="Beschreibung (optional)"
            placeholder="Kurze Beschreibung"
            variant="outlined"
            density="comfortable"
            rows="3"
            auto-grow
            hide-details="auto"
            class="mt-3"
            @update:modelValue="$emit('update:description', $event)" />

        <div class="mt-3">
            <div class="d-flex align-center justify-space-between mb-2">
                <div class="text-subtitle-2">Fach / Thema / Bereich (optional)</div>
                <div class="d-flex justify-end ga-2">
                    <v-btn
                        v-if="classificationEditorVisible"
                        size="x-small"
                        variant="text"
                        color="primary"
                        prepend-icon="mdi-arrow-left"
                        @click="closeClassificationEditor">
                        Zurück
                    </v-btn>
                    <v-btn
                        icon="mdi-plus"
                        size="x-small"
                        variant="tonal"
                        color="primary"
                        @click="addClassificationAndOpenEditor" />
                </div>
            </div>

            <v-card
                variant="tonal"
                color="primary"
                class="pa-3 mb-3">
                <div v-if="assignedClassificationItems.length" class="d-flex flex-wrap ga-2">
                    <v-chip
                        v-for="item in assignedClassificationItems"
                        :key="`assigned-classification-${item.key}`"
                        size="small"
                        :variant="classificationEditorVisible && activeClassificationIndex === item.index ? 'flat' : 'outlined'"
                        color="primary"
                        class="assigned-chip"
                        @click="openClassificationEditor(item.index)">
                        {{ item.label }}
                    </v-chip>
                </div>

                <div v-else class="text-body-2 text-medium-emphasis">Noch keine Zuordnung vorhanden.</div>
            </v-card>

            <transition name="classification-fade">
                <div v-show="classificationEditorVisible">
                    <v-row
                        v-for="entry in editableClassificationRows"
                        :key="`classification-${entry.index}`"
                        dense
                        class="classification-row mb-2"
                        :class="{ 'classification-row-active': activeClassificationIndex === entry.index }">
                        <template v-if="classificationToggleable">
                            <v-col cols="12">
                                <v-combobox
                                    :model-value="entry.row.subject"
                                    :items="subjectOptions"
                                    label="Fach"
                                    placeholder="Fach wählen oder neu"
                                    variant="outlined"
                                    density="comfortable"
                                    clearable
                                    hide-details="auto"
                                    @update:modelValue="updateClassificationField(entry.index, 'subject', $event)" />
                            </v-col>

                            <v-col cols="12">
                                <v-combobox
                                    :model-value="entry.row.topic"
                                    :items="topicOptionsFor(entry.row)"
                                    label="Thema (optional)"
                                    placeholder="Thema wählen oder neu"
                                    variant="outlined"
                                    density="comfortable"
                                    clearable
                                    :disabled="!normalizeText(entry.row.subject)"
                                    hide-details="auto"
                                    @update:modelValue="updateClassificationField(entry.index, 'topic', $event)" />
                            </v-col>

                            <v-col cols="12">
                                <div class="d-flex align-start ga-2">
                                    <v-combobox
                                        class="flex-grow-1"
                                        :model-value="entry.row.unit"
                                        :items="unitOptionsFor(entry.row)"
                                        label="Bereich (optional)"
                                        placeholder="Bereich wählen oder neu"
                                        variant="outlined"
                                        density="comfortable"
                                        clearable
                                        :disabled="!normalizeText(entry.row.topic)"
                                        hide-details="auto"
                                        @update:modelValue="updateClassificationField(entry.index, 'unit', $event)" />

                                    <v-btn
                                        icon="mdi-close"
                                        variant="text"
                                        color="error"
                                        class="mt-1"
                                        :disabled="visibleClassificationRows.length <= 1"
                                        @click="removeClassificationRow(entry.index)" />
                                </div>
                            </v-col>
                        </template>

                        <template v-else>
                            <v-col cols="12" md="4">
                                <v-combobox
                                    :model-value="entry.row.subject"
                                    :items="subjectOptions"
                                    label="Fach"
                                    placeholder="Fach wählen oder neu"
                                    variant="outlined"
                                    density="comfortable"
                                    clearable
                                    hide-details="auto"
                                    @update:modelValue="updateClassificationField(entry.index, 'subject', $event)" />
                            </v-col>

                            <v-col cols="12" md="4">
                                <v-combobox
                                    :model-value="entry.row.topic"
                                    :items="topicOptionsFor(entry.row)"
                                    label="Thema (optional)"
                                    placeholder="Thema wählen oder neu"
                                    variant="outlined"
                                    density="comfortable"
                                    clearable
                                    :disabled="!normalizeText(entry.row.subject)"
                                    hide-details="auto"
                                    @update:modelValue="updateClassificationField(entry.index, 'topic', $event)" />
                            </v-col>

                            <v-col cols="10" md="3">
                                <v-combobox
                                    :model-value="entry.row.unit"
                                    :items="unitOptionsFor(entry.row)"
                                    label="Bereich (optional)"
                                    placeholder="Bereich wählen oder neu"
                                    variant="outlined"
                                    density="comfortable"
                                    clearable
                                    :disabled="!normalizeText(entry.row.topic)"
                                    hide-details="auto"
                                    @update:modelValue="updateClassificationField(entry.index, 'unit', $event)" />
                            </v-col>

                            <v-col cols="2" md="1" class="d-flex align-center justify-end">
                                <v-btn
                                    icon="mdi-close"
                                    variant="text"
                                    color="error"
                                    :disabled="visibleClassificationRows.length <= 1"
                                    @click="removeClassificationRow(entry.index)" />
                            </v-col>
                        </template>
                    </v-row>
                </div>
            </transition>
        </div>

        <div class="mt-3">
            <div class="text-subtitle-2 mb-2">Inhalt hinzufügen</div>
            <div class="d-flex flex-wrap ga-2">
                <v-btn variant="tonal" color="primary" prepend-icon="mdi-text-box-plus-outline" disabled>
                    Text hinzufügen
                </v-btn>
                <v-btn variant="tonal" color="primary" prepend-icon="mdi-file-plus-outline" @click="openFilePicker">
                    Datei hinzufügen
                </v-btn>
                <v-btn variant="tonal" color="primary" prepend-icon="mdi-link-plus" disabled>
                    Link hinzufügen
                </v-btn>
            </div>

            <input
                ref="fileInput"
                type="file"
                multiple
                class="d-none"
                @change="handleFileSelection">

            <v-card
                v-if="normalizedPendingAttachments.length"
                variant="outlined"
                class="pa-3 mt-3">
                <div class="text-caption text-medium-emphasis mb-2">Dateien zur Übernahme</div>

                <div
                    v-for="(item, index) in normalizedPendingAttachments"
                    :key="`pending-file-${item.key}`"
                    class="pending-file-row mb-2">
                    <div class="d-flex align-start ga-2">
                        <v-text-field
                            class="flex-grow-1"
                            :model-value="item.title"
                            label="Dateititel"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            @update:modelValue="updatePendingAttachmentTitle(index, $event)" />

                        <v-btn
                            icon="mdi-close"
                            size="small"
                            variant="text"
                            color="error"
                            class="mt-1"
                            @click="removePendingAttachment(index)" />
                    </div>

                    <div class="text-caption text-medium-emphasis mt-1">
                        {{ item.fileName }}
                    </div>
                </div>
            </v-card>
        </div>

        <slot name="extra-content" />

        <div class="d-flex flex-wrap justify-end ga-2 mt-5">
            <v-btn variant="text" :disabled="isSaving" @click="$emit('cancel')">{{ cancelLabel }}</v-btn>
            <v-btn color="primary" variant="flat" :loading="isSaving" :disabled="!canSave || isSaving" @click="$emit('save')">{{ saveLabel }}</v-btn>
        </div>
    </v-card>
</template>

<script>
export default {
    name: 'MaterialsCreateInlineForm',
    props: {
        title: {
            type: String,
            default: '',
        },
        description: {
            type: String,
            default: '',
        },
        materialType: {
            type: String,
            default: '',
        },
        pendingAttachments: {
            type: Array,
            default: () => [],
        },
        classifications: {
            type: Array,
            default: () => [],
        },
        typeOptions: {
            type: Array,
            default: () => [],
        },
        canManageTypes: {
            type: Boolean,
            default: false,
        },
        status: {
            type: String,
            default: 'inbox',
        },
        statusOptions: {
            type: Array,
            default: () => [],
        },
        classificationTree: {
            type: Array,
            default: () => [],
        },
        classificationEditorVisible: {
            type: Boolean,
            default: true,
        },
        classificationToggleable: {
            type: Boolean,
            default: false,
        },
        isSaving: {
            type: Boolean,
            default: false,
        },
        formTitle: {
            type: String,
            default: 'Neues Material anlegen',
        },
        formSubline: {
            type: String,
            default: 'Gib einen Titel ein, dann kann gespeichert werden.',
        },
        saveLabel: {
            type: String,
            default: 'Speichern',
        },
        cancelLabel: {
            type: String,
            default: 'Abbrechen',
        },
        autofocusTitle: {
            type: Boolean,
            default: true,
        },
    },
    emits: ['update:title', 'update:description', 'update:materialType', 'update:pendingAttachments', 'add-files', 'update:status', 'update:classifications', 'update:classificationEditorVisible', 'manage-types', 'save', 'cancel'],
    data() {
        return {
            activeClassificationIndex: null,
            newlyAddedClassificationIndex: null,
        }
    },
    watch: {
        classificationEditorVisible(nextValue) {
            if (!nextValue) {
                this.activeClassificationIndex = null
                this.newlyAddedClassificationIndex = null
            }
        },
        classifications: {
            deep: true,
            handler() {
                if (this.activeClassificationIndex === null) return
                const maxIndex = this.visibleClassificationRows.length - 1
                if (this.activeClassificationIndex > maxIndex) {
                    this.activeClassificationIndex = maxIndex
                }
                if (
                    this.newlyAddedClassificationIndex !== null &&
                    this.newlyAddedClassificationIndex > maxIndex
                ) {
                    this.newlyAddedClassificationIndex = null
                }
            },
        },
    },
    computed: {
        canSave() {
            return String(this.title || '').trim().length > 0
        },
        normalizedStatusOptions() {
            const fallback = [
                { value: 'inbox', label: 'Neu/Idee' },
                { value: 'in_progress', label: 'In Arbeit' },
                { value: 'done', label: 'ok' },
                { value: 'update_needed', label: 'Änderung nötig' },
            ]

            const input = Array.isArray(this.statusOptions) ? this.statusOptions : []
            const result = input
                .map((option) => {
                    if (typeof option === 'string') {
                        const value = this.normalizeText(option)
                        return value ? { value, label: value } : null
                    }

                    if (!option || typeof option !== 'object') return null

                    const value = this.normalizeText(option.value)
                    const label = this.normalizeText(option.label) || value
                    if (!value) return null

                    return { value, label }
                })
                .filter(Boolean)

            return result.length ? result : fallback
        },
        normalizedStatusValue() {
            const value = this.normalizeText(this.status)
            if (value) {
                const matchingOption = this.normalizedStatusOptions.find(
                    (option) => option.value.toLocaleLowerCase() === value.toLocaleLowerCase()
                )
                if (matchingOption) {
                    return matchingOption.value
                }
            }
            return this.normalizedStatusOptions[0]?.value || 'inbox'
        },
        currentStatusOption() {
            return this.normalizedStatusOptions.find((option) => option.value === this.normalizedStatusValue) || null
        },
        currentStatusLabel() {
            return this.currentStatusOption?.label || 'Status'
        },
        currentStatusColor() {
            const map = {
                inbox: 'secondary',
                in_progress: 'warning',
                done: 'success',
                update_needed: 'error',
            }
            return map[this.normalizedStatusValue] || 'primary'
        },
        normalizedTypeOptions() {
            const input = Array.isArray(this.typeOptions) ? this.typeOptions : []
            return input
                .map((option) => {
                    if (!option || typeof option !== 'object') return null
                    const id = Number(option.id)
                    const value = this.normalizeText(option.value)
                    const label = this.normalizeText(option.label) || value
                    if (!value) return null

                    return {
                        id: Number.isFinite(id) && id > 0 ? id : null,
                        value,
                        label,
                    }
                })
                .filter(Boolean)
        },
        normalizedMaterialTypeValue() {
            const value = this.normalizeText(this.materialType)
            if (!value) return ''

            const matchingOption = this.normalizedTypeOptions.find(
                (option) => option.value.toLocaleLowerCase() === value.toLocaleLowerCase()
            )

            return matchingOption?.value || value
        },
        currentMaterialTypeLabel() {
            if (!this.normalizedMaterialTypeValue) {
                return 'Typ'
            }

            const option = this.normalizedTypeOptions.find(
                (entry) => entry.value === this.normalizedMaterialTypeValue
            )

            return option?.label || this.normalizedMaterialTypeValue
        },
        currentMaterialTypeColor() {
            return this.normalizedMaterialTypeValue ? 'primary' : 'grey'
        },
        normalizedPendingAttachments() {
            return this.toPendingAttachments(this.pendingAttachments)
        },
        visibleClassificationRows() {
            const rows = this.toClassificationRows(this.classifications)
            return rows.length > 0 ? rows : [this.emptyClassificationRow()]
        },
        editableClassificationRows() {
            const rows = this.visibleClassificationRows
            let index = Number.isInteger(this.activeClassificationIndex) ? this.activeClassificationIndex : 0

            if (index < 0) {
                index = 0
            }
            if (index >= rows.length) {
                index = rows.length - 1
            }

            return [{
                index,
                row: rows[index],
            }]
        },
        subjectOptions() {
            return this.classificationTree
                .map((subject) => this.normalizeText(subject?.name))
                .filter((subject) => subject !== '')
                .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))
        },
        assignedClassificationItems() {
            const rows = this.toClassificationRows(this.classifications)
            const result = []
            const seen = new Set()

            for (let index = 0; index < rows.length; index += 1) {
                const row = rows[index]
                const subject = this.normalizeText(row.subject)
                const topic = this.normalizeText(row.topic)
                const unit = this.normalizeText(row.unit)
                if (!subject) continue

                const normalized = {
                    subject,
                    topic,
                    unit: topic ? unit : '',
                }
                const dedupeKey = `${normalized.subject.toLocaleLowerCase()}|${normalized.topic.toLocaleLowerCase()}|${normalized.unit.toLocaleLowerCase()}`
                if (seen.has(dedupeKey)) continue
                seen.add(dedupeKey)

                let label = subject
                if (topic) label += ` / ${topic}`
                if (unit) label += ` / ${unit}`

                result.push({
                    index,
                    label,
                    key: `${dedupeKey}|${index}`,
                })
            }

            return result
        },
    },
    methods: {
        emptyClassificationRow() {
            return {
                subject: '',
                topic: '',
                unit: '',
            }
        },
        normalizeText(value) {
            return String(value ?? '').trim().slice(0, 255)
        },
        defaultAttachmentTitle(fileName) {
            const name = String(fileName || '').trim()
            if (!name) {
                return 'Datei'
            }

            const lastDot = name.lastIndexOf('.')
            const withoutExtension = lastDot > 0 ? name.slice(0, lastDot) : name
            return this.normalizeText(withoutExtension || name) || 'Datei'
        },
        toPendingAttachments(value) {
            const input = Array.isArray(value) ? value : []
            const result = []

            for (let index = 0; index < input.length; index += 1) {
                const item = input[index]
                const file = item instanceof File ? item : item?.file
                if (!(file instanceof File)) continue

                const fileName = this.normalizeText(file.name) || `Datei ${index + 1}`
                const rawTitle = item instanceof File ? '' : item?.title
                const title = this.normalizeText(rawTitle) || this.defaultAttachmentTitle(fileName)
                const source = this.normalizeText(item instanceof File ? '' : item?.source)
                const key = String(item instanceof File ? '' : item?.key) || `${fileName}|${file.size}|${file.lastModified}|${index}`

                result.push({
                    file,
                    title,
                    fileName,
                    source,
                    key,
                })
            }

            return result
        },
        emitPendingAttachments(rows) {
            const nextRows = (Array.isArray(rows) ? rows : []).map((row, index) => ({
                file: row.file,
                title: this.normalizeText(row.title) || this.defaultAttachmentTitle(row.fileName || row.file?.name),
                source: this.normalizeText(row.source),
                key: String(row.key || `${row.file?.name || 'datei'}|${row.file?.size || 0}|${row.file?.lastModified || 0}|${index}`),
            }))
            this.$emit('update:pendingAttachments', nextRows)
        },
        openFilePicker() {
            const input = this.$refs.fileInput
            if (input && typeof input.click === 'function') {
                input.click()
            }
        },
        handleFileSelection(event) {
            const files = Array.from(event?.target?.files || []).filter((file) => file instanceof File)
            if (files.length > 0) {
                this.$emit('add-files', files)
            }

            if (event?.target) {
                event.target.value = ''
            }
        },
        updatePendingAttachmentTitle(index, value) {
            const rows = this.toPendingAttachments(this.pendingAttachments)
            if (index < 0 || index >= rows.length) return

            rows[index] = {
                ...rows[index],
                title: this.normalizeText(value) || this.defaultAttachmentTitle(rows[index].fileName),
            }

            this.emitPendingAttachments(rows)
        },
        removePendingAttachment(index) {
            const rows = this.toPendingAttachments(this.pendingAttachments)
            if (index < 0 || index >= rows.length) return

            rows.splice(index, 1)
            this.emitPendingAttachments(rows)
        },
        isClassificationRowEmpty(row) {
            const subject = this.normalizeText(row?.subject)
            const topic = this.normalizeText(row?.topic)
            const unit = this.normalizeText(row?.unit)
            return subject === '' && topic === '' && unit === ''
        },
        toClassificationRows(value) {
            const input = Array.isArray(value) ? value : []
            const result = []

            for (const row of input) {
                if (!row || typeof row !== 'object') continue
                result.push({
                    subject: this.normalizeText(row.subject),
                    topic: this.normalizeText(row.topic),
                    unit: this.normalizeText(row.unit),
                })
            }

            return result
        },
        normalizedClassificationRows(value) {
            const input = this.toClassificationRows(value)
            const seen = new Set()
            const result = []

            for (const row of input) {
                if (!row.subject) continue
                const normalized = {
                    subject: row.subject,
                    topic: row.topic,
                    unit: row.topic ? row.unit : '',
                }
                const key = `${normalized.subject.toLocaleLowerCase()}|${normalized.topic.toLocaleLowerCase()}|${normalized.unit.toLocaleLowerCase()}`
                if (seen.has(key)) continue
                seen.add(key)
                result.push(normalized)
            }

            return result
        },
        subjectNodeByName(name) {
            const subjectName = this.normalizeText(name)
            if (!subjectName) return null
            return this.classificationTree.find(
                (subject) => this.normalizeText(subject?.name).toLocaleLowerCase() === subjectName.toLocaleLowerCase()
            ) || null
        },
        topicNodeByName(subjectNode, topicName) {
            const normalizedTopic = this.normalizeText(topicName)
            if (!subjectNode || !normalizedTopic || !Array.isArray(subjectNode.topics)) return null
            return subjectNode.topics.find(
                (topic) => this.normalizeText(topic?.name).toLocaleLowerCase() === normalizedTopic.toLocaleLowerCase()
            ) || null
        },
        topicOptionsFor(row) {
            const subjectNode = this.subjectNodeByName(row?.subject)
            const topics = Array.isArray(subjectNode?.topics) ? subjectNode.topics : []
            return topics
                .map((topic) => this.normalizeText(topic?.name))
                .filter((topic) => topic !== '')
                .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))
        },
        unitOptionsFor(row) {
            const subjectNode = this.subjectNodeByName(row?.subject)
            const topicNode = this.topicNodeByName(subjectNode, row?.topic)
            const units = Array.isArray(topicNode?.units) ? topicNode.units : []
            return units
                .map((unit) => this.normalizeText(unit?.name))
                .filter((unit) => unit !== '')
                .sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }))
        },
        updateClassificationField(index, field, value) {
            const rows = this.toClassificationRows(this.classifications)
            while (rows.length <= index) {
                rows.push(this.emptyClassificationRow())
            }

            rows[index] = {
                ...rows[index],
                [field]: this.normalizeText(value),
            }

            if (field === 'subject') {
                rows[index].topic = ''
                rows[index].unit = ''
            }
            if (field === 'topic') {
                rows[index].unit = ''
            }

            this.activeClassificationIndex = index
            if (
                this.newlyAddedClassificationIndex === index &&
                !this.isClassificationRowEmpty(rows[index])
            ) {
                this.newlyAddedClassificationIndex = null
            }
            this.$emit('update:classifications', rows)
        },
        addClassificationAndOpenEditor() {
            const rows = this.toClassificationRows(this.classifications)
            let targetIndex = rows.findIndex((row) => this.isClassificationRowEmpty(row))
            if (targetIndex < 0) {
                rows.push(this.emptyClassificationRow())
                targetIndex = rows.length - 1
                this.newlyAddedClassificationIndex = targetIndex
            } else {
                this.newlyAddedClassificationIndex = null
            }

            this.activeClassificationIndex = targetIndex
            this.$emit('update:classifications', rows)
            this.$emit('update:classificationEditorVisible', true)
        },
        closeClassificationEditor() {
            const rows = this.toClassificationRows(this.classifications)
            const index = Number.isInteger(this.newlyAddedClassificationIndex)
                ? this.newlyAddedClassificationIndex
                : null

            if (index !== null && index >= 0 && index < rows.length && this.isClassificationRowEmpty(rows[index])) {
                rows.splice(index, 1)
                this.$emit('update:classifications', this.normalizedClassificationRows(rows))
            }

            this.activeClassificationIndex = null
            this.newlyAddedClassificationIndex = null
            this.$emit('update:classificationEditorVisible', false)
        },
        openClassificationEditor(index) {
            const rows = this.visibleClassificationRows
            const parsedIndex = Number(index)
            let safeIndex = Number.isInteger(parsedIndex) ? parsedIndex : 0
            if (safeIndex < 0) safeIndex = 0
            if (safeIndex >= rows.length) safeIndex = rows.length - 1

            const sameTarget = this.classificationEditorVisible && this.activeClassificationIndex === safeIndex
            if (sameTarget) {
                this.closeClassificationEditor()
                return
            }

            this.activeClassificationIndex = safeIndex
            this.newlyAddedClassificationIndex = null
            this.$emit('update:classificationEditorVisible', true)
        },
        removeClassificationRow(index) {
            const rows = this.toClassificationRows(this.classifications)
            rows.splice(index, 1)
            const nextRows = rows.length ? rows : [this.emptyClassificationRow()]
            const normalizedRows = this.normalizedClassificationRows(nextRows)
            const nextVisibleCount = normalizedRows.length > 0 ? normalizedRows.length : 1

            this.activeClassificationIndex = Math.min(Math.max(index, 0), nextVisibleCount - 1)
            this.newlyAddedClassificationIndex = null
            this.$emit('update:classifications', normalizedRows)
        },
        selectStatus(value) {
            this.$emit('update:status', this.normalizeText(value))
        },
        selectMaterialType(value) {
            const normalized = this.normalizeText(value)
            this.$emit('update:materialType', normalized)
        },
        openTypeManager() {
            this.$emit('manage-types')
        },
        focusTitle() {
            const tryFocus = (attempt = 0) => {
                const field = this.$refs.titleField
                const input = field?.$el?.querySelector?.('input')

                if (field && typeof field.focus === 'function') {
                    field.focus()
                }
                if (input && typeof input.focus === 'function') {
                    input.focus()
                }

                const isFocused = document?.activeElement === input
                if (!isFocused && attempt < 6) {
                    setTimeout(() => tryFocus(attempt + 1), 80)
                }
            }

            this.$nextTick(() => {
                tryFocus(0)
            })
        },
    },
}
</script>

<style scoped>
.create-form-card {
    border: 1px solid rgba(253, 128, 46, 0.35);
    background: rgba(255, 255, 255, 0.96);
}

.form-subline {
    color: #314d5d;
}

.status-chip {
    cursor: pointer;
}

.type-chip {
    cursor: pointer;
}

.assigned-chip {
    cursor: pointer;
}

.pending-file-row:last-child {
    margin-bottom: 0 !important;
}

.classification-row {
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.6);
    padding: 10px 10px 2px;
}

.classification-row-active {
    border: 1px solid rgba(253, 128, 46, 0.45);
}

.classification-fade-enter-active,
.classification-fade-leave-active {
    transition: opacity 0.2s ease;
}

.classification-fade-enter-from,
.classification-fade-leave-to {
    opacity: 0;
}
</style>
